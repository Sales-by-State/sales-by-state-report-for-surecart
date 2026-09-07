<?php
/**
 * Shopify Admin GraphQL client for the report import.
 *
 * @package SalesByStateReportForShopify
 */

namespace SBSS\Data;

defined( 'ABSPATH' ) || exit;

/**
 * Reads Admin API credentials from the demo setup plugin, then a local override.
 */
class Client {

	/**
	 * Option for pasted Admin credentials.
	 */
	const OPTION = 'sbss_credentials';

	/**
	 * Cached client-credentials token.
	 */
	const TOKEN_OPTION = 'sbss_oauth_token';

	/**
	 * Admin API version.
	 */
	const API_VERSION = '2026-07';

	/**
	 * Run a GraphQL query or mutation.
	 *
	 * @param string              $query     Document.
	 * @param array<string,mixed> $variables Variables.
	 * @param int                 $attempt   Attempt.
	 * @return array|\WP_Error
	 */
	public function graphql( $query, array $variables = array(), $attempt = 1 ) {
		$token = self::access_token();

		if ( is_wp_error( $token ) ) {
			return $token;
		}

		$creds = self::credentials();
		$url   = sprintf(
			'https://%s/admin/api/%s/graphql.json',
			$creds['shop'],
			self::API_VERSION
		);

		$body = array( 'query' => $query );

		if ( $variables ) {
			$body['variables'] = $variables;
		}

		$response = wp_remote_post(
			$url,
			array(
				'timeout' => 45,
				'headers' => array(
					'Content-Type'           => 'application/json',
					'X-Shopify-Access-Token' => $token,
				),
				'body'    => wp_json_encode( $body ),
			)
		);

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$code = (int) wp_remote_retrieve_response_code( $response );
		$raw  = (string) wp_remote_retrieve_body( $response );

		if ( ( 429 === $code || $code >= 500 ) && $attempt < 4 ) {
			sleep( min( 20, 4 * $attempt ) );
			return $this->graphql( $query, $variables, $attempt + 1 );
		}

		$decoded = json_decode( $raw, true );
		$decoded = is_array( $decoded ) ? $decoded : array();

		if ( $code >= 400 ) {
			$detail = (string) ( $decoded['errors'][0]['message'] ?? $decoded['error'] ?? '' );

			if ( ! $detail ) {
				$detail = sprintf(
					/* translators: %d: HTTP status. */
					__( 'Shopify Admin API HTTP %d.', 'sales-by-state-report-for-shopify' ),
					$code
				);
			}

			return new \WP_Error( 'sbss_api', $detail, array( 'status' => $code ) );
		}

		if ( ! empty( $decoded['errors'] ) && is_array( $decoded['errors'] ) ) {
			$bits = array();

			foreach ( $decoded['errors'] as $err ) {
				$msg = is_array( $err ) ? (string) ( $err['message'] ?? '' ) : (string) $err;

				if ( $msg ) {
					$bits[] = $msg;
				}
			}

			return new \WP_Error(
				'sbss_graphql',
				$bits ? implode( ' — ', $bits ) : __( 'Shopify GraphQL error.', 'sales-by-state-report-for-shopify' )
			);
		}

		return isset( $decoded['data'] ) && is_array( $decoded['data'] ) ? $decoded['data'] : array();
	}

	/**
	 * Whether a shop domain and Admin token (or client credentials) are present.
	 *
	 * @return bool
	 */
	public static function ready() {
		$creds = self::credentials();

		if ( empty( $creds['shop'] ) ) {
			return false;
		}

		return ! empty( $creds['access_token'] ) || ( ! empty( $creds['client_id'] ) && ! empty( $creds['client_secret'] ) );
	}

	/**
	 * Resolved credentials.
	 *
	 * @return array{shop:string,access_token:string,client_id:string,client_secret:string}
	 */
	public static function credentials() {
		$override = get_option( self::OPTION, array() );
		$override = is_array( $override ) ? $override : array();

		$demo = get_option( 'sds_credentials', array() );
		$demo = is_array( $demo ) ? $demo : array();

		$shop   = self::normalize_shop( (string) ( $override['shop'] ?? $demo['shop'] ?? '' ) );
		$token  = (string) ( $override['access_token'] ?? $demo['access_token'] ?? '' );
		$cid    = (string) ( $override['client_id'] ?? $demo['client_id'] ?? '' );
		$secret = (string) ( $override['client_secret'] ?? $demo['client_secret'] ?? '' );

		if ( ! $shop ) {
			$shop = self::normalize_shop( (string) get_option( 'shopify_store_url', '' ) );
		}

		return array(
			'shop'          => $shop,
			'access_token'  => $token,
			'client_id'     => $cid,
			'client_secret' => $secret,
		);
	}

	/**
	 * Admin access token.
	 *
	 * @return string|\WP_Error
	 */
	public static function access_token() {
		if ( class_exists( '\SDS\Setup\Connection' ) ) {
			$token = \SDS\Setup\Connection::access_token();

			if ( ! is_wp_error( $token ) && $token ) {
				return $token;
			}
		}

		$creds = self::credentials();

		if ( ! empty( $creds['access_token'] ) ) {
			return $creds['access_token'];
		}

		$cached = get_option( self::TOKEN_OPTION, array() );
		$cached = is_array( $cached ) ? $cached : array();
		$exp    = (int) ( $cached['expires'] ?? 0 );

		if ( ! empty( $cached['token'] ) && $exp > ( time() + 120 ) ) {
			return (string) $cached['token'];
		}

		if ( empty( $creds['shop'] ) || empty( $creds['client_id'] ) || empty( $creds['client_secret'] ) ) {
			return new \WP_Error(
				'sbss_disconnected',
				__( 'Connect a Shopify Admin API token (or Client ID + secret) before importing orders. The WordPress plugin token is Storefront-only.', 'sales-by-state-report-for-shopify' )
			);
		}

		$response = wp_remote_post(
			sprintf( 'https://%s/admin/oauth/access_token', $creds['shop'] ),
			array(
				'timeout'     => 30,
				'redirection' => 0,
				'headers'     => array(
					'Content-Type' => 'application/x-www-form-urlencoded',
					'Accept'       => 'application/json',
				),
				'body'        => http_build_query(
					array(
						'grant_type'    => 'client_credentials',
						'client_id'     => $creds['client_id'],
						'client_secret' => $creds['client_secret'],
					),
					'',
					'&'
				),
			)
		);

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$body = json_decode( (string) wp_remote_retrieve_body( $response ), true );
		$body = is_array( $body ) ? $body : array();

		if ( empty( $body['access_token'] ) ) {
			return new \WP_Error(
				'sbss_oauth',
				__( 'Could not exchange Shopify client credentials for an Admin API token.', 'sales-by-state-report-for-shopify' )
			);
		}

		$ttl = (int) ( $body['expires_in'] ?? 86399 );

		update_option(
			self::TOKEN_OPTION,
			array(
				'token'   => (string) $body['access_token'],
				'expires' => time() + max( 60, $ttl ),
			),
			false
		);

		return (string) $body['access_token'];
	}

	/**
	 * Normalize a shop domain.
	 *
	 * @param string $value Raw value.
	 * @return string
	 */
	public static function normalize_shop( $value ) {
		$value = strtolower( trim( (string) $value ) );
		$value = preg_replace( '#^https?://#', '', $value );
		$value = trim( $value, '/' );

		if ( ! $value ) {
			return '';
		}

		if ( false === strpos( $value, '.' ) ) {
			$value .= '.myshopify.com';
		}

		if ( ! preg_match( '/^[a-z0-9][a-z0-9\-]*\.myshopify\.com$/', $value ) ) {
			return '';
		}

		return $value;
	}
}

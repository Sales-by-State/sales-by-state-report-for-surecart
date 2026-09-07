<?php
/**
 * Keeps the report table in step with Shopify orders.
 *
 * @package SalesByStateReportForShopify
 */

namespace SBSS\Data;

use SBSS\Install\Schema;
use SBSS\Regions;

defined( 'ABSPATH' ) || exit;

/**
 * Writes one row per order.
 *
 * Orders live on the Shopify Admin API, not in WordPress. Rows are written
 * during the one-off import and by the recent-order poll. Refunds are not
 * modelled: a refunded order is included or excluded by the status filter
 * like any other order.
 */
class Sync {

	/**
	 * Register hooks.
	 *
	 * Live order writes happen through Scheduler::sync_recent(); the official
	 * Shopify WordPress plugin does not fire a local order-save hook.
	 *
	 * @return void
	 */
	public function register() {
	}

	/**
	 * Insert or update the row for one order.
	 *
	 * @param array|string $order Order array or GID.
	 * @return bool
	 */
	public function upsert( $order ) {
		global $wpdb;

		if ( is_string( $order ) ) {
			$order = $this->load_order( $order );
		}

		if ( ! is_array( $order ) ) {
			return false;
		}

		$row = self::build_row( $order );

		if ( ! $row ) {
			return false;
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		return false !== $wpdb->replace(
			Schema::table(),
			$row,
			array( '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%f', '%f', '%f', '%f' )
		);
	}

	/**
	 * Remove the row for an order.
	 *
	 * @param string $order_id Order GID or numeric id.
	 * @return void
	 */
	public function delete( $order_id ) {
		global $wpdb;

		$order_id = (string) $order_id;

		if ( ! $order_id ) {
			return;
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->delete( Schema::table(), array( 'order_id' => $order_id ), array( '%s' ) );
	}

	/**
	 * Build the row for a Shopify Admin API order.
	 *
	 * Money is stored as decimals on the API and in the report table.
	 * Net Sales is total including tax, minus tax, minus shipping.
	 * Gross Sales is total including tax.
	 *
	 * @param array $order Order.
	 * @return array<string,mixed>|false
	 */
	public static function build_row( $order ) {
		if ( ! is_array( $order ) ) {
			return false;
		}

		$order_id = self::order_id_of( $order );

		if ( ! $order_id ) {
			return false;
		}

		$billing  = self::address_of( $order['billingAddress'] ?? null );
		$shipping = self::address_of( $order['shippingAddress'] ?? null );

		if ( '' === $shipping['country'] ) {
			$shipping = $billing;
		}

		$billing_state  = Regions::normalize_state( $billing['country'], $billing['state'] );
		$shipping_state = Regions::normalize_state( $shipping['country'], $shipping['state'] );

		$total          = self::money_of( $order['currentTotalPriceSet']['shopMoney']['amount'] ?? 0 );
		$tax            = self::money_of( $order['currentTotalTaxSet']['shopMoney']['amount'] ?? 0 );
		$shipping_total = self::money_of( $order['totalShippingPriceSet']['shopMoney']['amount'] ?? 0 );
		$status         = self::normalize_status( $order );
		$created        = self::normalize_datetime( $order['createdAt'] ?? null );
		$paid           = self::is_paid_status( $status ) ? self::normalize_datetime( $order['processedAt'] ?? $order['createdAt'] ?? null ) : null;
		$currency       = strtoupper( substr( (string) ( $order['currentTotalPriceSet']['shopMoney']['currencyCode'] ?? '' ), 0, 3 ) );

		if ( ! $currency ) {
			$currency = 'USD';
		}

		return array(
			'order_id'         => substr( $order_id, 0, 64 ),
			'status'           => substr( $status, 0, 32 ),
			'date_created'     => $created ? $created : '0000-00-00 00:00:00',
			'date_paid'        => $paid ? $paid : null,
			'billing_country'  => $billing['country'],
			'billing_state'    => substr( $billing_state, 0, 50 ),
			'shipping_country' => $shipping['country'],
			'shipping_state'   => substr( $shipping_state, 0, 50 ),
			'currency'         => $currency,
			'total_sales'      => $total,
			'tax_total'        => $tax,
			'shipping_total'   => $shipping_total,
			'net_total'        => $total - $tax - $shipping_total,
		);
	}

	/**
	 * Load one order from the API.
	 *
	 * @param string $order_id GID.
	 * @return array|null
	 */
	private function load_order( $order_id ) {
		$order_id = (string) $order_id;

		if ( ! $order_id || ! Client::ready() ) {
			return null;
		}

		if ( 0 !== strpos( $order_id, 'gid://' ) ) {
			$order_id = 'gid://shopify/Order/' . preg_replace( '/[^0-9]/', '', $order_id );
		}

		$client = new Client();
		$result = $client->graphql(
			'query One($id: ID!) {
				order(id: $id) {
					id
					name
					createdAt
					processedAt
					cancelledAt
					displayFinancialStatus
					currentTotalPriceSet { shopMoney { amount currencyCode } }
					currentTotalTaxSet { shopMoney { amount } }
					totalShippingPriceSet { shopMoney { amount } }
					shippingAddress { countryCodeV2 provinceCode province }
					billingAddress { countryCodeV2 provinceCode province }
				}
			}',
			array( 'id' => $order_id )
		);

		if ( is_wp_error( $result ) || empty( $result['order'] ) || ! is_array( $result['order'] ) ) {
			return null;
		}

		return $result['order'];
	}

	/**
	 * Stable order id for the report table.
	 *
	 * @param array $order Order.
	 * @return string
	 */
	private static function order_id_of( array $order ) {
		$id = (string) ( $order['id'] ?? '' );

		if ( preg_match( '#gid://shopify/Order/(\d+)#', $id, $match ) ) {
			return $match[1];
		}

		return $id;
	}

	/**
	 * Country and state from a Shopify address object.
	 *
	 * @param mixed $address Address.
	 * @return array{country:string,state:string}
	 */
	private static function address_of( $address ) {
		if ( ! is_array( $address ) ) {
			return array(
				'country' => '',
				'state'   => '',
			);
		}

		$country = strtoupper( substr( (string) ( $address['countryCodeV2'] ?? '' ), 0, 2 ) );
		$state   = (string) ( $address['provinceCode'] ?? '' );

		if ( ! $state ) {
			$state = (string) ( $address['province'] ?? '' );
		}

		return array(
			'country' => preg_match( '/^[A-Z]{2}$/', $country ) ? $country : '',
			'state'   => $state,
		);
	}

	/**
	 * Map a Shopify financial status onto a report status key.
	 *
	 * @param array $order Order.
	 * @return string
	 */
	private static function normalize_status( array $order ) {
		$status = strtolower( (string) ( $order['displayFinancialStatus'] ?? '' ) );
		$status = str_replace( array( ' ', '-' ), '_', $status );

		if ( 'canceled' === $status ) {
			$status = 'cancelled';
		}

		return $status ? $status : 'pending';
	}

	/**
	 * Statuses that represent a sale for the year filter.
	 *
	 * @param string $status Status key.
	 * @return bool
	 */
	private static function is_paid_status( $status ) {
		return in_array(
			$status,
			array(
				'paid',
				'partially_paid',
				'partially_refunded',
				'refunded',
				'authorized',
			),
			true
		);
	}

	/**
	 * Parse a money string.
	 *
	 * @param mixed $value Amount.
	 * @return float
	 */
	private static function money_of( $value ) {
		return round( (float) $value, 2 );
	}

	/**
	 * Normalise an ISO datetime string.
	 *
	 * @param mixed $value Datetime.
	 * @return string|null
	 */
	private static function normalize_datetime( $value ) {
		if ( empty( $value ) || '0000-00-00 00:00:00' === $value ) {
			return null;
		}

		$ts = strtotime( (string) $value );

		return $ts ? gmdate( 'Y-m-d H:i:s', $ts ) : null;
	}
}

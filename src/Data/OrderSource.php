<?php
/**
 * Locates Shopify orders through the Admin API.
 *
 * @package SalesByStateReportForShopify
 */

namespace SBSS\Data;

defined( 'ABSPATH' ) || exit;

/**
 * Pages orders from the Shopify Admin GraphQL API.
 */
class OrderSource {

	/**
	 * Orders query used for import.
	 */
	const ORDERS_QUERY = 'query Orders($first: Int!, $after: String) {
		orders(first: $first, after: $after, sortKey: CREATED_AT, reverse: false) {
			pageInfo { hasNextPage endCursor }
			nodes {
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
		}
	}';

	/**
	 * Total number of orders on the Shopify store.
	 *
	 * @return int
	 */
	public static function count() {
		$cached = get_transient( 'sbss_order_count' );

		if ( false !== $cached ) {
			return (int) $cached;
		}

		if ( ! Client::ready() ) {
			set_transient( 'sbss_order_count', 0, MINUTE_IN_SECONDS );
			return 0;
		}

		$client = new Client();
		$result = $client->graphql( 'query { ordersCount(query: "") { count } }' );

		if ( is_wp_error( $result ) ) {
			return 0;
		}

		$total = isset( $result['ordersCount']['count'] ) ? (int) $result['ordersCount']['count'] : 0;

		set_transient( 'sbss_order_count', $total, MINUTE_IN_SECONDS );

		return $total;
	}

	/**
	 * One page of orders, oldest first so the cursor is stable.
	 *
	 * @param string $after  GraphQL cursor.
	 * @param int    $limit  Page size.
	 * @return array{orders:array,count:int,after:string,has_next:bool}|\WP_Error
	 */
	public static function page( $after, $limit ) {
		if ( ! Client::ready() ) {
			return new \WP_Error(
				'sbss_disconnected',
				__( 'Connect a Shopify Admin API token before importing orders. The WordPress plugin token is Storefront-only.', 'sales-by-state-report-for-shopify' )
			);
		}

		$limit    = max( 1, min( 50, (int) $limit ) );
		$after    = is_string( $after ) ? $after : '';
		$client   = new Client();
		$variables = array( 'first' => $limit );

		if ( $after ) {
			$variables['after'] = $after;
		}

		$result = $client->graphql( self::ORDERS_QUERY, $variables );

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		$connection = isset( $result['orders'] ) && is_array( $result['orders'] ) ? $result['orders'] : array();
		$nodes      = isset( $connection['nodes'] ) && is_array( $connection['nodes'] ) ? $connection['nodes'] : array();
		$page_info  = isset( $connection['pageInfo'] ) && is_array( $connection['pageInfo'] ) ? $connection['pageInfo'] : array();

		return array(
			'orders'   => $nodes,
			'count'    => self::count(),
			'after'    => (string) ( $page_info['endCursor'] ?? '' ),
			'has_next' => ! empty( $page_info['hasNextPage'] ),
		);
	}

	/**
	 * Newest orders, used to keep the table current after the first import.
	 *
	 * @param int $limit Page size.
	 * @return array
	 */
	public static function recent( $limit = 50 ) {
		if ( ! Client::ready() ) {
			return array();
		}

		$limit  = max( 1, min( 50, (int) $limit ) );
		$client = new Client();
		$result = $client->graphql(
			'query Recent($first: Int!) {
				orders(first: $first, sortKey: CREATED_AT, reverse: true) {
					nodes {
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
				}
			}',
			array( 'first' => $limit )
		);

		if ( is_wp_error( $result ) ) {
			return array();
		}

		$nodes = $result['orders']['nodes'] ?? array();

		return is_array( $nodes ) ? $nodes : array();
	}
}

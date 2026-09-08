<?php
/**
 * Locates SureCart orders through the API.
 *
 * @package SalesByStateReportForSureCart
 */

namespace SBSSC\Data;

use SureCart\Models\Order;

defined( 'ABSPATH' ) || exit;

/**
 * Pages orders from the SureCart API (test and live).
 */
class OrderSource {

	/**
	 * Relations needed to build a report row.
	 *
	 * @return string[]
	 */
	public static function expands() {
		return array(
			'checkout',
			'checkout.invoice',
			'checkout.shipping_address',
			'checkout.billing_address',
		);
	}

	/**
	 * Total number of orders on the SureCart account.
	 *
	 * @return int
	 */
	public static function count() {
		$cached = get_transient( 'sbssc_order_count' );

		if ( false !== $cached ) {
			return (int) $cached;
		}

		$total = self::count_mode( false ) + self::count_mode( true );

		set_transient( 'sbssc_order_count', $total, MINUTE_IN_SECONDS );

		return $total;
	}

	/**
	 * Count orders in one mode.
	 *
	 * @param bool $live Live mode.
	 * @return int
	 */
	public static function count_mode( $live ) {
		if ( ! class_exists( Order::class ) ) {
			return 0;
		}

		$page = Order::where( array( 'live_mode' => (bool) $live ) )->paginate(
			array(
				'page'     => 1,
				'per_page' => 1,
			)
		);

		if ( is_wp_error( $page ) || ! is_object( $page ) ) {
			return 0;
		}

		return (int) ( $page->pagination->count ?? 0 );
	}

	/**
	 * One page of orders.
	 *
	 * @param bool $live  Live mode.
	 * @param int  $page  Page number (1-based).
	 * @param int  $limit Page size.
	 * @return array{orders:array,count:int}|\WP_Error
	 */
	public static function page( $live, $page, $limit ) {
		if ( ! class_exists( Order::class ) ) {
			return new \WP_Error( 'sbssc_surecart', __( 'SureCart is not available.', 'sales-by-state-report-for-surecart' ) );
		}

		$page  = max( 1, (int) $page );
		$limit = max( 1, min( 20, (int) $limit ) );

		$result = Order::with( self::expands() )->where( array( 'live_mode' => (bool) $live ) )->paginate(
			array(
				'page'     => $page,
				'per_page' => $limit,
			)
		);

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return array(
			'orders' => self::items( $result ),
			'count'  => (int) ( $result->pagination->count ?? 0 ),
		);
	}

	/**
	 * Orders on a SureCart Collection.
	 *
	 * @param mixed $result Paginate result.
	 * @return array
	 */
	private static function items( $result ) {
		if ( ! is_object( $result ) ) {
			return array();
		}

		$data = $result->data;

		return is_array( $data ) ? $data : array();
	}
}

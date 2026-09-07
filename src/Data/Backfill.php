<?php
/**
 * Populates the report table from existing Shopify orders.
 *
 * @package SalesByStateReportForShopify
 */

namespace SBSS\Data;

use SBSS\Install\Schema;

defined( 'ABSPATH' ) || exit;

/**
 * Walks every order once, one API page at a time.
 */
class Backfill {

	/**
	 * Option holding the current API page cursor.
	 */
	const CURSOR_OPTION = 'sbss_backfill_cursor';

	/**
	 * Process one batch.
	 *
	 * @param int $limit Orders per batch.
	 * @return array{processed:int,remaining:int,complete:bool,cursor:array}
	 */
	public static function run_batch( $limit = 25 ) {
		Schema::maybe_install();

		$cursor = self::cursor();
		$limit  = max( 1, min( 50, (int) $limit ) );

		if ( 'done' === $cursor['phase'] ) {
			return array(
				'processed' => 0,
				'remaining' => 0,
				'complete'  => true,
				'cursor'    => $cursor,
			);
		}

		$result = OrderSource::page( (string) $cursor['after'], $limit );

		if ( is_wp_error( $result ) ) {
			return array(
				'processed' => 0,
				'remaining' => OrderSource::count(),
				'complete'  => false,
				'cursor'    => $cursor,
			);
		}

		$orders = $result['orders'];
		$sync   = new Sync();
		$done   = 0;

		foreach ( $orders as $order ) {
			if ( $sync->upsert( $order ) ) {
				++$done;
			}
		}

		$fetched = count( $orders );

		if ( 0 === $fetched || empty( $result['has_next'] ) ) {
			$cursor = array(
				'phase' => 'done',
				'after' => (string) $result['after'],
			);
		} else {
			$cursor['after'] = (string) $result['after'];
		}

		update_option( self::CURSOR_OPTION, wp_json_encode( $cursor ), false );
		delete_transient( 'sbss_order_count' );

		$remaining = self::remaining_from( $cursor );

		return array(
			'processed' => $done,
			'remaining' => $remaining,
			'complete'  => 'done' === $cursor['phase'],
			'cursor'    => $cursor,
		);
	}

	/**
	 * Number of orders still to be walked.
	 *
	 * @return int
	 */
	public static function remaining() {
		return self::remaining_from( self::cursor() );
	}

	/**
	 * Whether the table has been built.
	 *
	 * @return bool
	 */
	public static function is_complete() {
		return 'done' === self::cursor()['phase'];
	}

	/**
	 * Empty the table and start again.
	 *
	 * @return void
	 */
	public static function reset() {
		Schema::maybe_install();
		Schema::truncate();
		delete_option( self::CURSOR_OPTION );
		delete_transient( 'sbss_order_count' );
	}

	/**
	 * Restart the import when the table is empty but the store has orders.
	 *
	 * @return void
	 */
	public static function heal_if_empty() {
		if ( ! Schema::table_exists() || ! self::is_complete() ) {
			return;
		}

		if ( Schema::row_count() > 0 ) {
			return;
		}

		if ( OrderSource::count() <= 0 ) {
			return;
		}

		delete_option( self::CURSOR_OPTION );
		delete_transient( 'sbss_order_count' );
	}

	/**
	 * Current cursor.
	 *
	 * @return array{phase:string,after:string}
	 */
	private static function cursor() {
		$raw = get_option( self::CURSOR_OPTION, '' );

		if ( is_array( $raw ) ) {
			$decoded = $raw;
		} else {
			$decoded = json_decode( (string) $raw, true );
		}

		$phase = isset( $decoded['phase'] ) ? (string) $decoded['phase'] : 'page';
		$after = isset( $decoded['after'] ) ? (string) $decoded['after'] : '';

		if ( ! in_array( $phase, array( 'page', 'done' ), true ) ) {
			$phase = 'page';
		}

		return array(
			'phase' => $phase,
			'after' => $after,
		);
	}

	/**
	 * Estimate remaining API rows from the cursor.
	 *
	 * @param array $cursor Cursor.
	 * @return int
	 */
	private static function remaining_from( array $cursor ) {
		if ( 'done' === $cursor['phase'] ) {
			return 0;
		}

		$total = OrderSource::count();
		$have  = Schema::row_count();

		return max( 0, $total - $have );
	}
}

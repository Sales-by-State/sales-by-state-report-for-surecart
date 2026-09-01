<?php
/**
 * Removes the plugin's data when it is deleted.
 *
 * @package SalesByStateReportForSureCart
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

global $wpdb;

$sbssc_options = array(
	'sbssc_db_version',
	'sbssc_backfill_cursor',
	'sbssc_year_start',
);

foreach ( $sbssc_options as $sbssc_option ) {
	delete_option( $sbssc_option );
}

delete_transient( 'sbssc_order_count' );
delete_transient( 'sbssc_store_country' );

if ( is_multisite() ) {
	foreach ( $sbssc_options as $sbssc_option ) {
		delete_site_option( $sbssc_option );
	}

	delete_site_transient( 'sbssc_order_count' );
	delete_site_transient( 'sbssc_store_country' );
}

// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange
$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}sbssc_order_state" );

if ( function_exists( 'as_unschedule_all_actions' ) ) {
	as_unschedule_all_actions( 'sbssc_backfill_batch', array(), 'sales-by-state-report-for-surecart' );
}

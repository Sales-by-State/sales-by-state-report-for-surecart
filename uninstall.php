<?php
/**
 * Removes the plugin's data when it is deleted.
 *
 * @package SalesByStateReportForShopify
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

global $wpdb;

$sbss_options = array(
	'sbss_db_version',
	'sbss_backfill_cursor',
	'sbss_year_start',
	'sbss_credentials',
	'sbss_oauth_token',
);

foreach ( $sbss_options as $sbss_option ) {
	delete_option( $sbss_option );
}

delete_transient( 'sbss_order_count' );
delete_transient( 'sbss_store_country' );
delete_transient( 'sbss_currency_code' );

if ( is_multisite() ) {
	foreach ( $sbss_options as $sbss_option ) {
		delete_site_option( $sbss_option );
	}

	delete_site_transient( 'sbss_order_count' );
	delete_site_transient( 'sbss_store_country' );
	delete_site_transient( 'sbss_currency_code' );
}

// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange
$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}sbss_order_state" );

if ( function_exists( 'as_unschedule_all_actions' ) ) {
	as_unschedule_all_actions( 'sbss_backfill_batch', array(), 'sales-by-state-report-for-shopify' );
	as_unschedule_all_actions( 'sbss_sync_recent', array(), 'sales-by-state-report-for-shopify' );
}

wp_unschedule_hook( 'sbss_backfill_batch' );
wp_unschedule_hook( 'sbss_sync_recent' );

<?php
/**
 * Plugin Name:          Sales by State Report for Shopify
 * Plugin URI:           https://salesbystate.com/
 * Description:          See a yearly breakdown of Shopify sales by state / county / province for a given country, filterable by order status.
 * Version:              1.0.0
 * Author:               Rodolfo Melogli
 * Author URI:           https://www.businessbloomer.com/
 * Developer:            Rodolfo Melogli
 * Developer URI:        https://www.businessbloomer.com/
 * Text Domain:          sales-by-state-report-for-shopify
 * Domain Path:          /languages
 * Requires at least:    6.4
 * Requires PHP:         7.4
 * License:              GPL-2.0-or-later
 * License URI:          https://www.gnu.org/licenses/gpl-2.0.html
 *
 * @package SalesByStateReportForShopify
 * @copyright 2026 Rodolfo Melogli
 */

defined( 'ABSPATH' ) || exit;

define( 'SBSS_VERSION', '1.0.0' );
define( 'SBSS_FILE', __FILE__ );
define( 'SBSS_DIR', plugin_dir_path( __FILE__ ) );
define( 'SBSS_URL', plugin_dir_url( __FILE__ ) );

spl_autoload_register(
	function ( $class_name ) {
		$prefix = 'SBSS\\';

		if ( 0 !== strpos( $class_name, $prefix ) ) {
			return;
		}

		$relative = substr( $class_name, strlen( $prefix ) );
		$path     = SBSS_DIR . 'src/' . str_replace( '\\', '/', $relative ) . '.php';

		if ( is_readable( $path ) ) {
			require_once $path;
		}
	}
);

add_action(
	'plugins_loaded',
	function () {
		if ( ! defined( 'SHOPIFY_WP_VERSION' ) && ! defined( 'SHOPIFY_PLUGIN_DIR' ) ) {
			add_action(
				'admin_notices',
				function () {
					if ( ! current_user_can( 'activate_plugins' ) ) {
						return;
					}

					printf(
						'<div class="notice notice-error"><p>%s</p></div>',
						esc_html__( 'Sales by State Report for Shopify requires the official Shopify WordPress plugin to be installed and active.', 'sales-by-state-report-for-shopify' )
					);
				}
			);

			return;
		}

		SBSS\Plugin::instance()->init();
	},
	20
);

register_activation_hook(
	SBSS_FILE,
	function () {
		require_once SBSS_DIR . 'src/Install/Schema.php';
		SBSS\Install\Schema::install();
	}
);

register_deactivation_hook(
	SBSS_FILE,
	function () {
		if ( function_exists( 'as_unschedule_all_actions' ) ) {
			as_unschedule_all_actions( 'sbss_backfill_batch', array(), 'sales-by-state-report-for-shopify' );
			as_unschedule_all_actions( 'sbss_sync_recent', array(), 'sales-by-state-report-for-shopify' );
		}

		wp_unschedule_hook( 'sbss_backfill_batch' );
		wp_unschedule_hook( 'sbss_sync_recent' );
	}
);

<?php
/**
 * Plugin Name:          Sales by State Report for SureCart
 * Plugin URI:           https://salesbystate.com/
 * Description:          See a yearly breakdown of SureCart sales by state / county / province for a given country, filterable by order status.
 * Version:              1.1.0
 * Author:               Rodolfo Melogli
 * Author URI:           https://salesbystate.com/
 * Developer:            Rodolfo Melogli
 * Developer URI:        https://salesbystate.com/
 * Text Domain:          sales-by-state-report-for-surecart
 * Domain Path:          /languages
 * Requires at least:    6.4
 * Tested up to:         7.1
 * Requires PHP:         7.4
 * Requires Plugins:     surecart
 * License:              GPL-2.0-or-later
 * License URI:          https://www.gnu.org/licenses/gpl-2.0.html
 *
 * @package SalesByStateReportForSureCart
 * @copyright 2026 Rodolfo Melogli
 */

defined( 'ABSPATH' ) || exit;

define( 'SBSSC_VERSION', '1.1.0' );
define( 'SBSSC_FILE', __FILE__ );
define( 'SBSSC_DIR', plugin_dir_path( __FILE__ ) );
define( 'SBSSC_URL', plugin_dir_url( __FILE__ ) );

spl_autoload_register(
	function ( $class_name ) {
		$prefix = 'SBSSC\\';

		if ( 0 !== strpos( $class_name, $prefix ) ) {
			return;
		}

		$relative = substr( $class_name, strlen( $prefix ) );
		$path     = SBSSC_DIR . 'src/' . str_replace( '\\', '/', $relative ) . '.php';

		if ( is_readable( $path ) ) {
			require_once $path;
		}
	}
);

add_action(
	'plugins_loaded',
	function () {
		if ( ! defined( 'SURECART_PLUGIN_FILE' ) ) {
			add_action(
				'admin_notices',
				function () {
					if ( ! current_user_can( 'activate_plugins' ) ) {
						return;
					}

					printf(
						'<div class="notice notice-error"><p>%s</p></div>',
						esc_html__( 'Sales by State Report for SureCart requires SureCart to be installed and active.', 'sales-by-state-report-for-surecart' )
					);
				}
			);

			return;
		}

		SBSSC\Plugin::instance()->init();
	},
	20
);

register_activation_hook(
	SBSSC_FILE,
	function () {
		require_once SBSSC_DIR . 'src/Install/Schema.php';
		SBSSC\Install\Schema::install();
	}
);

register_deactivation_hook(
	SBSSC_FILE,
	function () {
		if ( function_exists( 'as_unschedule_all_actions' ) ) {
			as_unschedule_all_actions( 'sbssc_backfill_batch', array(), 'sales-by-state-report-for-surecart' );
		}
	}
);

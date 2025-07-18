<?php
/**
 * Plugin Name: Connect Ecommerce
 * Plugin URI: https://close.technology/wordpress-plugins/connect-ecommerce/
 * Description: Connects Ecommerce WooCommerce to ERPs and CRMs. Syncs products, customers, orders and stock.
 * Author: Closetechnology
 * Author URI: https://close.technology/
 * Version: 3.1.2
 *
 * @package WordPress
 * Text Domain: connect-ecommerce
 * Requires Plugins: woocommerce
 * License:     GPL-2.0+
 * License URI: http://www.gnu.org/licenses/gpl-2.0.txt
 */

defined( 'ABSPATH' ) || exit;

define( 'CONECOM_VERSION', '3.1.2' );
define( 'CONECOM_FILE', __FILE__ );
define( 'CONECOM_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'CONECOM_PLUGIN_PATH', plugin_dir_path( __FILE__ ) );
define( 'CONECOM_SHOP_URL', 'https://close.technology/' );


require_once CONECOM_PLUGIN_PATH . 'vendor/autoload.php';

function conecom_get_options() {
		/**
	 * Default values
	 */
	global $wpdb;

	return apply_filters(
		'conecom_options_plugin',
		[
			'clientify' => [
				'name'                       => 'Clientify',
				'slug'                       => 'conecom-clientify',
				'version'                    => CONECOM_VERSION,
				'plugin_name'                => 'Connect WooCommerce Clientify',
				'plugin_slug'                => 'connect-ecommerce-clientify',
				'disable_modules'            => array( 'subscription' ),
				'api_url'                    => CONECOM_SHOP_URL,
				'api_pagination'             => 100,
				'product_price_tax_option'   => true,
				'product_price_rate_option'  => false,
				'product_option_stock'       => false,
				'order_send_attachments'     => true,
				'order_sync_partial'         => true,
				'order_import_free_order'    => true,
				'order_only_order_completed' => 'completed',
				'settings_logo'              => CONECOM_PLUGIN_URL . 'includes/Connector/assets/logo.svg',
				'settings_admin_message'     => sprintf(
					// translators: %s url of contact.
					__( 'Put the connection API key settings in order to connect and sync products. You can go here <a href = "%s" target = "_blank">App Test API</a>.', 'connect-ecommerce' ),
					'https://app.test.com/api'
				),
				'settings_special_tabs'      => array(),
				'settings_fields'            => array( 'apipassword' ),
				'table_sync'                 => $wpdb->prefix . 'sync_conecom-clientify',
				'file'                       => __FILE__,
				'cron'                       => array(
					array(
						'key'      => 'every_five_minutes',
						'interval' => 300,
						'display'  => __( 'Every 5 minutes', 'connect-ecommerce' ),
						'cron'     => 'conecom-clientify_sync_five_minutes',
					),
					array(
						'key'      => 'every_fifteen_minutes',
						'interval' => 900,
						'display'  => __( 'Every 15 minutes', 'connect-ecommerce' ),
						'cron'     => 'conecom-clientify_sync_fifteen_minutes',
					),
					array(
						'key'      => 'every_thirty_minutes',
						'interval' => 1800,
						'display'  => __( 'Every 30 Minutes', 'connect-ecommerce' ),
						'cron'     => 'conecom-clientify_sync_thirty_minutes',
					),
					array(
						'key'      => 'every_one_hour',
						'interval' => 3600,
						'display'  => __( 'Every 1 Hour', 'connect-ecommerce' ),
						'cron'     => 'conecom-clientify_sync_one_hour',
					),
					array(
						'key'      => 'every_three_hours',
						'interval' => 10800,
						'display'  => __( 'Every 3 Hours', 'connect-ecommerce' ),
						'cron'     => 'conecom-clientify_sync_three_hours',
					),
					array(
						'key'      => 'every_six_hours',
						'interval' => 21600,
						'display'  => __( 'Every 6 Hours', 'connect-ecommerce' ),
						'cron'     => 'conecom-clientify_sync_six_hours',
					),
					array(
						'key'      => 'every_twelve_hours',
						'interval' => 43200,
						'display'  => __( 'Every 12 Hours', 'connect-ecommerce' ),
						'cron'     => 'conecom-clientify_sync_twelve_hours',
					),
				),
			],
		]
	);
}



add_action( 'init', 'conecom_loads' );
/**
 * Connect WooCommerce loads.
 *
 * @return void
 */
function conecom_loads() {
	require_once CONECOM_PLUGIN_PATH . 'includes/Plugin_Main.php';
	require_once CONECOM_PLUGIN_PATH . 'includes/Connector/class-api-clientify.php';

	$conecom_options = conecom_get_options();
	new CLOSE\ConnectEcommerce\Base( $conecom_options );
}

if ( defined( 'WP_CLI' ) && WP_CLI ) {
	require_once CONECOM_PLUGIN_PATH . 'includes/CLI/Import_Products_Command.php';

	/**
	 * Registers our command when cli get's initialized.
	 *
	 * @since  1.0.0
	 * @author David Perez
	 */
	function conecom_import_products_register_commands() {
		WP_CLI::add_command('conecom', 'Import_Products_Command' );
	}

	add_action( 'cli_init', 'conecom_import_products_register_commands', 20 );
}
<?php
/**
 * Plugin Name:       Connect and EU VAT Compliance for WooCommerce
 * Plugin URI:        https://close.technology/wordpress-plugins/connect-ecommerce/
 * Description:       Connects Ecommerce WooCommerce to ERPs and CRMs. Syncs products, customers, orders and stock. Includes EU VAT Compliance. Import European Taxes and check VAT compliance.
 * Author:            Closetechnology
 * Author URI:        https://close.technology/
 * Version:           3.2.1-beta.2
 * Requires PHP:      7.4
 * Requires at least: 6.3
 * Text Domain:       woocommerce-es
 * Requires Plugins:  woocommerce
 * License:           GPL-2.0+
 * License URI:       http://www.gnu.org/licenses/gpl-2.0.txt
 *
 * Prefix:            conecom_
 *
 * @package WordPress
 */

defined( 'ABSPATH' ) || exit;

define( 'CONECOM_VERSION', '3.2.1-beta.2' );
define( 'CONECOM_FILE', __FILE__ );
define( 'CONECOM_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'CONECOM_PLUGIN_PATH', plugin_dir_path( __FILE__ ) );
define( 'CONECOM_SHOP_URL', 'https://close.technology/' );

// VAT field slugs used across the plugin.
define(
	'CONECOM_VAT_FIELD_SLUGS',
	array(
		'billing_vat',
		'billing_nif',
		'billing_vat_number',
		'VAT Number', // Support to SIMBA Hosting plugin.
	)
);

require_once CONECOM_PLUGIN_PATH . 'vendor/autoload.php';

/**
 * Gets the options for the plugin.
 *
 * @return array
 */
function conecom_get_options() {
		/**
	 * Default values
	 */
	global $wpdb;

	return apply_filters(
		'conecom_options_plugin',
		array(
			'clientify' => array(
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
					__( 'Put the connection API key settings in order to connect and sync products. You can go here <a href = "%s" target = "_blank">App Test API</a>.', 'woocommerce-es' ),
					'https://app.test.com/api'
				),
				'settings_special_tabs'      => array(),
				'settings_fields'            => array( 'apipassword' ),
				'table_sync'                 => $wpdb->prefix . 'sync_conecom-clientify',
				'file'                       => __FILE__,
			),
		)
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
		WP_CLI::add_command( 'conecom', 'Import_Products_Command' );
	}

	add_action( 'cli_init', 'conecom_import_products_register_commands', 20 );
}

register_activation_hook( __FILE__, 'conecom_move_settings' );
/**
 * Move settings from old plugin to new plugin
 *
 * @return void
 */
function conecom_move_settings() {
	CLOSE\ConnectEcommerce\Helpers\HELPER::move_settings();
}

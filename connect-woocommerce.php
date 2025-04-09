<?php
/**
 * Plugin Name: Connect WooCommerce
 * Plugin URI: https://close.technology/wordpress-plugins/connect-woocommerce-test/
 * Description: Connects Test with WooCommerce and syncs products, customers, orders and stock.
 * Author: Closetechnology
 * Author URI: https://close.technology/
 * Version: 2.2.2
 *
 * @package WordPress
 * Text Domain: connect-woocommerce-test
 * Domain Path: /languages
 * License: GNU General Public License version 3.0
 * License URI: http://www.gnu.org/licenses/gpl-3.0.html
 */

defined( 'ABSPATH' ) || exit;

define( 'CONTEST_VERSION', '1.0.0' );
define( 'CONTEST_FILE', __FILE__ );
define( 'CONTEST_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'CONTEST_PLUGIN_PATH', plugin_dir_path( __FILE__ ) );
define( 'CONTEST_SHOP_URL', 'https://close.technology/' );

/**
 * Default values
 */
global $wpdb;

$connwoo_options_test = array(
	'name'                       => 'TEST',
	'slug'                       => 'connwoo_test',
	'version'                    => CONTEST_VERSION,
	'plugin_name'                => 'Connect WooCommerce Test',
	'plugin_slug'                => 'connect-woocommerce-test',
	'disable_modules'            => array( 'order', 'subscription' ),
	'api_url'                    => CONTEST_SHOP_URL,
	'product_price_tax_option'   => true,
	'product_price_rate_option'  => true,
	'product_option_stock'       => true,
	'order_send_attachments'     => true,
	'order_sync_partial'         => true,
	'order_import_free_order'    => true,
	'order_only_order_completed' => 'completed',
	'settings_logo'              => CONTEST_PLUGIN_URL . 'includes/assets/logo.svg',
	'settings_admin_message'     => sprintf(
		// translators: %s url of contact.
		__( 'Put the connection API key settings in order to connect and sync products. You can go here <a href = "%s" target = "_blank">App Test API</a>.', 'connect-woocommerce-test' ),
		'https://app.test.com/api'
	),
	'settings_special_tabs'      => array( 'subscriptions' ),
	'settings_fields'            => array( 'url', 'username', 'password', 'apipassword', 'company' ),
	'table_sync'                 => $wpdb->prefix . 'sync_connwoo_test',
	'file'                       => __FILE__,
	'cron'                       => array(
		array(
			'key'      => 'every_five_minutes',
			'interval' => 300,
			'display'  => __( 'Every 5 minutes', 'connect-woocommerce' ),
			'cron'     => 'connwoo_test_sync_five_minutes',
		),
		array(
			'key'      => 'every_fifteen_minutes',
			'interval' => 900,
			'display'  => __( 'Every 15 minutes', 'connect-woocommerce' ),
			'cron'     => 'connwoo_test_sync_fifteen_minutes',
		),
		array(
			'key'      => 'every_thirty_minutes',
			'interval' => 1800,
			'display'  => __( 'Every 30 Minutes', 'connect-woocommerce' ),
			'cron'     => 'connwoo_test_sync_thirty_minutes',
		),
		array(
			'key'      => 'every_one_hour',
			'interval' => 3600,
			'display'  => __( 'Every 1 Hour', 'connect-woocommerce' ),
			'cron'     => 'connwoo_test_sync_one_hour',
		),
		array(
			'key'      => 'every_three_hours',
			'interval' => 10800,
			'display'  => __( 'Every 3 Hours', 'connect-woocommerce' ),
			'cron'     => 'connwoo_test_sync_three_hours',
		),
		array(
			'key'      => 'every_six_hours',
			'interval' => 21600,
			'display'  => __( 'Every 6 Hours', 'connect-woocommerce' ),
			'cron'     => 'connwoo_test_sync_six_hours',
		),
		array(
			'key'      => 'every_twelve_hours',
			'interval' => 43200,
			'display'  => __( 'Every 12 Hours', 'connect-woocommerce' ),
			'cron'     => 'connwoo_test_sync_twelve_hours',
		),
	),
);

require_once CONTEST_PLUGIN_PATH . 'connect-woocommerce-library/loader.php';
require_once CONTEST_PLUGIN_PATH . 'includes/class-api-test.php';

new Connect_WooCommerce( $connwoo_options_test );

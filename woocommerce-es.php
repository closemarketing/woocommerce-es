<?php
/*
 * Plugin Name: WooCommerce Enhancements for Spanish Market
 * Plugin URI: http://www.closemarketing.es/portafolio/plugin-woocommerce-espanol/
 * Description: Extends the WooCommerce plugin for Spanish needs: EU VAT included in form and order, and add-ons with the Spanish language.
 *
 * Version: 2.0
 * Requires at least: 5.0
 *
 * WC requires at least: 3.0
 * WC tested up to: 4.1
 *
 * Author: Closemarketing
 * Author URI: http://www.closemarketing.net/
 *
 * Text Domain: woocommerce-es
 * Domain Path: /languages/
 *
 * License: GNU General Public License v3.0
 * License URI: http://www.gnu.org/licenses/gpl-3.0.html
*/

define( 'WCES_NAME', 'WooCommerce Enhancements for Spanish Market' );
define( 'WCES_REQUIRED_PHP_VERSION', '5.4' );
define( 'WCES_REQUIRED_WP_VERSION', '4.6' );
define( 'WCES_REQUIRED_WC_VERSION', '2.6' );

if ( ! function_exists( 'wces_fs' ) ) {

	/**
	 * Freemius Loader
	 *
	 * @return array
	 */
	function wces_fs() {
		global $wces_fs;
  
		if ( ! isset( $wces_fs ) ) {
			// Include Freemius SDK.
			require_once dirname( __FILE__ ) . '/vendor/freemius/wordpress-sdk/start.php';

			$wces_fs = fs_dynamic_init(
				array(
					'id'             => '8034',
					'slug'           => 'woocommerce-es',
					'type'           => 'plugin',
					'public_key'     => 'pk_a7641c2a1d3188ddea51542610085',
					'is_premium'     => false,
					'has_addons'     => true,
					'has_paid_plans' => false,
					'menu'           => array(
					'slug'           => 'wces',
					'first-path'     => 'admin.php?page=wces',
				),
			));
		}

		return $wces_fs;
	}
  
	// Init Freemius.
	wces_fs();
	// Signal that SDK was initiated.
	do_action( 'wces_fs_loaded' );
}

add_action( 'plugins_loaded', 'wces_update_option_check' );
/**
 * Reload options
 *
 * @return void
 */
function wces_update_option_check() {
	$array_options = array( 'wces_vat_show', 'wces_vat_mandatory', 'wces_opt_checkout', 'wces_company' );
	foreach ( $array_options as $option ) {
		$value_option = get_option( $option );
		if ( $value_option ) {
			$actual_options            = get_option( 'wces_settings' );
			$actual_options[ $option ] = $value_option;
			delete_option( $option );
			update_option( 'wces_settings', $actual_options );
		}
	}
}


/**
 * Checks if the system requirements are met
 *
 * @return bool True if system requirements are met, false if not
 */
function wces_requirements_met() {
	global $wp_version;
	require_once( ABSPATH . '/wp-admin/includes/plugin.php' );  // to get is_plugin_active() early

	if ( version_compare( PHP_VERSION, WCES_REQUIRED_PHP_VERSION, '<' ) ) {
		return false;
	}

	if ( version_compare( $wp_version, WCES_REQUIRED_WP_VERSION, '<' ) ) {
		return false;
	}

	if ( ! is_plugin_active( 'woocommerce/woocommerce.php' ) ) {
		return false;
	}

	$woocommer_data = get_plugin_data( WP_PLUGIN_DIR .'/woocommerce/woocommerce.php', false, false );

	if ( version_compare($woocommer_data['Version'] , WCES_REQUIRED_WC_VERSION, '<' ) ) {
		return false;
	}

	return true;
}

function wces_requirements_error () {
	global $wp_version;
	?>
	<div class="notice notice-success is-dismissible">
		<p>
			<?php esc_html_e( 'You need to install WooCommerce in order to use the plugin:', 'woocommerce-es' ); ?>
			<strong>WooCommerce Enhancements for Spanish Market</strong>
		</p>
	</div>
	<?php
}

if ( wces_requirements_met() ) {
	// Include files.
	require_once plugin_dir_path( __FILE__ ) . '/includes/class-languages.php';
	require_once plugin_dir_path( __FILE__ ) . '/includes/class-admin.php';
} else {
	add_action( 'admin_notices', 'wces_requirements_error' );
}

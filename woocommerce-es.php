<?php
/*
 * Plugin Name: WPSPA Spanish Enhacements for WooCommerce
 * Plugin URI: http://www.closemarketing.es/portafolio/plugin-woocommerce-espanol/
 * Description: Extends the WooCommerce plugin for Spanish needs: EU VAT included in form and order, and add-ons with the Spanish language.
 *
 * Version: 2.1
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

define( 'WCES_NAME', 'WPSPA Spanish Enhacements for WooCommerce' );
define( 'WPSPA_VERSION', '2.1' );
define( 'WCES_REQUIRED_PHP_VERSION', '5.4' );
define( 'WCES_REQUIRED_WP_VERSION', '4.6' );
define( 'WCES_REQUIRED_WC_VERSION', '2.6' );

add_action( 'init', 'wces_update_options_settings' );
/**
 * Update process
 *
 * @return void
 */
function wces_update_options_settings() {
	$old_version = get_option( 'wces_plugin_version', '1.7' );

	if ( ! ( version_compare( $old_version, WPSPA_VERSION ) < 0 ) ) {
		return false;
	}
	$array_options = array(
		'wces_vat_show'      => 'vat_show',
		'wces_vat_mandatory' => 'vat_mandatory',
		'wces_opt_checkout'  => 'opt_checkout',
		'wces_company'       => 'company_field',
	);
	foreach ( $array_options as $key => $new_key ) {
		$value_option = get_option( $key );
		if ( $value_option ) {
			$actual_options             = get_option( 'wces_settings' );
			$actual_options[ $new_key ] = $value_option;
			delete_option( $key );
			update_option( 'wces_settings', $actual_options );
		}
	}
	update_option( 'wpspa_plugin_version', WPSPA_VERSION );
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
	require_once plugin_dir_path( __FILE__ ) . '/includes/class-public.php';
	require_once plugin_dir_path( __FILE__ ) . '/includes/class-admin.php';
} else {
	add_action( 'admin_notices', 'wces_requirements_error' );
}

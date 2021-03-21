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

// Include files.
require_once plugin_dir_path( __FILE__ ) . '/includes/class-languages.php';
require_once plugin_dir_path( __FILE__ ) . '/includes/class-admin.php';

if ( ! function_exists( 'wces_fs' ) ) {
	// Create a helper function for easy SDK access.
	function wces_fs() {
		global $wces_fs;
  
		if ( ! isset( $wces_fs ) ) {
			// Include Freemius SDK.
			require_once dirname( __FILE__ ) . '/freemius/start.php';

			$wces_fs = fs_dynamic_init( array(
				'id'                  => '8034',
				'slug'                => 'woocommerce-es',
				'type'                => 'plugin',
				'public_key'          => 'pk_a7641c2a1d3188ddea51542610085',
				'is_premium'          => false,
				'has_addons'          => true,
				'has_paid_plans'      => false,
				'menu'                => array(
				'slug'           => 'wces',
				'first-path'     => 'admin.php?page=wces',
				),
			) );
		}
  
	    return $wces_fs;
	}
  
	// Init Freemius.
	wces_fs();
	// Signal that SDK was initiated.
	do_action( 'wces_fs_loaded' );
}
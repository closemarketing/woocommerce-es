<?php
/**
 * Library for Connect WooCommerce
 *
 * @package    WordPress
 * @author     David Perez <david@closemarketing.es>
 * @copyright  2022 Closemarketing
 * @version    1.5.10
 */

namespace CLOSE\ConnectEcommerce;

defined( 'ABSPATH' ) || exit;

use CLOSE\ConnectEcommerce\Admin\Settings;

/**
 * Class Wrapper.
 *
 * @since Version 3 digits
 */
class Base {

	/**
	 * Construct of class
	 *
	 * @param array $options Options of plugin.
	 */
	public function __construct( $options = array() ) {
		if ( is_admin() ) {
			new Settings( $options );
		}

		new Connect_WooCommerce_Orders( $options );
		new Connect_WooCommerce_Import( $options );
		new Connect_WooCommerce_Public( $options );
		new Connect_WooCommerce_Public_MyAccount(  $options );
		new Connect_WooCommerce_Product_Widget( $options );
		new Connect_WooCommerce_Orders_Widget( $options );
	}
}

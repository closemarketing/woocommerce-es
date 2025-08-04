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
use CLOSE\ConnectEcommerce\Admin\Import_Products;
use CLOSE\ConnectEcommerce\Admin\Widget_Order;
use CLOSE\ConnectEcommerce\Admin\Widget_Product;
use CLOSE\ConnectEcommerce\Admin\Orders;
use CLOSE\ConnectEcommerce\Frontend\Checkout;
use CLOSE\ConnectEcommerce\Frontend\MyAccount;
/**
 * Class Wrapper.
 *
 * @since Version 3 digits
 */
class Base {

	/**
	 * Options of plugin.
	 *
	 * @var array
	 */
	private $options = array();

	/**
	 * Construct of class
	 *
	 * @param array $options Options of plugin.
	 */
	public function __construct( $options = array() ) {
		$this->options = $options;
		if ( is_admin() ) {
			new Settings( $options );
			new Import_Products( $options );
			new Widget_Product( $options );
			new Widget_Order( $options );
		}

		new Orders( $options );
		new Checkout( $options );
		new MyAccount( $options );
	}

	/**
	 * Get options of plugin.
	 *
	 * @return array
	 */
	public function get_options( ) {
		return $this->options;
	}
}

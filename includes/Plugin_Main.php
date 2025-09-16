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
use CLOSE\ConnectEcommerce\Admin\Notices;
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
			new Notices();
		}

		new Orders( $options );
		new Checkout( $options );
		new MyAccount( $options );

		register_activation_hook( $options['main_file'], array( $this, 'process_activation' ) );
	}

	/**
	 * Process activation.
	 */
	public function process_activation() {
		HELPER::create_sync_table( $this->options['table_sync'] ?? '' );
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

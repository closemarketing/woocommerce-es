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
use CLOSE\ConnectEcommerce\Admin\Setup_Wizard;
use CLOSE\ConnectEcommerce\Admin\Import_Products;
use CLOSE\ConnectEcommerce\Admin\Widget_Order;
use CLOSE\ConnectEcommerce\Admin\Widget_Product;
use CLOSE\ConnectEcommerce\Admin\Orders;
use CLOSE\ConnectEcommerce\Admin\Notices;
use CLOSE\ConnectEcommerce\Admin\Taxes_Rates;
use CLOSE\ConnectEcommerce\Admin\Taxes_Types_ERP;
use CLOSE\ConnectEcommerce\Helpers\HELPER;
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
	private $options  = array();

	/**
	 * Construct of class
	 *
	 * @param array $options Options of plugin.
	 */
	public function __construct( $options = array() ) {
		$this->options   = $options;
		$connectors_data = HELPER::get_connectors( $options );
		$connector       = HELPER::get_connector( $options );

		add_action( 'update_option_woocommerce_prices_include_tax', array( HELPER::class, 'sync_tax_option_with_woocommerce' ) );

		if ( is_admin() ) {
			new Settings( $connectors_data );
			new Setup_Wizard( $options );
			new Import_Products( $connector );
			new Widget_Product( $connector, $connectors_data );
			new Widget_Order( $connector, $connectors_data );
			new Notices();
			new Taxes_Rates( $connector );
			new Taxes_Types_ERP( $connector );
		}

		new Orders( $connector );
		new Checkout( $connector );
		new MyAccount( $connector );
	}

	/**
	 * Get options of plugin.
	 *
	 * @return array
	 */
	public function get_options() {
		return $this->options;
	}
}

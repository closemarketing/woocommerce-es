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
use CLOSE\ConnectEcommerce\Admin\Taxes_Rates;
use CLOSE\ConnectEcommerce\Admin\Taxes_Types_ERP;
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
		$connector     = $this->get_connector( $options );

		if ( is_admin() ) {
			new Settings( $options );
			new Import_Products( $options );
			new Widget_Product( $options );
			new Widget_Order( $options );
			new Notices();
			new Taxes_Rates( $connector );
			new Taxes_Types_ERP( $connector );
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
	public function get_options() {
		return $this->options;
	}

	/**
	 * Get connector of plugin.
	 *
	 * @param array $options Options of plugin.
	 * @return string
	 */
	public function get_connector( $options ) {
		$connector                 = array();
		$connector['settings_all'] = get_option( 'connect_ecommerce' );
		$connector['connector']    = isset( $connector['settings_all']['connector'] ) ? $connector['settings_all']['connector'] : '';
		$connector['settings']     = $connector['settings_all'][ $connector['connector'] ] ?? array();
		$connector['all_options']  = $options;

		if ( ! empty( $connector['connector'] ) ) {
			$connector['options'] = $options[ $connector['connector'] ];
			if ( empty( $connector['options']['name'] ) ) {
				$connector['settings_all']['connector'] = '';
				update_option( 'connect_ecommerce', $connector['settings_all'] );
				return;
			}
			$apiname = 'Connect_Ecommerce_' . $connector['options']['name'];

			$connector['connapi_erp']        = new $apiname( $options );
			$connector['is_mergevars']       = method_exists( $connector['connapi_erp'], 'get_product_attributes' ) ? true : false;
			$connector['is_disabled_orders'] = isset( $connector['options']['disable_modules'] ) && in_array( 'order', $connector['options']['disable_modules'], true ) ? true : false;
			$connector['is_disabled_ai']     = isset( $connector['options']['disable_modules'] ) && in_array( 'ai', $connector['options']['disable_modules'], true ) ? true : false;
		}

		return $connector;
	}
}

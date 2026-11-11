<?php
/**
 * Class ShippingVATSourceTest
 *
 * Covers the "VAT number source" setting: basing the VAT treatment on the
 * shipping address instead of the billing address.
 *
 * Command: composer test -- --filter=ShippingVATSourceTest
 *
 * @package Connect_Ecommerce
 */

use CLOSE\ConnectEcommerce\Frontend\Checkout;
use CLOSE\ConnectEcommerce\Helpers\ORDER;
use CLOSE\ConnectEcommerce\Helpers\VAT;

/**
 * Shipping VAT source tests.
 */
class ShippingVATSourceTest extends WP_UnitTestCase {
	/**
	 * Set up test environment.
	 */
	public function setUp(): void {
		parent::setUp();
		$this->set_source( 'billing' );
	}

	/**
	 * Tear down test environment.
	 */
	public function tearDown(): void {
		VAT::clear_cache();
		if ( function_exists( 'WC' ) && WC()->session ) {
			VAT::remove_vat_exemption();
		}
		parent::tearDown();
	}

	/**
	 * Sets the VAT number source.
	 *
	 * @param string $source 'billing' or 'shipping'.
	 * @return void
	 */
	private function set_source( $source ) {
		update_option(
			'connect_ecommerce_public',
			array(
				'vat_show'           => 'yes',
				'vat_mandatory'      => 'no',
				'vat_vies_enabled'   => 'yes',
				'vat_vies_mandatory' => 'no',
				'vat_source'         => $source,
			)
		);
	}

	/**
	 * Creates an order with the given countries and meta.
	 *
	 * @param string $billing_country  Billing country.
	 * @param string $shipping_country Shipping country.
	 * @param array  $meta             Meta key => value.
	 * @return WC_Order
	 */
	private function create_order( $billing_country, $shipping_country, $meta = array() ) {
		$order = wc_create_order();
		$order->set_billing_country( $billing_country );
		$order->set_shipping_country( $shipping_country );
		foreach ( $meta as $key => $value ) {
			$order->update_meta_data( $key, $value );
		}
		$order->save();
		return $order;
	}

	/**
	 * Lets VIES report the given VAT number as valid.
	 *
	 * @param string $vat_number VAT number.
	 * @return callable The filter callback, to remove it afterwards.
	 */
	private function mock_valid_vat( $vat_number ) {
		$expected = VAT::clean_vat_number( $vat_number );
		$filter   = static function ( $pre, $country_code, $vat ) use ( $expected ) {
			if ( $expected !== $vat && $expected !== $country_code . $vat ) {
				return $pre;
			}
			return array(
				'valid'   => true,
				'message' => 'VAT number is valid (mock)',
			);
		};
		add_filter( 'conecom_pre_vies_validation', $filter, 20, 3 );
		return $filter;
	}

	/**
	 * The source defaults to billing and only accepts billing or shipping.
	 *
	 * @return void
	 */
	public function test_get_vat_source() {
		delete_option( 'connect_ecommerce_public' );
		$this->assertSame( 'billing', VAT::get_vat_source() );

		update_option( 'connect_ecommerce_public', array( 'vat_source' => 'other' ) );
		$this->assertSame( 'billing', VAT::get_vat_source() );

		$this->set_source( 'shipping' );
		$this->assertSame( 'shipping', VAT::get_vat_source() );
	}

	/**
	 * Cases for VAT::resolve_vat_context().
	 *
	 * @return array
	 */
	public function resolve_provider() {
		return array(
			'billing source ignores shipping'     => array( 'billing', 'FRB1', 'FR', 'DEB2', 'DE', 'FRB1', 'FR', 'billing' ),
			'shipping only'                       => array( 'shipping', '', 'FR', 'DEB2', 'DE', 'DEB2', 'DE', 'shipping' ),
			'billing only, same country'          => array( 'shipping', 'FRB1', 'FR', '', 'FR', 'FRB1', 'FR', 'billing' ),
			'billing only, no shipping address'   => array( 'shipping', 'FRB1', 'FR', '', '', 'FRB1', 'FR', 'billing' ),
			'billing only, different country'     => array( 'shipping', 'GB123', 'GB', '', 'AE', '', 'AE', 'shipping' ),
			'both different, shipping wins'       => array( 'shipping', 'GB123', 'GB', 'DEB2', 'DE', 'DEB2', 'DE', 'shipping' ),
			'lowercase countries are normalized'  => array( 'shipping', '', 'fr', 'DEB2', 'de', 'DEB2', 'DE', 'shipping' ),
		);
	}

	/**
	 * VAT::resolve_vat_context() picks the right VAT number and country.
	 *
	 * @dataProvider resolve_provider
	 *
	 * @param string $source           Source.
	 * @param string $billing_vat      Billing VAT.
	 * @param string $billing_country  Billing country.
	 * @param string $shipping_vat     Shipping VAT.
	 * @param string $shipping_country Shipping country.
	 * @param string $expected_vat     Expected VAT number.
	 * @param string $expected_country Expected country.
	 * @param string $expected_address Expected address.
	 * @return void
	 */
	public function test_resolve_vat_context( $source, $billing_vat, $billing_country, $shipping_vat, $shipping_country, $expected_vat, $expected_country, $expected_address ) {
		$context = VAT::resolve_vat_context( $billing_vat, $billing_country, $shipping_vat, $shipping_country, $source );

		$this->assertSame( $expected_vat, $context['vat_number'] );
		$this->assertSame( $expected_country, $context['country'] );
		$this->assertSame( $expected_address, $context['address'] );
	}

	/**
	 * VAT::resolve_vat_context() uses the store setting when no source is given.
	 *
	 * @return void
	 */
	public function test_resolve_vat_context_uses_setting() {
		$this->set_source( 'shipping' );
		$context = VAT::resolve_vat_context( 'GB123', 'GB', 'DEB2', 'DE' );

		$this->assertSame( 'DEB2', $context['vat_number'] );
	}

	/**
	 * Shipping VAT meta keys, including legacy ones from other VAT plugins.
	 *
	 * @return array
	 */
	public function shipping_meta_keys_provider() {
		return array(
			'classic checkout field' => array( '_shipping_vat' ),
			'legacy VAT plugin'      => array( '_shipping_vat_number' ),
			'blocks checkout field'  => array( '_wc_shipping/connect_ecommerce/billing_vat' ),
		);
	}

	/**
	 * ORDER::get_shipping_vat() reads every known shipping meta key.
	 *
	 * @dataProvider shipping_meta_keys_provider
	 *
	 * @param string $meta_key Meta key.
	 * @return void
	 */
	public function test_get_shipping_vat( $meta_key ) {
		$order = $this->create_order( 'FR', 'DE', array( $meta_key => 'DE123456789' ) );

		$this->assertSame( 'DE123456789', ORDER::get_shipping_vat( $order ) );

		$order->delete( true );
	}

	/**
	 * ORDER::get_shipping_vat() handles invalid input, empty orders and the filter.
	 *
	 * @return void
	 */
	public function test_get_shipping_vat_edge_cases() {
		$this->assertSame( '', ORDER::get_shipping_vat( null ) );

		$order = $this->create_order( 'FR', 'DE', array( '_custom_ship_vat' => 'DE999' ) );
		$this->assertSame( '', ORDER::get_shipping_vat( $order ) );

		$filter = static function ( $slugs ) {
			$slugs[] = '_custom_ship_vat';
			return $slugs;
		};
		add_filter( 'conecom_shipping_vat_field_slugs', $filter );
		$this->assertSame( 'DE999', ORDER::get_shipping_vat( $order ) );
		remove_filter( 'conecom_shipping_vat_field_slugs', $filter );

		$order->delete( true );
	}

	/**
	 * ORDER::get_order_vat() follows the VAT number source.
	 *
	 * @return void
	 */
	public function test_get_order_vat_follows_source() {
		$both = $this->create_order(
			'GB',
			'DE',
			array(
				'_billing_vat'  => 'GB123',
				'_shipping_vat' => 'DE456',
			)
		);
		$billing_only_other_country = $this->create_order( 'GB', 'AE', array( '_billing_vat' => 'GB123' ) );
		$billing_only_same_country  = $this->create_order( 'FR', 'FR', array( '_billing_vat' => 'FR789' ) );

		$this->assertSame( 'GB123', ORDER::get_order_vat( $both ) );
		$this->assertSame( 'GB123', ORDER::get_order_vat( $billing_only_other_country ) );

		$this->set_source( 'shipping' );
		$this->assertSame( 'DE456', ORDER::get_order_vat( $both ) );
		$this->assertSame( '', ORDER::get_order_vat( $billing_only_other_country ) );
		$this->assertSame( 'FR789', ORDER::get_order_vat( $billing_only_same_country ) );
		$this->assertSame( '', ORDER::get_order_vat( null ) );

		$both->delete( true );
		$billing_only_other_country->delete( true );
		$billing_only_same_country->delete( true );
	}

	/**
	 * Admin shipping fields show the shipping VAT number, including legacy keys.
	 *
	 * @return void
	 */
	public function test_admin_shipping_fields() {
		$checkout = new Checkout();
		$order    = $this->create_order( 'FR', 'DE', array( '_shipping_vat_number' => 'DE111' ) );
		$fields   = $checkout->add_shipping_fields_admin( array(), $order );

		$this->assertSame( 'DE111', $fields['vat']['value'] );

		$empty  = $this->create_order( 'FR', 'DE' );
		$fields = $checkout->add_shipping_fields_admin( array(), $empty );
		$this->assertArrayHasKey( 'vat', $fields );
		$this->assertArrayNotHasKey( 'value', $fields['vat'] );

		$order->delete( true );
		$empty->delete( true );
	}

	/**
	 * The classic checkout shipping VAT field is added after the country.
	 *
	 * @return void
	 */
	public function test_add_shipping_fields() {
		$checkout = new Checkout();
		$fields   = $checkout->add_shipping_fields(
			array(
				'shipping_first_name' => array(),
				'shipping_country'    => array(),
				'shipping_city'       => array(),
			)
		);

		$this->assertSame( array( 'shipping_first_name', 'shipping_vat', 'shipping_country', 'shipping_city' ), array_keys( $fields ) );
		$this->assertFalse( $fields['shipping_vat']['required'] );

		$fields = $checkout->add_shipping_fields( array() );
		$this->assertArrayHasKey( 'shipping_vat', $fields );
	}

	/**
	 * The shipping VAT field is only hooked when the source is shipping.
	 *
	 * @return void
	 */
	public function test_shipping_field_hook_follows_source() {
		$checkout = new Checkout();
		$this->assertFalse( has_filter( 'woocommerce_shipping_fields', array( $checkout, 'add_shipping_fields' ) ) );

		$this->set_source( 'shipping' );
		$checkout = new Checkout();
		$this->assertNotFalse( has_filter( 'woocommerce_shipping_fields', array( $checkout, 'add_shipping_fields' ) ) );
	}

	/**
	 * Classic checkout validates the shipping VAT number against the shipping country.
	 *
	 * @return void
	 */
	public function test_classic_checkout_uses_shipping_vat() {
		if ( ! function_exists( 'WC' ) || ! WC()->session ) {
			$this->markTestSkipped( 'WooCommerce session not available' );
		}
		update_option( 'woocommerce_default_country', 'ES' );
		$this->set_source( 'shipping' );
		$filter = $this->mock_valid_vat( 'DE123456789' );

		$checkout = new Checkout();
		$checkout->validate_vat_number_checkout(
			array(
				'billing_vat'      => 'GB123456789',
				'billing_country'  => 'GB',
				'shipping_vat'     => 'DE123456789',
				'shipping_country' => 'DE',
			),
			new WP_Error()
		);

		$info = VAT::get_vat_exemption_info();
		$this->assertNotNull( $info, 'Exemption should apply for a valid German shipping VAT number' );
		$this->assertSame( 'DE', $info['country'] );
		$this->assertSame( 'DE123456789', $info['vat_number'] );

		remove_filter( 'conecom_pre_vies_validation', $filter, 20 );
	}

	/**
	 * Classic checkout does not reuse a billing VAT number for a shipment to another country.
	 *
	 * @return void
	 */
	public function test_classic_checkout_ignores_billing_vat_for_other_country() {
		if ( ! function_exists( 'WC' ) || ! WC()->session ) {
			$this->markTestSkipped( 'WooCommerce session not available' );
		}
		update_option( 'woocommerce_default_country', 'ES' );
		$this->set_source( 'shipping' );
		$filter = $this->mock_valid_vat( 'FR12345678901' );

		$checkout = new Checkout();
		$checkout->validate_vat_number_checkout(
			array(
				'billing_vat'      => 'FR12345678901',
				'billing_country'  => 'FR',
				'shipping_country' => 'DE',
			),
			new WP_Error()
		);

		$this->assertFalse( VAT::is_customer_vat_exempt() );

		remove_filter( 'conecom_pre_vies_validation', $filter, 20 );
	}

	/**
	 * With the billing source, the shipping data does not change the result.
	 *
	 * @return void
	 */
	public function test_classic_checkout_billing_source_unchanged() {
		if ( ! function_exists( 'WC' ) || ! WC()->session ) {
			$this->markTestSkipped( 'WooCommerce session not available' );
		}
		update_option( 'woocommerce_default_country', 'ES' );
		$filter = $this->mock_valid_vat( 'FR12345678901' );

		$checkout = new Checkout();
		$checkout->validate_vat_number_checkout(
			array(
				'billing_vat'      => 'FR12345678901',
				'billing_country'  => 'FR',
				'shipping_vat'     => 'DE123456789',
				'shipping_country' => 'DE',
			),
			new WP_Error()
		);

		$info = VAT::get_vat_exemption_info();
		$this->assertNotNull( $info );
		$this->assertSame( 'FR', $info['country'] );

		remove_filter( 'conecom_pre_vies_validation', $filter, 20 );
	}

	/**
	 * Blocks checkout reads the VAT field of the shipping address.
	 *
	 * @return void
	 */
	public function test_blocks_checkout_uses_shipping_vat() {
		if ( ! function_exists( 'WC' ) || ! WC()->session ) {
			$this->markTestSkipped( 'WooCommerce session not available' );
		}
		update_option( 'woocommerce_default_country', 'ES' );
		$this->set_source( 'shipping' );
		$filter = $this->mock_valid_vat( 'DE123456789' );

		$order   = $this->create_order( 'GB', 'DE' );
		$request = new WP_REST_Request( 'POST', '/wc/store/v1/checkout' );
		$request->set_param(
			'billing_address',
			array(
				'country'                      => 'GB',
				'connect_ecommerce/billing_vat' => 'GB123456789',
			)
		);
		$request->set_param(
			'shipping_address',
			array(
				'country'                      => 'DE',
				'connect_ecommerce/billing_vat' => 'DE123456789',
			)
		);

		$checkout = new Checkout();
		$checkout->validate_vat_number_checkout_blocks( $order, $request );

		$order = wc_get_order( $order->get_id() );
		$this->assertSame( 'DE', get_post_meta( $order->get_id(), '_vat_exempt_country', true ) );
		$this->assertSame( 'DE123456789', get_post_meta( $order->get_id(), '_vat_exempt_vat_number', true ) );

		remove_filter( 'conecom_pre_vies_validation', $filter, 20 );
		$order->delete( true );
	}

	/**
	 * Checkout updates drop the exemption when the shipping country changes.
	 *
	 * @return void
	 */
	public function test_update_removes_exemption_on_shipping_country_change() {
		if ( ! function_exists( 'WC' ) || ! WC()->session ) {
			$this->markTestSkipped( 'WooCommerce session not available' );
		}
		update_option( 'woocommerce_default_country', 'ES' );
		$this->set_source( 'shipping' );

		VAT::apply_vat_exemption( 'DE', 'DE123456789', true );
		$this->assertTrue( VAT::is_customer_vat_exempt() );

		$checkout = new Checkout();
		$checkout->maybe_remove_vat_exemption_on_update(
			http_build_query(
				array(
					'billing_country'           => 'GB',
					'billing_vat'               => 'GB123456789',
					'ship_to_different_address' => '1',
					'shipping_country'          => 'IT',
					'shipping_vat'              => 'DE123456789',
				)
			)
		);

		$this->assertFalse( VAT::is_customer_vat_exempt() );
	}
}

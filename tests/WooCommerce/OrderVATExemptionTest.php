<?php
/**
 * Class OrderVATExemptionTest
 *
 * Command: composer test -- --filter=OrderVATExemptionTest
 *
 * @package Connect_Ecommerce
 */

use CLOSE\ConnectEcommerce\Admin\Order_VAT;
use CLOSE\ConnectEcommerce\Helpers\ORDER;
use CLOSE\ConnectEcommerce\Helpers\VAT;

/**
 * VAT exemption on orders created or edited in the admin, and its sync to the ERP.
 *
 * @group woocommerce
 */
class OrderVATExemptionTest extends WP_UnitTestCase {

	/**
	 * Standard tax rate for Sweden.
	 *
	 * @var int
	 */
	private $tax_rate_id;

	/**
	 * Fake VIES answer: only these VAT numbers (without prefix) are valid.
	 *
	 * @var callable
	 */
	private $vies_filter;

	/**
	 * Set up test environment.
	 */
	public function setUp(): void {
		parent::setUp();

		update_option( 'woocommerce_default_country', 'ES:GR' );
		update_option( 'woocommerce_calc_taxes', 'yes' );
		update_option( 'woocommerce_tax_based_on', 'billing' );
		update_option( 'woocommerce_prices_include_tax', 'no' );
		update_option(
			'connect_ecommerce_public',
			array(
				'vat_show'         => 'yes',
				'vat_vies_enabled' => 'yes',
			)
		);
		VAT::clear_cache();

		$this->tax_rate_id = WC_Tax::_insert_tax_rate(
			array(
				'tax_rate_country'  => 'SE',
				'tax_rate'          => '25.0000',
				'tax_rate_name'     => 'Moms',
				'tax_rate_priority' => 1,
				'tax_rate_compound' => 0,
				'tax_rate_shipping' => 1,
				'tax_rate_order'    => 0,
				'tax_rate_class'    => '',
			)
		);

		$this->vies_filter = function ( $pre, $country_code, $vat_number ) {
			return array( 'valid' => in_array( $vat_number, array( '556805334101', 'B12345678' ), true ) );
		};
		add_filter( 'conecom_pre_vies_validation', $this->vies_filter, 20, 3 );
	}

	/**
	 * Tear down test environment.
	 */
	public function tearDown(): void {
		remove_filter( 'conecom_pre_vies_validation', $this->vies_filter, 20 );
		WC_Tax::_delete_tax_rate( $this->tax_rate_id );
		VAT::clear_cache();
		parent::tearDown();
	}

	/**
	 * Creates a pending order for a Swedish customer with a 100 € product.
	 *
	 * @param string $vat_number VAT number.
	 * @param string $country    Billing country.
	 * @return WC_Order
	 */
	private function create_order( $vat_number, $country = 'SE' ) {
		$product = new WC_Product_Simple();
		$product->set_name( 'Test product' );
		$product->set_regular_price( 100 );
		$product->set_tax_status( 'taxable' );
		$product->save();

		$order = wc_create_order();
		$order->set_billing_country( $country );
		$order->update_meta_data( '_billing_vat', $vat_number );
		$order->add_product( $product, 1 );
		$order->calculate_totals( true );
		$order->save();

		return $order;
	}

	/**
	 * The country prefix is removed before calling VIES.
	 */
	public function test_strip_country_prefix() {
		$this->assertSame( '556805334101', VAT::strip_country_prefix( 'SE556805334101', 'SE' ) );
		$this->assertSame( '556805334101', VAT::strip_country_prefix( 'se 5568-0533.4101', 'SE' ) );
		$this->assertSame( 'B12345678', VAT::strip_country_prefix( 'B12345678', 'ES' ) );
		$this->assertSame( '123456789', VAT::strip_country_prefix( 'EL123456789', 'GR' ) );
	}

	/**
	 * A VAT number with its country prefix validates the same as without it.
	 */
	public function test_validate_vat_number_with_prefix_and_country() {
		$this->assertTrue( VAT::validate_vat_number( 'SE556805334101', 'SE' )['valid'] );
		$this->assertTrue( VAT::validate_vat_number( '556805334101', 'SE' )['valid'] );
	}

	/**
	 * Valid intra-community VAT number: the order is exempt and the recalculation removes the VAT.
	 */
	public function test_valid_vat_number_makes_order_vat_exempt() {
		$order = $this->create_order( 'SE556805334101' );
		$this->assertEquals( 25, $order->get_total_tax() );

		$response = VAT::update_order_vat_exemption( $order );
		$order->save();

		$this->assertSame( 'valid', $response['status'] );
		$this->assertTrue( $response['vat_exempt'] );
		$this->assertSame( 'yes', $order->get_meta( 'is_vat_exempt' ) );
		$this->assertSame( 'yes', $order->get_meta( '_vat_number_validated' ) );

		// Same as the admin "Recalculate" button.
		$order = wc_get_order( $order->get_id() );
		$order->calculate_totals( true );
		$this->assertEquals( 0, $order->get_total_tax() );
		$this->assertEquals( 100, $order->get_total() );
	}

	/**
	 * Valid VAT number from the store country: domestic sale, VAT applies.
	 */
	public function test_domestic_vat_number_is_not_exempt() {
		$order    = $this->create_order( 'ESB12345678', 'ES' );
		$response = VAT::update_order_vat_exemption( $order );

		$this->assertSame( 'valid', $response['status'] );
		$this->assertFalse( $response['vat_exempt'] );
		$this->assertNotSame( 'yes', $order->get_meta( 'is_vat_exempt' ) );
	}

	/**
	 * Invalid VAT number removes the exemption this plugin applied, but not a manual one.
	 */
	public function test_invalid_vat_number_removes_plugin_exemption_only() {
		$order = $this->create_order( 'SE556805334101' );
		VAT::update_order_vat_exemption( $order );
		$this->assertSame( 'yes', $order->get_meta( 'is_vat_exempt' ) );

		$response = VAT::update_order_vat_exemption( $order, 'SE000000000001' );
		$this->assertSame( 'invalid', $response['status'] );
		$this->assertSame( 'no', $order->get_meta( 'is_vat_exempt' ) );
		$this->assertSame( '', $order->get_meta( '_vat_exempt_applied' ) );

		// Exemption set by hand in the order custom fields is respected.
		$manual = $this->create_order( '' );
		$manual->update_meta_data( 'is_vat_exempt', 'yes' );
		VAT::update_order_vat_exemption( $manual, 'SE000000000001' );
		$this->assertSame( 'yes', $manual->get_meta( 'is_vat_exempt' ) );
	}

	/**
	 * Saving an editable order in the admin applies the exemption and recalculates the taxes.
	 */
	public function test_admin_save_recalculates_exempt_order() {
		$order = $this->create_order( 'SE556805334101' );
		$this->assertEquals( 25, $order->get_total_tax() );

		( new Order_VAT() )->maybe_update_exemption_on_save( $order->get_id() );

		$order = wc_get_order( $order->get_id() );
		$this->assertSame( 'yes', $order->get_meta( 'is_vat_exempt' ) );
		$this->assertEquals( 0, $order->get_total_tax() );
	}

	/**
	 * The billing VAT number stored by the official WooCommerce EU VAT Number plugin is read.
	 */
	public function test_legacy_vat_number_meta_is_read() {
		$order = wc_create_order();
		$order->update_meta_data( '_vat_number', 'SE556805334101' );
		$order->save();

		$this->assertSame( 'SE556805334101', ORDER::get_billing_vat( $order ) );
	}

	/**
	 * Lines without taxes are sent to the ERP with an explicit 0% tax.
	 */
	public function test_exempt_order_items_are_sent_with_zero_tax() {
		$order = $this->create_order( 'SE556805334101' );
		VAT::update_order_vat_exemption( $order );
		$order->save();
		$order->calculate_totals( true );
		$order->save();

		$order_data = ORDER::generate_order_data( array( 'doctype' => 'invoice' ), $order, 'conecom-test' );

		$this->assertNotEmpty( $order_data['items'] );
		$this->assertArrayHasKey( 'tax', $order_data['items'][0] );
		$this->assertSame( 0.0, $order_data['items'][0]['tax'] );
		$this->assertArrayNotHasKey( 'taxes', $order_data['items'][0] );
	}

	/**
	 * Lines with an unmapped tax rate are sent with the tax percentage, not the tax amount.
	 */
	public function test_unmapped_tax_rate_is_sent_as_percentage() {
		$product = new WC_Product_Simple();
		$product->set_name( 'Expensive product' );
		$product->set_regular_price( 200 );
		$product->set_tax_status( 'taxable' );
		$product->save();

		$order = wc_create_order();
		$order->set_billing_country( 'SE' );
		$order->add_product( $product, 1 );
		$order->calculate_totals( true );
		$order->save();

		$order_data = ORDER::generate_order_data( array( 'doctype' => 'invoice' ), $order, 'conecom-test' );

		$this->assertEquals( 50, $order->get_total_tax() );
		$this->assertSame( 25.0, $order_data['items'][0]['tax'] );
	}
}

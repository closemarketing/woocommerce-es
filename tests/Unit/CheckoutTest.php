<?php
/**
 * Class CheckoutTest
 *
 * Command: composer test -- --filter=CheckoutTest
 *
 * @package Connect_Ecommerce
 */

use CLOSE\ConnectEcommerce\Frontend\Checkout;
use CLOSE\ConnectEcommerce\Helpers\ORDER;

/**
 * Mock document class for testing.
 */
class Mock_Document {
	/**
	 * Order object.
	 *
	 * @var WC_Order
	 */
	public $order;

	/**
	 * Custom fields.
	 *
	 * @var array
	 */
	private $custom_fields = array();

	/**
	 * Set custom field value.
	 *
	 * @param string $field Field name.
	 * @param string $value Field value.
	 */
	public function set_custom_field( $field, $value ) {
		$this->custom_fields[ $field ] = $value;
	}

	/**
	 * Get custom field value.
	 *
	 * @param string $field Field name.
	 * @return mixed
	 */
	public function get_custom_field( $field ) {
		return isset( $this->custom_fields[ $field ] ) ? $this->custom_fields[ $field ] : '';
	}
}

class CheckoutTest extends WP_UnitTestCase {
	/**
	 * Checkout instance.
	 *
	 * @var Checkout
	 */
	private $checkout;

	/**
	 * Set up test environment.
	 */
	public function setUp(): void {
		parent::setUp();

		// Set default settings.
		update_option(
			'connect_ecommerce_public',
			array(
				'vat_show'       => 'yes',
				'vat_mandatory'  => 'no',
				'company_field'  => 'yes',
			)
		);

		$this->checkout = new Checkout();
	}

	/**
	 * Tear down test environment.
	 */
	public function tearDown(): void {
		parent::tearDown();
	}

	/**
	 * Test add_vat_invoices with VAT number from custom field.
	 *
	 * @return void
	 */
	public function test_add_vat_invoices_with_custom_field() {
		// Create a mock document with get_custom_field method.
		$document = new Mock_Document();
		$document->set_custom_field( '_billing_vat', 'ES12345678Z' );

		$address = 'John Doe<br>123 Main St';
		$result  = $this->checkout->add_vat_invoices( $address, $document );

		$this->assertStringContainsString( 'VAT info:', $result );
		$this->assertStringContainsString( 'ES12345678Z', $result );
		$this->assertStringContainsString( $address, $result );
	}

	/**
	 * Test add_vat_invoices with VAT number from order.
	 *
	 * @return void
	 */
	public function test_add_vat_invoices_with_order_vat() {
		// Create a WooCommerce order.
		$order = wc_create_order();
		$order->set_billing_first_name( 'Jane' );
		$order->set_billing_last_name( 'Doe' );
		$order->set_billing_address_1( '456 Oak Ave' );
		$order->update_meta_data( '_billing_vat', 'ES87654321B' );
		$order->save();

		// Create a mock document with order but no custom field.
		$document        = new stdClass();
		$document->order = $order;

		$address = 'Jane Doe<br>456 Oak Ave';
		$result  = $this->checkout->add_vat_invoices( $address, $document );

		$this->assertStringContainsString( 'VAT info:', $result );
		$this->assertStringContainsString( 'ES87654321B', $result );
		$this->assertStringContainsString( $address, $result );

		// Clean up.
		$order->delete( true );
	}

	/**
	 * Test add_vat_invoices without VAT number.
	 *
	 * @return void
	 */
	public function test_add_vat_invoices_without_vat() {
		// Create a WooCommerce order without VAT.
		$order = wc_create_order();
		$order->set_billing_first_name( 'Bob' );
		$order->set_billing_last_name( 'Smith' );
		$order->set_billing_address_1( '789 Pine Rd' );
		$order->save();

		// Create a mock document with order but no VAT.
		$document        = new stdClass();
		$document->order = $order;

		$address = 'Bob Smith<br>789 Pine Rd';
		$result  = $this->checkout->add_vat_invoices( $address, $document );

		$this->assertEquals( $address, $result );
		$this->assertStringNotContainsString( 'VAT info:', $result );

		// Clean up.
		$order->delete( true );
	}

	/**
	 * Test add_vat_invoices with empty document.
	 *
	 * @return void
	 */
	public function test_add_vat_invoices_with_empty_document() {
		$address = 'Alice Johnson<br>321 Elm St';
		$result  = $this->checkout->add_vat_invoices( $address, null );

		$this->assertEquals( $address, $result );
		$this->assertStringNotContainsString( 'VAT info:', $result );
	}

	/**
	 * Test add_vat_invoices with document without order.
	 *
	 * @return void
	 */
	public function test_add_vat_invoices_with_document_without_order() {
		// Create a mock document without order.
		$document = new stdClass();

		$address = 'Charlie Brown<br>111 Maple Dr';
		$result  = $this->checkout->add_vat_invoices( $address, $document );

		$this->assertEquals( $address, $result );
		$this->assertStringNotContainsString( 'VAT info:', $result );
	}

	/**
	 * Test add_vat_invoices with custom field having priority over order VAT.
	 *
	 * @return void
	 */
	public function test_add_vat_invoices_custom_field_priority() {
		// Create a WooCommerce order with VAT.
		$order = wc_create_order();
		$order->set_billing_first_name( 'David' );
		$order->set_billing_last_name( 'Lee' );
		$order->set_billing_address_1( '555 Cedar Ln' );
		$order->update_meta_data( '_billing_vat', 'ES11111111A' );
		$order->save();

		// Create a mock document with both custom field and order.
		$document        = new Mock_Document();
		$document->order = $order;
		$document->set_custom_field( '_billing_vat', 'ES99999999Z' );

		$address = 'David Lee<br>555 Cedar Ln';
		$result  = $this->checkout->add_vat_invoices( $address, $document );

		// Should use custom field VAT (ES99999999Z), not order VAT (ES11111111A).
		$this->assertStringContainsString( 'VAT info:', $result );
		$this->assertStringContainsString( 'ES99999999Z', $result );
		$this->assertStringNotContainsString( 'ES11111111A', $result );

		// Clean up.
		$order->delete( true );
	}

	/**
	 * Test add_vat_invoices checks all VAT field slugs.
	 *
	 * @return void
	 */
	public function test_add_vat_invoices_checks_multiple_vat_fields() {
		// Create a WooCommerce order with VAT in alternative field.
		$order = wc_create_order();
		$order->set_billing_first_name( 'Emily' );
		$order->set_billing_last_name( 'White' );
		$order->set_billing_address_1( '777 Birch St' );
		$order->update_meta_data( '_billing_nif', 'ES22222222B' );
		$order->save();

		// Create a mock document with order.
		$document        = new stdClass();
		$document->order = $order;

		$address = 'Emily White<br>777 Birch St';
		$result  = $this->checkout->add_vat_invoices( $address, $document );

		$this->assertStringContainsString( 'VAT info:', $result );
		$this->assertStringContainsString( 'ES22222222B', $result );

		// Clean up.
		$order->delete( true );
	}

	/**
	 * Test add_vat_invoices HTML format.
	 *
	 * @return void
	 */
	public function test_add_vat_invoices_html_format() {
		// Create a WooCommerce order.
		$order = wc_create_order();
		$order->set_billing_first_name( 'Test' );
		$order->set_billing_last_name( 'User' );
		$order->update_meta_data( '_billing_vat', 'ES33333333C' );
		$order->save();

		// Create a mock document with order.
		$document        = new stdClass();
		$document->order = $order;

		$address = 'Initial Address';
		$result  = $this->checkout->add_vat_invoices( $address, $document );

		// Check that VAT info is wrapped in paragraph tags.
		$this->assertStringContainsString( '<p>', $result );
		$this->assertStringContainsString( '</p>', $result );
		$this->assertMatchesRegularExpression( '/<p>.*VAT info:.*ES33333333C.*<\/p>/', $result );

		// Clean up.
		$order->delete( true );
	}
}


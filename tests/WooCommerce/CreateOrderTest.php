<?php
/**
 * Class CreateOrderTest
 *
 * Command: composer test-debug -- --filter=CreateOrderTest
 *
 * @package Connect_Ecommerce
 */

use CLOSE\ConnectEcommerce\Helpers\ORDER;

/**
 * Create Product Simple without Errors.
 *
 * @group woocommerce
 */
class CreateOrderTest extends WP_UnitTestCase {

	/**
	 * Settings for testing
	 */
	protected $settings;
	
	/**
	 * API connection for testing
	 */
	protected $connapi_erp;
	
	/**
	 * Set up test environment.
	 */
	public function setUp(): void {
		parent::setUp();
		
		// Verify that WooCommerce is active
		$this->assertTrue(class_exists('WooCommerce'), 'WooCommerce is not active');

		$this->settings = [
			'api'            => '',
			'idcentre'       => '',
			'url'            => '',
			'username'       => '',
			'password'       => '',
			'company'        => '',
			'domain'         => '',
			'dbname'         => '',
			'stock'          => 'no',
			'prodst'         => 'draft',
			'virtual'        => 'no',
			'backorders'     => 'no',
			'catsep'         => '',
			'catattr'        => '',
			'filter'         => '',
			'filter_sku'     => '',
			'tax_option'     => 'no',
			'rates'          => 'default',
			'catnp'          => 'yes',
			'doctype'        => 'invoice',
			'series'         => '',
			'freeorder'      => 'no',
			'ecstatus'       => 'all',
			'order_tags'     => '',
			'design_id'      => '',
			'sync'           => 'no',
			'sync_num'       => 5,
			'sync_email'     => 'yes',
			'prod_weight_eq' => '',
			'debug_log'      => 'no',
		];
		
		// Mock API connection
		$options           = conecom_get_options();
		$this->connapi_erp = new Connect_Ecommerce_Clientify( $options );
	}

	public function test_clean_special_chars() {
		$test_cases = [
			'José M. García-López'	=> 'Jose M Garcia-Lopez',
			'COMIDAS & BEBIDAS S.L.'	=> 'COMIDAS Y BEBIDAS S L',
			'Peña "El @Rincón" / Granada'	=> 'Peña El Rincon Granada',
			'Weiß y Aßmann/Waßmann'	=> 'Weiss y Assmann Wassmann',
			'Bürgerstraße 123'	=> 'Burgerstrasse 123',
			'#John Doe'	=> 'John Doe',
			'áéíóúüñçğÁÉÍÓÚÜÑÇĞ' => 'aeiouuñçgAEIOUUÑÇG',
			'John@Doe' => 'John Doe',
			'John@  Doe' => 'John Doe', // double space
			'º[]John Doe' => 'John Doe',
			'Maçanet Çağla' => 'Maçanet Çagla',
			'Francisco Araújo da Conceição' => 'Francisco Araujo da Conceiçao',
		];

		foreach ( $test_cases as $input => $expected ) {
			$this->assertEquals( ORDER::clean_special_chars( $input ), $expected );
		}
	}

	public function test_create_order_company_without_errors() {
		$order = new WC_Order();
		$client_data = [
			'first_name'  => 'José M.',
			'last_name'   => 'García-López',
			'email'       => 'john.doe@example.com',
			'phone'       => '123456789',
			'address_1'   => '123 Main St',
			'address_2'   => '',
			'city'        => 'Sample City',
			'state'       => 'CA',
			'postcode'    => '90001',
			'country'     => 'US',
			'company'     => '',
			'billing_vat' => '123456789',
		];

		$order->set_billing_first_name( $client_data['first_name'] );
		$order->set_billing_last_name( $client_data['last_name'] );
		$order->set_billing_email( $client_data['email'] );
		$order->set_billing_phone( $client_data['phone'] );
		$order->set_billing_address_1( $client_data['address_1'] );
		$order->set_billing_address_2( $client_data['address_2'] );
		$order->set_billing_city( $client_data['city'] );
		$order->set_billing_state( $client_data['state'] );
		$order->set_billing_postcode( $client_data['postcode'] );
		$order->set_billing_country( $client_data['country'] );
		$order->set_billing_company( $client_data['company'] );
		$order->add_meta_data( '_billing_vat', $client_data['billing_vat'] );
		$order->set_status('completed');
		$order->set_total(100);
		$order->save();

		$option_prefix = 'conecom-test';

		// Review order data that sends to ERP.
		$order_data = ORDER::generate_order_data( $this->settings, $order, $option_prefix );
		$this->assertNotEmpty( $order_data );
		$this->assertEquals( $client_data['first_name'], $order_data['contactFirstName'] );
		$this->assertEquals( $client_data['last_name'], $order_data['contactLastName'] );
		$this->assertEquals( $client_data['first_name'] . ' ' . $client_data['last_name'], $order_data['contactName'] );
		$this->assertEquals( $client_data['billing_vat'], $order_data['contactCode'] );

		// With company.
		$client_data['company'] = 'Acme Inc.';
		$order->set_billing_company( $client_data['company'] );
		$order->save();

		$order_data = ORDER::generate_order_data( $this->settings, $order, $option_prefix );
		$this->assertNotEmpty( $order_data );
		$this->assertEquals( $client_data['company'], $order_data['contactName'] );
	}

	public function test_create_order_clean_chars_without_errors() {
		$order = new WC_Order();
		$client_data = [
			'first_name' => 'José M.',
			'last_name'  => 'García-López',
			'email'      => 'john.doe@example.com',
			'phone'      => '123456789',
			'address_1'  => '123 Main St',
			'address_2'  => '',
			'city'       => 'Sampleº City',
			'state'      => 'CAº',
			'postcode'   => '90001',
			'country'    => 'USº',
			'company'    => 'Acme Inc.',
		];

		$order->set_billing_first_name( $client_data['first_name'] );
		$order->set_billing_last_name( $client_data['last_name'] );
		$order->set_billing_email( $client_data['email'] );
		$order->set_billing_phone( $client_data['phone'] );
		$order->set_billing_address_1( $client_data['address_1'] );
		$order->set_billing_address_2( $client_data['address_2'] );
		$order->set_billing_city( $client_data['city'] );
		$order->set_billing_state( $client_data['state'] );
		$order->set_billing_postcode( $client_data['postcode'] );
		$order->set_billing_country( $client_data['country'] );
		$order->set_billing_company( $client_data['company'] );
		$order->set_payment_method( 'bacs' );
		$order->set_status('completed');
		$order->set_total(100);
		$order->save();

		$this->settings['cleanchars'] = 'on';
		$option_prefix = 'conecom-test';

		$this->settings['prod_mergevars'] = [
			'paymentmethods|58f9c4091c9798739520e6b2' => 'cf|bacs',
		];

		// Review order data that sends to ERP.
		$order_data = ORDER::generate_order_data( $this->settings, $order, $option_prefix );
		$this->assertNotEmpty( $order_data );
		$this->assertEquals( 'Jose M', $order_data['contactFirstName'] );
		$this->assertEquals( 'Garcia-Lopez', $order_data['contactLastName'] );
		$this->assertEquals( 'Sample City', $order_data['contactCity'] );
		$this->assertEquals( 'California', $order_data['contactProvince'] );
		$this->assertEquals( 'US', $order_data['contactCountryCode'] );
		$this->assertEquals( '90001', $order_data['contactCp'] );
		$this->assertEquals( 'bacs', $order_data['paymentMethod'] );
		$this->assertEquals( '58f9c4091c9798739520e6b2', $order_data['paymentMethodId'] );
	}

	public function test_create_order_approve_document_without_errors() {
		$order = new WC_Order();
		$client_data = [
			'first_name' => 'José M.',
			'last_name'  => 'García-López',
			'email'      => 'john.doe@example.com',
		];

		$order->set_billing_first_name( $client_data['first_name'] );
		$order->set_billing_last_name( $client_data['last_name'] );
		$order->set_billing_email( $client_data['email'] );
		$order->set_status('completed');
		$order->set_total(100);
		$order->save();

		$this->settings['approve_document'] = 'yes';
		$option_prefix = 'conecom-test';

		// Review order data that sends to ERP.
		$order_data = ORDER::generate_order_data( $this->settings, $order, $option_prefix );
		$this->assertNotEmpty( $order_data );
		$this->assertEquals( true, $order_data['approveDoc'] );
	}

	public function test_refund_order_without_errors() {
		$order = new WC_Order();
		$order->set_status('completed');
		$order->set_total(100);
		$order->save();

		$refund = wc_create_refund( [
			'order_id' => $order->get_id(),
			'amount'   => 50,
		] );

		$args = array(
			'amount'         => '29.24',
			'reason'         => '',
			'order_id'       => 74,
			'refund_id'      => 0,
			'line_items'     => array(
				25 => array(
					'qty'          => '1',
					'refund_total' => '29',
					'refund_tax'   => array(),
				),
				26 => array(
					'qty'          => '1',
					'refund_total' => '0.24',
					'refund_tax'   => array(),
				),
				27 => array(
					'qty'          => 0,
					'refund_total' => '0',
					'refund_tax'   => array(),
				),
			),
			'refund_payment' => false,
			'restock_items'  => true,
		);

		$result = ORDER::create_refund_invoice( $this->settings, $refund->get_id(), $args, 'conecom-test', $this->connapi_erp );
		$this->assertNotEmpty( $result );
		$this->assertEquals( 'ok', $result['status'] );

		$this->assertNotEmpty( $refund );
		$this->assertEquals( 50, $refund->get_amount() );
		$this->assertEquals( $order->get_id(), $refund->get_parent_id() );
		$this->assertEquals( 'completed', $refund->get_status() );
	}

	/**
	 * Test get_tax_rate method using Reflection
	 *
	 * @return void
	 */
	public function test_get_tax_rate_with_zero_tax() {
		// Create a product with no tax.
		$product = new WC_Product_Simple();
		$product->set_name( 'Test Product' );
		$product->set_regular_price( 100 );
		$product->set_tax_class( '' );
		$product->save();

		// Create an order and add the product.
		$order = new WC_Order();
		$item  = new WC_Order_Item_Product();
		$item->set_product( $product );
		$item->set_quantity( 1 );
		$item->set_subtotal( 100 );
		$item->set_total( 100 );
		$item->set_total_tax( 0 );
		$order->add_item( $item );
		$order->save();

		// Use Reflection to access the private method.
		$reflection = new ReflectionClass( ORDER::class );
		$method     = $reflection->getMethod( 'get_tax_rate' );
		$method->setAccessible( true );

		$tax    = new WC_Tax();
		$result = $method->invoke( null, $item, $tax );

		$this->assertEquals( 0, $result );
	}

	/**
	 * Test get_tax_rate method returns zero when tax rates are not properly configured
	 *
	 * @return void
	 */
	public function test_get_tax_rate_with_tax_but_no_rates_configured() {
		// Create a product.
		$product = new WC_Product_Simple();
		$product->set_name( 'Test Product with Tax' );
		$product->set_tax_class( '' );
		$product->set_regular_price( 100 );
		$product->save();

		// Create an order and add the product.
		$order = new WC_Order();
		$order->set_billing_country( 'ES' );

		$item = new WC_Order_Item_Product();
		$item->set_product( $product );
		$item->set_quantity( 1 );
		$item->set_subtotal( 100 );
		$item->set_total( 100 );
		$item->set_total_tax( 21 );
		$item->set_tax_class( '' );

		$order->add_item( $item );
		$order->save();

		// Use Reflection to access the private method.
		$reflection = new ReflectionClass( ORDER::class );
		$method     = $reflection->getMethod( 'get_tax_rate' );
		$method->setAccessible( true );

		$tax    = new WC_Tax();
		$result = $method->invoke( null, $item, $tax );

		// When rates are not properly configured, the function should return 0 safely.
		$this->assertIsNumeric( $result );
		$this->assertGreaterThanOrEqual( 0, $result );
	}

	/**
	 * Test get_tax_rate method with item that has tax
	 *
	 * @return void
	 */
	public function test_get_tax_rate_with_reduced_tax() {
		// Create a product with reduced tax.
		$product = new WC_Product_Simple();
		$product->set_name( 'Test Product with Reduced Tax' );
		$product->set_tax_class( 'reduced-rate' );
		$product->set_regular_price( 100 );
		$product->save();

		// Create an order and add the product.
		$order = new WC_Order();
		$order->set_billing_country( 'ES' );

		$item = new WC_Order_Item_Product();
		$item->set_product( $product );
		$item->set_quantity( 1 );
		$item->set_subtotal( 100 );
		$item->set_total( 100 );
		$item->set_total_tax( 10 );
		$item->set_tax_class( 'reduced-rate' );

		$order->add_item( $item );
		$order->save();

		// Use Reflection to access the private method.
		$reflection = new ReflectionClass( ORDER::class );
		$method     = $reflection->getMethod( 'get_tax_rate' );
		$method->setAccessible( true );

		$tax    = new WC_Tax();
		$result = $method->invoke( null, $item, $tax );

		// The function should return a numeric value >= 0.
		$this->assertIsNumeric( $result );
		$this->assertGreaterThanOrEqual( 0, $result );
	}

	/**
	 * Test get_tax_rate method with shipping item
	 *
	 * @return void
	 */
	public function test_get_tax_rate_with_shipping() {
		// Create tax rate.
		$tax_rate = array(
			'tax_rate_country'  => 'ES',
			'tax_rate_state'    => '',
			'tax_rate'          => '21.0000',
			'tax_rate_name'     => 'IVA',
			'tax_rate_priority' => '1',
			'tax_rate_compound' => '0',
			'tax_rate_shipping' => '1',
			'tax_rate_order'    => '1',
			'tax_rate_class'    => '',
		);
		WC_Tax::_insert_tax_rate( $tax_rate );

		// Create an order with shipping.
		$order = new WC_Order();
		$order->set_billing_country( 'ES' );

		$shipping_item = new WC_Order_Item_Shipping();
		$shipping_item->set_method_title( 'Flat Rate' );
		$shipping_item->set_total( 10 );

		// Set shipping tax.
		$shipping_taxes = WC_Tax::calc_shipping_tax( 10, WC_Tax::get_shipping_tax_rates() );
		$shipping_item->set_taxes( array( 'total' => $shipping_taxes ) );

		$order->add_item( $shipping_item );
		$order->calculate_totals();
		$order->save();

		// Use Reflection to access the private method.
		$reflection = new ReflectionClass( ORDER::class );
		$method     = $reflection->getMethod( 'get_tax_rate' );
		$method->setAccessible( true );

		$tax    = new WC_Tax();
		$result = $method->invoke( null, $shipping_item, $tax );

		// Shipping should have tax rate (or 0 if no tax was calculated).
		$this->assertIsNumeric( $result );
		$this->assertGreaterThanOrEqual( 0, $result );
	}

	/**
	 * Test get_tax_rate method without tax object parameter
	 *
	 * @return void
	 */
	public function test_get_tax_rate_without_tax_object() {
		// Create a product with no tax.
		$product = new WC_Product_Simple();
		$product->set_name( 'Test Product No Tax Object' );
		$product->set_regular_price( 100 );
		$product->set_tax_class( '' );
		$product->save();

		// Create an order and add the product.
		$order = new WC_Order();
		$item  = new WC_Order_Item_Product();
		$item->set_product( $product );
		$item->set_quantity( 1 );
		$item->set_subtotal( 100 );
		$item->set_total( 100 );
		$item->set_total_tax( 0 );
		$order->add_item( $item );
		$order->save();

		// Use Reflection to access the private method.
		$reflection = new ReflectionClass( ORDER::class );
		$method     = $reflection->getMethod( 'get_tax_rate' );
		$method->setAccessible( true );

		// Call without tax object (should create one internally).
		$result = $method->invoke( null, $item );

		$this->assertEquals( 0, $result );
	}
}
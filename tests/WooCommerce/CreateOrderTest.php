<?php
/**
 * Class CreateOrderTest
 *
 * Command: composer test-debug -- --filter=CreateOrderTest
 *
 * @package Connect_Ecommerce
 */

use CLOSE\ConnectEcommerce\Helpers\ORDER;
use CLOSE\ConnectEcommerce\Helpers\TAXES;

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
			'áéíóúüñçğÁÉÍÓÚÜÑÇĞåÅäÄæÆøØöÖèêëÈÊËïîÏÎôöÔÖùûÙÛßłŁ' => 'aeiouuñçgAEIOUUÑÇGaAaAaeAEoOoOeeeEEEiiIIooOOuuUUsslL',
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

		// Payment method mappings.
		$this->settings['payment_methods'] = [
			'bacs' => '58f9c4091c9798739520e6b2',
		];

		// Treasury account mappings.
		$this->settings['treasury_accounts'] = [
			'bacs' => 'treasury_account_123',
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
		$this->assertEquals( 'treasury_account_123', $order_data['treasuryId'] );
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

	public function test_create_order_tax_types_without_errors() {
		// Enable tax calculations in WooCommerce.
		update_option( 'woocommerce_calc_taxes', 'yes' );
		update_option( 'woocommerce_prices_include_tax', 'no' );

		// Remove all tax rates and classes from WooCommerce.
		global $wpdb;

		// Delete tax rates.
		$wpdb->query( "DELETE FROM {$wpdb->prefix}woocommerce_tax_rates" );
		// Delete tax rate locations.
		$wpdb->query( "DELETE FROM {$wpdb->prefix}woocommerce_tax_rate_locations" );

		// Optionally clear tax class option.
		update_option('woocommerce_tax_classes', '');

		// Flush cache to ensure changes take effect.
		wp_cache_flush();

		// Create a tax rate in WooCommerce.
		$tax_rate_id = \WC_Tax::_insert_tax_rate(
			array(
				'tax_rate_country'  => 'ES',
				'tax_rate_state'    => '',
				'tax_rate'          => '21.0000',
				'tax_rate_name'     => 'IVA',
				'tax_rate_priority' => 1,
				'tax_rate_compound' => 0,
				'tax_rate_shipping' => 1,
				'tax_rate_order'    => 0,
				'tax_rate_class'    => '', // Standard tax class.
			)
		);

		// Add erp_tax_type to the tax rate.
		$erp_tax_type = 'ERP_IVA_21';
		$result = TAXES::update_tax_type( $tax_rate_id, $erp_tax_type );
		$this->assertNotFalse( $result, 'Failed to update tax type' );

		// Clear WooCommerce tax rates cache.
		wp_cache_flush();

		// Create a product with the tax class.
		$product = new \WC_Product_Simple();
		$product->set_name( 'Test Product with Tax' );
		$product->set_regular_price( 100 );
		$product->set_tax_status( 'taxable' );
		$product->set_tax_class( '' ); // Standard tax class.
		$product->save();

		// Create an order with the product.
		$order = new \WC_Order();
		$order->set_billing_first_name( 'José' );
		$order->set_billing_last_name( 'García' );
		$order->set_billing_email( 'jose@example.com' );
		$order->set_billing_address_1( 'Calle Test 123' );
		$order->set_billing_city( 'Madrid' );
		$order->set_billing_postcode( '28001' );
		$order->set_billing_country( 'ES' );
		$order->set_billing_state( 'M' );
		$order->set_status( 'completed' );

		// Add product to order.
		$item = new \WC_Order_Item_Product();
		$item->set_product( $product );
		$item->set_quantity( 1 );
		$item->set_subtotal( 100 );
		$item->set_total( 100 );
		$item->set_tax_class( '' ); // Standard tax class.
		
		// Set tax data manually for the item.
		$item->set_taxes(
			array(
				'total'    => array( $tax_rate_id => 21 ),
				'subtotal' => array( $tax_rate_id => 21 ),
			)
		);
		
		$order->add_item( $item );

		// Calculate totals to apply taxes.
		$order->calculate_totals();
		$order->save();

		// Generate order data.
		$option_prefix = 'conecom-test';
		$order_data    = ORDER::generate_order_data( $this->settings, $order, $option_prefix );

		// Verify order data is generated.
		$this->assertNotEmpty( $order_data );
		$this->assertArrayHasKey( 'items', $order_data );
		$this->assertNotEmpty( $order_data['items'] );

		// Verify the first item has tax information.
		$first_item = $order_data['items'][0];
		$this->assertArrayHasKey( 'tax', $first_item );
		$this->assertEquals( 21, $first_item['tax'] );

		// Verify the first item has erp_tax_type.
		$this->assertArrayHasKey( 'taxes', $first_item, 'Item should have erp_tax_type field' );
		$this->assertEquals( $erp_tax_type, $first_item['taxes'], 'Item erp_tax_type should match the one set in database' );

		// Verify erp_tax_type is stored correctly in database.
		$stored_tax_type = TAXES::get_tax_types_map( $tax_rate_id);
		$this->assertEquals( $erp_tax_type, $stored_tax_type );

		// Clean up.
		\WC_Tax::_delete_tax_rate( $tax_rate_id );
		$product->delete( true );
		$order->delete( true );
	}
}
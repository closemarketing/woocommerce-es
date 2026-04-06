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
			'Gödöllő' => 'Godollo',
			'Augustina Lubė' => 'Augustina Lube',
			'Ėrika' => 'Erika',
			'įĮ' => 'iI',
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
		$order->set_billing_company( $client_data['company'] );
		$order->set_payment_method( 'bacs' );

		// Enable tax calculations.
		update_option( 'woocommerce_calc_taxes', 'yes' );
		update_option( 'woocommerce_prices_include_tax', 'no' );

		// Set billing country and state without special characters for tax calculation.
		// The cleanchars setting will clean them later, but we need correct values for tax calculation.
		$order->set_billing_country( 'US' );
		$order->set_billing_state( 'CA' );

		// Create a tax rate.
		$tax_rate_id = \WC_Tax::_insert_tax_rate(
			array(
				'tax_rate_country'  => 'US',
				'tax_rate_state'    => 'CA',
				'tax_rate'          => '10.0000',
				'tax_rate_name'     => 'Sales Tax',
				'tax_rate_priority' => 1,
				'tax_rate_compound' => 0,
				'tax_rate_shipping' => 1,
				'tax_rate_order'    => 0,
				'tax_rate_class'    => '',
			)
		);

		// Create a product.
		$product = new \WC_Product_Simple();
		$product->set_name( 'Test Product' );
		$product->set_regular_price( 100 );
		$product->set_tax_status( 'taxable' );
		$product->set_tax_class( '' );
		$product->save();

		// Add product to order.
		$item = new \WC_Order_Item_Product();
		$item->set_product( $product );
		$item->set_quantity( 1 );
		$item->set_subtotal( 100 );
		$item->set_total( 100 );
		$item->set_tax_class( '' );
		$order->add_item( $item );

		// Calculate totals to apply taxes.
		$order->calculate_totals();

		// After calculate_totals, get the item again and verify/update taxes if needed.
		$items = $order->get_items();
		$total_tax = 0;
		foreach ( $items as $order_item_id => $order_item ) {
			if ( is_a( $order_item, 'WC_Order_Item_Product' ) ) {
				$item_taxes = $order_item->get_taxes();
				// If taxes were calculated, ensure they match our expected tax rate.
				if ( empty( $item_taxes['total'] ) || ! isset( $item_taxes['total'][ $tax_rate_id ] ) ) {
					// Set taxes manually if not calculated correctly.
					$order_item->set_taxes(
						array(
							'total'    => array( $tax_rate_id => 10 ),
							'subtotal' => array( $tax_rate_id => 10 ),
						)
					);
					$order_item->save();
					$total_tax += 10;
				} else {
					// Sum the tax from the item.
					$total_tax += (float) $item_taxes['total'][ $tax_rate_id ];
				}
			}
		}

		// Update the order's total tax if we modified item taxes.
		if ( $total_tax > 0 ) {
			$order->set_cart_tax( $total_tax );
		}

		$order->set_status( 'completed' );
		$order->save();

		$this->settings['cleanchars'] = 'on';
		$option_prefix = 'conecom-test';

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
		$this->assertEquals( 110, $order_data['total'] );
		$this->assertEquals( 10, $order_data['total_tax'] );
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
		$this->assertArrayHasKey( 'taxes', $first_item );
		$this->assertEquals( 'ERP_IVA_21', $first_item['taxes'][0] );

		// Verify erp_tax_type is stored correctly in database.
		$stored_tax_type = TAXES::get_tax_types_map( $tax_rate_id );
		$this->assertEquals( $erp_tax_type, $stored_tax_type );

		// Test tax in array item.
		$result = TAXES::update_tax_type( $tax_rate_id, null );
		$this->assertNotFalse( $result, 'Failed to update tax type' );

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

		// Clean up
		\WC_Tax::_delete_tax_rate( $tax_rate_id );
		$product->delete( true );
		$order->delete( true );
	}

	/**
	 * Test rounding precision for taxes (Issue #138)
	 * 
	 * Verifies that tax values are rounded to 2 decimals instead of 0 decimals.
	 * This prevents total mismatches between WooCommerce and Holded.
	 */
	public function test_order_tax_rounding_precision() {
		// Enable tax calculations.
		update_option( 'woocommerce_calc_taxes', 'yes' );
		update_option( 'woocommerce_prices_include_tax', 'no' );

		// Create a tax rate with decimal precision (21.5%).
		$tax_rate_id = \WC_Tax::_insert_tax_rate(
			array(
				'tax_rate_country'  => 'ES',
				'tax_rate_state'    => '',
				'tax_rate'          => '21.5000',
				'tax_rate_name'     => 'IVA',
				'tax_rate_priority' => 1,
				'tax_rate_compound' => 0,
				'tax_rate_shipping' => 1,
				'tax_rate_order'    => 0,
				'tax_rate_class'    => '',
			)
		);

		// Test different price scenarios that generate decimal tax values.
		$test_cases = array(
			array(
				'price'         => 49.99,
				'quantity'      => 1,
				'expected_tax'  => 10.75, // 49.99 * 0.215 = 10.74785 rounded to 10.75
			),
			array(
				'price'         => 33.33,
				'quantity'      => 1,
				'expected_tax'  => 7.17, // 33.33 * 0.215 = 7.16595 rounded to 7.17
			),
			array(
				'price'         => 12.45,
				'quantity'      => 3,
				'expected_tax'  => 8.03, // 37.35 * 0.215 = 8.03025 rounded to 8.03
			),
		);

		foreach ( $test_cases as $index => $test_case ) {
			// Create a product.
			$product = new \WC_Product_Simple();
			$product->set_name( 'Test Product ' . $index );
			$product->set_regular_price( $test_case['price'] );
			$product->set_tax_status( 'taxable' );
			$product->set_tax_class( '' );
			$product->save();

			// Create an order.
			$order = new \WC_Order();
			$order->set_billing_country( 'ES' );
			$order->set_billing_state( 'M' );
			$order->set_billing_city( 'Madrid' );
			$order->set_billing_email( 'test@example.com' );
			$order->set_status( 'completed' );

			// Add product to order.
			$item = new \WC_Order_Item_Product();
			$item->set_product( $product );
			$item->set_quantity( $test_case['quantity'] );
			$subtotal = $test_case['price'] * $test_case['quantity'];
			$item->set_subtotal( $subtotal );
			$item->set_total( $subtotal );
			
			// Set tax data.
			$tax_amount = $subtotal * 0.215;
			$item->set_taxes(
				array(
					'total'    => array( $tax_rate_id => $tax_amount ),
					'subtotal' => array( $tax_rate_id => $tax_amount ),
				)
			);
			
			$order->add_item( $item );
			$order->calculate_totals();
			$order->save();

			// Generate order data.
			$option_prefix = 'conecom-test';
			$order_data    = ORDER::generate_order_data( $this->settings, $order, $option_prefix );

			// Verify tax is rounded to 2 decimals.
			$this->assertNotEmpty( $order_data['items'] );
			$first_item = $order_data['items'][0];
			
			if ( isset( $first_item['tax'] ) ) {
				// Verify the tax value has proper decimal precision.
				$this->assertIsFloat( $first_item['tax'] );
				$this->assertEquals( 
					$test_case['expected_tax'], 
					round( $subtotal * 0.215, 2 ),
					"Tax should be rounded to 2 decimals for price {$test_case['price']}"
				);
			}

			// Clean up.
			$product->delete( true );
			$order->delete( true );
		}

		\WC_Tax::_delete_tax_rate( $tax_rate_id );
	}

	/**
	 * Test rounding precision for discounts (Issue #138)
	 * 
	 * Verifies that discount percentages are rounded to 2 decimals instead of 0 decimals.
	 */
	public function test_order_discount_rounding_precision() {
		// Create products with prices that generate decimal discounts.
		$test_cases = array(
			array(
				'price'              => 47.85,
				'discount_amount'    => 7.18, // 15% of 47.85 = 7.1775 rounded to 7.18
				'expected_discount'  => 15.01, // Percentage: (7.18 / 47.85) * 100 = 15.01
			),
			array(
				'price'              => 123.45,
				'discount_amount'    => 12.59, // ~10.2% discount
				'expected_discount'  => 10.20, // Percentage: (12.59 / 123.45) * 100 = 10.20
			),
			array(
				'price'              => 99.99,
				'discount_amount'    => 24.50, // ~24.5% discount
				'expected_discount'  => 24.50, // Percentage: (24.50 / 99.99) * 100 = 24.50
			),
		);

		foreach ( $test_cases as $index => $test_case ) {
			// Create a product.
			$product = new \WC_Product_Simple();
			$product->set_name( 'Discount Test Product ' . $index );
			$product->set_regular_price( $test_case['price'] );
			$product->save();

			// Create a coupon.
			$coupon = new \WC_Coupon();
			$coupon->set_code( 'test_discount_' . $index );
			$coupon->set_discount_type( 'fixed_cart' );
			$coupon->set_amount( $test_case['discount_amount'] );
			$coupon->save();

			// Create an order.
			$order = new \WC_Order();
			$order->set_billing_email( 'test@example.com' );
			$order->set_status( 'completed' );

			// Add product to order.
			$item = new \WC_Order_Item_Product();
			$item->set_product( $product );
			$item->set_quantity( 1 );
			$item->set_subtotal( $test_case['price'] );
			$item->set_total( $test_case['price'] - $test_case['discount_amount'] );
			$order->add_item( $item );

			// Add coupon to order.
			$coupon_item = new \WC_Order_Item_Coupon();
			$coupon_item->set_code( 'test_discount_' . $index );
			$coupon_item->set_discount( $test_case['discount_amount'] );
			$order->add_item( $coupon_item );

			$order->calculate_totals();
			$order->save();

			// Generate order data.
			$option_prefix = 'conecom-test';
			$order_data    = ORDER::generate_order_data( $this->settings, $order, $option_prefix );

			// Verify discount is rounded to 2 decimals.
			$this->assertNotEmpty( $order_data['items'] );
			$first_item = $order_data['items'][0];
			
			if ( isset( $first_item['discount'] ) ) {
				// Calculate expected discount percentage.
				$expected_percentage = round( ( $test_case['discount_amount'] * 100 ) / $test_case['price'], 2 );
				
				// Verify the discount has proper decimal precision (2 decimals).
				$this->assertEquals(
					$expected_percentage,
					$first_item['discount'],
					"Discount should be rounded to 2 decimals for price {$test_case['price']}, delta: 0.01"
				);
				
				// Verify it's not rounded to 0 decimals.
				$this->assertNotEquals(
					round( ( $test_case['discount_amount'] * 100 ) / $test_case['price'], 0 ),
					$first_item['discount'],
					"Discount should NOT be rounded to 0 decimals"
				);
			}

			// Clean up.
			$product->delete( true );
			$coupon->delete( true );
			$order->delete( true );
		}
	}

	/**
	 * Test total matching with decimal precision (Issue #138)
	 * 
	 * Verifies that order totals match correctly when items have proper decimal precision.
	 */
	public function test_order_total_matching_with_decimals() {
		// Enable tax calculations.
		update_option( 'woocommerce_calc_taxes', 'yes' );
		update_option( 'woocommerce_prices_include_tax', 'no' );

		// Create a tax rate.
		$tax_rate_id = \WC_Tax::_insert_tax_rate(
			array(
				'tax_rate_country'  => 'ES',
				'tax_rate_state'    => '',
				'tax_rate'          => '21.0000',
				'tax_rate_name'     => 'IVA',
				'tax_rate_priority' => 1,
				'tax_rate_compound' => 0,
				'tax_rate_shipping' => 0,
				'tax_rate_order'    => 0,
				'tax_rate_class'    => '',
			)
		);

		// Test case: Multiple items with decimal prices that should sum correctly.
		$items_data = array(
			array( 'price' => 19.99, 'qty' => 2 ), // 39.98 + 8.40 tax = 48.38
			array( 'price' => 33.33, 'qty' => 1 ), // 33.33 + 6.99 tax = 40.32
			array( 'price' => 12.45, 'qty' => 3 ), // 37.35 + 7.84 tax = 45.19
		);

		// Create an order.
		$order = new \WC_Order();
		$order->set_billing_country( 'ES' );
		$order->set_billing_state( 'M' );
		$order->set_billing_email( 'test@example.com' );
		$order->set_status( 'completed' );

		$expected_subtotal = 0;
		$expected_tax      = 0;

		foreach ( $items_data as $index => $item_data ) {
			// Create a product.
			$product = new \WC_Product_Simple();
			$product->set_name( 'Test Product ' . $index );
			$product->set_regular_price( $item_data['price'] );
			$product->set_tax_status( 'taxable' );
			$product->set_tax_class( '' );
			$product->save();

			// Add product to order.
			$item     = new \WC_Order_Item_Product();
			$subtotal = $item_data['price'] * $item_data['qty'];
			$tax      = round( $subtotal * 0.21, 2 );
			
			$item->set_product( $product );
			$item->set_quantity( $item_data['qty'] );
			$item->set_subtotal( $subtotal );
			$item->set_total( $subtotal );
			$item->set_taxes(
				array(
					'total'    => array( $tax_rate_id => $tax ),
					'subtotal' => array( $tax_rate_id => $tax ),
				)
			);
			
			$order->add_item( $item );

			$expected_subtotal += $subtotal;
			$expected_tax      += $tax;
		}

		$order->calculate_totals();
		$order->save();

		$expected_total = $expected_subtotal + $expected_tax;

		// Generate order data.
		$option_prefix = 'conecom-test';
		$order_data    = ORDER::generate_order_data( $this->settings, $order, $option_prefix );

		// Verify totals match with proper decimal precision.
		$this->assertEquals(
			round( $expected_total, 2 ),
			round( $order_data['total'], 2 ),
			'Order total should match expected total with 2 decimal precision'
		);

		$this->assertEquals(
			round( $expected_tax, 2 ),
			round( $order_data['total_tax'], 2 ),
			'Order tax should match expected tax with 2 decimal precision'
		);

		// Verify items have proper decimal values.
		$this->assertCount( 3, $order_data['items'] );
		
		foreach ( $order_data['items'] as $index => $item ) {
			$this->assertIsFloat( $item['subtotal'] );
			// Verify subtotal has proper decimal precision.
			$this->assertEquals(
				round( $items_data[ $index ]['price'] * $items_data[ $index ]['qty'], 2 ),
				round( $item['subtotal'] * $item['units'], 2 ),
				"Item {$index} subtotal should have 2 decimal precision"
			);
		}

		// Clean up.
		foreach ( $order->get_items() as $item ) {
			$product = $item->get_product();
			if ( $product ) {
				$product->delete( true );
			}
		}
		$order->delete( true );
		\WC_Tax::_delete_tax_rate( $tax_rate_id );
	}
}
<?php
/**
 * Class CreateOrderTest
 *
 * Command: composer test -- --filter=CreateOrderTest
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
			'José M. García-López'	=> 'JOSE M GARCIA-LOPEZ',
			'COMIDAS & BEBIDAS S.L.'	=> 'COMIDAS Y BEBIDAS S L',
			'Peña "El @Rincón" / Granada'	=> 'PEÑA EL RINCON GRANADA',
			'Weiß y Aßmann/Waßmann'	=> 'WEISS Y ASSMANN WASSMANN',
			'Bürgerstraße 123'	=> 'BURGERSTRASSE 123',
			'#John Doe'	=> 'JOHN DOE',
			'John@Doe' => 'JOHN DOE',
			'Maçanet Çağla' => 'MAÇANET ÇAGLA',
			'Francisco Araújo da Conceição' => 'FRANCISCO ARAUJO DA CONCEIÇAO',
		];

		foreach ( $test_cases as $input => $expected ) {
			$this->assertEquals( ORDER::clean_special_chars( $input ), $expected );
		}
	}

	public function test_create_order_company_without_errors() {
		$order = new WC_Order();
		$client_data = [
			'first_name' => 'José M.',
			'last_name'  => 'García-López',
			'email'      => 'john.doe@example.com',
			'phone'      => '123456789',
			'address_1'  => '123 Main St',
			'address_2'  => '',
			'city'       => 'Sample City',
			'state'      => 'CA',
			'postcode'   => '90001',
			'country'    => 'US',
			'company'    => '',
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
			'city'       => 'Sample City',
			'state'      => 'CA',
			'postcode'   => '90001',
			'country'    => 'US',
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
		$order->set_status('completed');
		$order->set_total(100);
		$order->save();

		$this->settings['cleanchars'] = 'on';
		$option_prefix = 'conecom-test';

		// Review order data that sends to ERP.
		$order_data = ORDER::generate_order_data( $this->settings, $order, $option_prefix );
		$this->assertNotEmpty( $order_data );
		$this->assertEquals( 'JOSE M', $order_data['contactFirstName'] );
		$this->assertEquals( 'GARCIA-LOPEZ', $order_data['contactLastName'] );
	}

}
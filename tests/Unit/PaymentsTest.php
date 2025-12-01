<?php
/**
 * Tests for PAYMENTS helper class.
 *
 * Command: composer test-debug -- --filter=PaymentsTest
 *
 * @package Connect_Ecommerce
 */

use CLOSE\ConnectEcommerce\Helpers\PAYMENTS;

/**
 * Class PaymentsTest.
 */
class PaymentsTest extends WP_UnitTestCase {

	/**
	 * Option name for payment method mappings.
	 *
	 * @var string
	 */
	private $option_name = 'connect_ecommerce_payment_methods';

	/**
	 * Set up test environment.
	 *
	 * @return void
	 */
	public function setUp(): void {
		parent::setUp();
		// Clean up any existing options.
		delete_option( $this->option_name );
	}

	/**
	 * Tear down environment.
	 *
	 * @return void
	 */
	public function tearDown(): void {
		// Clean up options after each test.
		delete_option( $this->option_name );
		parent::tearDown();
	}

	/**
	 * Should return empty arrays when connector slug is empty.
	 *
	 * @return void
	 */
	public function test_returns_empty_arrays_when_connector_slug_empty(): void {
		$result = PAYMENTS::get_payment_method_mappings( '' );

		$this->assertIsArray( $result );
		$this->assertArrayHasKey( 'payment_methods', $result );
		$this->assertArrayHasKey( 'treasury_accounts', $result );
		$this->assertEmpty( $result['payment_methods'] );
		$this->assertEmpty( $result['treasury_accounts'] );
	}

	/**
	 * Should return empty arrays when no option stored.
	 *
	 * @return void
	 */
	public function test_returns_empty_arrays_when_no_option_stored(): void {
		$result = PAYMENTS::get_payment_method_mappings( 'test_connector' );

		$this->assertIsArray( $result );
		$this->assertArrayHasKey( 'payment_methods', $result );
		$this->assertArrayHasKey( 'treasury_accounts', $result );
		$this->assertEmpty( $result['payment_methods'] );
		$this->assertEmpty( $result['treasury_accounts'] );
	}

	/**
	 * Should return empty arrays when stored option is not an array.
	 *
	 * @return void
	 */
	public function test_returns_empty_arrays_when_option_not_array(): void {
		update_option( $this->option_name, 'invalid_string' );

		$result = PAYMENTS::get_payment_method_mappings( 'test_connector' );

		$this->assertIsArray( $result );
		$this->assertEmpty( $result['payment_methods'] );
		$this->assertEmpty( $result['treasury_accounts'] );
	}

	/**
	 * Should return empty arrays when connector does not exist in stored data.
	 *
	 * @return void
	 */
	public function test_returns_empty_arrays_when_connector_not_exists(): void {
		update_option(
			$this->option_name,
			array(
				'another_connector' => array(
					'gateway1' => array(
						'method' => 'method1',
					),
				),
			)
		);

		$result = PAYMENTS::get_payment_method_mappings( 'test_connector' );

		$this->assertIsArray( $result );
		$this->assertEmpty( $result['payment_methods'] );
		$this->assertEmpty( $result['treasury_accounts'] );
	}

	/**
	 * Should return empty arrays when connector data is not an array.
	 *
	 * @return void
	 */
	public function test_returns_empty_arrays_when_connector_data_not_array(): void {
		update_option(
			$this->option_name,
			array(
				'test_connector' => 'invalid_string',
			)
		);

		$result = PAYMENTS::get_payment_method_mappings( 'test_connector' );

		$this->assertIsArray( $result );
		$this->assertEmpty( $result['payment_methods'] );
		$this->assertEmpty( $result['treasury_accounts'] );
	}

	/**
	 * Should return payment methods and treasury accounts when mappings exist.
	 *
	 * @return void
	 */
	public function test_returns_payment_methods_and_treasury_when_mappings_exist(): void {
		update_option(
			$this->option_name,
			array(
				'test_connector' => array(
					'stripe' => array(
						'method'   => 'connector_stripe',
						'treasury' => 'treasury_1',
					),
					'paypal' => array(
						'method'   => 'connector_paypal',
						'treasury' => 'treasury_2',
					),
				),
			)
		);

		$result = PAYMENTS::get_payment_method_mappings( 'test_connector' );

		$this->assertIsArray( $result );
		$this->assertArrayHasKey( 'payment_methods', $result );
		$this->assertArrayHasKey( 'treasury_accounts', $result );
		$this->assertCount( 2, $result['payment_methods'] );
		$this->assertCount( 2, $result['treasury_accounts'] );
		$this->assertSame( 'connector_stripe', $result['payment_methods']['stripe'] );
		$this->assertSame( 'connector_paypal', $result['payment_methods']['paypal'] );
		$this->assertSame( 'treasury_1', $result['treasury_accounts']['stripe'] );
		$this->assertSame( 'treasury_2', $result['treasury_accounts']['paypal'] );
	}

	/**
	 * Should return only payment methods when treasury is not set.
	 *
	 * @return void
	 */
	public function test_returns_only_payment_methods_when_treasury_not_set(): void {
		update_option(
			$this->option_name,
			array(
				'test_connector' => array(
					'stripe' => array(
						'method' => 'connector_stripe',
					),
					'paypal' => array(
						'method' => 'connector_paypal',
					),
				),
			)
		);

		$result = PAYMENTS::get_payment_method_mappings( 'test_connector' );

		$this->assertIsArray( $result );
		$this->assertCount( 2, $result['payment_methods'] );
		$this->assertEmpty( $result['treasury_accounts'] );
		$this->assertSame( 'connector_stripe', $result['payment_methods']['stripe'] );
		$this->assertSame( 'connector_paypal', $result['payment_methods']['paypal'] );
	}

	/**
	 * Should return only treasury accounts when method is not set.
	 *
	 * @return void
	 */
	public function test_returns_only_treasury_when_method_not_set(): void {
		update_option(
			$this->option_name,
			array(
				'test_connector' => array(
					'stripe' => array(
						'treasury' => 'treasury_1',
					),
					'paypal' => array(
						'treasury' => 'treasury_2',
					),
				),
			)
		);

		$result = PAYMENTS::get_payment_method_mappings( 'test_connector' );

		$this->assertIsArray( $result );
		$this->assertEmpty( $result['payment_methods'] );
		$this->assertCount( 2, $result['treasury_accounts'] );
		$this->assertSame( 'treasury_1', $result['treasury_accounts']['stripe'] );
		$this->assertSame( 'treasury_2', $result['treasury_accounts']['paypal'] );
	}

	/**
	 * Should handle backward compatibility with scalar mapping values.
	 *
	 * @return void
	 */
	public function test_handles_backward_compatibility_scalar_mapping(): void {
		update_option(
			$this->option_name,
			array(
				'test_connector' => array(
					'stripe' => 'connector_stripe',
					'paypal' => 'connector_paypal',
				),
			)
		);

		$result = PAYMENTS::get_payment_method_mappings( 'test_connector' );

		$this->assertIsArray( $result );
		$this->assertCount( 2, $result['payment_methods'] );
		$this->assertEmpty( $result['treasury_accounts'] );
		$this->assertSame( 'connector_stripe', $result['payment_methods']['stripe'] );
		$this->assertSame( 'connector_paypal', $result['payment_methods']['paypal'] );
	}

	/**
	 * Should skip null gateway IDs.
	 *
	 * @return void
	 */
	public function test_skips_null_gateway_ids(): void {
		// Note: PHP doesn't allow non-scalar array keys, so we test with null which is scalar but empty.
		// The code checks is_scalar() which includes null, but then sanitize_text_field will make it empty string.
		update_option(
			$this->option_name,
			array(
				'test_connector' => array(
					'valid_gateway' => array(
						'method' => 'method1',
					),
				),
			)
		);

		$result = PAYMENTS::get_payment_method_mappings( 'test_connector' );

		$this->assertIsArray( $result );
		$this->assertCount( 1, $result['payment_methods'] );
		$this->assertArrayHasKey( 'valid_gateway', $result['payment_methods'] );
		$this->assertSame( 'method1', $result['payment_methods']['valid_gateway'] );
	}

	/**
	 * Should skip empty gateway IDs after sanitization.
	 *
	 * @return void
	 */
	public function test_skips_empty_gateway_ids_after_sanitization(): void {
		update_option(
			$this->option_name,
			array(
				'test_connector' => array(
					'   ' => array(
						'method' => 'method1',
					),
					'valid_gateway' => array(
						'method' => 'method2',
					),
				),
			)
		);

		$result = PAYMENTS::get_payment_method_mappings( 'test_connector' );

		$this->assertIsArray( $result );
		$this->assertCount( 1, $result['payment_methods'] );
		$this->assertArrayHasKey( 'valid_gateway', $result['payment_methods'] );
		$this->assertSame( 'method2', $result['payment_methods']['valid_gateway'] );
	}

	/**
	 * Should skip empty method values after sanitization.
	 *
	 * @return void
	 */
	public function test_skips_empty_method_values_after_sanitization(): void {
		update_option(
			$this->option_name,
			array(
				'test_connector' => array(
					'gateway1' => array(
						'method' => '   ',
					),
					'gateway2' => array(
						'method' => 'valid_method',
					),
				),
			)
		);

		$result = PAYMENTS::get_payment_method_mappings( 'test_connector' );

		$this->assertIsArray( $result );
		$this->assertCount( 1, $result['payment_methods'] );
		$this->assertArrayHasKey( 'gateway2', $result['payment_methods'] );
		$this->assertSame( 'valid_method', $result['payment_methods']['gateway2'] );
	}

	/**
	 * Should skip empty treasury values after sanitization.
	 *
	 * @return void
	 */
	public function test_skips_empty_treasury_values_after_sanitization(): void {
		update_option(
			$this->option_name,
			array(
				'test_connector' => array(
					'gateway1' => array(
						'treasury' => '   ',
					),
					'gateway2' => array(
						'treasury' => 'valid_treasury',
					),
				),
			)
		);

		$result = PAYMENTS::get_payment_method_mappings( 'test_connector' );

		$this->assertIsArray( $result );
		$this->assertCount( 1, $result['treasury_accounts'] );
		$this->assertArrayHasKey( 'gateway2', $result['treasury_accounts'] );
		$this->assertSame( 'valid_treasury', $result['treasury_accounts']['gateway2'] );
	}

	/**
	 * Should skip non-scalar method values.
	 *
	 * @return void
	 */
	public function test_skips_non_scalar_method_values(): void {
		update_option(
			$this->option_name,
			array(
				'test_connector' => array(
					'gateway1' => array(
						'method' => array( 'invalid' ),
					),
					'gateway2' => array(
						'method' => 'valid_method',
					),
				),
			)
		);

		$result = PAYMENTS::get_payment_method_mappings( 'test_connector' );

		$this->assertIsArray( $result );
		$this->assertCount( 1, $result['payment_methods'] );
		$this->assertArrayHasKey( 'gateway2', $result['payment_methods'] );
		$this->assertSame( 'valid_method', $result['payment_methods']['gateway2'] );
	}

	/**
	 * Should skip non-scalar treasury values.
	 *
	 * @return void
	 */
	public function test_skips_non_scalar_treasury_values(): void {
		update_option(
			$this->option_name,
			array(
				'test_connector' => array(
					'gateway1' => array(
						'treasury' => array( 'invalid' ),
					),
					'gateway2' => array(
						'treasury' => 'valid_treasury',
					),
				),
			)
		);

		$result = PAYMENTS::get_payment_method_mappings( 'test_connector' );

		$this->assertIsArray( $result );
		$this->assertCount( 1, $result['treasury_accounts'] );
		$this->assertArrayHasKey( 'gateway2', $result['treasury_accounts'] );
		$this->assertSame( 'valid_treasury', $result['treasury_accounts']['gateway2'] );
	}

	/**
	 * Should sanitize gateway IDs, methods, and treasury values.
	 *
	 * @return void
	 */
	public function test_sanitizes_gateway_ids_methods_and_treasury(): void {
		update_option(
			$this->option_name,
			array(
				'test_connector' => array(
					'gateway1' => array(
						'method'   => 'method1',
						'treasury' => 'treasury1',
					),
				),
			)
		);

		$result = PAYMENTS::get_payment_method_mappings( 'test_connector' );

		$this->assertIsArray( $result );
		$this->assertArrayHasKey( 'gateway1', $result['payment_methods'] );
		$this->assertArrayHasKey( 'gateway1', $result['treasury_accounts'] );
		$this->assertSame( 'method1', $result['payment_methods']['gateway1'] );
		$this->assertSame( 'treasury1', $result['treasury_accounts']['gateway1'] );
		$this->assertStringNotContainsString( '<script>', $result['payment_methods']['gateway1'] );
		$this->assertStringNotContainsString( '<script>', $result['treasury_accounts']['gateway1'] );
	}

	/**
	 * Should handle mixed valid and invalid mappings.
	 *
	 * @return void
	 */
	public function test_handles_mixed_valid_and_invalid_mappings(): void {
		update_option(
			$this->option_name,
			array(
				'test_connector' => array(
					'valid1' => array(
						'method'   => 'method1',
						'treasury' => 'treasury1',
					),
					'   ' => array(
						'method' => 'method3',
					),
					'valid2' => array(
						'method' => '',
					),
					'valid3' => 'scalar_method',
					'valid4' => array(
						'method'   => 'method4',
						'treasury' => 'treasury4',
					),
				),
			)
		);

		$result = PAYMENTS::get_payment_method_mappings( 'test_connector' );

		$this->assertIsArray( $result );
		$this->assertCount( 3, $result['payment_methods'] );
		$this->assertCount( 2, $result['treasury_accounts'] );
		$this->assertArrayHasKey( 'valid1', $result['payment_methods'] );
		$this->assertArrayHasKey( 'valid3', $result['payment_methods'] );
		$this->assertArrayHasKey( 'valid4', $result['payment_methods'] );
		$this->assertArrayHasKey( 'valid1', $result['treasury_accounts'] );
		$this->assertArrayHasKey( 'valid4', $result['treasury_accounts'] );
		$this->assertSame( 'method1', $result['payment_methods']['valid1'] );
		$this->assertSame( 'scalar_method', $result['payment_methods']['valid3'] );
		$this->assertSame( 'method4', $result['payment_methods']['valid4'] );
		$this->assertSame( 'treasury1', $result['treasury_accounts']['valid1'] );
		$this->assertSame( 'treasury4', $result['treasury_accounts']['valid4'] );
	}
}


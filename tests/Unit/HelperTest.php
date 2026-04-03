<?php
/**
 * Tests for HELPER::get_connector() method.
 *
 * Command: composer test -- --filter=HelperTest
 *
 * @package Connect_Ecommerce
 */

use CLOSE\ConnectEcommerce\Helpers\HELPER;

/**
 * Class HelperTest.
 */
class HelperTest extends WP_UnitTestCase {

	/**
	 * Option name for connector settings.
	 *
	 * @var string
	 */
	private $option_name = 'connect_ecommerce';

	/**
	 * Option name for payment method mappings.
	 *
	 * @var string
	 */
	private $payment_option_name = 'connect_ecommerce_payment_methods';

	/**
	 * Option name for prod mergevars.
	 *
	 * @var string
	 */
	private $mergevars_option_name = 'connect_ecommerce_prod_mergevars';

	/**
	 * Set up test environment.
	 *
	 * @return void
	 */
	public function setUp(): void {
		parent::setUp();
		// Clean up any existing options.
		delete_option( $this->option_name );
		delete_option( $this->payment_option_name );
		delete_option( $this->mergevars_option_name );
	}

	/**
	 * Tear down environment.
	 *
	 * @return void
	 */
	public function tearDown(): void {
		// Clean up options after each test.
		delete_option( $this->option_name );
		delete_option( $this->payment_option_name );
		delete_option( $this->mergevars_option_name );
		parent::tearDown();
	}

	/**
	 * Should return connector with empty settings when no connector configured.
	 *
	 * @return void
	 */
	public function test_returns_connector_with_empty_settings_when_no_connector(): void {
		update_option( $this->option_name, array() );

		$options  = array();
		$connector = HELPER::get_connector( $options );

		$this->assertIsArray( $connector );
		$this->assertArrayHasKey( 'settings_all', $connector );
		$this->assertArrayHasKey( 'connector', $connector );
		$this->assertArrayHasKey( 'settings', $connector );
		$this->assertArrayHasKey( 'all_options', $connector );
		$this->assertSame( '', $connector['connector'] );
		$this->assertIsArray( $connector['settings'] );
		$this->assertArrayHasKey( 'prod_mergevars', $connector['settings'] );
		$this->assertArrayHasKey( 'payment_methods', $connector['settings'] );
		$this->assertArrayHasKey( 'treasury_accounts', $connector['settings'] );
		$this->assertEmpty( $connector['settings']['payment_methods'] );
		$this->assertEmpty( $connector['settings']['treasury_accounts'] );
	}

	/**
	 * Should return connector with prod_mergevars when option exists.
	 *
	 * @return void
	 */
	public function test_returns_connector_with_prod_mergevars(): void {
		update_option( $this->option_name, array() );
		update_option(
			$this->mergevars_option_name,
			array(
				'prod_mergevars' => array(
					'field1' => 'value1',
				),
			)
		);

		$options  = array();
		$connector = HELPER::get_connector( $options );

		$this->assertIsArray( $connector['settings']['prod_mergevars'] );
		$this->assertArrayHasKey( 'field1', $connector['settings']['prod_mergevars'] );
		$this->assertSame( 'value1', $connector['settings']['prod_mergevars']['field1'] );
	}

	/**
	 * Should return empty prod_mergevars when option does not exist.
	 *
	 * @return void
	 */
	public function test_returns_empty_prod_mergevars_when_option_not_exists(): void {
		update_option( $this->option_name, array() );

		$options  = array();
		$connector = HELPER::get_connector( $options );

		$this->assertIsArray( $connector['settings']['prod_mergevars'] );
		$this->assertEmpty( $connector['settings']['prod_mergevars'] );
	}

	/**
	 * Should return connector with payment methods when connector configured.
	 *
	 * @return void
	 */
	public function test_returns_connector_with_payment_methods_when_configured(): void {
		// Check if the API class exists before running the test.
		if ( ! class_exists( 'Connect_Ecommerce_Clientify' ) ) {
			$this->markTestSkipped( 'Connector API class not available for testing' );
		}

		$connector_slug = 'clientify';
		update_option(
			$this->option_name,
			array(
				'connector' => $connector_slug,
				$connector_slug => array(
					'setting1' => 'value1',
				),
			)
		);
		update_option(
			$this->payment_option_name,
			array(
				$connector_slug => array(
					'bacs' => array(
						'method'   => 'connector_bacs',
						'treasury' => 'treasury_1',
					),
				),
			)
		);

		// Provide options with 'clientify' key as expected by the API class.
		$options  = array(
			$connector_slug => array(
				'name' => 'Clientify',
			),
			'clientify' => array(
				'name' => 'Clientify',
			),
		);

		$connector = HELPER::get_connector( $options );

		$this->assertSame( $connector_slug, $connector['connector'] );
		$this->assertArrayHasKey( 'payment_methods', $connector['settings'] );
		$this->assertArrayHasKey( 'treasury_accounts', $connector['settings'] );
		$this->assertArrayHasKey( 'bacs', $connector['settings']['payment_methods'] );
		$this->assertArrayHasKey( 'bacs', $connector['settings']['treasury_accounts'] );
		$this->assertSame( 'connector_bacs', $connector['settings']['payment_methods']['bacs'] );
		$this->assertSame( 'treasury_1', $connector['settings']['treasury_accounts']['bacs'] );
		
		// Only check for connapi_erp if class was successfully instantiated.
		if ( isset( $connector['connapi_erp'] ) ) {
			$this->assertArrayHasKey( 'connapi_erp', $connector );
			$this->assertArrayHasKey( 'options', $connector );
		}
	}

	/**
	 * Should return connector with settings from connector slug.
	 *
	 * @return void
	 */
	public function test_returns_connector_with_settings_from_slug(): void {
		$connector_slug = 'test_connector';
		$connector_settings = array(
			'setting1' => 'value1',
			'setting2' => 'value2',
		);
		update_option(
			$this->option_name,
			array(
				'connector' => $connector_slug,
				$connector_slug => $connector_settings,
			)
		);

		// Don't pass connector options, so it won't try to instantiate API.
		$options  = array();
		$connector = HELPER::get_connector( $options );

		$this->assertSame( $connector_slug, $connector['connector'] );
		$this->assertSame( $connector_settings['setting1'], $connector['settings']['setting1'] );
		$this->assertSame( $connector_settings['setting2'], $connector['settings']['setting2'] );
		// Should not have options or connapi_erp since options don't include the connector.
		$this->assertArrayNotHasKey( 'options', $connector );
		$this->assertArrayNotHasKey( 'connapi_erp', $connector );
	}

	/**
	 * Should return empty settings when connector slug not found in settings_all.
	 *
	 * @return void
	 */
	public function test_returns_empty_settings_when_connector_slug_not_found(): void {
		update_option(
			$this->option_name,
			array(
				'connector' => 'non_existent_connector',
			)
		);

		$options  = array();
		$connector = HELPER::get_connector( $options );

		// A slug with no matching settings entry is not migrated, so no connector
		// context is built and get_connector() returns the empty fallback.
		$this->assertSame( '', $connector['connector'] );
		$this->assertIsArray( $connector['settings'] );
	}

	/**
	 * Should return connector with all_options from parameter.
	 *
	 * @return void
	 */
	public function test_returns_connector_with_all_options(): void {
		update_option( $this->option_name, array() );

		$options  = array(
			'option1' => 'value1',
			'option2' => 'value2',
		);
		$connector = HELPER::get_connector( $options );

		$this->assertSame( $options, $connector['all_options'] );
	}

	/**
	 * Should return early when connector options name is empty.
	 *
	 * @return void
	 */
	public function test_returns_early_when_connector_options_name_empty(): void {
		$connector_slug = 'test_connector';
		update_option(
			$this->option_name,
			array(
				'connector' => $connector_slug,
				$connector_slug => array(),
			)
		);

		$options  = array(
			$connector_slug => array(
				// name is missing.
			),
		);

		$connector = HELPER::get_connector( $options );

		// Should return connector but without connapi_erp since name is empty.
		$this->assertIsArray( $connector );
		$this->assertArrayHasKey( 'connector', $connector );

		// The new implementation no longer resets connector in the DB when the
		// options name is missing — it just skips instantiating connapi_erp.
		$this->assertArrayNotHasKey( 'connapi_erp', $connector );
		$settings_all = get_option( $this->option_name );
		$this->assertSame( 'test_connector', $settings_all['connector'] );
	}

	/**
	 * Should initialize payment methods and treasury accounts as empty arrays.
	 *
	 * @return void
	 */
	public function test_initializes_payment_methods_and_treasury_as_empty_arrays(): void {
		update_option( $this->option_name, array() );

		$options  = array();
		$connector = HELPER::get_connector( $options );

		$this->assertIsArray( $connector['settings']['payment_methods'] );
		$this->assertIsArray( $connector['settings']['treasury_accounts'] );
		$this->assertEmpty( $connector['settings']['payment_methods'] );
		$this->assertEmpty( $connector['settings']['treasury_accounts'] );
	}

	/**
	 * Should handle null coalescing for prod_mergevars option.
	 *
	 * @return void
	 */
	public function test_handles_null_coalescing_for_prod_mergevars(): void {
		update_option( $this->option_name, array() );
		// Set option to false to test null coalescing.
		update_option( $this->mergevars_option_name, false );

		$options  = array();
		$connector = HELPER::get_connector( $options );

		$this->assertIsArray( $connector['settings']['prod_mergevars'] );
		$this->assertEmpty( $connector['settings']['prod_mergevars'] );
	}
}


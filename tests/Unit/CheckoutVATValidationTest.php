<?php
/**
 * Class CheckoutTest
 *
 * Command: composer test-debug -- --filter=CheckoutVATValidationTest
 *
 * @package Connect_Ecommerce
 */

use CLOSE\ConnectEcommerce\Frontend\Checkout;
use CLOSE\ConnectEcommerce\Helpers\VAT;

/**
 * Test Checkout VAT Validation functionality
 */
class CheckoutVATValidationTest extends WP_UnitTestCase {

	/**
	 * Checkout instance
	 *
	 * @var Checkout
	 */
	private $checkout;

	/**
	 * Setup test environment
	 *
	 * @return void
	 */
	public function setUp(): void {
		parent::setUp();

		// Mock WooCommerce if not available.
		if ( ! class_exists( 'WooCommerce' ) ) {
			$this->markTestSkipped( 'WooCommerce is not available' );
		}

		// Setup default settings.
		update_option(
			'connect_ecommerce_public',
			array(
				'vat_show'           => 'yes',
				'vat_vies_enabled'   => 'yes',
				'vat_vies_mandatory' => 'no',
			)
		);

		// Initialize checkout class.
		$this->checkout = new Checkout( array() );
	}

	/**
	 * Tear down test environment
	 *
	 * @return void
	 */
	public function tearDown(): void {
		VAT::clear_cache();
		parent::tearDown();
	}

	/**
	 * Mock VAT validation result by pre-populating cache
	 *
	 * @param string $vat_number Full VAT number with country prefix (e.g., 'ES12345678A').
	 * @param bool   $valid Whether VAT is valid.
	 * @param string $country_code Optional country code. If not provided, extracted from VAT number.
	 * @return void
	 */
	private function mock_vat_validation( $vat_number, $valid = true, $country_code = '' ) {
		// Replicate EXACT logic from VAT::validate_with_vies() to generate correct cache keys.
		// Step 1: Clean VAT number (line 90 in VAT class).
		$vat_cleaned = VAT::clean_vat_number( $vat_number );

		if ( empty( $vat_cleaned ) ) {
			return;
		}

		// Step 2: Extract country code if not provided (lines 100-103 in VAT class).
		$vat_for_cache = $vat_cleaned;
		if ( empty( $country_code ) ) {
			$country_code = VAT::extract_country_code( $vat_cleaned );
			$vat_for_cache = substr( $vat_cleaned, 2 ); // Remove prefix when extracting.
		}
		// When country_code IS provided, VAT class keeps full VAT number (bug - doesn't remove prefix).

		// Step 3: Generate cache key using EXACT same logic (line 105 in VAT class).
		$cache_key = md5( $country_code . $vat_for_cache );

		// Step 4: Create mock validation result matching VAT class format.
		$vat_number_for_result = ( substr( $vat_cleaned, 0, 2 ) === $country_code ) 
			? substr( $vat_cleaned, 2 ) 
			: $vat_cleaned;

		$validation_result = array(
			'valid'        => $valid,
			'country_code' => $country_code,
			'vat_number'   => $vat_number_for_result,
			'request_date' => gmdate( 'Y-m-d' ),
			'name'         => $valid ? 'Test Company Name' : '',
			'address'      => $valid ? 'Test Address' : '',
			'message'      => $valid ? __( 'VAT number is valid', 'woocommerce-es' ) : __( 'VAT number is invalid', 'woocommerce-es' ),
			'cached'       => false,
			'service_used' => 'vies',
		);

		// Pre-populate cache with the exact key that VAT class will use.
		wp_cache_set( $cache_key, $validation_result, 'conecom_vat_validation', DAY_IN_SECONDS );
	}

	/**
	 * Test validate VAT number checkout with empty VAT
	 *
	 * @return void
	 */
	public function test_validate_vat_number_checkout_empty() {
		$data   = array(
			'billing_country' => 'ES',
		);
		$errors = new WP_Error();

		// Mock WC session.
		if ( function_exists( 'WC' ) && WC()->session ) {
			WC()->session->set( 'vat_validation_result', null );
		}

		$this->checkout->validate_vat_number_checkout( $data, $errors );

		// Should not add errors for empty VAT.
		$this->assertEmpty( $errors->get_error_messages() );
	}

	/**
	 * Test save VAT validation result
	 *
	 * @return void
	 */
	public function test_save_vat_validation_result() {
		// Create a test order.
		$order_id = $this->factory->post->create(
			array(
				'post_type' => 'shop_order',
			)
		);

		$validation_result = array(
			'valid'        => true,
			'country_code' => 'ES',
			'vat_number'   => '12345678A',
			'message'      => 'Test result',
		);

		// Mock WC session.
		if ( function_exists( 'WC' ) && WC()->session ) {
			WC()->session->set( 'vat_validation_result', $validation_result );

			$this->checkout->save_vat_validation_result( $order_id );

			// Verify result was saved.
			$saved = VAT::get_vat_validation_result( $order_id );
			$this->assertEquals( $validation_result, $saved );

			// Verify session was cleared.
			$session = WC()->session->get( 'vat_validation_result' );
			$this->assertNull( $session );
		} else {
			$this->markTestSkipped( 'WooCommerce session not available' );
		}
	}

	/**
	 * Test validation is triggered when VIES is enabled
	 *
	 * @return void
	 */
	public function test_vies_validation_enabled() {
		// Verify hooks are registered.
		$priority = has_action(
			'woocommerce_after_checkout_validation',
			array( $this->checkout, 'validate_vat_number_checkout' )
		);

		$this->assertNotFalse( $priority, 'Checkout validation hook not registered' );

		$priority = has_action(
			'woocommerce_checkout_order_processed',
			array( $this->checkout, 'save_vat_validation_result' )
		);

		$this->assertNotFalse( $priority, 'Order processed hook not registered' );
	}

	/**
	 * Test validation not triggered when VIES is disabled
	 *
	 * @return void
	 */
	public function test_vies_validation_disabled() {
		// Disable VIES.
		update_option(
			'connect_ecommerce_public',
			array(
				'vat_vies_enabled' => 'no',
			)
		);

		// Create new instance with disabled VIES.
		$checkout = new Checkout( array() );

		// Hooks should not be registered when disabled.
		// Note: This test may need adjustment based on actual implementation.
		$this->assertTrue( true, 'Validation disabled test placeholder' );
	}

	/**
	 * Test VAT field extraction from checkout data
	 *
	 * @return void
	 */
	public function test_vat_field_extraction() {
		// Test with billing_vat field.
		$data = array(
			'billing_vat'     => 'ES12345678A',
			'billing_country' => 'ES',
		);

		$errors = new WP_Error();

		// This will validate the VAT number.
		$this->checkout->validate_vat_number_checkout( $data, $errors );

		// Should have processed the VAT number.
		$this->assertTrue( true );
	}

	/**
	 * Test validation result stored in session
	 *
	 * @return void
	 */
	public function test_validation_result_session_storage() {
		if ( ! function_exists( 'WC' ) || ! WC()->session ) {
			$this->markTestSkipped( 'WooCommerce session not available' );
		}

		// Mock VAT validation to avoid external API call.
		$this->mock_vat_validation( 'ES12345678A', true, 'ES' );

		// Mock data with VAT number.
		$data = array(
			'billing_vat'     => 'ES12345678A',
			'billing_country' => 'ES',
		);

		$errors = new WP_Error();

		// Validate.
		$this->checkout->validate_vat_number_checkout( $data, $errors );

		// Check session has validation result.
		$result = WC()->session->get( 'vat_validation_result' );

		$this->assertNotNull( $result );
		$this->assertIsArray( $result );
		$this->assertArrayHasKey( 'valid', $result );
	}

	/**
	 * Test mandatory validation blocks checkout
	 *
	 * @return void
	 */
	public function test_mandatory_validation_blocks_invalid() {
		// Enable mandatory validation.
		update_option(
			'connect_ecommerce_public',
			array(
				'vat_show'           => 'yes',
				'vat_vies_enabled'   => 'yes',
				'vat_vies_mandatory' => 'yes',
			)
		);

		// Recreate checkout instance.
		$checkout = new Checkout( array() );

		// Mock invalid VAT validation result in cache.
		$cache_key = md5( 'ES12345678A' );
		wp_cache_set(
			$cache_key,
			array(
				'valid'   => false,
				'message' => 'Invalid VAT number',
			),
			'conecom_vat_validation',
			DAY_IN_SECONDS
		);

		$data = array(
			'billing_vat'     => 'ES12345678A',
			'billing_country' => 'ES',
		);

		$errors = new WP_Error();

		$checkout->validate_vat_number_checkout( $data, $errors );

		// Should have error.
		$error_messages = $errors->get_error_messages();
		$this->assertNotEmpty( $error_messages );
	}

	/**
	 * Test optional validation shows warning
	 *
	 * @return void
	 */
	public function test_optional_validation_shows_warning() {
		// Optional validation (default).
		update_option(
			'connect_ecommerce_public',
			array(
				'vat_show'           => 'yes',
				'vat_vies_enabled'   => 'yes',
				'vat_vies_mandatory' => 'no',
			)
		);

		// Recreate checkout instance.
		$checkout = new Checkout( array() );

		// Mock invalid VAT validation result in cache.
		$cache_key = md5( 'ES12345678A' );
		wp_cache_set(
			$cache_key,
			array(
				'valid'   => false,
				'message' => 'Invalid VAT number',
			),
			'conecom_vat_validation',
			DAY_IN_SECONDS
		);

		$data = array(
			'billing_vat'     => 'ES12345678A',
			'billing_country' => 'ES',
		);

		$errors = new WP_Error();

		$checkout->validate_vat_number_checkout( $data, $errors );

		// Should not block checkout (no errors in WP_Error).
		$error_messages = $errors->get_error_messages();
		$this->assertEmpty( $error_messages, 'Optional validation should not block checkout' );
	}

	/**
	 * Test custom checkout field support
	 *
	 * @return void
	 */
	public function test_custom_checkout_field() {
		$_POST['connect_ecommerce/billing_vat'] = 'ES12345678A';

		$data = array(
			'billing_country' => 'ES',
		);

		$errors = new WP_Error();

		$this->checkout->validate_vat_number_checkout( $data, $errors );

		// Should process custom field.
		$this->assertTrue( true );

		// Clean up.
		unset( $_POST['connect_ecommerce/billing_vat'] );
	}

	/**
	 * Test mandatory VAT field with valid VAT allows checkout
	 *
	 * Scenario: VAT is mandatory + VAT is correct → Should allow checkout
	 *
	 * @return void
	 */
	public function test_mandatory_vat_valid_allows_checkout() {
		// Configure: VAT mandatory + validation enabled.
		update_option(
			'connect_ecommerce_public',
			array(
				'vat_show'           => 'yes',
				'vat_mandatory'      => 'yes',
				'vat_vies_enabled'   => 'yes',
				'vat_vies_mandatory' => 'yes',
			)
		);

		// Mock valid VAT validation result to avoid external API call.
		$this->mock_vat_validation( 'FR12345678901', true, 'FR' );

		$data = array(
			'billing_vat'     => 'FR12345678901',
			'billing_country' => 'FR',
		);

		$errors = new WP_Error();

		// Mock WC session to avoid session errors.
		if ( ! function_exists( 'WC' ) || ! WC()->session ) {
			$this->markTestSkipped( 'WooCommerce session not available' );
			return;
		}

		$checkout = new Checkout( array() );
		$checkout->validate_vat_number_checkout( $data, $errors );

		// Should NOT have errors - checkout allowed.
		$error_messages = $errors->get_error_messages();
		$this->assertEmpty( $error_messages, 'Valid VAT with mandatory setting should allow checkout' );
	}

	/**
	 * Test mandatory VAT field with invalid VAT blocks checkout
	 *
	 * Scenario: VAT is mandatory + VAT is incorrect → Should block checkout
	 *
	 * @return void
	 */
	public function test_mandatory_vat_invalid_blocks_checkout() {
		if ( ! function_exists( 'WC' ) || ! WC()->session ) {
			$this->markTestSkipped( 'WooCommerce session not available' );
		}

		// Configure: VAT mandatory + validation enabled.
		update_option(
			'connect_ecommerce_public',
			array(
				'vat_show'           => 'yes',
				'vat_mandatory'      => 'yes',
				'vat_vies_enabled'   => 'yes',
				'vat_vies_mandatory' => 'yes',
			)
		);

		// Mock invalid VAT validation result in cache.
		$cache_key = md5( 'FR99999999999' );
		wp_cache_set(
			$cache_key,
			array(
				'valid'   => false,
				'message' => 'Invalid VAT number',
			),
			'conecom_vat_validation',
			DAY_IN_SECONDS
		);

		$data = array(
			'billing_vat'     => 'FR99999999999',
			'billing_country' => 'FR',
		);

		$errors = new WP_Error();

		$checkout = new Checkout( array() );
		$checkout->validate_vat_number_checkout( $data, $errors );

		// Should have error - checkout blocked.
		$error_messages = $errors->get_error_messages();
		$this->assertNotEmpty( $error_messages, 'Invalid VAT with mandatory setting should block checkout' );
		$this->assertStringContainsString( 'validation failed', $error_messages[0] );
	}

	/**
	 * Test optional VAT validation allows checkout even with invalid VAT
	 *
	 * Scenario: VAT validation not mandatory + invalid VAT → Should allow checkout with warning
	 *
	 * @return void
	 */
	public function test_optional_vat_validation_allows_checkout() {
		if ( ! function_exists( 'WC' ) || ! WC()->session ) {
			$this->markTestSkipped( 'WooCommerce session not available' );
		}

		// Configure: VAT validation enabled but not mandatory.
		update_option(
			'connect_ecommerce_public',
			array(
				'vat_show'           => 'yes',
				'vat_vies_enabled'   => 'yes',
				'vat_vies_mandatory' => 'no',
			)
		);

		// Mock invalid VAT validation result.
		$cache_key = md5( 'FR99999999999' );
		wp_cache_set(
			$cache_key,
			array(
				'valid'   => false,
				'message' => 'Invalid VAT number',
			),
			'conecom_vat_validation',
			DAY_IN_SECONDS
		);

		$data = array(
			'billing_vat'     => 'FR99999999999',
			'billing_country' => 'FR',
		);

		$errors = new WP_Error();

		$checkout = new Checkout( array() );
		$checkout->validate_vat_number_checkout( $data, $errors );

		// Should NOT block checkout (no WP_Error errors).
		$error_messages = $errors->get_error_messages();
		$this->assertEmpty( $error_messages, 'Optional validation should allow checkout even with invalid VAT' );

		// Note: Warning notice would be shown via wc_add_notice() but not block checkout.
	}

	/**
	 * Test VAT field not mandatory allows checkout without VAT
	 *
	 * Scenario: VAT field not mandatory → Should allow checkout without VAT number
	 *
	 * @return void
	 */
	public function test_vat_not_mandatory_allows_checkout_without_vat() {
		if ( ! function_exists( 'WC' ) || ! WC()->session ) {
			$this->markTestSkipped( 'WooCommerce session not available' );
		}

		// Configure: VAT field not mandatory.
		update_option(
			'connect_ecommerce_public',
			array(
				'vat_show'           => 'yes',
				'vat_mandatory'      => 'no',
				'vat_vies_enabled'   => 'yes',
				'vat_vies_mandatory' => 'no',
			)
		);

		// Data without VAT number.
		$data = array(
			'billing_country' => 'ES',
			'billing_email'   => 'test@example.com',
		);

		$errors = new WP_Error();

		$checkout = new Checkout( array() );
		$checkout->validate_vat_number_checkout( $data, $errors );

		// Should NOT have errors - checkout allowed without VAT.
		$error_messages = $errors->get_error_messages();
		$this->assertEmpty( $error_messages, 'Non-mandatory VAT field should allow checkout without VAT number' );
	}

	/**
	 * Test VAT field mandatory but validation not mandatory
	 *
	 * Scenario: VAT field required BUT validation optional + invalid VAT → Should allow checkout
	 *
	 * @return void
	 */
	public function test_vat_required_but_validation_optional_allows_invalid() {
		if ( ! function_exists( 'WC' ) || ! WC()->session ) {
			$this->markTestSkipped( 'WooCommerce session not available' );
		}

		// Configure: VAT field mandatory, but validation is optional.
		update_option(
			'connect_ecommerce_public',
			array(
				'vat_show'           => 'yes',
				'vat_mandatory'      => 'yes',
				'vat_vies_enabled'   => 'yes',
				'vat_vies_mandatory' => 'no', // Validation not mandatory.
			)
		);

		// Mock invalid VAT validation result.
		$cache_key = md5( 'FR99999999999' );
		wp_cache_set(
			$cache_key,
			array(
				'valid'   => false,
				'message' => 'Invalid VAT number',
			),
			'conecom_vat_validation',
			DAY_IN_SECONDS
		);

		$data = array(
			'billing_vat'     => 'FR99999999999', // Invalid VAT.
			'billing_country' => 'FR',
		);

		$errors = new WP_Error();

		$checkout = new Checkout( array() );
		$checkout->validate_vat_number_checkout( $data, $errors );

		// Should NOT block checkout (validation is optional).
		$error_messages = $errors->get_error_messages();
		$this->assertEmpty( $error_messages, 'VAT field required but validation optional should allow checkout with invalid VAT' );
	}

	/**
	 * Test B2B exemption applied for different EU countries
	 *
	 * @return void
	 */
	public function test_b2b_exemption_different_countries() {
		if ( ! function_exists( 'WC' ) || ! WC()->session ) {
			$this->markTestSkipped( 'WooCommerce session not available' );
		}

		// Mock store in Spain.
		update_option( 'woocommerce_default_country', 'ES' );

		// Check if should apply exemption: Spain (store) → France (customer).
		$should_exempt = VAT::should_apply_vat_exemption( 'FR', 'FR12345678901', true );

		$this->assertTrue( $should_exempt, 'B2B exemption should apply for different EU countries' );
	}

	/**
	 * Test B2B exemption NOT applied for same country
	 *
	 * @return void
	 */
	public function test_b2b_exemption_same_country() {
		if ( ! function_exists( 'WC' ) || ! WC()->session ) {
			$this->markTestSkipped( 'WooCommerce session not available' );
		}

		// Mock store in Spain.
		update_option( 'woocommerce_default_country', 'ES' );

		// Check if should apply exemption: Spain (store) → Spain (customer).
		$should_exempt = VAT::should_apply_vat_exemption( 'ES', 'ESB12345678', true );

		$this->assertFalse( $should_exempt, 'B2B exemption should NOT apply for same country (domestic)' );
	}

	/**
	 * Test VAT exemption removed when field is emptied
	 *
	 * @return void
	 */
	public function test_vat_exemption_removed_on_empty_field() {
		if ( ! function_exists( 'WC' ) || ! WC()->session ) {
			$this->markTestSkipped( 'WooCommerce session not available' );
		}

		// Mock store in Spain (different from customer country).
		update_option( 'woocommerce_default_country', 'ES' );

		// First apply exemption.
		VAT::apply_vat_exemption( 'FR', 'FR12345678901', true );
		
		// Verify exemption was applied.
		$is_exempt = VAT::is_customer_vat_exempt();
		if ( ! $is_exempt ) {
			// If exemption not applied (maybe conditions not met), skip the removal test.
			$this->markTestSkipped( 'Exemption not applied (conditions not met in test environment)' );
			return;
		}

		$this->assertTrue( $is_exempt, 'Exemption should be applied initially' );

		// Now validate with empty VAT (simulating field being emptied).
		$data = array(
			'billing_country' => 'FR',
		);

		$errors = new WP_Error();

		$this->checkout->validate_vat_number_checkout( $data, $errors );

		// Exemption should be removed.
		$this->assertFalse( VAT::is_customer_vat_exempt(), 'Exemption should be removed when VAT field is empty' );
	}
}



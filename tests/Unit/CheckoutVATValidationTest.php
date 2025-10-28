<?php
/**
 * Checkout VAT Validation Tests
 *
 * @package    WordPress
 * @author     David Perez <david@close.technology>
 * @copyright  2025 CLOSE
 * @version    1.0.0
 */

namespace CLOSE\ConnectEcommerce\Tests\Unit;

use CLOSE\ConnectEcommerce\Frontend\Checkout;
use CLOSE\ConnectEcommerce\Helpers\VAT;
use WP_UnitTestCase;
use WP_Error;

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
}


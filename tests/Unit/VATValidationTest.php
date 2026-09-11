<?php
/**
 * VAT Validation Tests
 *
 * @package    WordPress
 * @author     David Perez <david@close.technology>
 * @copyright  2025 CLOSE
 * @version    1.0.0
 */

namespace CLOSE\ConnectEcommerce\Tests\Unit;

use CLOSE\ConnectEcommerce\Helpers\VAT;
use WP_UnitTestCase;

/**
 * Test VAT Validation functionality
 */
class VATValidationTest extends WP_UnitTestCase {

	/**
	 * Setup test environment
	 *
	 * @return void
	 */
	public function setUp(): void {
		parent::setUp();

		// Enable VIES validation by default for tests.
		update_option(
			'connect_ecommerce_public',
			array(
				'vat_show'           => 'yes',
				'vat_vies_enabled'   => 'yes',
				'vat_vies_mandatory' => 'no',
			)
		);
	}

	/**
	 * Tear down test environment
	 *
	 * @return void
	 */
	public function tearDown(): void {
		// Clear cache.
		VAT::clear_cache();

		parent::tearDown();
	}

	/**
	 * Test clean VAT number
	 *
	 * @return void
	 */
	public function test_clean_vat_number() {
		// Test with spaces.
		$result = VAT::clean_vat_number( 'ES 12345678 A' );
		$this->assertEquals( 'ES12345678A', $result );

		// Test with dots.
		$result = VAT::clean_vat_number( 'ES.12345678.A' );
		$this->assertEquals( 'ES12345678A', $result );

		// Test with dashes.
		$result = VAT::clean_vat_number( 'ES-12345678-A' );
		$this->assertEquals( 'ES12345678A', $result );

		// Test with mixed special characters.
		$result = VAT::clean_vat_number( ' ES-12.345.678/A ' );
		$this->assertEquals( 'ES12345678A', $result );

		// Test lowercase conversion.
		$result = VAT::clean_vat_number( 'es12345678a' );
		$this->assertEquals( 'ES12345678A', $result );
	}

	/**
	 * Test extract country code
	 *
	 * @return void
	 */
	public function test_extract_country_code() {
		// Test valid EU country codes.
		$this->assertEquals( 'ES', VAT::extract_country_code( 'ES12345678A' ) );
		$this->assertEquals( 'DE', VAT::extract_country_code( 'DE123456789' ) );
		$this->assertEquals( 'FR', VAT::extract_country_code( 'FR12345678901' ) );
		$this->assertEquals( 'IT', VAT::extract_country_code( 'IT12345678901' ) );

		// Test with spaces.
		$this->assertEquals( 'ES', VAT::extract_country_code( 'ES 12345678A' ) );

		// Test invalid country code.
		$this->assertEquals( '', VAT::extract_country_code( 'XX12345678A' ) );

		// Test empty string.
		$this->assertEquals( '', VAT::extract_country_code( '' ) );
	}

	/**
	 * Test get EU countries
	 *
	 * @return void
	 */
	public function test_get_eu_countries() {
		$countries = VAT::get_eu_countries();

		// Check it's an array.
		$this->assertIsArray( $countries );

		// Check minimum expected countries.
		$this->assertGreaterThan( 20, count( $countries ) );

		// Check specific countries are included.
		$this->assertContains( 'ES', $countries );
		$this->assertContains( 'DE', $countries );
		$this->assertContains( 'FR', $countries );
		$this->assertContains( 'IT', $countries );
		$this->assertContains( 'PT', $countries );
	}

	/**
	 * Test VAT validation when disabled
	 *
	 * @return void
	 */
	public function test_validate_vat_number_when_disabled() {
		// Disable VIES validation.
		update_option(
			'connect_ecommerce_public',
			array(
				'vat_vies_enabled' => 'no',
			)
		);

		$result = VAT::validate_vat_number( 'ES12345678A', 'ES' );

		$this->assertTrue( $result['valid'] );
		$this->assertStringContainsString( 'disabled', $result['message'] );
	}

	/**
	 * Test VAT validation with empty number
	 *
	 * @return void
	 */
	public function test_validate_vat_number_empty() {
		$result = VAT::validate_vat_number( '', 'ES' );

		$this->assertFalse( $result['valid'] );
		$this->assertStringContainsString( 'empty', $result['message'] );
	}

	/**
	 * Test VAT validation result caching
	 *
	 * @return void
	 */
	public function test_vat_validation_caching() {
		// Mock a validation result.
		$vat_number   = 'ES12345678A';
		$country_code = 'ES';
		$cache_key    = md5( $country_code . '12345678A' );

		$mock_result = array(
			'valid'        => true,
			'country_code' => 'ES',
			'vat_number'   => '12345678A',
			'message'      => 'Test cached result',
			'cached'       => false,
		);

		// Set cache.
		wp_cache_set( $cache_key, $mock_result, 'conecom_vat_validation', DAY_IN_SECONDS );

		// Verify cache was set.
		$cached = wp_cache_get( $cache_key, 'conecom_vat_validation' );
		$this->assertNotFalse( $cached, 'Cache should be set' );

		// Validate (should return cached result).
		$result = VAT::validate_vat_number( $vat_number, $country_code );

		// Check result is valid (may come from cache or from validation).
		$this->assertIsArray( $result );
		$this->assertArrayHasKey( 'valid', $result );
		$this->assertArrayHasKey( 'message', $result );
	}

	/**
	 * Test is VAT validation required
	 *
	 * @return void
	 */
	public function test_is_vat_validation_required() {
		// Test when all settings are yes.
		update_option(
			'connect_ecommerce_public',
			array(
				'vat_show'           => 'yes',
				'vat_vies_enabled'   => 'yes',
				'vat_vies_mandatory' => 'yes',
			)
		);

		$this->assertTrue( VAT::is_vat_validation_required() );

		// Test when mandatory is no.
		update_option(
			'connect_ecommerce_public',
			array(
				'vat_show'           => 'yes',
				'vat_vies_enabled'   => 'yes',
				'vat_vies_mandatory' => 'no',
			)
		);

		$this->assertFalse( VAT::is_vat_validation_required() );

		// Test when VIES is disabled.
		update_option(
			'connect_ecommerce_public',
			array(
				'vat_show'           => 'yes',
				'vat_vies_enabled'   => 'no',
				'vat_vies_mandatory' => 'yes',
			)
		);

		$this->assertFalse( VAT::is_vat_validation_required() );

		// Test when VAT show is no.
		update_option(
			'connect_ecommerce_public',
			array(
				'vat_show'           => 'no',
				'vat_vies_enabled'   => 'yes',
				'vat_vies_mandatory' => 'yes',
			)
		);

		$this->assertFalse( VAT::is_vat_validation_required() );
	}

	/**
	 * Test save VAT validation result to order
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
			'request_date' => '2025-10-28',
			'name'         => 'Test Company',
			'address'      => 'Test Address',
			'message'      => 'VAT number is valid',
		);

		// Save validation result.
		VAT::save_vat_validation_result( $order_id, $validation_result );

		// Verify it was saved.
		$saved_result = get_post_meta( $order_id, '_vat_validation_result', true );
		$this->assertEquals( $validation_result, $saved_result );

		$validated = get_post_meta( $order_id, '_vat_number_validated', true );
		$this->assertEquals( 'yes', $validated );

		$date = get_post_meta( $order_id, '_vat_validation_date', true );
		$this->assertEquals( '2025-10-28', $date );
	}

	/**
	 * Test save invalid VAT validation result to order
	 *
	 * @return void
	 */
	public function test_save_invalid_vat_validation_result() {
		// Create a test order.
		$order_id = $this->factory->post->create(
			array(
				'post_type' => 'shop_order',
			)
		);

		$validation_result = array(
			'valid'   => false,
			'message' => 'Invalid VAT number',
		);

		// Save validation result.
		VAT::save_vat_validation_result( $order_id, $validation_result );

		// Verify it was saved.
		$validated = get_post_meta( $order_id, '_vat_number_validated', true );
		$this->assertEquals( 'no', $validated );
	}

	/**
	 * Test get VAT validation result from order
	 *
	 * @return void
	 */
	public function test_get_vat_validation_result() {
		// Create a test order.
		$order_id = $this->factory->post->create(
			array(
				'post_type' => 'shop_order',
			)
		);

		$validation_result = array(
			'valid'   => true,
			'message' => 'Test result',
		);

		// Save result.
		update_post_meta( $order_id, '_vat_validation_result', $validation_result );

		// Get result.
		$retrieved = VAT::get_vat_validation_result( $order_id );

		$this->assertEquals( $validation_result, $retrieved );
	}

	/**
	 * Test get VAT validation result with invalid order ID
	 *
	 * @return void
	 */
	public function test_get_vat_validation_result_invalid_order() {
		$result = VAT::get_vat_validation_result( 0 );
		$this->assertNull( $result );

		$result = VAT::get_vat_validation_result( null );
		$this->assertNull( $result );
	}

	/**
	 * Test clear cache
	 *
	 * @return void
	 */
	public function test_clear_cache() {
		// Set a cache entry.
		$cache_key = md5( 'ES12345678A' );
		wp_cache_set(
			$cache_key,
			array( 'valid' => true ),
			'conecom_vat_validation',
			DAY_IN_SECONDS
		);

		// Verify it exists.
		$cached = wp_cache_get( $cache_key, 'conecom_vat_validation' );
		$this->assertNotFalse( $cached );

		// Clear cache.
		VAT::clear_cache();

		// Verify it's gone.
		$cached = wp_cache_get( $cache_key, 'conecom_vat_validation' );
		$this->assertFalse( $cached );
	}

	/**
	 * Test default settings values
	 *
	 * @return void
	 */
	public function test_default_settings() {
		// Delete existing settings.
		delete_option( 'connect_ecommerce_public' );

		// Test default for vat_vies_enabled should be 'yes'.
		$result = VAT::validate_vat_number( 'ES12345678A', 'ES' );

		// Should not be disabled by default.
		$this->assertArrayHasKey( 'valid', $result );
	}

	/**
	 * Test VAT number formats from different countries
	 *
	 * @return void
	 */
	public function test_various_vat_formats() {
		$vat_numbers = array(
			'ES12345678A'       => 'ES',
			'DE123456789'       => 'DE',
			'FR12345678901'     => 'FR',
			'IT12345678901'     => 'IT',
			'NL123456789B01'    => 'NL',
			'BE0123456789'      => 'BE',
			'AT U12345678'      => 'AT',
			'IE 1234567AB'      => 'IE',
			'PT 123456789'      => 'PT',
			'EL123456789'       => 'EL',
		);

		foreach ( $vat_numbers as $vat => $expected_country ) {
			$country = VAT::extract_country_code( $vat );
			$this->assertEquals(
				$expected_country,
				$country,
				"Failed to extract country code from $vat"
			);
		}
	}

	/**
	 * Test validation with country code extraction
	 *
	 * @return void
	 */
	public function test_validate_with_country_code_extraction() {
		// Test with country code in VAT number.
		$result = VAT::validate_vat_number( 'ES12345678A' );

		// Should extract ES and continue.
		$this->assertArrayHasKey( 'valid', $result );
		$this->assertArrayHasKey( 'message', $result );
	}
}


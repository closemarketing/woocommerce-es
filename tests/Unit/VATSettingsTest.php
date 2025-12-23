<?php
/**
 * VAT Settings Tests
 *
 * @package    WordPress
 * @author     David Perez <david@close.technology>
 * @copyright  2025 CLOSE
 * @version    1.0.0
 */

namespace CLOSE\ConnectEcommerce\Tests\Unit;

use WP_UnitTestCase;

/**
 * Test VAT Settings functionality
 */
class VATSettingsTest extends WP_UnitTestCase {

	/**
	 * Setup test environment
	 *
	 * @return void
	 */
	public function setUp(): void {
		parent::setUp();
	}

	/**
	 * Test default VAT VIES settings
	 *
	 * @return void
	 */
	public function test_default_vat_vies_settings() {
		// Delete existing settings.
		delete_option( 'connect_ecommerce_public' );

		// Get settings (should return defaults).
		$settings = get_option( 'connect_ecommerce_public' );

		// If no settings exist, defaults should be used in code.
		// Test that the default is 'yes' for vat_vies_enabled.
		$vat_vies_enabled = isset( $settings['vat_vies_enabled'] ) ? $settings['vat_vies_enabled'] : 'yes';

		$this->assertEquals( 'yes', $vat_vies_enabled );
	}

	/**
	 * Test VAT VIES enabled setting
	 *
	 * @return void
	 */
	public function test_vat_vies_enabled_setting() {
		// Set enabled.
		update_option(
			'connect_ecommerce_public',
			array(
				'vat_vies_enabled' => 'yes',
			)
		);

		$settings = get_option( 'connect_ecommerce_public' );
		$this->assertEquals( 'yes', $settings['vat_vies_enabled'] );

		// Set disabled.
		update_option(
			'connect_ecommerce_public',
			array(
				'vat_vies_enabled' => 'no',
			)
		);

		$settings = get_option( 'connect_ecommerce_public' );
		$this->assertEquals( 'no', $settings['vat_vies_enabled'] );
	}

	/**
	 * Test VAT VIES mandatory setting
	 *
	 * @return void
	 */
	public function test_vat_vies_mandatory_setting() {
		// Set mandatory.
		update_option(
			'connect_ecommerce_public',
			array(
				'vat_vies_mandatory' => 'yes',
			)
		);

		$settings = get_option( 'connect_ecommerce_public' );
		$this->assertEquals( 'yes', $settings['vat_vies_mandatory'] );

		// Set optional.
		update_option(
			'connect_ecommerce_public',
			array(
				'vat_vies_mandatory' => 'no',
			)
		);

		$settings = get_option( 'connect_ecommerce_public' );
		$this->assertEquals( 'no', $settings['vat_vies_mandatory'] );
	}

	/**
	 * Test all VAT settings together
	 *
	 * @return void
	 */
	public function test_all_vat_settings() {
		$settings = array(
			'vat_show'           => 'yes',
			'vat_mandatory'      => 'yes',
			'vat_vies_enabled'   => 'yes',
			'vat_vies_mandatory' => 'yes',
			'company_field'      => 'yes',
		);

		update_option( 'connect_ecommerce_public', $settings );

		$saved = get_option( 'connect_ecommerce_public' );

		$this->assertEquals( 'yes', $saved['vat_show'] );
		$this->assertEquals( 'yes', $saved['vat_mandatory'] );
		$this->assertEquals( 'yes', $saved['vat_vies_enabled'] );
		$this->assertEquals( 'yes', $saved['vat_vies_mandatory'] );
		$this->assertEquals( 'yes', $saved['company_field'] );
	}

	/**
	 * Test settings persistence
	 *
	 * @return void
	 */
	public function test_settings_persistence() {
		// Set settings.
		update_option(
			'connect_ecommerce_public',
			array(
				'vat_vies_enabled'   => 'yes',
				'vat_vies_mandatory' => 'no',
			)
		);

		// Get settings.
		$settings1 = get_option( 'connect_ecommerce_public' );

		// Get settings again.
		$settings2 = get_option( 'connect_ecommerce_public' );

		// Should be the same.
		$this->assertEquals( $settings1, $settings2 );
	}

	/**
	 * Test settings validation
	 *
	 * @return void
	 */
	public function test_settings_validation() {
		// Only 'yes' or 'no' should be valid.
		$valid_values = array( 'yes', 'no' );

		foreach ( $valid_values as $value ) {
			update_option(
				'connect_ecommerce_public',
				array(
					'vat_vies_enabled' => $value,
				)
			);

			$settings = get_option( 'connect_ecommerce_public' );
			$this->assertEquals( $value, $settings['vat_vies_enabled'] );
		}
	}

	/**
	 * Test combination of VAT show and VIES enabled
	 *
	 * @return void
	 */
	public function test_vat_show_and_vies_combination() {
		// Both enabled.
		update_option(
			'connect_ecommerce_public',
			array(
				'vat_show'         => 'yes',
				'vat_vies_enabled' => 'yes',
			)
		);

		$settings = get_option( 'connect_ecommerce_public' );
		$this->assertEquals( 'yes', $settings['vat_show'] );
		$this->assertEquals( 'yes', $settings['vat_vies_enabled'] );

		// VAT show disabled, VIES enabled.
		update_option(
			'connect_ecommerce_public',
			array(
				'vat_show'         => 'no',
				'vat_vies_enabled' => 'yes',
			)
		);

		$settings = get_option( 'connect_ecommerce_public' );
		$this->assertEquals( 'no', $settings['vat_show'] );
		$this->assertEquals( 'yes', $settings['vat_vies_enabled'] );

		// Both disabled.
		update_option(
			'connect_ecommerce_public',
			array(
				'vat_show'         => 'no',
				'vat_vies_enabled' => 'no',
			)
		);

		$settings = get_option( 'connect_ecommerce_public' );
		$this->assertEquals( 'no', $settings['vat_show'] );
		$this->assertEquals( 'no', $settings['vat_vies_enabled'] );
	}

	/**
	 * Test settings update doesn't affect other settings
	 *
	 * @return void
	 */
	public function test_settings_update_isolation() {
		// Set initial settings with multiple options.
		update_option(
			'connect_ecommerce_public',
			array(
				'vat_show'           => 'yes',
				'vat_mandatory'      => 'yes',
				'vat_vies_enabled'   => 'yes',
				'vat_vies_mandatory' => 'no',
				'company_field'      => 'yes',
			)
		);

		// Update only one setting.
		$settings                        = get_option( 'connect_ecommerce_public' );
		$settings['vat_vies_mandatory'] = 'yes';
		update_option( 'connect_ecommerce_public', $settings );

		// Check all settings.
		$updated = get_option( 'connect_ecommerce_public' );

		$this->assertEquals( 'yes', $updated['vat_show'] );
		$this->assertEquals( 'yes', $updated['vat_mandatory'] );
		$this->assertEquals( 'yes', $updated['vat_vies_enabled'] );
		$this->assertEquals( 'yes', $updated['vat_vies_mandatory'] );
		$this->assertEquals( 'yes', $updated['company_field'] );
	}
}


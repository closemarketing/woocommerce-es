<?php
/**
 * Class SettingsTest
 *
 * Command: composer test -- --filter=SettingsTest
 *
 * @package Connect_Ecommerce
 */

use CLOSE\ConnectEcommerce\Helpers\HELPER;
 
class SettingsTest extends WP_UnitTestCase {
	/**
	 * Set up test environment.
	 */
	public function setUp(): void {
		parent::setUp();
		$old_settings = array(
			'vat_show' => 'yes',
			'vat_mandatory' => 'yes',
			'company_field' => 'yes',
			'opt_checkout' => 'yes',
			'terms_registration' => 'yes',
			'remove_free' => 'yes',
		);
		update_option( 'wces_settings', $old_settings );
	}

	public function test_move_settings_without_errors() {
		$wces_settings = get_option( 'wces_settings' );

		HELPER::move_settings();

		$new_settings = get_option( 'connect_ecommerce_public' );
		$this->assertEquals( count( $wces_settings ), count( $new_settings ) );
		foreach ( $wces_settings as $key => $value ) {
			$this->assertEquals( $value, $new_settings[ $key ] );
		}

		$this->assertEmpty( get_option( 'wces_settings' ) );

		// Check that old settings does not exist and does not affect new settings.
		$new_settings = get_option( 'connect_ecommerce_public' );
		$new_settings['vat_show'] = 'no';
		update_option( 'connect_ecommerce_public', $new_settings );

		HELPER::move_settings();

		$new_settings = get_option( 'connect_ecommerce_public' );
		$this->assertEquals( 'no', $new_settings['vat_show'] );
	}
}

<?php
/**
 * Class SettingsTest
 *
 * Command: composer test -- --filter=SettingsTest
 *
 * @package Connect_Ecommerce
 */

use CLOSE\ConnectEcommerce\Helpers\HELPER;

/**
 * Create Product Simple without Errors.
 *
 * @group woocommerce
 */
class SettingsTest extends WP_UnitTestCase {
	public function test_get_settings_with_environment_variables() {
		define( 'CONECOM_CONNECTOR', 'holded' );
		define( 'CONECOM_AUTH_APIKEY', '1234567890' );
		define( 'CONECOM_AUTH_IDCENTRE', '1234567890' );
		define( 'CONECOM_AUTH_URL', 'https://example.com' );
		define( 'CONECOM_AUTH_USERNAME', '1234567890' );
		define( 'CONECOM_AUTH_PASSWORD', '1234567890' );
		define( 'CONECOM_AUTH_COMPANY', '1234567890' );
		define( 'CONECOM_AUTH_COMPANY_ID', '1234567890' );
		define( 'CONECOM_AUTH_DOMAIN', 'domain' );
		define( 'CONECOM_AUTH_DBNAME', '1234567890' );

		$settings = HELPER::get_settings();
		$this->assertNotEmpty($settings);
		$this->assertEquals('holded', $settings['connector']);
		$this->assertEquals('1234567890', $settings['holded']['api']);
		$this->assertEquals('1234567890', $settings['holded']['idcentre']);
		$this->assertEquals('https://example.com', $settings['holded']['url']);
		$this->assertEquals('1234567890', $settings['holded']['username']);
		$this->assertEquals('1234567890', $settings['holded']['password']);
		$this->assertEquals('1234567890', $settings['holded']['company']);
		$this->assertEquals('1234567890', $settings['holded']['company_id']);
		$this->assertEquals('domain', $settings['holded']['domain']);
		$this->assertEquals('1234567890', $settings['holded']['dbname']);
	}

}

<?php
/**
 * Class SettingsTest
 *
 * Command: composer test -- --filter=SettingsTest
 *
 * @package Connect_Ecommerce
 */

use CLOSE\ConnectEcommerce\Helpers\HELPER;
use CLOSE\ConnectEcommerce\Admin\Settings;
 
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

	/**
	 * Connectors can omit the optional administrative message.
	 */
	public function test_connection_section_allows_connector_without_admin_message() {
		$settings = new Settings(
			array(
				'settings_all' => array(),
				'connector'    => 'test',
				'settings'     => array(),
				'all_options'  => array(),
				'options'      => array(),
				'connapi_erp'  => null,
			)
		);

		ob_start();
		$settings->connect_woocommerce_section_info();
		$output = ob_get_clean();

		$this->assertSame( '', $output );
	}

	/**
	 * Order-only connectors do not expose ERP product attributes.
	 */
	public function test_category_attribute_callback_allows_connector_without_get_attributes() {
		$settings = new Settings(
			array(
				'settings_all' => array(),
				'connector'    => 'test',
				'settings'     => array(),
				'all_options'  => array(),
				'options'      => array(),
				'connapi_erp'  => new stdClass(),
			)
		);

		ob_start();
		$settings->catattr_callback();
		$output = ob_get_clean();

		$this->assertSame( '', $output );
	}

	/**
	 * Order-only connectors must not expose product import settings.
	 */
	public function test_order_only_connector_hides_product_settings() {
		$GLOBALS['wp_settings_fields']['connect_ecommerce_admin']['connect_woocommerce_setting_section'] = array();

		$settings = new Settings(
			array(
				'settings_all' => array(),
				'connector'    => 'orders-only',
				'settings'     => array(),
				'all_options'  => array(),
				'options'      => array(
					'name'                      => 'Orders Only',
					'slug'                      => 'orders-only',
					'disable_modules'           => array( 'product' ),
					'settings_fields'           => array(),
					'product_option_stock'      => false,
					'product_price_tax_option'  => false,
					'product_price_rate_option' => false,
				),
				'connapi_erp'  => new stdClass(),
			)
		);
		$settings->page_init();

		$fields = $GLOBALS['wp_settings_fields']['connect_ecommerce_admin']['connect_woocommerce_setting_section'];
		$this->assertArrayNotHasKey( 'wcpimh_prodst', $fields );
		$this->assertArrayNotHasKey( 'wcpimh_catattr', $fields );
		$this->assertArrayNotHasKey( 'wcpimh_rates', $fields );
	}

	/**
	 * Connectors can explicitly opt out of payment-method mappings.
	 */
	public function test_connector_can_disable_payment_method_mapping() {
		$settings = new Settings(
			array(
				'settings_all' => array(),
				'connector'    => 'orders-only',
				'settings'     => array(),
				'all_options'  => array(),
				'options'      => array(
					'payment_methods' => false,
				),
				'connapi_erp'  => new class() {
					/**
					 * Returns connector payment methods.
					 *
					 * @return array
					 */
					public function get_payment_methods() {
						return array();
					}
				},
			)
		);

		$property = new ReflectionProperty( Settings::class, 'have_payments_methods' );
		$property->setAccessible( true );

		$this->assertFalse( $property->getValue( $settings ) );
	}

	/**
	 * Connector-specific connection fields can sanitize and persist their own values.
	 */
	public function test_connector_connection_settings_filter_preserves_sanitized_custom_fields() {
		$settings = new Settings(
			array(
				'settings_all' => array(),
				'connector'    => 'custom',
				'settings'     => array(),
				'all_options'  => array(),
				'options'      => array(),
				'connapi_erp'  => null,
			)
		);
		$filter = static function ( $sanitary_values, $input_values ) {
			$sanitary_values['custom_key'] = isset( $input_values['custom_key'] ) ? sanitize_text_field( $input_values['custom_key'] ) : '';
			return $sanitary_values;
		};

		add_filter( 'conecom_sanitize_connection_settings', $filter, 10, 2 );
		$sanitary_values = $settings->sanitize_fields_settings(
			array(
				'connector' => 'custom',
				'custom'    => array(
					'custom_key' => '<strong>Saved connector value</strong>',
				),
			)
		);
		remove_filter( 'conecom_sanitize_connection_settings', $filter, 10 );

		$this->assertSame( 'Saved connector value', $sanitary_values['custom']['custom_key'] );
	}
}

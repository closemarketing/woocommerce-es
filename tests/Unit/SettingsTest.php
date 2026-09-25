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
	 * Custom connector fields used by the settings tests.
	 *
	 * @param array  $fields Connector fields.
	 * @param string $slug Connector slug.
	 * @param array  $settings_fields Connector-declared setting keys.
	 * @return array
	 */
	public function add_custom_connector_fields( $fields, $slug, $settings_fields ) {
		if ( 'test-connector' !== $slug ) {
			return $fields;
		}

		return array(
			array(
				'key'     => 'custom_select',
				'label'   => 'Custom select',
				'default' => 'no',
			),
			array(
				'key'     => 'custom_checkbox',
				'label'   => 'Custom checkbox',
				'type'    => 'checkbox',
				'default' => 'no',
			),
		);
	}

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
		add_filter( 'connect_ecommerce_connector_custom_settings_fields', array( $this, 'add_custom_connector_fields' ), 10, 3 );
	}

	/**
	 * Clean up test settings and filters.
	 *
	 * @return void
	 */
	public function tearDown(): void {
		remove_filter( 'connect_ecommerce_connector_custom_settings_fields', array( $this, 'add_custom_connector_fields' ), 10 );
		delete_option( 'connect_ecommerce' );
		parent::tearDown();
	}

	/**
	 * Creates a Settings instance for a connector declaring custom fields.
	 *
	 * @param array $settings Saved connector settings.
	 * @return Settings
	 */
	private function make_custom_fields_settings( $settings = array() ) {
		$options = array(
			'slug'            => 'test-connector',
			'name'            => 'Test Connector',
			'disable_modules' => array(),
		);

		return new Settings(
			array(
				'active'       => 'test-instance',
				'settings_all' => array(),
				'items'        => array(
					'test-instance' => array(
						'id'          => 'test-instance',
						'connector'   => 'test',
						'settings'    => $settings,
						'all_options' => array( 'test' => $options ),
						'options'     => $options,
						'connapi_erp' => new stdClass(),
					),
				),
			)
		);
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
	 * Connector-declared fields must be accepted by the settings sanitizer.
	 */
	public function test_custom_connector_fields_are_saved() {
		update_option(
			'connect_ecommerce',
			array(
				'connector'       => 'test-instance',
				'connectors_meta' => array(
					'test-instance' => array( 'type' => 'test' ),
				),
				'test-instance'   => array(
					'custom_select'   => 'no',
					'custom_checkbox' => 'yes',
				),
			)
		);

		$settings = $this->make_custom_fields_settings();
		$updated  = $settings->sanitize_fields_settings(
			array(
				'connector'     => 'test-instance',
				'test-instance' => array(
					'custom_select'   => 'yes',
					'custom_checkbox' => 'no',
				),
			)
		);

		$this->assertSame( 'yes', $updated['test-instance']['custom_select'] );
		$this->assertSame( 'no', $updated['test-instance']['custom_checkbox'] );
	}

	/**
	 * Checkbox controls send an explicit false value when they are cleared.
	 */
	public function test_custom_checkbox_renders_an_unchecked_value() {
		$settings = $this->make_custom_fields_settings( array( 'custom_checkbox' => 'yes' ) );

		ob_start();
		$settings->custom_field_callback(
			array(
				'custom_field' => array(
					'key'   => 'custom_checkbox',
					'label' => 'Custom checkbox',
					'type'  => 'checkbox',
				),
			)
		);
		$output = ob_get_clean();

		$this->assertStringContainsString( 'type="hidden"', $output );
		$this->assertStringContainsString( 'value="no"', $output );
		$this->assertStringContainsString( 'type="checkbox"', $output );
	}

	/**
	 * A custom field with no section must appear in the documented orders section.
	 */
	public function test_custom_connector_field_defaults_to_orders_section() {
		$GLOBALS['wp_settings_fields']['connect_ecommerce_admin']['connect_woocommerce_setting_section_orders'] = array();

		$settings = $this->make_custom_fields_settings();
		$settings->page_init();

		$fields = $GLOBALS['wp_settings_fields']['connect_ecommerce_admin']['connect_woocommerce_setting_section_orders'];
		$this->assertArrayHasKey( 'wcpimh_custom_select', $fields );
		$this->assertArrayHasKey( 'wcpimh_custom_checkbox', $fields );
	}
}

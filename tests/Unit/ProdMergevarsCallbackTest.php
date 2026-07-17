<?php
/**
 * Class ProdMergevarsCallbackTest
 *
 * Command: composer test -- --filter=ProdMergevarsCallbackTest
 *
 * @package Connect_Ecommerce
 */

use CLOSE\ConnectEcommerce\Admin\Settings;

/**
 * Verifies Settings::prod_mergevars_callback() renders the "Field from [ERP]"
 * dropdown from the flat field => label map get_product_attributes() must
 * return (readme.md), and stays defensive if a connector still returns the
 * old nested { id, name, elements } shape for an entry.
 *
 * @group woocommerce
 */
class ProdMergevarsCallbackTest extends WP_UnitTestCase {

	/**
	 * Build a Settings instance with a mock connector, bypassing the
	 * constructor's admin-page/hook wiring since it isn't needed here.
	 *
	 * @param object $connapi_erp Mock connector exposing get_product_attributes().
	 * @return Settings
	 */
	private function make_settings( $connapi_erp ) {
		$settings = new Settings(
			array(
				'settings_all' => array(),
				'connector'    => 'mock',
				'settings'     => array(),
				'all_options'  => array(),
				'options'      => array( 'slug' => 'mock', 'name' => 'Mock ERP' ),
				'connapi_erp'  => $connapi_erp,
			)
		);

		return $settings;
	}

	/**
	 * Flat field => label map (the documented, actually-used shape) must render
	 * one option per field, using the field key as the option value.
	 */
	public function test_renders_flat_field_map() {
		$connapi_erp = new class() {
			public function get_product_attributes() {
				return array(
					'field_a' => 'Field A Label',
					'field_b' => 'Field B Label',
				);
			}
		};

		$settings = $this->make_settings( $connapi_erp );

		ob_start();
		$settings->prod_mergevars_callback();
		$output = ob_get_clean();

		$this->assertStringContainsString( 'value="field_a"', $output );
		$this->assertStringContainsString( 'Field A Label', $output );
		$this->assertStringContainsString( 'value="field_b"', $output );
		$this->assertStringContainsString( 'Field B Label', $output );
	}

	/**
	 * A connector returning the old nested { id, name, elements } shape for an
	 * entry must not render "Array" or trigger an array-to-string conversion
	 * error — that entry is skipped, and any other flat entries still render.
	 */
	public function test_skips_non_scalar_label_without_error() {
		$connapi_erp = new class() {
			public function get_product_attributes() {
				return array(
					'product_cat' => array(
						'id'       => 'product_cat',
						'name'     => 'Product Category',
						'elements' => array(),
					),
					'field_b'     => 'Field B Label',
				);
			}
		};

		$settings = $this->make_settings( $connapi_erp );

		ob_start();
		$settings->prod_mergevars_callback();
		$output = ob_get_clean();

		$this->assertStringNotContainsString( 'value="product_cat"', $output, 'Non-scalar label entries must be skipped.' );
		$this->assertStringContainsString( 'value="field_b"', $output, 'Other flat entries must still render.' );
		$this->assertStringNotContainsString( '>Array<', $output, 'Must never render the literal "Array" as option text.' );
	}
}

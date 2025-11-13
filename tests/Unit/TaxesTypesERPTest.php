<?php
/**
 * Class TaxesTypesERPTest
 *
 * Command: composer test-debug -- --filter=TaxesTypesERPTest
 *
 * @package Connect_Ecommerce
 */

use CLOSE\ConnectEcommerce\Admin\Taxes_Types_ERP;
use CLOSE\ConnectEcommerce\Helpers\TAXES;

/**
 * Test Taxes_Types_ERP class.
 *
 * @group taxes
 */
class TaxesTypesERPTest extends WP_UnitTestCase {

	/**
	 * Connector mock.
	 *
	 * @var array
	 */
	protected $connector;

	/**
	 * Set up test environment.
	 */
	public function setUp(): void {
		parent::setUp();

		// Mock connector.
		$this->connector = array(
			'options' => array(
				'name' => 'Test ERP',
			),
		);
	}

	/**
	 * Test database column exists after initialization.
	 */
	public function test_database_column_exists() {
		global $wpdb;

		// Create instance to trigger column creation.
		TAXES::ensure_tax_type_column();

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$column_exists = $wpdb->get_results(
			$wpdb->prepare(
				'SELECT * FROM INFORMATION_SCHEMA.COLUMNS
				WHERE TABLE_SCHEMA = %s
				AND TABLE_NAME = %s
				AND COLUMN_NAME = %s',
				DB_NAME,
				$wpdb->prefix . 'woocommerce_tax_rates',
				'erp_tax_type'
			)
		);

		$this->assertNotEmpty( $column_exists, 'Column erp_tax_type should exist in woocommerce_tax_rates table' );
	}

	/**
	 * Test updating tax type.
	 */
	public function test_update_tax_type() {
		global $wpdb;

		// Create a tax rate.
		$tax_rate_id = \WC_Tax::_insert_tax_rate(
			array(
				'tax_rate_country'  => 'ES',
				'tax_rate_state'    => '',
				'tax_rate'          => '21.0000',
				'tax_rate_name'     => 'IVA 21%',
				'tax_rate_priority' => 1,
				'tax_rate_compound' => 0,
				'tax_rate_shipping' => 1,
				'tax_rate_order'    => 0,
				'tax_rate_class'    => '',
			)
		);

		// Update the tax type using TAXES helper.
		$erp_tax_type = 'ERP_IVA_21';
		TAXES::update_tax_type( $tax_rate_id, $erp_tax_type );

		// Verify the update.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$stored_tax_type = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT erp_tax_type FROM {$wpdb->prefix}woocommerce_tax_rates WHERE tax_rate_id = %d",
				$tax_rate_id
			)
		);

		$this->assertEquals( $erp_tax_type, $stored_tax_type );

		// Clean up.
		\WC_Tax::_delete_tax_rate( $tax_rate_id );
	}

	/**
	 * Test get tax types map.
	 */
	public function test_get_tax_types_map() {
		// Create multiple tax rates with different ERP tax types.
		$tax_rate_1 = \WC_Tax::_insert_tax_rate(
			array(
				'tax_rate_country'  => 'ES',
				'tax_rate_state'    => '',
				'tax_rate'          => '21.0000',
				'tax_rate_name'     => 'IVA 21%',
				'tax_rate_priority' => 1,
				'tax_rate_compound' => 0,
				'tax_rate_shipping' => 1,
				'tax_rate_order'    => 0,
				'tax_rate_class'    => '',
			)
		);

		$tax_rate_2 = \WC_Tax::_insert_tax_rate(
			array(
				'tax_rate_country'  => 'ES',
				'tax_rate_state'    => '',
				'tax_rate'          => '10.0000',
				'tax_rate_name'     => 'IVA 10%',
				'tax_rate_priority' => 1,
				'tax_rate_compound' => 0,
				'tax_rate_shipping' => 1,
				'tax_rate_order'    => 0,
				'tax_rate_class'    => 'reduced-rate',
			)
		);

		// Add ERP tax types.
		TAXES::update_tax_type( $tax_rate_1, 'ERP_IVA_21' );
		TAXES::update_tax_type( $tax_rate_2, 'ERP_IVA_10' );

		// Get tax types map.
		$tax_types_map = TAXES::get_tax_types_map();

		// Verify the map contains both tax rates.
		$this->assertIsArray( $tax_types_map );
		$this->assertArrayHasKey( $tax_rate_1, $tax_types_map );
		$this->assertArrayHasKey( $tax_rate_2, $tax_types_map );
		$this->assertEquals( 'ERP_IVA_21', $tax_types_map[ $tax_rate_1 ] );
		$this->assertEquals( 'ERP_IVA_10', $tax_types_map[ $tax_rate_2 ] );

		// Clean up.
		\WC_Tax::_delete_tax_rate( $tax_rate_1 );
		\WC_Tax::_delete_tax_rate( $tax_rate_2 );
	}

	/**
	 * Test intercept tax save functionality.
	 */
	public function test_intercept_tax_save() {
		global $wpdb;

		// Create a tax rate.
		$tax_rate_id = \WC_Tax::_insert_tax_rate(
			array(
				'tax_rate_country'  => 'ES',
				'tax_rate_state'    => '',
				'tax_rate'          => '21.0000',
				'tax_rate_name'     => 'IVA 21%',
				'tax_rate_priority' => 1,
				'tax_rate_compound' => 0,
				'tax_rate_shipping' => 1,
				'tax_rate_order'    => 0,
				'tax_rate_class'    => '',
			)
		);

		// Simulate AJAX save with POST data.
		$_POST['changes'] = array(
			$tax_rate_id => array(
				'erp_tax_type' => 'ERP_IVA_21_AJAX',
			),
		);

		// Create instance and trigger intercept_tax_save.
		$taxes_erp = new Taxes_Types_ERP( $this->connector );
		$taxes_erp->intercept_tax_save();

		// Verify the tax type was saved.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$stored_tax_type = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT erp_tax_type FROM {$wpdb->prefix}woocommerce_tax_rates WHERE tax_rate_id = %d",
				$tax_rate_id
			)
		);

		$this->assertEquals( 'ERP_IVA_21_AJAX', $stored_tax_type );

		// Clean up.
		unset( $_POST['changes'] );
		\WC_Tax::_delete_tax_rate( $tax_rate_id );
	}

	/**
	 * Test that empty erp_tax_type values are not saved.
	 */
	public function test_intercept_tax_save_with_empty_value() {
		global $wpdb;

		// Create a tax rate with an initial erp_tax_type.
		$tax_rate_id = \WC_Tax::_insert_tax_rate(
			array(
				'tax_rate_country'  => 'ES',
				'tax_rate_state'    => '',
				'tax_rate'          => '21.0000',
				'tax_rate_name'     => 'IVA 21%',
				'tax_rate_priority' => 1,
				'tax_rate_compound' => 0,
				'tax_rate_shipping' => 1,
				'tax_rate_order'    => 0,
				'tax_rate_class'    => '',
			)
		);

		TAXES::update_tax_type( $tax_rate_id, 'INITIAL_VALUE' );

		// Simulate AJAX save with empty erp_tax_type.
		$_POST['changes'] = array(
			$tax_rate_id => array(
				'erp_tax_type' => '',
			),
		);

		// Create instance and trigger intercept_tax_save.
		$taxes_erp = new Taxes_Types_ERP( $this->connector );
		$taxes_erp->intercept_tax_save();

		// Verify the tax type was not changed.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$stored_tax_type = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT erp_tax_type FROM {$wpdb->prefix}woocommerce_tax_rates WHERE tax_rate_id = %d",
				$tax_rate_id
			)
		);

		$this->assertEquals( 'INITIAL_VALUE', $stored_tax_type, 'Empty erp_tax_type should not update the database' );

		// Clean up.
		unset( $_POST['changes'] );
		\WC_Tax::_delete_tax_rate( $tax_rate_id );
	}
}


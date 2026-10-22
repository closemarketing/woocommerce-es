<?php
/**
 * Tests for SKU-based import statistics and variation-aware import.
 *
 * Command: composer test -- --filter=ImportStatsSkuTest
 *
 * @package Connect_Ecommerce
 */

use CLOSE\ConnectEcommerce\Helpers\PROD;

/**
 * Class ImportStatsSkuTest.
 *
 * Covers:
 * - PROD::get_woocommerce_product_data_for_import_stats() matches by SKU only (no connect_ecommerce_id needed)
 *   and includes variations.
 * - PROD::get_import_stats() compares the API SKUs against products and variations.
 * - PROD::sync_product_item() does not create a simple product when the SKU belongs to a variation.
 *
 * @group woocommerce
 */
class ImportStatsSkuTest extends WP_UnitTestCase {

	/**
	 * Variable product created for the tests.
	 *
	 * @var WC_Product_Variable
	 */
	private $parent;

	/**
	 * Variation created for the tests.
	 *
	 * @var WC_Product_Variation
	 */
	private $variation;

	/**
	 * Simple product without connect_ecommerce_id.
	 *
	 * @var WC_Product_Simple
	 */
	private $simple;

	/**
	 * Create a variable product with one variation and a simple product, none linked to a connector.
	 */
	public function set_up() {
		parent::set_up();

		$this->parent = new WC_Product_Variable();
		$this->parent->set_name( 'Parent' );
		$this->parent->save();

		$this->variation = new WC_Product_Variation();
		$this->variation->set_parent_id( $this->parent->get_id() );
		$this->variation->set_sku( 'VAR-001' );
		$this->variation->save();

		$this->simple = new WC_Product_Simple();
		$this->simple->set_name( 'Simple' );
		$this->simple->set_sku( 'SIMPLE-001' );
		$this->simple->save();
	}

	/**
	 * Build a connector mock returning the given SKUs.
	 *
	 * @param array $skus List of SKUs.
	 * @return object
	 */
	private function make_connector( $skus ) {
		return new class( $skus ) {
			/**
			 * SKUs returned by the API.
			 *
			 * @var array
			 */
			private $skus;

			/**
			 * Constructor.
			 *
			 * @param array $skus SKUs.
			 */
			public function __construct( $skus ) {
				$this->skus = $skus;
			}

			/**
			 * Mock of get_all_product_skus().
			 *
			 * @return array
			 */
			public function get_all_product_skus() {
				return array_map(
					function ( $sku ) {
						return array( 'sku' => $sku );
					},
					$this->skus
				);
			}
		};
	}

	/**
	 * Products and variations are returned by SKU even without connect_ecommerce_id.
	 */
	public function test_wc_data_matches_by_sku_without_connector_meta() {
		$data = PROD::get_woocommerce_product_data_for_import_stats();

		$this->assertArrayHasKey( 'SIMPLE-001', $data );
		$this->assertArrayHasKey( 'VAR-001', $data );
		$this->assertSame( $this->simple->get_id(), $data['SIMPLE-001']['post_id'] );
		$this->assertSame( $this->variation->get_id(), $data['VAR-001']['post_id'] );
	}

	/**
	 * Products without SKU and trashed products are ignored.
	 */
	public function test_wc_data_ignores_empty_sku_and_trashed() {
		$trashed = new WC_Product_Simple();
		$trashed->set_name( 'Trashed' );
		$trashed->set_sku( 'TRASHED-001' );
		$trashed->save();
		wp_trash_post( $trashed->get_id() );

		$data = PROD::get_woocommerce_product_data_for_import_stats();

		$this->assertArrayNotHasKey( 'TRASHED-001', $data );
		$this->assertArrayNotHasKey( '', $data );
	}

	/**
	 * Import stats count variations as synced, so only really new SKUs are importable.
	 */
	public function test_import_stats_counts_variations_as_synced() {
		$connector = $this->make_connector( array( 'VAR-001', 'SIMPLE-001', 'NEW-001', 'NEW-002' ) );

		$result = PROD::get_import_stats( $connector, array( 'slug' => 'mock-sku-test' ), array( 'connector_id' => 'sku_test' ) );
		delete_transient( 'conecom_all_product_skus_v2_mock-sku-test_sku_test' );

		$this->assertSame( 'success', $result['status'] );
		$this->assertSame( 4, $result['api_count'] );
		$this->assertSame( 2, $result['new_count'] );
		$this->assertSame( 0, $result['delete_count'] );
		$this->assertGreaterThanOrEqual( 2, $result['wp_count'] );
	}

	/**
	 * A simple API item whose SKU is a WooCommerce variation is skipped, not duplicated.
	 */
	public function test_sync_product_item_skips_sku_of_existing_variation() {
		$item = array(
			'id'    => 99,
			'kind'  => 'simple',
			'sku'   => 'VAR-001',
			'name'  => 'Variation as simple',
			'price' => 10,
		);

		$result = PROD::sync_product_item( array(), $item, null );

		$this->assertSame( 'ok', $result['status'] );
		$this->assertSame( $this->variation->get_id(), $result['post_id'] );
		$this->assertStringContainsString( 'VAR-001', $result['message'] );
		$this->assertSame( 0, PROD::find_product( 'VAR-001' ), 'No simple product must be created with the variation SKU.' );
		$this->assertSame( $this->variation->get_id(), PROD::find_product( 'VAR-001', 'product_variation' ) );
	}
}

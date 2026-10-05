<?php
/**
 * Tests that product import statistics are scoped per connector.
 *
 * Command: composer test -- --filter=ImportStatsScopeTest
 *
 * @package Connect_Ecommerce
 */

use CLOSE\ConnectEcommerce\Helpers\PROD;

/**
 * Class ImportStatsScopeTest.
 */
class ImportStatsScopeTest extends WP_UnitTestCase {

	/**
	 * Creates a product-like post linked to an ERP item.
	 *
	 * @param string      $sku       Product SKU.
	 * @param string|null $connector Owner connector ID, or null for a legacy product.
	 * @return int
	 */
	private function create_linked_product( $sku, $connector = null ) {
		$post_id = self::factory()->post->create( array( 'post_type' => 'product' ) );
		update_post_meta( $post_id, 'connect_ecommerce_id', 'erp-' . $sku );
		update_post_meta( $post_id, '_sku', $sku );
		if ( null !== $connector ) {
			update_post_meta( $post_id, 'connect_ecommerce_connector', $connector );
		}
		return $post_id;
	}

	/**
	 * Each connector only sees its own products; legacy ones go to the active connector.
	 *
	 * @return void
	 */
	public function test_stats_products_are_scoped_to_connector() {
		update_option( 'connect_ecommerce', array( 'connector' => 'holded' ) );

		$this->create_linked_product( 'H-1', 'holded' );
		$this->create_linked_product( 'C-1', 'clientify' );
		$this->create_linked_product( 'C-2', 'clientify' );
		$this->create_linked_product( 'LEGACY-1' );

		$holded    = PROD::get_woocommerce_product_data_for_import_stats( 'holded' );
		$clientify = PROD::get_woocommerce_product_data_for_import_stats( 'clientify' );
		$all       = PROD::get_woocommerce_product_data_for_import_stats();

		$this->assertEqualsCanonicalizing( array( 'H-1', 'LEGACY-1' ), array_keys( $holded ) );
		$this->assertEqualsCanonicalizing( array( 'C-1', 'C-2' ), array_keys( $clientify ) );
		$this->assertCount( 4, $all );
	}
}

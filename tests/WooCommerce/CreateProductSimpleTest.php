<?php
/**
 * Class CreateProductSimpleTest
 *
 * Command: composer test -- --filter=CreateProductSimpleTest
 *
 * @package Connect_Ecommerce
 */

use CLOSE\ConnectEcommerce\Helpers\PROD;
use CLOSE\ConnectEcommerce\Helpers\TAX;

/**
 * Create Product Simple without Errors.
 *
 * @group woocommerce
 */
class CreateProductSimpleTest extends WP_UnitTestCase {

	/**
	 * Settings for testing
	 */
	protected $settings;
	
	/**
	 * API connection for testing
	 */
	protected $connapi_erp;
	
	/**
	 * Set up test environment.
	 */
	public function setUp(): void {
		parent::setUp();
		
		// Verify that WooCommerce is active
		$this->assertTrue(class_exists('WooCommerce'), 'WooCommerce is not active');

		$this->settings = [
			'api'            => '',
			'idcentre'       => '',
			'url'            => '',
			'username'       => '',
			'password'       => '',
			'company'        => '',
			'domain'         => '',
			'dbname'         => '',
			'stock'          => 'no',
			'prodst'         => 'draft',
			'virtual'        => 'no',
			'backorders'     => 'no',
			'catsep'         => '',
			'catattr'        => '',
			'filter'         => '',
			'filter_sku'     => '',
			'tax_option'     => 'no',
			'rates'          => 'default',
			'catnp'          => 'yes',
			'catmode'        => 'replace',
			'doctype'        => 'invoice',
			'series'         => '',
			'freeorder'      => 'no',
			'ecstatus'       => 'all',
			'order_tags'     => '',
			'design_id'      => '',
			'sync'           => 'no',
			'sync_num'       => 5,
			'sync_email'     => 'yes',
			'prod_weight_eq' => '',
			'debug_log'      => 'no',
		];
		
		// Mock API connection
		$options           = conecom_get_options();
		$this->connapi_erp = new Connect_Ecommerce_Clientify( $options );
	}

	/**
	 * Create Product Simple without Errors.
	 */
	public function test_create_product_simple_without_errors() {
		$item_path = UNIT_TESTS_DATA_PLUGIN_DIR . 'product-simple.json';
		$item      = file_get_contents( $item_path );
		$item      = json_decode( $item, true )[0];
		
		$result_sync    = PROD::sync_product_item( $this->settings, $item, $this->connapi_erp );
		$result_prod_id = $result_sync['post_id'];
		
		$this->assertNotNull( $result_sync );
		$this->assertEquals( 'ok', $result_sync['status'] );
		$this->assertIsInt( $result_prod_id );
		
		$product = wc_get_product( $result_prod_id );
		$this->assertInstanceOf( 'WC_Product_Simple', $product );
		$this->assertEquals( $item['sku'], $product->get_sku() );
		$this->assertEquals( $item['barcode'], $product->get_global_unique_id() );
		$this->assertEquals( $item['desc'], $product->get_description() );

		// Update product asserts.
		$update_post = [
			'ID'           => $result_prod_id,
			'post_title'   => 'Updated Product Title',
			'post_content' => 'Updated product description.',
		];
		wp_update_post( $update_post );
		$item['price'] = 100;
		$result_sync_upd = PROD::sync_product_item( $this->settings, $item, $this->connapi_erp, false, $result_prod_id );

		$this->assertNotNull( $result_sync_upd );
		$this->assertEquals( 'ok', $result_sync_upd['status'] );
		$this->assertIsInt( $result_sync_upd['post_id'] );
		$this->assertEquals( $result_prod_id, $result_sync_upd['post_id'] );

		// Prices update.
		$this->assertEquals( 100, get_post_meta( $result_sync_upd['post_id'], '_regular_price', true ) );

		// Product update does not change Title and Content.
		$this->assertEquals( 'Updated Product Title', get_the_title( $result_sync_upd['post_id'] ) );
		$this->assertEquals( 'Updated product description.', get_post_field( 'post_content', $result_sync_upd['post_id'] ) );

		wp_delete_post( $result_sync_upd['post_id'], true ); // Clean up after test
	}

	public function test_create_product_simple_with_errors() {
		$item_path = UNIT_TESTS_DATA_PLUGIN_DIR . 'product-simple.json';
		$item      = file_get_contents( $item_path );
		$item      = json_decode( $item, true )[0];
		$original_item = $item;
		
		unset( $item['sku'] );
		$result_sync    = PROD::sync_product_item( $this->settings, $item, $this->connapi_erp );
		$this->assertEquals( 'error', $result_sync['status'] );
		$this->assertEquals( 0, $result_sync['post_id'] );

		$item = $original_item;
		unset( $item['kind'] );
		$result_sync    = PROD::sync_product_item( $this->settings, $item, $this->connapi_erp );
		$this->assertEquals( 'ok', $result_sync['status'] );
		$this->assertNotNull( $result_sync['post_id'] );

		$item = $original_item;
		unset( $item['name'] );
		$result_sync = PROD::sync_product_item( $this->settings, $item, $this->connapi_erp );
		$this->assertEquals( 'ok', $result_sync['status'] );
		$this->assertNotNull( $result_sync['post_id'] );
		$this->assertNotEmpty( 'Product without name', get_the_title( $result_sync['post_id'] ) );

		// Blank product.
		$item = [];
		$result_sync = PROD::sync_product_item( $this->settings, $item, $this->connapi_erp );
		$this->assertEquals( 'error', $result_sync['status'] );
		$this->assertEquals( 0, $result_sync['post_id'] );
	}

	public function test_category_sync_new_products_without_errors() {
		$item_path = UNIT_TESTS_DATA_PLUGIN_DIR . 'product-simple-cats.json';
		$item      = file_get_contents( $item_path );
		$item      = json_decode( $item, true )[0];

		$this->settings['catattr'] = 'sandalias';
		$this->settings['catnp']   = 'yes'; // yes means only on new products.
		
		$result_sync    = PROD::sync_product_item( $this->settings, $item, $this->connapi_erp );
		$result_prod_id = $result_sync['post_id'];
		$product_cats   = wp_get_post_terms( $result_prod_id, 'product_cat', [ 'fields' => 'names' ] );
		
		$this->assertEquals( true, in_array( 'Calzado', $product_cats ) );

		// Update product asserts.
		$item['attributes'] = [
			[
				'id'    => '64be2e55727b35ad0b0d2c42',
				'name'  => 'sandalias',
				'value' => 'Chanclas',
			],
		];

		$result_sync_upd = PROD::sync_product_item( $this->settings, $item, $this->connapi_erp, false, $result_prod_id );
		$product_cats    = wp_get_post_terms( $result_prod_id, 'product_cat', [ 'fields' => 'names' ] );
		$this->assertEquals( false, in_array( 'Chanclas', $product_cats ) );

		wp_delete_post( $result_sync_upd['post_id'], true ); // Clean up after test
	}

	public function test_category_sync_updated_products_without_errors() {
		$item_path = UNIT_TESTS_DATA_PLUGIN_DIR . 'product-simple-cats.json';
		$item      = file_get_contents( $item_path );
		$item      = json_decode( $item, true )[0];

		$this->settings['catattr'] = 'sandalias';
		$this->settings['catnp']   = 'no'; // yes means only on new products.
		$this->settings['catmode'] = 'replace';
		
		$result_sync    = PROD::sync_product_item( $this->settings, $item, $this->connapi_erp );
		$result_prod_id = $result_sync['post_id'];
		$product_cats   = wp_get_post_terms( $result_prod_id, 'product_cat', [ 'fields' => 'names' ] );
		$product_count_cats = count( $product_cats );
		
		$this->assertEquals( true, in_array( 'Calzado', $product_cats ) );
		$this->assertEquals( 2, $product_count_cats );

		// Update product asserts.
		$item['attributes'] = [
			[
				'id'    => '64be2e55727b35ad0b0d2c42',
				'name'  => 'sandalias',
				'value' => 'Chanclas',
			],
		];

		$result_sync_upd = PROD::sync_product_item( $this->settings, $item, $this->connapi_erp, false, $result_prod_id );

		$product_cats           = wp_get_post_terms( $result_prod_id, 'product_cat', [ 'fields' => 'names' ] );
		$product_count_cats_upd = count( $product_cats );
		
		$this->assertEquals( true, in_array( 'Chanclas', $product_cats ) );
		$this->assertEquals( 1, $product_count_cats_upd );

		wp_delete_post( $result_sync_upd['post_id'], true ); // Clean up after test
	}

	/**
	 * Merge mode retains manually added categories while replacing ERP-managed ones.
	 */
	public function test_category_sync_merge_mode_preserves_manual_categories() {
		$item_path = UNIT_TESTS_DATA_PLUGIN_DIR . 'product-simple-cats.json';
		$item      = json_decode( file_get_contents( $item_path ), true )[0];

		$this->settings['catattr'] = 'sandalias';
		$this->settings['catnp']   = 'no';
		$this->settings['catmode'] = 'merge';

		$result_sync    = PROD::sync_product_item( $this->settings, $item, $this->connapi_erp );
		$result_prod_id = $result_sync['post_id'];
		$manual_term    = wp_insert_term( 'Manual category', 'product_cat' );
		wp_set_object_terms( $result_prod_id, array( $manual_term['term_id'] ), 'product_cat', true );

		$item['attributes'] = array(
			array(
				'id'    => '64be2e55727b35ad0b0d2c42',
				'name'  => 'sandalias',
				'value' => 'Chanclas',
			),
		);

		PROD::sync_product_item( $this->settings, $item, $this->connapi_erp, false, $result_prod_id );
		$product_cats = wp_get_post_terms( $result_prod_id, 'product_cat', array( 'fields' => 'names' ) );

		$this->assertContains( 'Manual category', $product_cats );
		$this->assertContains( 'Chanclas', $product_cats );
		$this->assertNotContains( 'Calzado', $product_cats );
		$this->assertNotContains( 'Zapatillas', $product_cats );

		wp_delete_post( $result_prod_id, true );
	}

	/**
	 * Merge mode preserves manual terms in custom taxonomies as well.
	 */
	public function test_custom_taxonomy_sync_merge_mode_preserves_manual_terms() {
		$taxonomy = 'conecom_sync_test';
		if ( ! taxonomy_exists( $taxonomy ) ) {
			register_taxonomy( $taxonomy, 'product' );
		}

		$product_id  = self::factory()->post->create( array( 'post_type' => 'product' ) );
		TAX::set_terms_taxonomy( array( 'catmode' => 'merge' ), $taxonomy, 'ERP old', $product_id );
		$manual_term = wp_insert_term( 'Manual term', $taxonomy );
		wp_set_object_terms( $product_id, array( $manual_term['term_id'] ), $taxonomy, true );
		TAX::set_terms_taxonomy( array( 'catmode' => 'merge' ), $taxonomy, 'ERP new', $product_id );

		$terms = wp_get_object_terms( $product_id, $taxonomy, array( 'fields' => 'names' ) );
		$this->assertContains( 'Manual term', $terms );
		$this->assertContains( 'ERP new', $terms );
		$this->assertNotContains( 'ERP old', $terms );

		wp_delete_post( $product_id, true );
	}

	/**
	 * A manual term remains manual when a later ERP payload also contains it.
	 */
	public function test_category_sync_merge_mode_preserves_overlapping_manual_terms() {
		$taxonomy = 'conecom_overlap_sync_test';
		if ( ! taxonomy_exists( $taxonomy ) ) {
			register_taxonomy( $taxonomy, 'product' );
		}

		$product_id  = self::factory()->post->create( array( 'post_type' => 'product' ) );
		$manual_term = wp_insert_term( 'Manual term', $taxonomy );

		TAX::set_terms_taxonomy( array( 'catmode' => 'merge' ), $taxonomy, 'ERP term', $product_id );
		wp_set_object_terms( $product_id, array( $manual_term['term_id'] ), $taxonomy, true );
		TAX::set_terms_taxonomy( array( 'catmode' => 'merge' ), $taxonomy, array( 'ERP term', 'Manual term' ), $product_id );
		TAX::set_terms_taxonomy( array( 'catmode' => 'merge' ), $taxonomy, 'ERP term', $product_id );

		$terms = wp_get_object_terms( $product_id, $taxonomy, array( 'fields' => 'names' ) );
		$this->assertContains( 'ERP term', $terms );
		$this->assertContains( 'Manual term', $terms );

		wp_delete_post( $product_id, true );
	}

	/**
	 * An unregistered taxonomy must not abort a product synchronization.
	 */
	public function test_sync_terms_taxonomy_returns_error_for_unregistered_taxonomy() {
		$product_id = self::factory()->post->create( array( 'post_type' => 'product' ) );
		$result     = TAX::sync_terms_taxonomy( array( 'catmode' => 'merge' ), 'conecom_missing_taxonomy', array( 1 ), $product_id );

		$this->assertWPError( $result );
		wp_delete_post( $product_id, true );
	}

	/**
	 * Direct taxonomy mappings also retain manual terms in merge mode.
	 */
	public function test_direct_custom_taxonomy_sync_merge_mode_preserves_manual_terms() {
		$taxonomy = 'conecom_direct_sync_test';
		if ( ! taxonomy_exists( $taxonomy ) ) {
			register_taxonomy( $taxonomy, 'product' );
		}

		$product_id  = self::factory()->post->create( array( 'post_type' => 'product' ) );
		TAX::assign_product_term( $product_id, $taxonomy, 'ERP direct old', array( 'catmode' => 'merge' ) );
		$manual_term = wp_insert_term( 'Manual direct term', $taxonomy );
		wp_set_object_terms( $product_id, array( $manual_term['term_id'] ), $taxonomy, true );
		TAX::assign_product_term( $product_id, $taxonomy, 'ERP direct new', array( 'catmode' => 'merge' ) );

		$terms = wp_get_object_terms( $product_id, $taxonomy, array( 'fields' => 'names' ) );
		$this->assertContains( 'Manual direct term', $terms );
		$this->assertContains( 'ERP direct new', $terms );
		$this->assertNotContains( 'ERP direct old', $terms );

		wp_delete_post( $product_id, true );
	}

	public function test_category_sync_products_mergevars_without_errors() {
		$item_path = UNIT_TESTS_DATA_PLUGIN_DIR . 'product-simple-cats.json';
		$item      = file_get_contents( $item_path );
		$item      = json_decode( $item, true )[0];

		$this->settings['catattr'] = 'sandalias';
		$this->settings['catnp']   = 'no'; // yes means only on new products.
		
		$result_sync    = PROD::sync_product_item( $this->settings, $item, $this->connapi_erp );
		$result_prod_id = $result_sync['post_id'];
		$product_cats   = wp_get_post_terms( $result_prod_id, 'product_cat' );
		$product_count_cats = count( $product_cats );
		
		$this->assertEquals( true, in_array( 'Calzado', wp_list_pluck( $product_cats, 'name' ) ) );
		$this->assertEquals( true, in_array( 'Zapatillas', wp_list_pluck( $product_cats, 'name' ) ) );
		$this->assertEquals( 2, $product_count_cats );

		update_option(
			'connect_ecommerce_prod_mergevars',
			array(
				'prod_mergevars' => 
				array (
					'product_cat|Chanclas' => 'product_cat|' . $product_cats[0]->term_id,
				),
			)
		);

		// Update product asserts.
		$item['attributes'] = [
			[
				'id'    => '64be2e55727b35ad0b0d2c42',
				'value' => 'sandalias',
				'name'  => 'Chanclas',
			],
		];

		$result_sync_upd = PROD::sync_product_item( $this->settings, $item, $this->connapi_erp, false, $result_prod_id );

		$product_cats   = wp_get_post_terms( $result_prod_id, 'product_cat', [ 'fields' => 'names' ] );
		$this->assertEquals( true, in_array( 'Calzado', $product_cats ) ); // We moved Chanclas to Calzado.

		wp_delete_post( $result_sync_upd['post_id'], true ); // Clean up after test
	}

	/**
	 * By default (stock_visibility = 'hide'), a product that runs out of stock
	 * is hidden from the catalog on sync.
	 */
	public function test_stock_out_hides_product_by_default() {
		$item_path = UNIT_TESTS_DATA_PLUGIN_DIR . 'product-simple.json';
		$item      = file_get_contents( $item_path );
		$item      = json_decode( $item, true )[0];

		$this->settings['stock'] = 'yes';

		$result_sync    = PROD::sync_product_item( $this->settings, $item, $this->connapi_erp );
		$result_prod_id = $result_sync['post_id'];

		$product = wc_get_product( $result_prod_id );
		$this->assertEquals( 'visible', $product->get_catalog_visibility() );

		$item['stock']   = 0;
		$result_sync_upd = PROD::sync_product_item( $this->settings, $item, $this->connapi_erp, false, $result_prod_id );

		$product = wc_get_product( $result_sync_upd['post_id'] );
		$this->assertEquals( 'outofstock', $product->get_stock_status() );
		$this->assertEquals( 'hidden', $product->get_catalog_visibility() );

		wp_delete_post( $result_sync_upd['post_id'], true ); // Clean up after test
	}

	/**
	 * With stock_visibility = 'no_change', running out of stock updates stock
	 * status/quantity but must not touch catalog visibility, so manual admin
	 * changes are preserved across syncs.
	 */
	public function test_stock_out_does_not_change_visibility_when_disabled() {
		$item_path = UNIT_TESTS_DATA_PLUGIN_DIR . 'product-simple.json';
		$item      = file_get_contents( $item_path );
		$item      = json_decode( $item, true )[0];

		$this->settings['stock']            = 'yes';
		$this->settings['stock_visibility'] = 'no_change';

		$result_sync    = PROD::sync_product_item( $this->settings, $item, $this->connapi_erp );
		$result_prod_id = $result_sync['post_id'];

		// Manually hide the product, as a shop manager could do in wp-admin.
		wp_set_object_terms( $result_prod_id, array( 'exclude-from-catalog', 'exclude-from-search' ), 'product_visibility' );
		$product = wc_get_product( $result_prod_id );
		$product->set_catalog_visibility( 'hidden' );
		$product->save();

		$item['stock']   = 0;
		$result_sync_upd = PROD::sync_product_item( $this->settings, $item, $this->connapi_erp, false, $result_prod_id );

		$product = wc_get_product( $result_sync_upd['post_id'] );
		$this->assertEquals( 'outofstock', $product->get_stock_status() );
		$this->assertEquals( 0, $product->get_stock_quantity() );
		// Visibility untouched by the sync (still whatever the admin set it to).
		$this->assertEquals( 'hidden', $product->get_catalog_visibility() );

		// And restoring it manually to visible must also survive a resync while out of stock.
		$product->set_catalog_visibility( 'visible' );
		$product->save();
		wp_remove_object_terms( $result_prod_id, 'exclude-from-catalog', 'product_visibility' );
		wp_remove_object_terms( $result_prod_id, 'exclude-from-search', 'product_visibility' );

		$result_sync_upd = PROD::sync_product_item( $this->settings, $item, $this->connapi_erp, false, $result_prod_id );
		$product         = wc_get_product( $result_sync_upd['post_id'] );
		$this->assertEquals( 'visible', $product->get_catalog_visibility() );

		wp_delete_post( $result_sync_upd['post_id'], true ); // Clean up after test
	}
}

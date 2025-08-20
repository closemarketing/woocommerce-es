<?php
/**
 * Class CreateProductSimpleTest
 *
 * Command: composer test -- --filter=CreateProductSimpleTest
 *
 * @package Connect_Ecommerce
 */

use CLOSE\ConnectEcommerce\Helpers\PROD;

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

	/**
	 * Create Product Simple without Errors.
	 */
	public function test_category_sync_new_products_without_errors() {
		$item_path = UNIT_TESTS_DATA_PLUGIN_DIR . 'product-simple-cats.json';
		$item      = file_get_contents( $item_path );
		$item      = json_decode( $item, true )[0];

		$this->settings['catattr'] = 'sandalias';
		$this->settings['catnp']   = 'yes'; // yes means only on new products.
		
		$result_sync    = PROD::sync_product_item( $this->settings, $item, $this->connapi_erp );
		$result_prod_id = $result_sync['post_id'];
		
		$this->assertEquals( true, in_array( 'Calzado', wp_get_post_terms( $result_prod_id, 'product_cat', [ 'fields' => 'names' ] ) ) );

		// Update product asserts.
		$item['attributes'] = [
			[
				'id'    => '64be2e55727b35ad0b0d2c42',
				'value' => 'sandalias',
				'name'  => 'Chanclas',
			],
		];

		$result_sync_upd = PROD::sync_product_item( $this->settings, $item, $this->connapi_erp, false, $result_prod_id );

		$this->assertEquals( false, in_array( 'Chanclas', wp_get_post_terms( $result_prod_id, 'product_cat', [ 'fields' => 'names' ] ) ) );

		wp_delete_post( $result_sync_upd['post_id'], true ); // Clean up after test
	}

	/**
	 * Create Product Simple without Errors.
	 */
	public function test_category_sync_updated_products_without_errors() {
		$item_path = UNIT_TESTS_DATA_PLUGIN_DIR . 'product-simple-cats.json';
		$item      = file_get_contents( $item_path );
		$item      = json_decode( $item, true )[0];

		$this->settings['catattr'] = 'sandalias';
		$this->settings['catnp']   = 'no'; // yes means only on new products.
		
		$result_sync    = PROD::sync_product_item( $this->settings, $item, $this->connapi_erp );
		$result_prod_id = $result_sync['post_id'];
		
		$this->assertEquals( true, in_array( 'Calzado', wp_get_post_terms( $result_prod_id, 'product_cat', [ 'fields' => 'names' ] ) ) );

		// Update product asserts.
		$item['attributes'] = [
			[
				'id'    => '64be2e55727b35ad0b0d2c42',
				'value' => 'sandalias',
				'name'  => 'Chanclas',
			],
		];

		$result_sync_upd = PROD::sync_product_item( $this->settings, $item, $this->connapi_erp, false, $result_prod_id );

		$this->assertEquals( true, in_array( 'Chanclas', wp_get_post_terms( $result_prod_id, 'product_cat', [ 'fields' => 'names' ] ) ) );

		wp_delete_post( $result_sync_upd['post_id'], true ); // Clean up after test
	}
}

<?php
/**
 * Class CreateProductVariableTest
 *
 * Command: composer test -- --filter=CreateProductVariableTest
 *
 * @package Connect_Ecommerce
 */

use CLOSE\ConnectEcommerce\Helpers\PROD;

/**
 * Create Product Simple without Errors.
 *
 * @group woocommerce
 */
class CreateProductVariableTest extends WP_UnitTestCase {

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
	 * Create Product Variable without Errors.
	 */
	public function test_create_product_variable_without_errors() {
		$item_path = UNIT_TESTS_DATA_PLUGIN_DIR . 'product-variable.json';
		$item      = file_get_contents( $item_path );
		$item      = json_decode( $item, true )[0];

		$this->settings['catattr'] = 'sandalias';
		$this->settings['catnp']   = 'yes'; // only in new products.

		// Test images.
		$image_path = UNIT_TESTS_DATA_PLUGIN_DIR . 'dummy-image.png';
		$image_dummy = [
			'url' => $image_path,
			'file' => $image_path,
			'content_type' => 'image/png',
		];
		$image_path = UNIT_TESTS_DATA_PLUGIN_DIR . 'dummy-image-alt.png';
		$image_dummy_alt = [
			'url' => $image_path,
			'file' => $image_path,
			'content_type' => 'image/png',
		];
		$item['images'] = [ $image_dummy, $image_dummy, $image_dummy ];
		$item['variants'][0]['image'] = $image_dummy;
		$item['variants'][1]['image'] = $image_dummy_alt;
		
		// Sync product.
		$result_sync    = PROD::sync_product_item( $this->settings, $item, $this->connapi_erp );
		$result_prod_id = $result_sync['post_id'];
		$product_cats   = wp_get_post_terms( $result_prod_id, 'product_cat', [ 'fields' => 'names' ] );

		$this->assertNotNull( $result_sync );
		$this->assertEquals( 'ok', $result_sync['status'] );
		$this->assertIsInt( $result_prod_id );
		
		$product = wc_get_product( $result_prod_id );
		$this->assertInstanceOf( 'WC_Product_Variable', $product );
		$this->assertEquals( $item['sku'], $product->get_sku() ); // Only SKU, barcode not needed for variable products.
		$this->assertEquals( $item['name'], $product->get_name() );
		$this->assertEquals( $item['desc'], $product->get_description() );
		$this->assertEquals( true, in_array( 'Calzado', $product_cats ) );

		// Assert featured images.
		$featured_image_url = get_the_post_thumbnail_url( $result_prod_id );
		$gallery_image_ids  = $product->get_gallery_image_ids();
		$this->assertNotEmpty( $featured_image_url );
		$this->assertStringContainsString( 'dummy-image.png', $featured_image_url );
		$this->assertEquals( 2, count( $gallery_image_ids ) );

		// Variable product asserts.
		$variations = $product->get_children();
		$this->assertNotEmpty( $variations );
		$index = 0;
		foreach ( $variations as $variation_id ) {
			$prod_variation = new WC_Product_Variation( $variation_id );
			$this->assertEquals( $item['variants'][$index]['price'], (float) $prod_variation->get_regular_price() );
			$this->assertEquals( $item['variants'][$index]['stock'], $prod_variation->get_stock_quantity() );
			$this->assertEquals( $item['variants'][$index]['barcode'], $prod_variation->get_global_unique_id() );

			$images = [
				'dummy-image.png',
				'dummy-image-alt.png',
			];

			// Assert images.
			$variation_image_url = get_the_post_thumbnail_url( $variation_id );
			$this->assertNotEmpty( $variation_image_url );
			$this->assertStringContainsString( $images[$index], $variation_image_url );

			$index++;
		}

		// Update product asserts.
		$update_post = [
			'ID'           => $result_prod_id,
			'post_title'   => 'Updated Product Title',
			'post_content' => 'Updated product description.',
		];
		wp_update_post( $update_post );
		$result_sync_upd = PROD::sync_product_item( $this->settings, $item, $this->connapi_erp, false, $result_prod_id );

		$this->assertNotNull( $result_sync_upd );
		$this->assertEquals( 'ok', $result_sync_upd['status'] );
		$this->assertIsInt( $result_sync_upd['post_id'] );
		$this->assertEquals( $result_prod_id, $result_sync_upd['post_id'] );

		// Prices.
		$this->assertEquals( $item['price'], get_post_meta( $result_sync_upd['post_id'], '_regular_price', true ) );

		// Product update does not change Title and Content.
		$this->assertEquals( 'Updated Product Title', get_the_title( $result_sync_upd['post_id'] ) );
		$this->assertEquals( 'Updated product description.', get_post_field( 'post_content', $result_sync_upd['post_id'] ) );

		wp_delete_post( $result_sync_upd['post_id'], true ); // Clean up after test
	}

	public function test_create_product_variable_with_errors() {
		$item_path = UNIT_TESTS_DATA_PLUGIN_DIR . 'product-variable.json';
		$item      = file_get_contents( $item_path );
		$item      = json_decode( $item, true )[0];
		$original_item = $item;
		
		// Without SKU.
		unset( $item['sku'] );
		$result_sync    = PROD::sync_product_item( $this->settings, $item, $this->connapi_erp );
		$this->assertEquals( 'error', $result_sync['status'] );
		$this->assertEquals( 0, $result_sync['post_id'] );

		// Without variants.
		$item = $original_item;
		unset( $item['variants'] );
		$result_sync    = PROD::sync_product_item( $this->settings, $item, $this->connapi_erp );
		$this->assertEquals( 'error', $result_sync['status'] );
		$this->assertEquals( 0, $result_sync['post_id'] );

		// Without name.
		$item = $original_item;
		unset( $item['name'] );
		$result_sync = PROD::sync_product_item( $this->settings, $item, $this->connapi_erp );
		$this->assertEquals( 'ok', $result_sync['status'] );
		$this->assertNotNull( $result_sync['post_id'] );
		$this->assertNotEmpty( 'Product without name', get_the_title( $result_sync['post_id'] ) );

		// Without images.
		$item = $original_item;
		$item['images'] = '';
		$result_sync = PROD::sync_product_item( $this->settings, $item, $this->connapi_erp );
		$this->assertEquals( 'ok', $result_sync['status'] );
		$this->assertNotNull( $result_sync['post_id'] );

		// Blank product.
		$item = [];
		$result_sync = PROD::sync_product_item( $this->settings, $item, $this->connapi_erp );
		$this->assertEquals( 'error', $result_sync['status'] );
		$this->assertEquals( 0, $result_sync['post_id'] );
	}

	public function test_create_product_variable_without_parent_sku() {
		$item_path = UNIT_TESTS_DATA_PLUGIN_DIR . 'product-variable-without-parent-sku.json';
		$item      = file_get_contents( $item_path );
		$item      = json_decode( $item, true )[0];

		$result_sync = PROD::sync_product_item( $this->settings, $item, $this->connapi_erp );
		$result_prod_id = $result_sync['post_id'];
		$this->assertEquals( 'ok', $result_sync['status'] );
		$this->assertNotNull( $result_prod_id );

		$product = wc_get_product( $result_prod_id );
		$this->assertInstanceOf( 'WC_Product_Variable', $product );
		$this->assertEmpty( $product->get_sku() );
		$this->assertEquals( $item['name'], $product->get_name() );
		$this->assertEquals( $item['desc'], $product->get_description() );

		// Variable product asserts.
		$variations = $product->get_children();
		$this->assertNotEmpty( $variations );
		$index = 0;
		foreach ( $variations as $variation_id ) {
			$prod_variation = new WC_Product_Variation( $variation_id );
			$this->assertEquals( $item['variants'][$index]['price'], (float) $prod_variation->get_regular_price() );
			$this->assertEquals( $item['variants'][$index]['sku'], $prod_variation->get_sku() );
			$this->assertEquals( $item['variants'][$index]['barcode'], $prod_variation->get_global_unique_id() );
			$index++;
		}

		// Check that update gets the correct product without sku in parent.
		$update_post = [
			'ID'           => $result_prod_id,
			'post_title'   => 'Updated Product Title',
			'post_content' => 'Updated product description.',
		];
		wp_update_post( $update_post );
		$result_sync_upd = PROD::sync_product_item( $this->settings, $item, $this->connapi_erp, false, $result_prod_id );

		$this->assertNotNull( $result_sync_upd );
		$this->assertEquals( 'ok', $result_sync_upd['status'] );
		$this->assertIsInt( $result_sync_upd['post_id'] );
		$this->assertEquals( $result_prod_id, $result_sync_upd['post_id'] );
	}
}

<?php
/**
 * Class CreateProductVariableTest
 *
 * Command: composer test-debug -- --filter=CreateProductVariableTest
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
			// Stock should NOT be imported when stock setting is 'no'.
			$this->assertFalse( $prod_variation->get_manage_stock(), 'Stock management should be disabled when stock import is disabled' );
			$this->assertEquals( $item['variants'][$index]['barcode'], $prod_variation->get_global_unique_id() );

			// Assert tax class is set to 'parent' for new variations.
			$this->assertEquals( 'parent', $prod_variation->get_tax_class( 'edit' ), 'Variation should have "parent" tax class when created' );

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
		
		// Without Parent SKU.
		unset( $item['sku'] );
		$result_sync    = PROD::sync_product_item( $this->settings, $item, $this->connapi_erp );
		$this->assertEquals( 'ok', $result_sync['status'] );
		$this->assertNotNull( $result_sync['post_id'] );

		// Without Variants SKU.
		$item = $original_item;
		unset( $item['variants'][0]['sku'] );
		$result_sync    = PROD::sync_product_item( $this->settings, $item, $this->connapi_erp );
		$this->assertEquals( 'ok', $result_sync['status'] );
		$this->assertNotNull( $result_sync['post_id'] );

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

			// Assert tax class is set to 'parent' for new variations.
			$this->assertEquals( 'parent', $prod_variation->get_tax_class( 'edit' ), 'Variation should have "parent" tax class when created' );

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

	/**
	 * Test that stock is NOT imported for variable products when stock setting is 'no'.
	 */
	public function test_variable_product_stock_not_imported_when_disabled() {
		$item_path = UNIT_TESTS_DATA_PLUGIN_DIR . 'product-variable.json';
		$item      = file_get_contents( $item_path );
		$item      = json_decode( $item, true )[0];

		// Ensure stock import is disabled.
		$this->settings['stock'] = 'no';
		$this->settings['catattr'] = 'sandalias';
		$this->settings['catnp']   = 'yes';

		// Test images.
		$image_path = UNIT_TESTS_DATA_PLUGIN_DIR . 'dummy-image.png';
		$image_dummy = [
			'url' => $image_path,
			'file' => $image_path,
			'content_type' => 'image/png',
		];
		$item['images'] = [ $image_dummy ];
		$item['variants'][0]['image'] = $image_dummy;
		$item['variants'][1]['image'] = $image_dummy;

		// Set stock values in variants to test they are not imported.
		$item['variants'][0]['stock'] = 10;
		$item['variants'][1]['stock'] = 5;

		// Sync product.
		$result_sync    = PROD::sync_product_item( $this->settings, $item, $this->connapi_erp );
		$result_prod_id = $result_sync['post_id'];

		$this->assertEquals( 'ok', $result_sync['status'] );
		$this->assertIsInt( $result_prod_id );

		$product = wc_get_product( $result_prod_id );
		$this->assertInstanceOf( 'WC_Product_Variable', $product );

		// Check all variations.
		$variations = $product->get_children();
		$this->assertNotEmpty( $variations );

		foreach ( $variations as $variation_id ) {
			$prod_variation = new WC_Product_Variation( $variation_id );
			// Stock management should be disabled.
			$this->assertFalse( $prod_variation->get_manage_stock(), 'Stock management should be disabled when stock import is disabled' );
			// Stock quantity should be null or 0 when not managing stock.
			$this->assertNull( $prod_variation->get_stock_quantity(), 'Stock quantity should be null when stock import is disabled' );

			// Assert tax class is set to 'parent' for new variations.
			$this->assertEquals( 'parent', $prod_variation->get_tax_class( 'edit' ), 'Variation should have "parent" tax class when created' );
		}

		wp_delete_post( $result_prod_id, true ); // Clean up after test
	}

	/**
	 * Test that stock IS imported for variable products when stock setting is 'yes'.
	 */
	public function test_variable_product_stock_imported_when_enabled() {
		$item_path = UNIT_TESTS_DATA_PLUGIN_DIR . 'product-variable.json';
		$item      = file_get_contents( $item_path );
		$item      = json_decode( $item, true )[0];

		// Enable stock import.
		$this->settings['stock'] = 'yes';
		$this->settings['catattr'] = 'sandalias';
		$this->settings['catnp']   = 'yes';

		// Test images.
		$image_path = UNIT_TESTS_DATA_PLUGIN_DIR . 'dummy-image.png';
		$image_dummy = [
			'url' => $image_path,
			'file' => $image_path,
			'content_type' => 'image/png',
		];
		$item['images'] = [ $image_dummy ];
		$item['variants'][0]['image'] = $image_dummy;
		$item['variants'][1]['image'] = $image_dummy;

		// Set stock values in variants to test they are imported.
		$item['variants'][0]['stock'] = 10;
		$item['variants'][1]['stock'] = 5;

		// Sync product.
		$result_sync    = PROD::sync_product_item( $this->settings, $item, $this->connapi_erp );
		$result_prod_id = $result_sync['post_id'];

		$this->assertEquals( 'ok', $result_sync['status'] );
		$this->assertIsInt( $result_prod_id );

		$product = wc_get_product( $result_prod_id );
		$this->assertInstanceOf( 'WC_Product_Variable', $product );

		// Check all variations.
		$variations = $product->get_children();
		$this->assertNotEmpty( $variations );

		$index = 0;
		foreach ( $variations as $variation_id ) {
			$prod_variation = new WC_Product_Variation( $variation_id );
			// Stock management should be enabled.
			$this->assertTrue( $prod_variation->get_manage_stock(), 'Stock management should be enabled when stock import is enabled' );
			// Stock quantity should match the imported value.
			$this->assertEquals( $item['variants'][$index]['stock'], $prod_variation->get_stock_quantity(), 'Stock quantity should match imported value' );
			// Stock status should be correct.
			$expected_status = 0 === (int) $item['variants'][$index]['stock'] ? 'outofstock' : 'instock';
			$this->assertEquals( $expected_status, $prod_variation->get_stock_status(), 'Stock status should match stock quantity' );

			// Assert tax class is set to 'parent' for new variations.
			$this->assertEquals( 'parent', $prod_variation->get_tax_class( 'edit' ), 'Variation should have "parent" tax class when created' );

			$index++;
		}

		wp_delete_post( $result_prod_id, true ); // Clean up after test
	}

	/**
	 * Test that stock import setting is respected when updating existing variable products.
	 */
	public function test_variable_product_stock_respected_on_update() {
		$item_path = UNIT_TESTS_DATA_PLUGIN_DIR . 'product-variable.json';
		$item      = file_get_contents( $item_path );
		$item      = json_decode( $item, true )[0];

		$this->settings['catattr'] = 'sandalias';
		$this->settings['catnp']   = 'yes';

		// Test images.
		$image_path = UNIT_TESTS_DATA_PLUGIN_DIR . 'dummy-image.png';
		$image_dummy = [
			'url' => $image_path,
			'file' => $image_path,
			'content_type' => 'image/png',
		];
		$item['images'] = [ $image_dummy ];
		$item['variants'][0]['image'] = $image_dummy;
		$item['variants'][1]['image'] = $image_dummy;

		// First, create product with stock import enabled.
		$this->settings['stock'] = 'yes';
		$item['variants'][0]['stock'] = 10;
		$item['variants'][1]['stock'] = 5;

		$result_sync    = PROD::sync_product_item( $this->settings, $item, $this->connapi_erp );
		$result_prod_id = $result_sync['post_id'];

		// Verify stock was imported.
		$product = wc_get_product( $result_prod_id );
		$variations = $product->get_children();
		$first_variation = new WC_Product_Variation( $variations[0] );
		$this->assertTrue( $first_variation->get_manage_stock() );
		$this->assertEquals( 10, $first_variation->get_stock_quantity() );

		// Now update with stock import disabled.
		$this->settings['stock'] = 'no';
		$item['variants'][0]['stock'] = 20; // Changed value that should NOT be imported.
		$item['variants'][1]['stock'] = 15; // Changed value that should NOT be imported.

		$result_sync_upd = PROD::sync_product_item( $this->settings, $item, $this->connapi_erp, false, $result_prod_id );

		$this->assertEquals( 'ok', $result_sync_upd['status'] );

		// Verify stock management is now disabled and values were not updated.
		$product_updated = wc_get_product( $result_prod_id );

		$manage_stock_parent = $product_updated->get_manage_stock();
		$this->assertNotTrue( $manage_stock_parent, 'Stock management should be disabled after update when stock import is disabled' );

		$variations_updated = $product_updated->get_children();
		foreach ( $variations_updated as $variation_id ) {
			$prod_variation   = new WC_Product_Variation( $variation_id );
			$manage_stock_var = $prod_variation->get_manage_stock();

			$this->assertNotTrue( $manage_stock_var, 'Stock management should be disabled after update when stock import is disabled' );
		}

		wp_delete_post( $result_prod_id, true ); // Clean up after test
	}
}

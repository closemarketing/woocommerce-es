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

		// Variable parent has no regular_price — price is derived from variations.
		$this->assertEmpty( get_post_meta( $result_sync_upd['post_id'], '_regular_price', true ) );

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
	 * Test that importing a variable product never enables stock management on the
	 * parent product, even when stock import is enabled — stock is tracked on the
	 * variations only. A parent with manage_stock enabled and no stock quantity of
	 * its own shows as "out of stock" regardless of variation availability.
	 *
	 * @see https://github.com/closemarketing/woocommerce-es/issues/204
	 */
	public function test_variable_product_parent_never_manages_stock() {
		$item_path = UNIT_TESTS_DATA_PLUGIN_DIR . 'product-variable.json';
		$item      = file_get_contents( $item_path );
		$item      = json_decode( $item, true )[0];

		// Enable stock import — this is exactly the setting that previously
		// leaked manage_stock=true onto the parent.
		$this->settings['stock']   = 'yes';
		$this->settings['catattr'] = 'sandalias';
		$this->settings['catnp']   = 'yes';

		$image_path  = UNIT_TESTS_DATA_PLUGIN_DIR . 'dummy-image.png';
		$image_dummy = [
			'url'          => $image_path,
			'file'         => $image_path,
			'content_type' => 'image/png',
		];
		$item['images']                = [ $image_dummy ];
		$item['variants'][0]['image']  = $image_dummy;
		$item['variants'][1]['image']  = $image_dummy;

		// Both variants have stock, so the parent must be sellable.
		$item['variants'][0]['stock'] = 10;
		$item['variants'][1]['stock'] = 5;

		$result_sync    = PROD::sync_product_item( $this->settings, $item, $this->connapi_erp );
		$result_prod_id = $result_sync['post_id'];

		$this->assertEquals( 'ok', $result_sync['status'] );
		$this->assertIsInt( $result_prod_id );

		$product = wc_get_product( $result_prod_id );
		$this->assertInstanceOf( 'WC_Product_Variable', $product );

		$this->assertFalse( $product->get_manage_stock(), 'Parent product must never manage stock — stock is tracked on variations.' );
		$this->assertEquals( 'instock', $product->get_stock_status(), 'Parent must show in stock when its variations have stock.' );

		// Variations must still have stock management enabled, since that's
		// where stock is actually tracked.
		$variations = $product->get_children();
		$this->assertNotEmpty( $variations );
		foreach ( $variations as $variation_id ) {
			$prod_variation = new WC_Product_Variation( $variation_id );
			$this->assertTrue( $prod_variation->get_manage_stock(), 'Variations must manage their own stock.' );
		}

		wp_delete_post( $result_prod_id, true ); // Clean up after test
	}

	/**
	 * Test that resyncing a variable product created before the fix (parent still
	 * has manage_stock enabled from an old import) corrects the parent on the next
	 * sync, instead of only preventing the issue on brand-new products.
	 *
	 * @see https://github.com/closemarketing/woocommerce-es/issues/204
	 */
	public function test_variable_product_parent_stock_management_corrected_on_resync() {
		$item_path = UNIT_TESTS_DATA_PLUGIN_DIR . 'product-variable.json';
		$item      = file_get_contents( $item_path );
		$item      = json_decode( $item, true )[0];

		$this->settings['catattr'] = 'sandalias';
		$this->settings['catnp']   = 'yes';

		$image_path  = UNIT_TESTS_DATA_PLUGIN_DIR . 'dummy-image.png';
		$image_dummy = [
			'url'          => $image_path,
			'file'         => $image_path,
			'content_type' => 'image/png',
		];
		$item['images']               = [ $image_dummy ];
		$item['variants'][0]['image'] = $image_dummy;
		$item['variants'][1]['image'] = $image_dummy;
		$item['variants'][0]['stock'] = 10;
		$item['variants'][1]['stock'] = 5;

		$this->settings['stock'] = 'yes';
		$result_sync    = PROD::sync_product_item( $this->settings, $item, $this->connapi_erp );
		$result_prod_id = $result_sync['post_id'];

		// Simulate a product imported before this fix: force manage_stock back on
		// at the parent, bypassing PROD, the way the old buggy code used to leave it.
		$product = wc_get_product( $result_prod_id );
		$product->set_manage_stock( true );
		$product->set_stock_status( 'outofstock' );
		$product->save();

		$this->assertTrue( wc_get_product( $result_prod_id )->get_manage_stock(), 'Setup: parent must start with manage_stock enabled for this test to be meaningful.' );

		// Resync — must correct the parent even though it already existed.
		$result_resync = PROD::sync_product_item( $this->settings, $item, $this->connapi_erp, false, $result_prod_id );
		$this->assertEquals( 'ok', $result_resync['status'] );

		$product_after = wc_get_product( $result_prod_id );
		$this->assertFalse( $product_after->get_manage_stock(), 'Resync must disable manage_stock on the parent even if it was already enabled.' );
		$this->assertEquals( 'instock', $product_after->get_stock_status(), 'Resync must recompute stock status from variations, not leave the stale "out of stock" value.' );

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

	/**
	 * Test that tax class 'parent' is preserved when product is synced again.
	 *
	 * This test ensures that when a variable product is re-synced (updated),
	 * the variation tax classes remain set to 'parent', preventing tax calculation
	 * inconsistencies that occur when variations don't inherit the parent tax class.
	 *
	 * @see https://github.com/closemarketing/woocommerce-es/issues/XXX
	 */
	public function test_variation_tax_class_preserved_on_resync() {
		$item_path = UNIT_TESTS_DATA_PLUGIN_DIR . 'product-variable.json';
		$item      = file_get_contents( $item_path );
		$item      = json_decode( $item, true )[0];

		$this->settings['catattr'] = 'sandalias';
		$this->settings['catnp']   = 'yes';

		// Setup test images.
		$image_path  = UNIT_TESTS_DATA_PLUGIN_DIR . 'dummy-image.png';
		$image_dummy = [
			'url'          => $image_path,
			'file'         => $image_path,
			'content_type' => 'image/png',
		];
		$item['images']             = [ $image_dummy ];
		$item['variants'][0]['image'] = $image_dummy;
		$item['variants'][1]['image'] = $image_dummy;

		// First sync - create the product.
		$result_sync    = PROD::sync_product_item( $this->settings, $item, $this->connapi_erp );
		$result_prod_id = $result_sync['post_id'];

		$this->assertEquals( 'ok', $result_sync['status'] );
		$this->assertIsInt( $result_prod_id );

		// Verify initial tax class is 'parent' for all variations.
		$product = wc_get_product( $result_prod_id );
		$this->assertInstanceOf( 'WC_Product_Variable', $product );

		$variations          = $product->get_children();
		$initial_tax_classes = [];
		
		$this->assertNotEmpty( $variations, 'Variable product should have variations' );

		foreach ( $variations as $variation_id ) {
			$prod_variation = new WC_Product_Variation( $variation_id );
			$tax_class      = $prod_variation->get_tax_class( 'edit' );
			
			$this->assertEquals( 'parent', $tax_class, 'Initial tax class should be "parent" for variation #' . $variation_id );
			$initial_tax_classes[ $variation_id ] = $tax_class;
		}

		// Second sync - update the same product with different data.
		$item['name']                 = 'Updated Product Name';
		$item['desc']                 = 'Updated product description';
		$item['price']                = 99.99;
		$item['variants'][0]['price'] = 44.99;
		$item['variants'][1]['price'] = 54.99;

		$result_sync_upd = PROD::sync_product_item( $this->settings, $item, $this->connapi_erp, false, $result_prod_id );

		$this->assertEquals( 'ok', $result_sync_upd['status'], 'Product resync should succeed' );
		$this->assertEquals( $result_prod_id, $result_sync_upd['post_id'], 'Product ID should remain the same after resync' );

		// Verify tax class is STILL 'parent' after update.
		$product_updated    = wc_get_product( $result_prod_id );
		$variations_updated = $product_updated->get_children();

		$this->assertNotEmpty( $variations_updated, 'Variable product should still have variations after resync' );
		$this->assertCount( count( $variations ), $variations_updated, 'Number of variations should remain the same' );

		foreach ( $variations_updated as $variation_id ) {
			$prod_variation = new WC_Product_Variation( $variation_id );
			$tax_class      = $prod_variation->get_tax_class( 'edit' );
			
			$this->assertEquals(
				'parent',
				$tax_class,
				'Tax class should remain "parent" after product resync for variation #' . $variation_id
			);
			
			$this->assertEquals(
				$initial_tax_classes[ $variation_id ],
				$tax_class,
				'Tax class should not have changed from initial value for variation #' . $variation_id
			);
		}

		wp_delete_post( $result_prod_id, true );
	}

	/**

	 * When the ERP serializes a numeric SKU as a JSON number, json_decode() gives
	 * $variant['sku'] as an int, while WC_Product_Variation::get_sku() always
	 * returns a string. The strict array_search() used to match existing
	 * variations must not miss this case, or the sync treats an existing
	 * variation as new (and duplicate-SKU logic can then reject it).
	 */
	public function test_variation_matched_when_erp_sku_is_numeric() {
		$item_path = UNIT_TESTS_DATA_PLUGIN_DIR . 'product-variable.json';
		$item      = file_get_contents( $item_path );
		$item      = json_decode( $item, true )[0];

		$this->settings['catattr'] = 'sandalias';
		$this->settings['catnp']   = 'yes';

		// Numeric SKUs, as an ERP that serializes them as JSON numbers would.
		$item['variants'][0]['sku'] = 1001;
		$item['variants'][1]['sku'] = 1002;

		$result_sync    = PROD::sync_product_item( $this->settings, $item, $this->connapi_erp );
		$result_prod_id = $result_sync['post_id'];
		$this->assertEquals( 'ok', $result_sync['status'] );

		$product    = wc_get_product( $result_prod_id );
		$variations = $product->get_children();
		$this->assertCount( 2, $variations, 'Expected two variations after first sync.' );

		// Resync with the same (numeric) SKUs — must match the existing
		// variations rather than creating duplicates or new ones.
		$result_resync = PROD::sync_product_item( $this->settings, $item, $this->connapi_erp, false, $result_prod_id );
		$this->assertEquals( 'ok', $result_resync['status'] );

		$product_after    = wc_get_product( $result_prod_id );
		$variations_after = $product_after->get_children();
		$this->assertCount( 2, $variations_after, 'Resync with numeric SKUs must not create duplicate variations.' );
		$this->assertEqualsCanonicalizing( $variations, $variations_after, 'Resync with numeric SKUs must match the same existing variations, not create new ones.' );

		wp_delete_post( $result_prod_id, true );
	}

	/**
	 * A new ERP variant whose SKU already belongs to a variation of a DIFFERENT
	 * product must be rejected gracefully ("Duplicated SKU" message), not cause
	 * WooCommerce's own duplicate-SKU exception to abort the whole sync — the
	 * duplicate check must also search product_variation posts, not just
	 * top-level products.
	 */
	public function test_duplicate_sku_against_another_products_variation_is_handled_gracefully() {
		$item_path = UNIT_TESTS_DATA_PLUGIN_DIR . 'product-variable.json';
		$item      = file_get_contents( $item_path );
		$item      = json_decode( $item, true )[0];

		$this->settings['catattr'] = 'sandalias';
		$this->settings['catnp']   = 'yes';

		// Create a first variable product normally.
		$result_sync      = PROD::sync_product_item( $this->settings, $item, $this->connapi_erp );
		$first_product_id = $result_sync['post_id'];
		$this->assertEquals( 'ok', $result_sync['status'] );

		$taken_sku = $item['variants'][0]['sku'];

		// A second, already-existing variable product (created directly with its
		// own parent SKU, bypassing the ERP-variant-SKU parent-discovery in
		// sync_product_item(), which is not what this test is about) whose first
		// variant reuses a SKU that already belongs to a variation of the first
		// product.
		$second_parent = new WC_Product_Variable();
		$second_parent->set_name( 'Second Shoes' );
		$second_parent->set_sku( 'second-parent-sku' );
		$second_parent->set_status( 'publish' );
		$second_parent->save();
		$second_product_id = $second_parent->get_id();

		$second_item                        = $item;
		$second_item['sku']                 = 'second-parent-sku';
		$second_item['variants'][0]['sku']  = $taken_sku;
		$second_item['variants'][1]['sku']  = 'second-parent-variant-2';

		$result_second = PROD::sync_product( $this->settings, $second_item, $this->connapi_erp, $second_product_id, 'variable', null );

		// Must complete gracefully — not "error" from an uncaught WooCommerce
		// duplicate-SKU exception.
		$this->assertEquals( 'ok', $result_second['status'], 'Sync must not abort when a variant SKU collides with another product\'s variation.' );
		$this->assertStringContainsString( 'Duplicated SKU', $result_second['message'], 'Result message must report the duplicated SKU.' );

		$second_product    = wc_get_product( $second_product_id );
		$second_variations = $second_product->get_children();

		// Only the non-colliding variant should have been created.
		$this->assertCount( 1, $second_variations, 'Only the variant with a unique SKU should be created.' );

		wp_delete_post( $first_product_id, true );
		wp_delete_post( $second_product_id, true );
	}

	/**
	 * Regression test: blank SKU when the ERP renames an existing variant's SKU on resync.
	 *
	 * Reproduces a production issue reported for an Odoo-synced variable product:
	 * a variant was originally synced with one SKU, then the ERP corrected that
	 * SKU on a later sync (same ERP variant id, same attributes, no change to
	 * the underlying variant identity).
	 *
	 * woocommerce-es matches an incoming variant to an existing WC variation by
	 * SKU equality (`array_search()` against the current variations' SKUs), not
	 * by the ERP's variant id. When the SKU changes, the match fails, so the
	 * variant is treated as brand new: a fresh WC_Product_Variation is created
	 * for it, while the untouched old variation is left over and gets set to
	 * draft. This test asserts the new variation gets a non-blank SKU (fixed by
	 * gating set_sku() on the variation's own empty SKU rather than on the
	 * parent-level $is_new_product flag) and documents the still-present
	 * orphan/draft side effect as a known follow-up (matching should key off
	 * the ERP's variant id, not SKU).
	 */
	public function test_variant_sku_renamed_on_resync_gets_new_sku() {
		$item_path = UNIT_TESTS_DATA_PLUGIN_DIR . 'product-variable-new-variant-resync.json';
		$item      = file_get_contents( $item_path );
		$item      = json_decode( $item, true )[0];

		$this->settings['catattr'] = 'categoria';
		$this->settings['catnp']   = 'yes';

		// First sync: variant 9001 comes in with SKU "GENBRA-85D-CH".
		$result_sync    = PROD::sync_product_item( $this->settings, $item, $this->connapi_erp );
		$result_prod_id = $result_sync['post_id'];

		$this->assertEquals( 'ok', $result_sync['status'] );
		$this->assertIsInt( $result_prod_id );

		$product    = wc_get_product( $result_prod_id );
		$variations = $product->get_children();
		$this->assertCount( 2, $variations, 'Should have 2 variations after first sync' );

		$old_variation_id = null;
		foreach ( $variations as $variation_id ) {
			$prod_variation = new WC_Product_Variation( $variation_id );
			if ( 'GENBRA-85D-CH' === $prod_variation->get_sku() ) {
				$old_variation_id = $variation_id;
			}
		}
		$this->assertNotNull( $old_variation_id, 'First sync should create a variation with SKU GENBRA-85D-CH' );

		// Second sync (resync of the SAME product): ERP renamed variant 9001's
		// SKU from "GENBRA-85D-CH" to "GENBRA-75AA-CH" — same ERP variant id, same attributes.
		$item_resync                        = $item;
		$item_resync['variants'][0]['sku']  = 'GENBRA-75AA-CH';

		$result_sync_upd = PROD::sync_product_item( $this->settings, $item_resync, $this->connapi_erp, false, $result_prod_id );

		$this->assertEquals( 'ok', $result_sync_upd['status'] );
		$this->assertEquals( $result_prod_id, $result_sync_upd['post_id'] );

		$product_updated    = wc_get_product( $result_prod_id );
		$variations_updated = $product_updated->get_children();

		// Find the variation carrying the renamed variant's barcode (unique across the payload).
		$renamed_variation      = null;
		foreach ( $variations_updated as $variation_id ) {
			$prod_variation = new WC_Product_Variation( $variation_id );
			if ( '0000000000001' === $prod_variation->get_global_unique_id() ) {
				$renamed_variation = $prod_variation;
			}
		}

		$this->assertNotNull( $renamed_variation, 'A variation for the renamed variant (barcode 0000000000001) should exist after resync' );
		$this->assertEquals(
			'GENBRA-75AA-CH',
			$renamed_variation->get_sku(),
			'Variation for a variant whose SKU was renamed in the ERP must carry the new SKU, not be left blank'
		);

		// The stale old variation (SKU GENBRA-85D-CH) should not remain published with its old SKU
		// still active — WooCommerce would otherwise report it in stock listings using a SKU
		// that no longer represents the current ERP state.
		$old_variation_status = get_post_status( $old_variation_id );
		if ( $renamed_variation->get_id() !== $old_variation_id ) {
			$this->assertEquals( 'draft', $old_variation_status, 'Orphaned old variation should be set to draft when superseded by a renamed-SKU variant' );
		}

		wp_delete_post( $result_prod_id, true );
	}

}

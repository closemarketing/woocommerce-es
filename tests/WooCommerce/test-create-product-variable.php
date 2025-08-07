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
		
		$result_sync    = PROD::sync_product_item( $this->settings, $item, $this->connapi_erp );
		$result_prod_id = $result_sync['post_id'];
		
		$this->assertNotNull( $result_sync );
		$this->assertEquals( 'ok', $result_sync['status'] );
		$this->assertIsInt( $result_prod_id );
		
		$product = wc_get_product( $result_prod_id );
		$this->assertInstanceOf( 'WC_Product_Variable', $product );
		$this->assertEquals( $item['sku'], $product->get_sku() ); // Only SKU, barcode not needed for variable products.
		$this->assertEquals( $item['name'], $product->get_name() );
		$this->assertEquals( $item['desc'], $product->get_description() );
		$this->assertEquals( true, in_array( 'Calzado', wp_get_post_terms( $result_prod_id, 'product_cat', [ 'fields' => 'names' ] ) ) );
		//$this->assertEquals( $item['tags'], wp_get_post_terms( $result_prod_id, 'product_tag', [ 'fields' => 'names' ] ) );

		// Variable product asserts.
		$variations = $product->get_children();
		$this->assertNotEmpty( $variations );
		$index = 0;
		foreach ( $variations as $variation_id ) {
			$prod_variation = new WC_Product_Variation( $variation_id );
			$this->assertEquals( $item['variations'][$index]['price'], $prod_variation->get_regular_price() );
			$this->assertEquals( $item['variations'][$index]['attributes'], $prod_variation->get_attributes() );
			$this->assertEquals( $item['variations'][$index]['sale_price'], $prod_variation->get_sale_price() );
			$this->assertEquals( $item['variations'][$index]['stock_quantity'], $prod_variation->get_stock_quantity() );
			$this->assertEquals( $item['variations'][$index]['manage_stock'], $prod_variation->get_manage_stock() );
			$this->assertEquals( $item['variations'][$index]['backorders'], $prod_variation->get_backorders() );
			$this->assertEquals( $item['variations'][$index]['tax_status'], $prod_variation->get_tax_status() );
			$this->assertEquals( $item['variations'][$index]['tax_class'], $prod_variation->get_tax_class() );
			$this->assertEquals( $item['variations'][$index]['weight'], $prod_variation->get_weight() );
			$this->assertEquals( $item['variations'][$index]['length'], $prod_variation->get_length() );
			$this->assertEquals( $item['variations'][$index]['width'], $prod_variation->get_width() );
			$this->assertEquals( $item['variations'][$index]['height'], $prod_variation->get_height() );
			$this->assertEquals( $item['variations'][$index]['shipping_class'], $prod_variation->get_shipping_class() );
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
}

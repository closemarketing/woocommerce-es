<?php
/**
 * Class CreateProductPackTest
 *
 * Command: composer test -- --filter=CreateProductPackTest
 *
 * @package Connect_Ecommerce
 */

use CLOSE\ConnectEcommerce\Helpers\PROD;

/**
 * Create Product Pack (WPC Product Bundles) without Errors.
 *
 * @group woocommerce
 */
class CreateProductPackTest extends WP_UnitTestCase {

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

		$this->assertTrue( class_exists( 'WooCommerce' ), 'WooCommerce is not active' );

		$this->settings = array(
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
		);

		$options           = conecom_get_options();
		$this->connapi_erp = new Connect_Ecommerce_Pack_Test_Connector( $options );

		// PROD::sync_product_item() only creates a pack when WPC Product Bundles
		// is reported as active — simulate that without requiring the real plugin
		// to be installed in the test environment.
		add_filter( 'active_plugins', array( $this, 'activate_wpc_product_bundle' ) );
	}

	/**
	 * Undo the simulated active_plugins filter.
	 */
	public function tearDown(): void {
		remove_filter( 'active_plugins', array( $this, 'activate_wpc_product_bundle' ) );
		parent::tearDown();
	}

	/**
	 * Reports WPC Product Bundles for WooCommerce as active.
	 *
	 * @param array $plugins Active plugins list.
	 * @return array
	 */
	public function activate_wpc_product_bundle( $plugins ) {
		$plugins[] = 'woo-product-bundle/wpc-product-bundles.php';
		return $plugins;
	}

	/**
	 * Create Product Pack without Errors.
	 */
	public function test_create_product_pack_without_errors() {
		$item_path = UNIT_TESTS_DATA_PLUGIN_DIR . 'product-pack.json';
		$item      = json_decode( file_get_contents( $item_path ), true )[0];

		$result_sync    = PROD::sync_product_item( $this->settings, $item, $this->connapi_erp );
		$result_prod_id = $result_sync['post_id'];

		$this->assertNotNull( $result_sync );
		$this->assertEquals( 'ok', $result_sync['status'] );
		$this->assertIsInt( $result_prod_id );

		$product_type_terms = wp_get_post_terms( $result_prod_id, 'product_type', array( 'fields' => 'names' ) );
		$this->assertContains( 'woosb', $product_type_terms );
		$this->assertEquals( $item['sku'], get_post_meta( $result_prod_id, '_sku', true ) );

		wp_delete_post( $result_prod_id, true );
	}

	/**
	 * The bundled item referenced via packItems must be created and its real
	 * WooCommerce post ID (not the sync_product_simple() result array) must end
	 * up in woosb_ids, otherwise WPC Product Bundles can never resolve it.
	 */
	public function test_pack_items_reference_real_bundled_product_ids() {
		$item_path = UNIT_TESTS_DATA_PLUGIN_DIR . 'product-pack.json';
		$item      = json_decode( file_get_contents( $item_path ), true )[0];

		$result_sync    = PROD::sync_product_item( $this->settings, $item, $this->connapi_erp );
		$result_prod_id = $result_sync['post_id'];

		$woosb_ids = get_post_meta( $result_prod_id, 'woosb_ids', true );
		$this->assertNotEmpty( $woosb_ids );
		$this->assertStringNotContainsString( 'Array', $woosb_ids );

		$bundled_item_id = wc_get_product_id_by_sku( 'PACK-ITEM-001' );
		$this->assertNotEmpty( $bundled_item_id, 'Bundled sub-product was not created from packItems' );

		list( $stored_id, $stored_qty ) = explode( '/', $woosb_ids );
		$this->assertEquals( $bundled_item_id, (int) $stored_id );
		$this->assertEquals( 3, (int) $stored_qty );

		wp_delete_post( $result_prod_id, true );
		wp_delete_post( $bundled_item_id, true );
	}

	/**
	 * Holded packs always report price 0 (the ERP has no pack price concept),
	 * so the synced product must fall back to the sum of its bundled items'
	 * prices instead of being left at 0 (which WPC Product Bundles treats as
	 * "no price" and never overrides via its own auto-calculation).
	 */
	public function test_pack_price_falls_back_to_bundled_items_sum() {
		$item_path = UNIT_TESTS_DATA_PLUGIN_DIR . 'product-pack.json';
		$item      = json_decode( file_get_contents( $item_path ), true )[0];

		$this->assertEquals( 0, $item['price'], 'Fixture must reproduce the real ERP shape: pack price is 0' );

		$result_sync    = PROD::sync_product_item( $this->settings, $item, $this->connapi_erp );
		$result_prod_id = $result_sync['post_id'];

		// Bundled item price (5) x qty (3) = 15.
		$this->assertEquals( '15', get_post_meta( $result_prod_id, '_regular_price', true ) );
		$this->assertEquals( '15', get_post_meta( $result_prod_id, '_price', true ) );

		$bundled_item_id = wc_get_product_id_by_sku( 'PACK-ITEM-001' );
		wp_delete_post( $result_prod_id, true );
		wp_delete_post( $bundled_item_id, true );
	}

	/**
	 * An explicit non-zero ERP pack price must be respected as-is, without
	 * being overridden by the bundled-items fallback sum.
	 */
	public function test_pack_price_respects_explicit_erp_price() {
		$item_path = UNIT_TESTS_DATA_PLUGIN_DIR . 'product-pack.json';
		$item      = json_decode( file_get_contents( $item_path ), true )[0];
		$item['price'] = 49.99;

		$result_sync    = PROD::sync_product_item( $this->settings, $item, $this->connapi_erp );
		$result_prod_id = $result_sync['post_id'];

		$this->assertEquals( '49.99', get_post_meta( $result_prod_id, '_regular_price', true ) );

		$bundled_item_id = wc_get_product_id_by_sku( 'PACK-ITEM-001' );
		wp_delete_post( $result_prod_id, true );
		wp_delete_post( $bundled_item_id, true );
	}

	/**
	 * Without WPC Product Bundles active, a pack item must not be imported and
	 * must warn the admin instead of silently creating a broken product.
	 */
	public function test_pack_without_plugin_active_is_not_imported() {
		remove_filter( 'active_plugins', array( $this, 'activate_wpc_product_bundle' ) );

		$item_path = UNIT_TESTS_DATA_PLUGIN_DIR . 'product-pack.json';
		$item      = json_decode( file_get_contents( $item_path ), true )[0];

		$result_sync = PROD::sync_product_item( $this->settings, $item, $this->connapi_erp );

		$this->assertStringContainsString( 'WPC Product Bundles for WooCommerce', $result_sync['message'] );
		$this->assertEmpty( wc_get_product_id_by_sku( $item['sku'] ) );

		add_filter( 'active_plugins', array( $this, 'activate_wpc_product_bundle' ) );
	}
}

/**
 * Minimal connector stub that resolves packItems[].pid to the local bundled
 * item fixture, mirroring Connect_Ecommerce_Clientify::get_products()'s
 * per-ID lookup contract used by PROD::sync_product_item().
 */
class Connect_Ecommerce_Pack_Test_Connector extends Connect_Ecommerce_Clientify {

	/**
	 * Returns the bundled item fixture for any requested pid.
	 *
	 * @param string $id Item pid from packItems.
	 * @param string $period Unused.
	 * @return array
	 */
	public function get_products( $id = null, $period = null ) {
		$item_path = UNIT_TESTS_DATA_PLUGIN_DIR . 'product-pack-item.json';
		return json_decode( file_get_contents( $item_path ), true );
	}
}

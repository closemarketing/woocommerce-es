<?php
/**
 * Class SubscriptionProductSyncTest
 *
 * Command: composer test -- --filter=SubscriptionProductSyncTest
 *
 * @package Connect_Ecommerce
 */

use CLOSE\ConnectEcommerce\Helpers\PROD;

/**
 * Minimal stub for WC_Product_Subscription (WooCommerce Subscriptions plugin).
 * Registers the 'subscription' product type so wc_get_product() returns it
 * without requiring the actual plugin to be active.
 */
class Mock_WC_Product_Subscription extends WC_Product_Simple {
	public function get_type() {
		return 'subscription';
	}
}

/**
 * Minimal stub for WC_Product_Variable_Subscription.
 *
 * WC_Product::__construct() resolves its data store via
 * WC_Data_Store::load( 'product-' . $this->get_type() ), so overriding
 * get_type() alone makes it request a 'product-variable-subscription' store.
 * That key isn't registered, so WC_Data_Store falls back to the bare
 * 'product' store (WC_Product_Data_Store_CPT), which has no read_children().
 * Register the key via the woocommerce_data_stores filter (setUp()) so it
 * resolves to the same store class as 'product-variable'.
 */
class Mock_WC_Product_Variable_Subscription extends WC_Product_Variable {
	public function get_type() {
		return 'variable-subscription';
	}
}

/**
 * Verifies that ERP product sync preserves subscription product types and
 * correctly syncs subscription prices.
 *
 * @group woocommerce
 */
class SubscriptionProductSyncTest extends WP_UnitTestCase {

	protected $settings;
	protected $connapi_erp;

	public function setUp(): void {
		parent::setUp();

		$this->assertTrue( class_exists( 'WooCommerce' ), 'WooCommerce is not active' );

		// Route subscription product types to the mock stubs so wc_get_product()
		// returns the correct class without WooCommerce Subscriptions installed.
		add_filter( 'woocommerce_product_class', array( $this, 'map_subscription_classes' ), 10, 2 );

		// Mirror what WooCommerce Subscriptions itself does: register the
		// 'product-variable-subscription' data store key so the mock's
		// get_children()/data store calls resolve like a real variable product.
		add_filter( 'woocommerce_data_stores', array( $this, 'map_subscription_data_stores' ) );

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
		$this->connapi_erp = new Connect_Ecommerce_Clientify( $options );
	}

	public function tearDown(): void {
		remove_filter( 'woocommerce_product_class', array( $this, 'map_subscription_classes' ) );
		remove_filter( 'woocommerce_data_stores', array( $this, 'map_subscription_data_stores' ) );
		parent::tearDown();
	}

	/**
	 * Filter callback: reuse the 'product-variable' data store for
	 * 'product-variable-subscription' so the mock behaves like a real
	 * variable product (get_children(), variation reads, etc.).
	 */
	public function map_subscription_data_stores( $stores ) {
		$stores['product-variable-subscription'] = $stores['product-variable'];
		return $stores;
	}

	/**
	 * Filter callback: map subscription type slugs to the mock stubs.
	 */
	public function map_subscription_classes( $classname, $product_type ) {
		if ( 'subscription' === $product_type ) {
			return 'Mock_WC_Product_Subscription';
		}
		if ( 'variable-subscription' === $product_type ) {
			return 'Mock_WC_Product_Variable_Subscription';
		}
		return $classname;
	}

	/**
	 * Create a product and force its product_type taxonomy term.
	 *
	 * @param string $type  WooCommerce product type slug.
	 * @param string $sku   SKU for the product.
	 * @param string $price Regular price.
	 * @return int Product ID.
	 */
	private function create_product_with_type( $type, $sku, $price = '10.00' ) {
		$product = new WC_Product_Simple();
		$product->set_name( 'Test ' . $type );
		$product->set_sku( $sku );
		$product->set_status( 'publish' );
		$product->set_regular_price( $price );
		$product->save();
		$product_id = $product->get_id();
		wp_set_object_terms( $product_id, $type, 'product_type' );
		// Flush all relevant caches so subsequent wc_get_product() reads fresh data.
		// WC_Product_Data_Store_CPT::get_product_type() caches the resolved type
		// under its own 'products' cache group key, which clean_post_cache() and
		// wc_delete_product_transients() do not touch — without this, wc_get_product()
		// keeps returning the pre-taxonomy-change type (e.g. 'simple').
		$type_cache_key = WC_Cache_Helper::get_cache_prefix( 'product_' . $product_id ) . '_type_' . $product_id;
		wp_cache_delete( $type_cache_key, 'products' );
		clean_post_cache( $product_id );
		wc_delete_product_transients( $product_id );
		return $product_id;
	}

	/**
	 * Build a minimal simple ERP item array.
	 *
	 * @param string $sku
	 * @param float  $price
	 * @return array
	 */
	private function simple_item( $sku, $price = 25.00 ) {
		return array(
			'id'         => 'erp-' . $sku,
			'kind'       => 'simple',
			'name'       => 'Product ' . $sku,
			'desc'       => '',
			'sku'        => $sku,
			'price'      => $price,
			'weight'     => 0,
			'taxes'      => array(),
			'stock'      => 0,
			'barcode'    => '',
			'rates'      => array(),
			'attributes' => array(),
		);
	}

	/**
	 * When ERP sends a simple item and the product already exists as a
	 * subscription, the product type must remain 'subscription' after sync.
	 */
	public function test_simple_erp_preserves_subscription_type() {
		$product_id = $this->create_product_with_type( 'subscription', 'sub-erp-sku-1' );

		PROD::sync_product( $this->settings, $this->simple_item( 'sub-erp-sku-1', 77.5 ), $this->connapi_erp, $product_id, 'simple', null );

		$synced = wc_get_product( $product_id );
		$this->assertEquals( 'subscription', $synced->get_type() );
		$this->assertEquals( '77.5', $synced->get_meta( '_subscription_price' ), '_subscription_price must be kept in sync with the ERP price.' );

		wp_delete_post( $product_id, true );
	}

	/**
	 * When ERP sends a variable item and the product already exists as a
	 * variable-subscription, the product type must remain 'variable-subscription'
	 * after sync.
	 */
	public function test_variable_erp_preserves_variable_subscription_type() {
		$product_id = $this->create_product_with_type( 'variable-subscription', 'varsub-erp-sku-1' );

		$item      = json_decode( file_get_contents( UNIT_TESTS_DATA_PLUGIN_DIR . 'product-variable.json' ), true )[0];
		$item['sku'] = 'varsub-erp-sku-1';

		PROD::sync_product( $this->settings, $item, $this->connapi_erp, $product_id, 'variable', null );

		$synced = wc_get_product( $product_id );
		$this->assertEquals( 'variable-subscription', $synced->get_type() );

		wp_delete_post( $product_id, true );
	}

	/**
	 * When ERP sends a variable item but the existing product is a simple
	 * subscription, the two shapes do not match and sync must complete without
	 * errors (no preservation, no crash).
	 */
	public function test_variable_erp_with_simple_subscription_does_not_error() {
		$product_id = $this->create_product_with_type( 'subscription', 'sub-mismatch-sku-1' );

		$item        = json_decode( file_get_contents( UNIT_TESTS_DATA_PLUGIN_DIR . 'product-variable.json' ), true )[0];
		$item['sku'] = 'sub-mismatch-sku-1';

		$result = PROD::sync_product( $this->settings, $item, $this->connapi_erp, $product_id, 'variable', null );

		$this->assertNotEquals( 'error', $result['status'] ?? '', 'Shape mismatch must not cause a sync error.' );

		wp_delete_post( $product_id, true );
	}

	/**
	 * When syncing variations under a variable-subscription parent, each
	 * variation must have _subscription_price set to the same value as its
	 * regular price from the ERP.
	 */
	public function test_variable_subscription_variants_get_subscription_price() {
		$product_id = $this->create_product_with_type( 'variable-subscription', 'varsub-price-sku-1' );

		$item        = json_decode( file_get_contents( UNIT_TESTS_DATA_PLUGIN_DIR . 'product-variable.json' ), true )[0];
		$item['sku'] = 'varsub-price-sku-1';

		PROD::sync_product( $this->settings, $item, $this->connapi_erp, $product_id, 'variable', null );

		$parent   = wc_get_product( $product_id );
		$children = $parent->get_children();
		$this->assertNotEmpty( $children, 'Variable-subscription must have variations after sync.' );

		foreach ( $children as $child_id ) {
			$variation = wc_get_product( $child_id );
			$this->assertEquals(
				$variation->get_regular_price(),
				$variation->get_meta( '_subscription_price' ),
				"Variation {$child_id}: _subscription_price must match regular_price."
			);
		}

		wp_delete_post( $product_id, true );
	}

	/**
	 * A new variant added to an existing variable-subscription must inherit
	 * the billing schedule from an existing sibling variation, not just the
	 * parent product — WC Subscriptions stores the schedule per-variation,
	 * so a parent with no such meta must not leave the new variant blank.
	 */
	public function test_new_variant_inherits_schedule_from_sibling_not_parent() {
		$product_id = $this->create_product_with_type( 'variable-subscription', 'varsub-schedule-sku-1' );

		$item          = json_decode( file_get_contents( UNIT_TESTS_DATA_PLUGIN_DIR . 'product-variable.json' ), true )[0];
		$item['sku']   = 'varsub-schedule-sku-1';
		$first_variant = $item['variants'][0];
		$item['variants'] = array( $first_variant );

		// First sync: only one variant exists yet, so it falls back to the
		// parent's (empty) schedule meta — matches a real first-time import.
		PROD::sync_product( $this->settings, $item, $this->connapi_erp, $product_id, 'variable', null );

		$parent           = wc_get_product( $product_id );
		$existing_children = $parent->get_children();
		$this->assertCount( 1, $existing_children, 'Expected exactly one variation after first sync.' );

		// Simulate the merchant setting a billing schedule directly on the
		// existing variation (the parent itself still has none).
		$existing_variation = wc_get_product( $existing_children[0] );
		$existing_variation->update_meta_data( '_subscription_period', 'month' );
		$existing_variation->update_meta_data( '_subscription_period_interval', '3' );
		$existing_variation->save();

		$this->assertEmpty( get_post_meta( $product_id, '_subscription_period', true ), 'Parent must have no schedule meta for this test to be meaningful.' );

		// Second sync: ERP now sends both variants, so the second one is new.
		$item_full          = json_decode( file_get_contents( UNIT_TESTS_DATA_PLUGIN_DIR . 'product-variable.json' ), true )[0];
		$item_full['sku']   = 'varsub-schedule-sku-1';
		PROD::sync_product( $this->settings, $item_full, $this->connapi_erp, $product_id, 'variable', null );

		$parent_after   = wc_get_product( $product_id );
		$children_after = $parent_after->get_children();
		$this->assertCount( 2, $children_after, 'Expected a second variation after second sync.' );

		$new_child_id = current( array_diff( $children_after, $existing_children ) );
		$new_variation = wc_get_product( $new_child_id );

		$this->assertEquals( 'month', $new_variation->get_meta( '_subscription_period' ), 'New variant must inherit schedule from sibling variation.' );
		$this->assertEquals( '3', $new_variation->get_meta( '_subscription_period_interval' ), 'New variant must inherit schedule from sibling variation.' );

		wp_delete_post( $product_id, true );
	}
}

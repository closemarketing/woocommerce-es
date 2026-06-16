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
 */
class Mock_WC_Product_Variable_Subscription extends WC_Product_Variable {
	protected $data_store_name = 'product-variable';

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
		parent::tearDown();
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
		wp_set_object_terms( $product->get_id(), $type, 'product_type' );
		return $product->get_id();
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

		PROD::sync_product( $this->settings, $this->simple_item( 'sub-erp-sku-1' ), $this->connapi_erp, $product_id, 'simple', null );

		$synced = wc_get_product( $product_id );
		$this->assertEquals( 'subscription', $synced->get_type() );

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
}

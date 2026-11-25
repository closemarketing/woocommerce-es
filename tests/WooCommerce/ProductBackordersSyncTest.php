<?php
/**
 * Class ProductBackordersSyncTest
 *
 * Command: composer test -- --filter=ProductBackordersSyncTest
 *
 * @package Connect_Ecommerce
 */

use CLOSE\ConnectEcommerce\Helpers\PROD;

/**
 * Backorders setting: yes / no overwrite the product, keep leaves it untouched.
 *
 * @group woocommerce
 */
class ProductBackordersSyncTest extends WP_UnitTestCase {

	/**
	 * Settings for testing.
	 *
	 * @var array
	 */
	protected $settings;

	/**
	 * API connection for testing.
	 *
	 * @var object
	 */
	protected $connapi_erp;

	/**
	 * Set up test environment.
	 */
	public function setUp(): void {
		parent::setUp();

		$this->assertTrue( class_exists( 'WooCommerce' ), 'WooCommerce is not active' );

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

		$this->connapi_erp = new Connect_Ecommerce_Clientify( conecom_get_options() );
	}

	/**
	 * Loads the simple product fixture.
	 *
	 * @return array
	 */
	private function get_item() {
		return json_decode( file_get_contents( UNIT_TESTS_DATA_PLUGIN_DIR . 'product-simple.json' ), true )[0];
	}

	/**
	 * Creates the product, sets its backorders and syncs it again with the given setting.
	 *
	 * @param string $setting  Value of the backorders setting.
	 * @param string $existing Backorders value the product has before the second sync.
	 * @return string Backorders of the product after the sync.
	 */
	private function sync_with_backorders( $setting, $existing ) {
		$item    = $this->get_item();
		$created = PROD::sync_product_item( $this->settings, $item, $this->connapi_erp );
		$this->assertEquals( 'ok', $created['status'] );

		$product = wc_get_product( $created['post_id'] );
		// WooCommerce only keeps backorders on products that manage stock.
		$product->set_manage_stock( true );
		$product->set_stock_quantity( 5 );
		$product->set_backorders( $existing );
		$product->save();

		$this->settings['backorders'] = $setting;
		$updated                      = PROD::sync_product_item( $this->settings, $item, $this->connapi_erp, false, $created['post_id'] );
		$this->assertEquals( 'ok', $updated['status'] );

		$result = wc_get_product( $created['post_id'] )->get_backorders();
		wp_delete_post( $created['post_id'], true );

		return $result;
	}

	/**
	 * "keep" respects whatever value the existing product has.
	 */
	public function test_keep_respects_existing_value() {
		$this->assertEquals( 'yes', $this->sync_with_backorders( 'keep', 'yes' ) );
		$this->assertEquals( 'notify', $this->sync_with_backorders( 'keep', 'notify' ) );
		$this->assertEquals( 'no', $this->sync_with_backorders( 'keep', 'no' ) );
	}

	/**
	 * "no" still overwrites the existing value (previous behavior).
	 */
	public function test_no_overwrites_existing_value() {
		$this->assertEquals( 'no', $this->sync_with_backorders( 'no', 'yes' ) );
	}

	/**
	 * "yes" still overwrites the existing value (previous behavior).
	 */
	public function test_yes_overwrites_existing_value() {
		$this->assertEquals( 'yes', $this->sync_with_backorders( 'yes', 'no' ) );
	}

	/**
	 * A new product with "keep" gets the WooCommerce default.
	 */
	public function test_keep_new_product_gets_woocommerce_default() {
		$this->settings['backorders'] = 'keep';
		$created                      = PROD::sync_product_item( $this->settings, $this->get_item(), $this->connapi_erp );
		$this->assertEquals( 'ok', $created['status'] );
		$this->assertEquals( 'no', wc_get_product( $created['post_id'] )->get_backorders() );
		wp_delete_post( $created['post_id'], true );
	}
}

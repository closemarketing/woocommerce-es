<?php
/**
 * Class ProductTaxClassSyncTest
 *
 * Command: composer test -- --filter=ProductTaxClassSyncTest
 *
 * @package Connect_Ecommerce
 */

use CLOSE\ConnectEcommerce\Helpers\PROD;
use CLOSE\ConnectEcommerce\Helpers\TAXES;

/**
 * Verifies that importing a product from the ERP maps the ERP's tax key
 * (e.g. Holded's "s_iva_21") to the WooCommerce tax class configured for it
 * via the "ERP Tax Type" mapping (WooCommerce > Settings > Tax), instead of
 * always falling back to the standard rate.
 *
 * @group woocommerce
 */
class ProductTaxClassSyncTest extends WP_UnitTestCase {

	/**
	 * Settings for testing
	 */
	protected $settings;

	/**
	 * API connection for testing
	 */
	protected $connapi_erp;

	/**
	 * Tax rate ids created during a test, cleaned up in tearDown().
	 *
	 * @var int[]
	 */
	protected $tax_rate_ids = array();

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

	/**
	 * Clean up any tax rates created during the test.
	 */
	public function tearDown(): void {
		foreach ( $this->tax_rate_ids as $tax_rate_id ) {
			\WC_Tax::_delete_tax_rate( $tax_rate_id );
		}
		$this->tax_rate_ids = array();

		parent::tearDown();
	}

	/**
	 * Creates a WooCommerce tax rate mapped to the given ERP tax key and
	 * tracks it for cleanup.
	 *
	 * @param string $erp_tax_type ERP tax key (e.g. 's_iva_21').
	 * @param string $tax_class    WooCommerce tax class slug to map it to ('' = Standard).
	 * @return int Tax rate id.
	 */
	protected function create_mapped_tax_rate( $erp_tax_type, $tax_class = '' ) {
		$tax_rate_id = \WC_Tax::_insert_tax_rate(
			array(
				'tax_rate_country'  => 'ES',
				'tax_rate_state'    => '',
				'tax_rate'          => '21.0000',
				'tax_rate_name'     => 'IVA 21%',
				'tax_rate_priority' => 1,
				'tax_rate_compound' => 0,
				'tax_rate_shipping' => 1,
				'tax_rate_order'    => 0,
				'tax_rate_class'    => $tax_class,
			)
		);

		TAXES::update_tax_type( $tax_rate_id, $erp_tax_type );

		$this->tax_rate_ids[] = $tax_rate_id;

		return $tax_rate_id;
	}

	/**
	 * A product synced from an item carrying a mapped ERP tax key gets the
	 * WooCommerce tax class configured for that key, instead of Standard.
	 */
	public function test_product_gets_tax_class_mapped_from_erp_tax_key() {
		if ( ! array_key_exists( 'reduced-rate', \WC_Tax::get_tax_classes() ) ) {
			update_option( 'woocommerce_tax_classes', "Reduced rate\nZero rate" );
		}
		$this->create_mapped_tax_rate( 's_iva_21', 'reduced-rate' );

		$item_path = UNIT_TESTS_DATA_PLUGIN_DIR . 'product-simple.json';
		$item      = json_decode( file_get_contents( $item_path ), true )[0];
		$this->assertSame( array( 's_iva_21' ), $item['taxes'], 'Fixture is expected to carry the s_iva_21 ERP tax key' );

		$result_sync = PROD::sync_product_item( $this->settings, $item, $this->connapi_erp );
		$this->assertEquals( 'ok', $result_sync['status'] );

		$product = wc_get_product( $result_sync['post_id'] );
		$this->assertEquals( 'reduced-rate', $product->get_tax_class() );

		wp_delete_post( $result_sync['post_id'], true );
	}

	/**
	 * When the ERP tax key has no configured mapping, the product falls
	 * back to the WooCommerce standard tax class ('') instead of erroring
	 * or carrying over a stale/invalid class name.
	 */
	public function test_product_falls_back_to_standard_when_erp_tax_key_is_unmapped() {
		$item_path = UNIT_TESTS_DATA_PLUGIN_DIR . 'product-simple.json';
		$item      = json_decode( file_get_contents( $item_path ), true )[0];
		$item['taxes'] = array( 's_iva_unmapped' );

		$result_sync = PROD::sync_product_item( $this->settings, $item, $this->connapi_erp );
		$this->assertEquals( 'ok', $result_sync['status'] );

		$product = wc_get_product( $result_sync['post_id'] );
		$this->assertSame( '', $product->get_tax_class() );

		wp_delete_post( $result_sync['post_id'], true );
	}

	/**
	 * Connectors whose payload never includes a "taxes" field (e.g. Clientify)
	 * must not trigger a PHP warning/notice and must sync with the standard
	 * tax class.
	 */
	public function test_product_syncs_without_errors_when_item_has_no_taxes_field() {
		$item_path = UNIT_TESTS_DATA_PLUGIN_DIR . 'product-simple.json';
		$item      = json_decode( file_get_contents( $item_path ), true )[0];
		unset( $item['taxes'] );

		$result_sync = PROD::sync_product_item( $this->settings, $item, $this->connapi_erp );
		$this->assertEquals( 'ok', $result_sync['status'] );

		$product = wc_get_product( $result_sync['post_id'] );
		$this->assertSame( '', $product->get_tax_class() );

		wp_delete_post( $result_sync['post_id'], true );
	}
}

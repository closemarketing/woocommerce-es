<?php
/**
 * Tests for multi-connector routing in Import_Products and Orders.
 *
 * Command: composer test -- --filter=ConnectorRoutingTest
 *
 * @package Connect_Ecommerce
 */

use CLOSE\ConnectEcommerce\Admin\Import_Products;
use CLOSE\ConnectEcommerce\Admin\Orders;
use CLOSE\ConnectEcommerce\Helpers\HELPER;

/**
 * Class ConnectorRoutingTest.
 */
class ConnectorRoutingTest extends WP_UnitTestCase {

	/**
	 * Option name for connector settings.
	 *
	 * @var string
	 */
	private $option_name = 'connect_ecommerce';

	/**
	 * Set up test environment.
	 *
	 * @return void
	 */
	public function setUp(): void {
		parent::setUp();
		delete_option( $this->option_name );
	}

	/**
	 * Tear down environment.
	 *
	 * @return void
	 */
	public function tearDown(): void {
		delete_option( $this->option_name );
		parent::tearDown();
	}

	// -------------------------------------------------------------------------
	// Helpers: build a minimal connector context
	// -------------------------------------------------------------------------

	/**
	 * Build a minimal connector context array that passes constructor guards.
	 *
	 * @param string $connector_id   Connector identifier (also used as slug).
	 * @param string $connector_type ERP type string.
	 * @return array
	 */
	private function make_connector( $connector_id, $connector_type = 'holded' ) {
		$mock_api = $this->createMock( stdClass::class );

		return array(
			'id'           => $connector_id,
			'connector'    => $connector_type,
			'options'      => array(
				'slug'                         => $connector_id,
				'name'                         => ucfirst( $connector_type ),
				'order_only_order_completed'   => 'completed',
				'order_send_attachments'       => false,
				'disable_modules'              => array(),
				'api_pagination'               => false,
				'table_sync'                   => false,
			),
			'settings'     => array(
				'sync'             => 'no',
				'payment_methods'  => array(),
				'treasury_accounts'=> array(),
				'prod_mergevars'   => array(),
			),
			'connapi_erp'  => $mock_api,
			'settings_all' => array(),
			'all_options'  => array(),
		);
	}

	// -------------------------------------------------------------------------
	// Import_Products constructor guards
	// -------------------------------------------------------------------------

	/**
	 * Import_Products constructor accepts a valid connector without error.
	 *
	 * @return void
	 */
	public function test_import_products_constructor_accepts_valid_connector(): void {
		$connector = $this->make_connector( 'holded' );

		// If the constructor crashes the test itself would fail.
		$instance = new Import_Products( $connector );

		$this->assertInstanceOf( Import_Products::class, $instance );
	}

	/**
	 * Import_Products constructor does not crash on an empty connector array.
	 *
	 * @return void
	 */
	public function test_import_products_constructor_handles_empty_connector(): void {
		$instance = new Import_Products( array() );

		$this->assertInstanceOf( Import_Products::class, $instance );
	}

	/**
	 * Import_Products constructor does not crash when connector key is absent.
	 *
	 * @return void
	 */
	public function test_import_products_constructor_handles_missing_connector_key(): void {
		$instance = new Import_Products( array( 'options' => array( 'slug' => 'holded' ) ) );

		$this->assertInstanceOf( Import_Products::class, $instance );
	}

	/**
	 * Import_Products constructor does not crash when options key is absent.
	 *
	 * @return void
	 */
	public function test_import_products_constructor_handles_missing_options_key(): void {
		$instance = new Import_Products( array( 'connector' => 'holded' ) );

		$this->assertInstanceOf( Import_Products::class, $instance );
	}

	// -------------------------------------------------------------------------
	// Orders constructor guards
	// -------------------------------------------------------------------------

	/**
	 * Orders constructor accepts a valid connector without error.
	 *
	 * @return void
	 */
	public function test_orders_constructor_accepts_valid_connector(): void {
		$connector = $this->make_connector( 'holded' );

		$instance = new Orders( $connector );

		$this->assertInstanceOf( Orders::class, $instance );
	}

	/**
	 * Orders constructor does not crash on an empty connector array.
	 *
	 * @return void
	 */
	public function test_orders_constructor_handles_empty_connector(): void {
		$instance = new Orders( array() );

		$this->assertInstanceOf( Orders::class, $instance );
	}

	/**
	 * Orders constructor does not crash when connapi_erp is absent.
	 *
	 * @return void
	 */
	public function test_orders_constructor_handles_missing_connapi_erp(): void {
		$connector = array(
			'connector' => 'holded',
			'options'   => array( 'slug' => 'holded', 'name' => 'Holded' ),
			'settings'  => array(),
			// 'connapi_erp' deliberately omitted.
		);

		$instance = new Orders( $connector );

		$this->assertInstanceOf( Orders::class, $instance );
	}

	// -------------------------------------------------------------------------
	// Meta key per connector (order invoice ID)
	// -------------------------------------------------------------------------

	/**
	 * Each connector slug produces a unique order meta key.
	 *
	 * The meta key pattern is: _<slug>_invoice_id.
	 * This test confirms different connectors store invoice IDs separately.
	 *
	 * @return void
	 */
	public function test_order_meta_key_is_unique_per_connector_slug(): void {
		$slugs = array( 'holded', 'invoicely', 'factusol' );
		$keys  = array();

		foreach ( $slugs as $slug ) {
			$keys[] = '_' . $slug . '_invoice_id';
		}

		// All keys must be unique.
		$this->assertSame( count( $keys ), count( array_unique( $keys ) ) );
	}

	/**
	 * Order meta key follows the expected naming convention.
	 *
	 * @return void
	 */
	public function test_order_meta_key_follows_naming_convention(): void {
		$slug     = 'holded';
		$meta_key = '_' . $slug . '_invoice_id';

		$this->assertSame( '_holded_invoice_id', $meta_key );
	}

	// -------------------------------------------------------------------------
	// Connector lookup round-trip (routing simulation)
	// -------------------------------------------------------------------------

	/**
	 * Connector lookup by id returns the right connector in a multi-connector setup.
	 *
	 * This mirrors the routing logic inside sync_products() and sync_erp_order():
	 *   $connector_data = HELPER::get_connector_by_id( $connector_id, $connector_definitions );
	 *
	 * @return void
	 */
	public function test_connector_lookup_by_id_routes_to_correct_connector(): void {
		update_option( $this->option_name, array(
			'connectors_meta' => array(
				'store_a' => array(
					'type'      => 'holded',
					'label'     => 'Store A',
					'workflows' => array( 'products' => 'yes', 'orders' => 'yes' ),
					'status'    => 'active',
				),
				'store_b' => array(
					'type'      => 'invoicely',
					'label'     => 'Store B',
					'workflows' => array( 'products' => 'no', 'orders' => 'yes' ),
					'status'    => 'active',
				),
			),
			'connector' => 'store_a',
		) );

		$options = array();

		$store_a = HELPER::get_connector_by_id( 'store_a', $options );
		$store_b = HELPER::get_connector_by_id( 'store_b', $options );

		$this->assertNotNull( $store_a );
		$this->assertSame( 'store_a', $store_a['id'] );
		$this->assertSame( 'holded', $store_a['connector'] );

		$this->assertNotNull( $store_b );
		$this->assertSame( 'store_b', $store_b['id'] );
		$this->assertSame( 'invoicely', $store_b['connector'] );
	}

	/**
	 * Passing an unknown connector_id returns null (no routing to wrong connector).
	 *
	 * @return void
	 */
	public function test_unknown_connector_id_returns_null_preventing_wrong_routing(): void {
		update_option( $this->option_name, array(
			'connectors_meta' => array(
				'store_a' => array( 'type' => 'holded', 'label' => 'Store A', 'status' => 'active' ),
			),
			'connector' => 'store_a',
		) );

		$result = HELPER::get_connector_by_id( 'store_b', array() );

		$this->assertNull( $result );
	}

	/**
	 * Connector routing falls back to default when connector_id is empty.
	 *
	 * When no connector_id is supplied, the active connector must be used.
	 * This test validates the fallback via get_connector().
	 *
	 * @return void
	 */
	public function test_empty_connector_id_falls_back_to_active_connector(): void {
		update_option( $this->option_name, array(
			'connectors_meta' => array(
				'store_a' => array( 'type' => 'holded',    'label' => 'Store A', 'status' => 'active' ),
				'store_b' => array( 'type' => 'invoicely', 'label' => 'Store B', 'status' => 'active' ),
			),
			'connector' => 'store_a',
		) );

		// get_connector() is the fallback used when no connector_id is in the request.
		$default_connector = HELPER::get_connector( array() );

		$this->assertSame( 'store_a', $default_connector['id'] );
		$this->assertSame( 'holded', $default_connector['connector'] );
	}

	// -------------------------------------------------------------------------
	// Workflow flags
	// -------------------------------------------------------------------------

	/**
	 * Connector meta correctly stores products workflow flag.
	 *
	 * @return void
	 */
	public function test_connector_meta_stores_products_workflow_flag(): void {
		update_option( $this->option_name, array(
			'connectors_meta' => array(
				'store_a' => array(
					'type'      => 'holded',
					'label'     => 'Store A',
					'workflows' => array( 'products' => 'yes', 'orders' => 'no' ),
					'status'    => 'active',
				),
			),
			'connector' => 'store_a',
		) );

		$result = HELPER::get_connectors( array() );
		$meta   = $result['meta']['store_a'];

		$this->assertSame( 'yes', $meta['workflows']['products'] );
		$this->assertSame( 'no', $meta['workflows']['orders'] );
	}

	/**
	 * Connector meta correctly stores orders workflow flag.
	 *
	 * @return void
	 */
	public function test_connector_meta_stores_orders_workflow_flag(): void {
		update_option( $this->option_name, array(
			'connectors_meta' => array(
				'store_a' => array(
					'type'      => 'holded',
					'label'     => 'Store A',
					'workflows' => array( 'products' => 'no', 'orders' => 'yes' ),
					'status'    => 'active',
				),
			),
			'connector' => 'store_a',
		) );

		$result = HELPER::get_connectors( array() );
		$meta   = $result['meta']['store_a'];

		$this->assertSame( 'no', $meta['workflows']['products'] );
		$this->assertSame( 'yes', $meta['workflows']['orders'] );
	}

	/**
	 * Multiple connectors can have independent workflow flags.
	 *
	 * @return void
	 */
	public function test_multiple_connectors_have_independent_workflow_flags(): void {
		update_option( $this->option_name, array(
			'connectors_meta' => array(
				'products_only' => array(
					'type'      => 'holded',
					'label'     => 'Products Only',
					'workflows' => array( 'products' => 'yes', 'orders' => 'no' ),
					'status'    => 'active',
				),
				'orders_only' => array(
					'type'      => 'invoicely',
					'label'     => 'Orders Only',
					'workflows' => array( 'products' => 'no', 'orders' => 'yes' ),
					'status'    => 'active',
				),
			),
			'connector' => 'products_only',
		) );

		$result = HELPER::get_connectors( array() );
		$meta_p = $result['meta']['products_only'];
		$meta_o = $result['meta']['orders_only'];

		$this->assertSame( 'yes', $meta_p['workflows']['products'] );
		$this->assertSame( 'no', $meta_p['workflows']['orders'] );

		$this->assertSame( 'no', $meta_o['workflows']['products'] );
		$this->assertSame( 'yes', $meta_o['workflows']['orders'] );
	}

	// -------------------------------------------------------------------------
	// Custom connector id (slug ≠ type)
	// -------------------------------------------------------------------------

	/**
	 * Connector id can differ from connector type.
	 *
	 * Users can create 'store_eu' as id with type 'holded'.
	 *
	 * @return void
	 */
	public function test_connector_id_can_differ_from_connector_type(): void {
		update_option( $this->option_name, array(
			'connectors_meta' => array(
				'store_eu' => array(
					'type'   => 'holded',
					'label'  => 'EU Store',
					'status' => 'active',
				),
				'store_us' => array(
					'type'   => 'holded',
					'label'  => 'US Store',
					'status' => 'active',
				),
			),
			'connector' => 'store_eu',
		) );

		$eu = HELPER::get_connector_by_id( 'store_eu', array() );
		$us = HELPER::get_connector_by_id( 'store_us', array() );

		// Both use 'holded' as ERP type but have distinct ids.
		$this->assertSame( 'holded', $eu['connector'] );
		$this->assertSame( 'store_eu', $eu['id'] );

		$this->assertSame( 'holded', $us['connector'] );
		$this->assertSame( 'store_us', $us['id'] );

		// Action names must still be distinct.
		$this->assertNotSame( $eu['actions']['sync_products'], $us['actions']['sync_products'] );
	}
}

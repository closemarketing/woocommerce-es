<?php
/**
 * Tests that sync entry points (Import_Products/Orders AJAX handlers) only ever
 * operate on connectors that are active AND have the relevant workflow enabled.
 *
 * These tests cover three properties requested for the multi-connector work:
 *  - No regression for users who had a single connector before multi-connector support.
 *  - A connector with only 'orders' enabled must never sync products (and vice versa).
 *  - Syncing always routes to/uses the active connectors, never a disabled or inactive one.
 *
 * Command: composer test -- --filter=ConnectorSyncGatingTest
 *
 * @package Connect_Ecommerce
 */

use CLOSE\ConnectEcommerce\Admin\Import_Products;
use CLOSE\ConnectEcommerce\Admin\Orders;
use CLOSE\ConnectEcommerce\Admin\Widget_Product;
use CLOSE\ConnectEcommerce\Admin\Widget_Order;
use CLOSE\ConnectEcommerce\Helpers\HELPER;

/**
 * Class ConnectorSyncGatingTest.
 */
class ConnectorSyncGatingTest extends WP_UnitTestCase {

	/**
	 * Option name for connector settings.
	 *
	 * @var string
	 */
	private $option_name = 'connect_ecommerce';

	/**
	 * Registers the built-in 'clientify' connector type so that connectors of
	 * that type get a real connapi_erp instance (Connect_Ecommerce_Clientify),
	 * matching how the plugin itself registers connector types via the
	 * 'conecom_options_plugin' filter.
	 *
	 * @return void
	 */
	private function register_clientify_type() {
		add_filter( 'conecom_options_plugin', array( $this, 'filter_add_clientify_type' ) );
	}

	/**
	 * Filter callback: adds the 'clientify' connector type definition.
	 *
	 * @param array $options Connector type definitions.
	 * @return array
	 */
	public function filter_add_clientify_type( $options ) {
		return $this->connector_type_definitions( $options );
	}

	/**
	 * Connector type definitions used directly wherever a HELPER method takes
	 * $options as a parameter (get_connectors()/get_connector_by_id() do not
	 * consult the 'conecom_options_plugin' filter themselves; only the AJAX
	 * handlers do, via resolve_connector()).
	 *
	 * @param array $options Existing definitions to merge into.
	 * @return array
	 */
	private function connector_type_definitions( $options = array() ) {
		$options['clientify'] = array(
			'name' => 'Clientify',
			'slug' => 'clientify',
		);
		return $options;
	}

	/**
	 * Set up test environment.
	 *
	 * @return void
	 */
	public function setUp(): void {
		parent::setUp();
		if ( ! class_exists( 'Connect_Ecommerce_Clientify' ) ) {
			$this->markTestSkipped( 'Connector API class not available for testing' );
		}
		delete_option( $this->option_name );
		$this->register_clientify_type();
	}

	/**
	 * Tear down environment.
	 *
	 * @return void
	 */
	public function tearDown(): void {
		delete_option( $this->option_name );
		remove_filter( 'conecom_options_plugin', array( $this, 'filter_add_clientify_type' ) );
		parent::tearDown();
	}

	// -------------------------------------------------------------------------
	// Helpers
	// -------------------------------------------------------------------------

	/**
	 * Build a minimal connector context array (for the connector bound at construct time).
	 *
	 * @param object $mock_api Mock API object standing in for the ERP connector.
	 * @return array
	 */
	private function make_default_connector( $mock_api ) {
		return array(
			'connector'   => 'clientify',
			'options'     => array(
				'slug'                       => 'default_connector',
				'name'                       => 'Default',
				'order_only_order_completed' => 'completed',
				'order_send_attachments'     => false,
			),
			'settings'    => array( 'sync' => 'no' ),
			'connapi_erp' => $mock_api,
		);
	}

	/**
	 * Invokes a private method via reflection.
	 *
	 * @param object $object      Object instance.
	 * @param string $method_name Method name.
	 * @param array  $args        Arguments.
	 * @return mixed
	 */
	private function invoke_private( $object, $method_name, array $args = array() ) {
		$reflection = new ReflectionMethod( $object, $method_name );
		$reflection->setAccessible( true );
		return $reflection->invokeArgs( $object, $args );
	}

	// -------------------------------------------------------------------------
	// Property 1: no regression for single-connector users.
	// -------------------------------------------------------------------------

	/**
	 * With no connector_id override (the single-connector code path used before
	 * multi-connector support existed), sync must keep using this instance's own
	 * connector untouched — exactly as it did pre-multi-connector.
	 *
	 * @return void
	 */
	public function test_import_products_default_path_unaffected_for_single_connector(): void {
		$mock_api  = $this->createMock( stdClass::class );
		$connector = $this->make_default_connector( $mock_api );
		$instance  = new Import_Products( $connector );

		list( $connapi_erp, $settings, $options ) = $this->invoke_private( $instance, 'resolve_connector', array( '' ) );

		$this->assertSame( $mock_api, $connapi_erp );
		$this->assertSame( array( 'sync' => 'no' ), $settings );
		$this->assertSame( 'default_connector', $options['slug'] );
	}

	/**
	 * Same as above, for Orders.
	 *
	 * @return void
	 */
	public function test_orders_default_path_unaffected_for_single_connector(): void {
		$mock_api  = $this->createMock( stdClass::class );
		$connector = $this->make_default_connector( $mock_api );
		$instance  = new Orders( $connector );

		list( $connapi_erp, $settings, $options, $meta_key_order ) = $this->invoke_private( $instance, 'resolve_connector', array( '' ) );

		$this->assertSame( $mock_api, $connapi_erp );
		$this->assertSame( 'default_connector', $options['slug'] );
		$this->assertSame( '_default_connector_invoice_id', $meta_key_order );
	}

	/**
	 * A single active connector with default (unset) workflow flags is treated
	 * as fully enabled, and shows no connector selector (single-connector UI).
	 *
	 * @return void
	 */
	public function test_single_connector_is_syncable_for_both_workflows(): void {
		update_option( $this->option_name, array(
			'connectors_meta' => array(
				'onlystore' => array(
					'type'   => 'clientify',
					'label'  => 'Only Store',
					'status' => 'active',
				),
			),
			'connector' => 'onlystore',
		) );

		$connectors_data = HELPER::get_connectors( $this->connector_type_definitions() );
		$connector       = $connectors_data['items']['onlystore'];

		$widget_product = new Widget_Product( $connector, $connectors_data );
		$widget_order   = new Widget_Order( $connector, $connectors_data );

		$products_syncable = $this->invoke_private( $widget_product, 'get_syncable_connectors' );
		$orders_syncable   = $this->invoke_private( $widget_order, 'get_syncable_connectors' );

		$this->assertCount( 1, $products_syncable );
		$this->assertArrayHasKey( 'onlystore', $products_syncable );
		$this->assertCount( 1, $orders_syncable );
		$this->assertArrayHasKey( 'onlystore', $orders_syncable );
	}

	// -------------------------------------------------------------------------
	// Property 2: an orders-only connector must not sync products (and vice versa).
	// -------------------------------------------------------------------------

	/**
	 * A connector with workflows.products = 'no' must be rejected when requested
	 * explicitly for a products sync: resolve_connector must not return its API.
	 *
	 * @return void
	 */
	public function test_import_products_rejects_connector_with_products_workflow_disabled(): void {
		update_option( $this->option_name, array(
			'connectors_meta' => array(
				'orders_only' => array(
					'type'      => 'clientify',
					'label'     => 'Orders Only',
					'workflows' => array( 'products' => 'no', 'orders' => 'yes' ),
					'status'    => 'active',
				),
			),
			'connector' => 'orders_only',
		) );

		$mock_api  = $this->createMock( stdClass::class );
		$connector = $this->make_default_connector( $mock_api );
		$instance  = new Import_Products( $connector );

		list( $connapi_erp ) = $this->invoke_private( $instance, 'resolve_connector', array( 'orders_only' ) );

		$this->assertNull( $connapi_erp, 'Products sync must be rejected for a connector with products workflow disabled.' );
	}

	/**
	 * The same connector must still be allowed to sync orders, since its orders
	 * workflow is enabled.
	 *
	 * @return void
	 */
	public function test_orders_allows_connector_with_only_orders_workflow_enabled(): void {
		update_option( $this->option_name, array(
			'connectors_meta' => array(
				'orders_only' => array(
					'type'      => 'clientify',
					'label'     => 'Orders Only',
					'workflows' => array( 'products' => 'no', 'orders' => 'yes' ),
					'status'    => 'active',
				),
			),
			'connector' => 'orders_only',
		) );

		$mock_api  = $this->createMock( stdClass::class );
		$connector = $this->make_default_connector( $mock_api );
		$instance  = new Orders( $connector );

		list( $connapi_erp ) = $this->invoke_private( $instance, 'resolve_connector', array( 'orders_only' ) );

		$this->assertNotNull( $connapi_erp, 'Orders sync must be allowed for a connector with orders workflow enabled.' );
		$this->assertInstanceOf( Connect_Ecommerce_Clientify::class, $connapi_erp );
	}

	/**
	 * Widget_Product must not offer an orders-only connector in its selector.
	 *
	 * @return void
	 */
	public function test_widget_product_excludes_orders_only_connector(): void {
		update_option( $this->option_name, array(
			'connectors_meta' => array(
				'products_store' => array(
					'type'      => 'clientify',
					'label'     => 'Products Store',
					'workflows' => array( 'products' => 'yes', 'orders' => 'yes' ),
					'status'    => 'active',
				),
				'orders_only'    => array(
					'type'      => 'clientify',
					'label'     => 'Orders Only',
					'workflows' => array( 'products' => 'no', 'orders' => 'yes' ),
					'status'    => 'active',
				),
			),
			'connector' => 'products_store',
		) );

		$connectors_data = HELPER::get_connectors( $this->connector_type_definitions() );
		$connector       = $connectors_data['items']['products_store'];
		$widget_product  = new Widget_Product( $connector, $connectors_data );
		$widget_order    = new Widget_Order( $connector, $connectors_data );

		$products_syncable = $this->invoke_private( $widget_product, 'get_syncable_connectors' );
		$orders_syncable   = $this->invoke_private( $widget_order, 'get_syncable_connectors' );

		$this->assertArrayNotHasKey( 'orders_only', $products_syncable, 'Products selector must exclude a connector with products workflow disabled.' );
		$this->assertArrayHasKey( 'orders_only', $orders_syncable, 'Orders selector must include a connector with orders workflow enabled.' );
	}

	/**
	 * Mirrors the previous test but for a products-only connector: it must not
	 * be offered for orders sync, and Import_Products must still allow products.
	 *
	 * @return void
	 */
	public function test_products_only_connector_is_rejected_for_orders_sync(): void {
		update_option( $this->option_name, array(
			'connectors_meta' => array(
				'products_only' => array(
					'type'      => 'clientify',
					'label'     => 'Products Only',
					'workflows' => array( 'products' => 'yes', 'orders' => 'no' ),
					'status'    => 'active',
				),
			),
			'connector' => 'products_only',
		) );

		$mock_api        = $this->createMock( stdClass::class );
		$import_instance = new Import_Products( $this->make_default_connector( $mock_api ) );
		$orders_instance = new Orders( $this->make_default_connector( $mock_api ) );

		list( $products_connapi ) = $this->invoke_private( $import_instance, 'resolve_connector', array( 'products_only' ) );
		list( $orders_connapi )   = $this->invoke_private( $orders_instance, 'resolve_connector', array( 'products_only' ) );

		$this->assertNotNull( $products_connapi, 'Products sync must be allowed for a connector with products workflow enabled.' );
		$this->assertNull( $orders_connapi, 'Orders sync must be rejected for a connector with orders workflow disabled.' );
	}

	// -------------------------------------------------------------------------
	// Property 3: syncing always uses the active connectors.
	// -------------------------------------------------------------------------

	/**
	 * A connector marked inactive must be rejected for both products and orders
	 * sync, even though its workflow flags say 'yes'.
	 *
	 * @return void
	 */
	public function test_inactive_connector_rejected_for_products_and_orders(): void {
		update_option( $this->option_name, array(
			'connectors_meta' => array(
				'disabled_store' => array(
					'type'      => 'clientify',
					'label'     => 'Disabled Store',
					'workflows' => array( 'products' => 'yes', 'orders' => 'yes' ),
					'status'    => 'inactive',
				),
			),
			'connector' => 'disabled_store',
		) );

		$mock_api        = $this->createMock( stdClass::class );
		$import_instance = new Import_Products( $this->make_default_connector( $mock_api ) );
		$orders_instance = new Orders( $this->make_default_connector( $mock_api ) );

		list( $products_connapi ) = $this->invoke_private( $import_instance, 'resolve_connector', array( 'disabled_store' ) );
		list( $orders_connapi )   = $this->invoke_private( $orders_instance, 'resolve_connector', array( 'disabled_store' ) );

		$this->assertNull( $products_connapi, 'Products sync must be rejected for an inactive connector.' );
		$this->assertNull( $orders_connapi, 'Orders sync must be rejected for an inactive connector.' );
	}

	/**
	 * Widget selectors must never offer an inactive connector, regardless of its
	 * workflow flags.
	 *
	 * @return void
	 */
	public function test_widgets_exclude_inactive_connector(): void {
		update_option( $this->option_name, array(
			'connectors_meta' => array(
				'active_store'   => array(
					'type'      => 'clientify',
					'label'     => 'Active Store',
					'workflows' => array( 'products' => 'yes', 'orders' => 'yes' ),
					'status'    => 'active',
				),
				'disabled_store' => array(
					'type'      => 'clientify',
					'label'     => 'Disabled Store',
					'workflows' => array( 'products' => 'yes', 'orders' => 'yes' ),
					'status'    => 'inactive',
				),
			),
			'connector' => 'active_store',
		) );

		$connectors_data = HELPER::get_connectors( $this->connector_type_definitions() );
		$connector       = $connectors_data['items']['active_store'];
		$widget_product  = new Widget_Product( $connector, $connectors_data );
		$widget_order    = new Widget_Order( $connector, $connectors_data );

		$products_syncable = $this->invoke_private( $widget_product, 'get_syncable_connectors' );
		$orders_syncable   = $this->invoke_private( $widget_order, 'get_syncable_connectors' );

		$this->assertArrayHasKey( 'active_store', $products_syncable );
		$this->assertArrayNotHasKey( 'disabled_store', $products_syncable );
		$this->assertArrayHasKey( 'active_store', $orders_syncable );
		$this->assertArrayNotHasKey( 'disabled_store', $orders_syncable );
	}

	/**
	 * In a multi-connector setup, syncing a given connector_id must route to
	 * that connector's own settings — never silently fall back to a different
	 * (e.g. the default/active) connector.
	 *
	 * @return void
	 */
	public function test_multi_connector_sync_routes_to_the_requested_active_connector(): void {
		update_option( $this->option_name, array(
			'connectors_meta' => array(
				'store_a' => array(
					'type'      => 'clientify',
					'label'     => 'Store A',
					'workflows' => array( 'products' => 'yes', 'orders' => 'yes' ),
					'status'    => 'active',
				),
				'store_b' => array(
					'type'      => 'clientify',
					'label'     => 'Store B',
					'workflows' => array( 'products' => 'yes', 'orders' => 'yes' ),
					'status'    => 'active',
				),
			),
			'connector' => 'store_a',
			'store_a'   => array( 'marker' => 'A' ),
			'store_b'   => array( 'marker' => 'B' ),
		) );

		$mock_api  = $this->createMock( stdClass::class );
		$connector = $this->make_default_connector( $mock_api );
		$instance  = new Import_Products( $connector );

		list( $connapi_erp_a, $settings_a ) = $this->invoke_private( $instance, 'resolve_connector', array( 'store_a' ) );
		list( $connapi_erp_b, $settings_b ) = $this->invoke_private( $instance, 'resolve_connector', array( 'store_b' ) );

		$this->assertNotSame( $mock_api, $connapi_erp_a, 'Explicit connector_id must route to that connector, not the default one.' );
		$this->assertNotSame( $connapi_erp_a, $connapi_erp_b, 'Two different connectors must resolve to two different API instances.' );
		$this->assertSame( 'A', $settings_a['marker'] );
		$this->assertSame( 'B', $settings_b['marker'] );
	}

	/**
	 * An unknown connector_id must not silently fall back to the default
	 * connector: it must be rejected, since routing to the wrong connector
	 * would violate "sync always uses the [requested] active connector".
	 *
	 * @return void
	 */
	public function test_unknown_connector_id_is_rejected_not_silently_routed_to_default(): void {
		update_option( $this->option_name, array(
			'connectors_meta' => array(
				'store_a' => array(
					'type'      => 'clientify',
					'label'     => 'Store A',
					'workflows' => array( 'products' => 'yes', 'orders' => 'yes' ),
					'status'    => 'active',
				),
			),
			'connector' => 'store_a',
		) );

		$mock_api        = $this->createMock( stdClass::class );
		$import_instance = new Import_Products( $this->make_default_connector( $mock_api ) );
		$orders_instance = new Orders( $this->make_default_connector( $mock_api ) );

		list( $products_connapi ) = $this->invoke_private( $import_instance, 'resolve_connector', array( 'does_not_exist' ) );
		list( $orders_connapi )   = $this->invoke_private( $orders_instance, 'resolve_connector', array( 'does_not_exist' ) );

		$this->assertNull( $products_connapi );
		$this->assertNull( $orders_connapi );
	}
}

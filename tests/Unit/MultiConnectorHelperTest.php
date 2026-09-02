<?php
/**
 * Tests for multi-connector HELPER methods.
 *
 * Command: composer test -- --filter=MultiConnectorHelperTest
 *
 * @package Connect_Ecommerce
 */

use CLOSE\ConnectEcommerce\Helpers\HELPER;

/**
 * Class MultiConnectorHelperTest.
 */
class MultiConnectorHelperTest extends WP_UnitTestCase {

	/**
	 * Option name for connector settings.
	 *
	 * @var string
	 */
	private $option_name = 'connect_ecommerce';

	/**
	 * Option name for payment method mappings.
	 *
	 * @var string
	 */
	private $payment_option_name = 'connect_ecommerce_payment_methods';

	/**
	 * Option name for prod mergevars.
	 *
	 * @var string
	 */
	private $mergevars_option_name = 'connect_ecommerce_prod_mergevars';

	/**
	 * Set up test environment.
	 *
	 * @return void
	 */
	public function setUp(): void {
		parent::setUp();
		delete_option( $this->option_name );
		delete_option( $this->payment_option_name );
		delete_option( $this->mergevars_option_name );
	}

	/**
	 * Tear down environment.
	 *
	 * @return void
	 */
	public function tearDown(): void {
		delete_option( $this->option_name );
		delete_option( $this->payment_option_name );
		delete_option( $this->mergevars_option_name );
		parent::tearDown();
	}

	// -------------------------------------------------------------------------
	// get_workflows()
	// -------------------------------------------------------------------------

	/**
	 * get_workflows returns products and orders.
	 *
	 * @return void
	 */
	public function test_get_workflows_returns_default_workflows(): void {
		$workflows = HELPER::get_workflows();

		$this->assertIsArray( $workflows );
		$this->assertContains( 'products', $workflows );
		$this->assertContains( 'orders', $workflows );
	}

	// -------------------------------------------------------------------------
	// get_connectors() — empty state
	// -------------------------------------------------------------------------

	/**
	 * get_connectors returns empty items when no option exists.
	 *
	 * @return void
	 */
	public function test_get_connectors_returns_empty_items_when_no_option(): void {
		$result = HELPER::get_connectors( array() );

		$this->assertIsArray( $result );
		$this->assertArrayHasKey( 'items', $result );
		$this->assertArrayHasKey( 'active', $result );
		$this->assertArrayHasKey( 'settings_all', $result );
		$this->assertArrayHasKey( 'meta', $result );
		$this->assertEmpty( $result['items'] );
	}

	/**
	 * get_connectors returns correct structure keys.
	 *
	 * @return void
	 */
	public function test_get_connectors_returns_required_structure_keys(): void {
		update_option( $this->option_name, array(
			'connectors_meta' => array(
				'holded' => array(
					'type'      => 'holded',
					'label'     => 'Holded',
					'workflows' => array( 'products' => 'yes', 'orders' => 'yes' ),
					'status'    => 'active',
				),
			),
			'connector'       => 'holded',
		) );

		$result = HELPER::get_connectors( array() );

		$this->assertArrayHasKey( 'items', $result );
		$this->assertArrayHasKey( 'active', $result );
		$this->assertArrayHasKey( 'settings_all', $result );
		$this->assertArrayHasKey( 'meta', $result );
	}

	// -------------------------------------------------------------------------
	// get_connectors() — single connector (new format)
	// -------------------------------------------------------------------------

	/**
	 * get_connectors returns one connector when one is configured.
	 *
	 * @return void
	 */
	public function test_get_connectors_returns_one_item_for_single_connector(): void {
		update_option( $this->option_name, array(
			'connectors_meta' => array(
				'holded' => array(
					'type'      => 'holded',
					'label'     => 'Holded',
					'workflows' => array( 'products' => 'yes', 'orders' => 'yes' ),
					'status'    => 'active',
				),
			),
			'connector'       => 'holded',
		) );

		$result = HELPER::get_connectors( array() );

		$this->assertCount( 1, $result['items'] );
		$this->assertArrayHasKey( 'holded', $result['items'] );
	}

	/**
	 * get_connectors returns each connector with required context keys.
	 *
	 * @return void
	 */
	public function test_get_connectors_items_have_required_context_keys(): void {
		update_option( $this->option_name, array(
			'connectors_meta' => array(
				'holded' => array(
					'type'      => 'holded',
					'label'     => 'Holded',
					'workflows' => array( 'products' => 'yes', 'orders' => 'yes' ),
					'status'    => 'active',
				),
			),
			'connector'       => 'holded',
		) );

		$result    = HELPER::get_connectors( array() );
		$connector = $result['items']['holded'];

		$this->assertArrayHasKey( 'id', $connector );
		$this->assertArrayHasKey( 'connector', $connector );
		$this->assertArrayHasKey( 'meta', $connector );
		$this->assertArrayHasKey( 'settings', $connector );
		$this->assertArrayHasKey( 'actions', $connector );
		$this->assertSame( 'holded', $connector['id'] );
		$this->assertSame( 'holded', $connector['connector'] );
	}

	/**
	 * get_connectors active key reflects configured active connector.
	 *
	 * @return void
	 */
	public function test_get_connectors_active_reflects_connector_option(): void {
		update_option( $this->option_name, array(
			'connectors_meta' => array(
				'holded' => array(
					'type'   => 'holded',
					'label'  => 'Holded',
					'status' => 'active',
				),
			),
			'connector'       => 'holded',
		) );

		$result = HELPER::get_connectors( array() );

		$this->assertSame( 'holded', $result['active'] );
	}

	// -------------------------------------------------------------------------
	// get_connectors() — multiple connectors
	// -------------------------------------------------------------------------

	/**
	 * get_connectors returns all configured connectors.
	 *
	 * @return void
	 */
	public function test_get_connectors_returns_all_configured_connectors(): void {
		update_option( $this->option_name, array(
			'connectors_meta' => array(
				'holded'    => array(
					'type'      => 'holded',
					'label'     => 'Holded Store',
					'workflows' => array( 'products' => 'yes', 'orders' => 'yes' ),
					'status'    => 'active',
				),
				'invoicely' => array(
					'type'      => 'invoicely',
					'label'     => 'Invoicely Store',
					'workflows' => array( 'products' => 'no', 'orders' => 'yes' ),
					'status'    => 'active',
				),
			),
			'connector'       => 'holded',
		) );

		$result = HELPER::get_connectors( array() );

		$this->assertCount( 2, $result['items'] );
		$this->assertArrayHasKey( 'holded', $result['items'] );
		$this->assertArrayHasKey( 'invoicely', $result['items'] );
	}

	/**
	 * Each connector in items has its own id and connector type.
	 *
	 * @return void
	 */
	public function test_get_connectors_each_item_has_correct_id_and_type(): void {
		update_option( $this->option_name, array(
			'connectors_meta' => array(
				'holded'    => array( 'type' => 'holded',    'label' => 'Holded',    'status' => 'active' ),
				'invoicely' => array( 'type' => 'invoicely', 'label' => 'Invoicely', 'status' => 'active' ),
			),
			'connector' => 'holded',
		) );

		$result = HELPER::get_connectors( array() );

		$this->assertSame( 'holded', $result['items']['holded']['id'] );
		$this->assertSame( 'holded', $result['items']['holded']['connector'] );
		$this->assertSame( 'invoicely', $result['items']['invoicely']['id'] );
		$this->assertSame( 'invoicely', $result['items']['invoicely']['connector'] );
	}

	// -------------------------------------------------------------------------
	// Legacy migration via get_connectors()
	// -------------------------------------------------------------------------

	/**
	 * Legacy single-connector option is migrated to connectors_meta format.
	 *
	 * @return void
	 */
	public function test_get_connectors_migrates_legacy_single_connector_format(): void {
		// Old format: no connectors_meta key.
		update_option( $this->option_name, array(
			'connector' => 'holded',
			'holded'    => array( 'api_key' => 'abc123' ),
		) );

		$result = HELPER::get_connectors( array() );

		$this->assertArrayHasKey( 'holded', $result['items'] );
		$this->assertSame( 'holded', $result['items']['holded']['id'] );
	}

	/**
	 * After legacy migration the option is updated so it does not migrate again.
	 *
	 * @return void
	 */
	public function test_legacy_migration_persists_connectors_meta_to_db(): void {
		update_option( $this->option_name, array(
			'connector' => 'holded',
			'holded'    => array( 'api_key' => 'abc123' ),
		) );

		HELPER::get_connectors( array() );

		$saved = get_option( $this->option_name );
		$this->assertArrayHasKey( 'connectors_meta', $saved );
		$this->assertArrayHasKey( 'holded', $saved['connectors_meta'] );
	}

	/**
	 * Legacy migration does not occur when connectors_meta is already set.
	 *
	 * @return void
	 */
	public function test_no_migration_when_connectors_meta_already_present(): void {
		$original_meta = array(
			'mystore' => array(
				'type'      => 'holded',
				'label'     => 'My Store',
				'workflows' => array( 'products' => 'yes', 'orders' => 'no' ),
				'status'    => 'active',
			),
		);

		update_option( $this->option_name, array(
			'connectors_meta' => $original_meta,
			'connector'       => 'mystore',
		) );

		HELPER::get_connectors( array() );

		$saved = get_option( $this->option_name );
		// The custom key must survive untouched.
		$this->assertArrayHasKey( 'mystore', $saved['connectors_meta'] );
		$this->assertArrayNotHasKey( 'holded', $saved['connectors_meta'] );
	}

	/**
	 * Legacy migration uses connector name from options when available.
	 *
	 * @return void
	 */
	public function test_legacy_migration_uses_name_from_options_for_label(): void {
		update_option( $this->option_name, array(
			'connector' => 'holded',
			'holded'    => array( 'api_key' => 'abc123' ),
		) );

		$options = array( 'holded' => array( 'name' => 'Holded ERP' ) );
		$result  = HELPER::get_connectors( $options );

		$saved = get_option( $this->option_name );
		$this->assertSame( 'Holded ERP', $saved['connectors_meta']['holded']['label'] );
	}

	/**
	 * Legacy migration with no options falls back to ucfirst of connector slug.
	 *
	 * @return void
	 */
	public function test_legacy_migration_falls_back_to_ucfirst_when_no_options(): void {
		update_option( $this->option_name, array(
			'connector' => 'holded',
			'holded'    => array( 'api_key' => 'abc123' ),
		) );

		$result = HELPER::get_connectors( array() );

		$saved = get_option( $this->option_name );
		$this->assertSame( 'Holded', $saved['connectors_meta']['holded']['label'] );
	}

	/**
	 * Missing workflow flags are filled with 'yes' during normalisation.
	 *
	 * @return void
	 */
	public function test_normalisation_fills_missing_workflow_flags_with_yes(): void {
		// connectors_meta exists but workflows are absent.
		update_option( $this->option_name, array(
			'connectors_meta' => array(
				'holded' => array( 'type' => 'holded', 'label' => 'Holded', 'status' => 'active' ),
			),
			'connector' => 'holded',
		) );

		HELPER::get_connectors( array() );

		$saved = get_option( $this->option_name );
		$meta  = $saved['connectors_meta']['holded'];

		$this->assertArrayHasKey( 'workflows', $meta );
		$this->assertSame( 'yes', $meta['workflows']['products'] );
		$this->assertSame( 'yes', $meta['workflows']['orders'] );
	}

	/**
	 * Existing workflow flag 'no' is preserved during normalisation.
	 *
	 * @return void
	 */
	public function test_normalisation_preserves_existing_workflow_flags(): void {
		update_option( $this->option_name, array(
			'connectors_meta' => array(
				'holded' => array(
					'type'      => 'holded',
					'label'     => 'Holded',
					'workflows' => array( 'products' => 'no', 'orders' => 'yes' ),
					'status'    => 'active',
				),
			),
			'connector' => 'holded',
		) );

		HELPER::get_connectors( array() );

		$saved = get_option( $this->option_name );
		$this->assertSame( 'no', $saved['connectors_meta']['holded']['workflows']['products'] );
		$this->assertSame( 'yes', $saved['connectors_meta']['holded']['workflows']['orders'] );
	}

	// -------------------------------------------------------------------------
	// get_connector_by_id()
	// -------------------------------------------------------------------------

	/**
	 * get_connector_by_id returns null for an unknown id.
	 *
	 * @return void
	 */
	public function test_get_connector_by_id_returns_null_for_unknown_id(): void {
		update_option( $this->option_name, array(
			'connectors_meta' => array(
				'holded' => array( 'type' => 'holded', 'label' => 'Holded', 'status' => 'active' ),
			),
			'connector' => 'holded',
		) );

		$result = HELPER::get_connector_by_id( 'does_not_exist', array() );

		$this->assertNull( $result );
	}

	/**
	 * get_connector_by_id returns connector data for a known id.
	 *
	 * @return void
	 */
	public function test_get_connector_by_id_returns_correct_connector(): void {
		update_option( $this->option_name, array(
			'connectors_meta' => array(
				'holded'    => array( 'type' => 'holded',    'label' => 'Holded',    'status' => 'active' ),
				'invoicely' => array( 'type' => 'invoicely', 'label' => 'Invoicely', 'status' => 'active' ),
			),
			'connector' => 'holded',
		) );

		$holded    = HELPER::get_connector_by_id( 'holded', array() );
		$invoicely = HELPER::get_connector_by_id( 'invoicely', array() );

		$this->assertNotNull( $holded );
		$this->assertSame( 'holded', $holded['id'] );
		$this->assertSame( 'holded', $holded['connector'] );

		$this->assertNotNull( $invoicely );
		$this->assertSame( 'invoicely', $invoicely['id'] );
		$this->assertSame( 'invoicely', $invoicely['connector'] );
	}

	/**
	 * get_connector_by_id returns null when no connectors are configured.
	 *
	 * @return void
	 */
	public function test_get_connector_by_id_returns_null_when_no_connectors(): void {
		$result = HELPER::get_connector_by_id( 'holded', array() );

		$this->assertNull( $result );
	}

	/**
	 * get_connector_by_id returns connector found via legacy migration.
	 *
	 * @return void
	 */
	public function test_get_connector_by_id_works_after_legacy_migration(): void {
		// Old format without connectors_meta.
		update_option( $this->option_name, array(
			'connector' => 'holded',
			'holded'    => array( 'api_key' => 'xyz' ),
		) );

		$result = HELPER::get_connector_by_id( 'holded', array() );

		$this->assertNotNull( $result );
		$this->assertSame( 'holded', $result['id'] );
	}

	// -------------------------------------------------------------------------
	// get_connector_action_name()
	// -------------------------------------------------------------------------

	/**
	 * get_connector_action_name with empty connector_id returns base action.
	 *
	 * @return void
	 */
	public function test_get_connector_action_name_with_empty_id_returns_base(): void {
		$name = HELPER::get_connector_action_name( 'connect_ecommerce_sync_products', '' );

		$this->assertSame( 'connect_ecommerce_sync_products', $name );
	}

	/**
	 * get_connector_action_name appends sanitized connector_id.
	 *
	 * @return void
	 */
	public function test_get_connector_action_name_appends_connector_id(): void {
		$name = HELPER::get_connector_action_name( 'connect_ecommerce_sync_products', 'holded' );

		$this->assertSame( 'connect_ecommerce_sync_products_holded', $name );
	}

	/**
	 * get_connector_action_name sanitizes connector_id with special characters.
	 *
	 * @return void
	 */
	public function test_get_connector_action_name_sanitizes_connector_id(): void {
		$name = HELPER::get_connector_action_name( 'sync_erp_order', 'My Store!' );

		// sanitize_key lowercases and strips non-alphanumeric/hyphen/underscore
		// characters — spaces and ! are removed entirely (not converted to '-').
		$this->assertSame( 'sync_erp_order_mystore', $name );
	}

	// -------------------------------------------------------------------------
	// Connector actions array
	// -------------------------------------------------------------------------

	/**
	 * Each connector context includes an actions array with sync keys.
	 *
	 * @return void
	 */
	public function test_connector_context_includes_actions_array(): void {
		update_option( $this->option_name, array(
			'connectors_meta' => array(
				'holded' => array( 'type' => 'holded', 'label' => 'Holded', 'status' => 'active' ),
			),
			'connector' => 'holded',
		) );

		$result    = HELPER::get_connectors( array() );
		$connector = $result['items']['holded'];

		$this->assertArrayHasKey( 'actions', $connector );
		$this->assertArrayHasKey( 'sync_products', $connector['actions'] );
		$this->assertArrayHasKey( 'sync_orders', $connector['actions'] );
		$this->assertArrayHasKey( 'single_order', $connector['actions'] );
	}

	/**
	 * Actions are connector-specific (contain the connector id).
	 *
	 * @return void
	 */
	public function test_connector_actions_include_connector_id(): void {
		update_option( $this->option_name, array(
			'connectors_meta' => array(
				'holded' => array( 'type' => 'holded', 'label' => 'Holded', 'status' => 'active' ),
			),
			'connector' => 'holded',
		) );

		$result    = HELPER::get_connectors( array() );
		$connector = $result['items']['holded'];

		$this->assertStringContainsString( 'holded', $connector['actions']['sync_products'] );
		$this->assertStringContainsString( 'holded', $connector['actions']['sync_orders'] );
		$this->assertStringContainsString( 'holded', $connector['actions']['single_order'] );
	}

	/**
	 * Two connectors have distinct action names.
	 *
	 * @return void
	 */
	public function test_two_connectors_have_distinct_action_names(): void {
		update_option( $this->option_name, array(
			'connectors_meta' => array(
				'holded'    => array( 'type' => 'holded',    'label' => 'Holded',    'status' => 'active' ),
				'invoicely' => array( 'type' => 'invoicely', 'label' => 'Invoicely', 'status' => 'active' ),
			),
			'connector' => 'holded',
		) );

		$result = HELPER::get_connectors( array() );

		$holded_action    = $result['items']['holded']['actions']['sync_products'];
		$invoicely_action = $result['items']['invoicely']['actions']['sync_products'];

		$this->assertNotSame( $holded_action, $invoicely_action );
	}

	// -------------------------------------------------------------------------
	// get_connector() backward-compat (uses new internals)
	// -------------------------------------------------------------------------

	/**
	 * get_connector returns the active connector from new connectors_meta format.
	 *
	 * @return void
	 */
	public function test_get_connector_backward_compat_with_connectors_meta(): void {
		update_option( $this->option_name, array(
			'connectors_meta' => array(
				'holded' => array(
					'type'      => 'holded',
					'label'     => 'Holded',
					'workflows' => array( 'products' => 'yes', 'orders' => 'yes' ),
					'status'    => 'active',
				),
			),
			'connector' => 'holded',
		) );

		$connector = HELPER::get_connector( array() );

		$this->assertSame( 'holded', $connector['id'] );
		$this->assertSame( 'holded', $connector['connector'] );
	}

	/**
	 * get_connector returns first connector when active is not set.
	 *
	 * @return void
	 */
	public function test_get_connector_falls_back_to_first_when_active_unset(): void {
		update_option( $this->option_name, array(
			'connectors_meta' => array(
				'first_connector'  => array( 'type' => 'holded',    'label' => 'First',  'status' => 'active' ),
				'second_connector' => array( 'type' => 'invoicely', 'label' => 'Second', 'status' => 'active' ),
			),
			// No 'connector' key set.
		) );

		$connector = HELPER::get_connector( array() );

		$this->assertSame( 'first_connector', $connector['id'] );
	}

	/**
	 * get_connector returns empty connector id when no connectors are configured.
	 *
	 * @return void
	 */
	public function test_get_connector_returns_empty_id_when_no_connectors(): void {
		$connector = HELPER::get_connector( array() );

		$this->assertSame( '', $connector['id'] );
		$this->assertSame( '', $connector['connector'] );
	}

	// -------------------------------------------------------------------------
	// prod_mergevars propagated to each connector
	// -------------------------------------------------------------------------

	/**
	 * prod_mergevars option is available in each connector's settings.
	 *
	 * @return void
	 */
	public function test_prod_mergevars_propagated_to_connector_settings(): void {
		update_option( $this->option_name, array(
			'connectors_meta' => array(
				'holded' => array( 'type' => 'holded', 'label' => 'Holded', 'status' => 'active' ),
			),
			'connector' => 'holded',
		) );
		update_option( $this->mergevars_option_name, array(
			'prod_mergevars' => array( 'attr1' => 'val1' ),
		) );

		$result    = HELPER::get_connectors( array() );
		$connector = $result['items']['holded'];

		$this->assertArrayHasKey( 'prod_mergevars', $connector['settings'] );
		$this->assertSame( 'val1', $connector['settings']['prod_mergevars']['attr1'] );
	}

	/**
	 * payment_methods and treasury_accounts are initialised as empty arrays.
	 *
	 * @return void
	 */
	public function test_connector_settings_initialize_payment_arrays_as_empty(): void {
		update_option( $this->option_name, array(
			'connectors_meta' => array(
				'holded' => array( 'type' => 'holded', 'label' => 'Holded', 'status' => 'active' ),
			),
			'connector' => 'holded',
		) );

		$result    = HELPER::get_connectors( array() );
		$connector = $result['items']['holded'];

		$this->assertIsArray( $connector['settings']['payment_methods'] );
		$this->assertIsArray( $connector['settings']['treasury_accounts'] );
	}

	// -------------------------------------------------------------------------
	// is_workflow_enabled_for_connector()
	// -------------------------------------------------------------------------

	/**
	 * An active connector with workflow 'yes' is enabled.
	 *
	 * @return void
	 */
	public function test_is_workflow_enabled_returns_true_for_active_connector_with_yes(): void {
		$meta = array(
			'status'    => 'active',
			'workflows' => array( 'products' => 'yes', 'orders' => 'yes' ),
		);

		$this->assertTrue( HELPER::is_workflow_enabled_for_connector( $meta, 'products' ) );
		$this->assertTrue( HELPER::is_workflow_enabled_for_connector( $meta, 'orders' ) );
	}

	/**
	 * A connector with the workflow explicitly set to 'no' is disabled for that workflow only.
	 *
	 * @return void
	 */
	public function test_is_workflow_enabled_returns_false_when_workflow_is_no(): void {
		$meta = array(
			'status'    => 'active',
			'workflows' => array( 'products' => 'no', 'orders' => 'yes' ),
		);

		$this->assertFalse( HELPER::is_workflow_enabled_for_connector( $meta, 'products' ) );
		$this->assertTrue( HELPER::is_workflow_enabled_for_connector( $meta, 'orders' ) );
	}

	/**
	 * An inactive connector is disabled for every workflow, even if workflows say 'yes'.
	 *
	 * @return void
	 */
	public function test_is_workflow_enabled_returns_false_when_connector_is_inactive(): void {
		$meta = array(
			'status'    => 'inactive',
			'workflows' => array( 'products' => 'yes', 'orders' => 'yes' ),
		);

		$this->assertFalse( HELPER::is_workflow_enabled_for_connector( $meta, 'products' ) );
		$this->assertFalse( HELPER::is_workflow_enabled_for_connector( $meta, 'orders' ) );
	}

	/**
	 * Missing status/workflows keys default to active/enabled (single-connector
	 * legacy setups do not always have these keys populated).
	 *
	 * @return void
	 */
	public function test_is_workflow_enabled_defaults_to_true_when_keys_are_missing(): void {
		$this->assertTrue( HELPER::is_workflow_enabled_for_connector( array(), 'products' ) );
		$this->assertTrue( HELPER::is_workflow_enabled_for_connector( array( 'status' => 'active' ), 'orders' ) );
	}

	/**
	 * Non-array connector meta (e.g. connector not found, caller passes null) is
	 * treated as not enabled — this is what guards resolve_connector() against an
	 * unknown connector_id.
	 *
	 * @return void
	 */
	public function test_is_workflow_enabled_returns_false_for_non_array_meta(): void {
		$this->assertFalse( HELPER::is_workflow_enabled_for_connector( null, 'products' ) );
	}

	/**
	 * An empty (but valid) meta array defaults like any other missing keys: enabled.
	 *
	 * @return void
	 */
	public function test_is_workflow_enabled_defaults_to_true_for_empty_array_meta(): void {
		$this->assertTrue( HELPER::is_workflow_enabled_for_connector( array(), 'products' ) );
	}
}

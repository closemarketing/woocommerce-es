<?php
/**
 * Class WebhookProductSyncTest
 *
 * Command: composer test -- --filter=WebhookProductSyncTest
 *
 * @package Connect_Ecommerce
 */

use CLOSE\ConnectEcommerce\Helpers\WEBHOOK;

require_once UNIT_TESTS_DATA_PLUGIN_DIR . 'webhook-test-connectors.php';

/**
 * Syncs a product from a webhook payload.
 *
 * @group woocommerce
 */
class WebhookProductSyncTest extends WP_UnitTestCase {
	/**
	 * Tear down.
	 */
	public function tearDown(): void {
		delete_option( WEBHOOK::OPTION_LOGS );
		parent::tearDown();
	}

	/**
	 * A complete translated payload creates the product without a second API request.
	 */
	public function test_complete_payload_creates_product_without_api_request() {
		$connapi   = new Webhook_Test_Holded_Connector();
		$connector = array(
			'id'          => 'holded',
			'connector'   => 'holded',
			'meta'        => array(
				'status'    => 'active',
				'workflows' => array( 'products' => 'yes' ),
			),
			'settings'    => array(
				'stock'      => 'no',
				'prodst'     => 'publish',
				'tax_option' => 'no',
				'rates'      => 'default',
			),
			'connapi_erp' => $connapi,
		);
		$payload   = json_decode( file_get_contents( UNIT_TESTS_DATA_PLUGIN_DIR . 'webhook-holded-product.json' ), true );

		$result = WEBHOOK::process( $connector, $payload );

		$this->assertSame( 'ok', $result['status'], $result['message'] );
		$this->assertSame( 'payload', $result['source'] );
		$this->assertSame( array(), $connapi->requested );

		$product = wc_get_product( $result['post_id'] );
		$this->assertInstanceOf( WC_Product::class, $product );
		$this->assertSame( '234234234', $product->get_sku() );
		$this->assertSame( 'prueba producto webhook', $product->get_name() );
		$this->assertEquals( 10, (float) $product->get_regular_price() );
	}

	/**
	 * Real Holded lifecycle: create without SKU, update with SKU, delete.
	 */
	public function test_holded_lifecycle_create_update_delete() {
		$connapi   = new Webhook_Test_Holded_Connector();
		$connector = array(
			'id'          => 'holded',
			'connector'   => 'holded',
			'meta'        => array(
				'status'    => 'active',
				'workflows' => array( 'products' => 'yes' ),
			),
			'settings'    => array(
				'stock'      => 'no',
				'prodst'     => 'publish',
				'tax_option' => 'no',
				'rates'      => 'default',
			),
			'connapi_erp' => $connapi,
		);
		$create    = json_decode( file_get_contents( UNIT_TESTS_DATA_PLUGIN_DIR . 'webhook-holded-product-create.json' ), true );
		$delete    = json_decode( file_get_contents( UNIT_TESTS_DATA_PLUGIN_DIR . 'webhook-holded-product-delete.json' ), true );

		// 1. Created in Holded without SKU: nothing is created yet.
		$result = WEBHOOK::process( $connector, $create, array( 'x_holded_webhook_event' => 'product.create' ) );
		$this->assertSame( 'ignored', $result['status'] );

		// 2. Updated with a SKU: the product is created from the payload.
		$update        = $create;
		$update['sku'] = 'BORRAR-1';
		$update['price'] = '5';
		$result        = WEBHOOK::process( $connector, $update, array( 'x_holded_webhook_event' => 'product.update' ) );
		$this->assertSame( 'ok', $result['status'], $result['message'] );
		$post_id = $result['post_id'];
		$this->assertSame( '6ac0d41e5da01d285801216c', wc_get_product( $post_id )->get_meta( 'connect_ecommerce_id' ) );

		// 3. Deleted in Holded: found by ERP ID (the payload has no SKU), kept by default.
		$result = WEBHOOK::process( $connector, $delete, array( 'x_holded_webhook_event' => 'product.delete' ) );
		$this->assertSame( 'ok', $result['status'] );
		$this->assertSame( $post_id, $result['post_id'] );
		$this->assertSame( 'publish', get_post_status( $post_id ) );

		// With the filter, the product goes to draft.
		$draft = function () {
			return 'draft';
		};
		add_filter( 'conecom_webhook_delete_behaviour', $draft );
		WEBHOOK::process( $connector, $delete, array( 'x_holded_webhook_event' => 'product.delete' ) );
		remove_filter( 'conecom_webhook_delete_behaviour', $draft );
		$this->assertSame( 'draft', get_post_status( $post_id ) );
		$this->assertSame( array(), $connapi->requested );
	}

	/**
	 * Remote-ID lookups are scoped to the connector: the same remote ID in another
	 * connector is never touched, and untagged products are only used when unambiguous.
	 */
	public function test_delete_lookup_is_scoped_to_the_connector() {
		$delete = json_decode( file_get_contents( UNIT_TESTS_DATA_PLUGIN_DIR . 'webhook-holded-product-delete.json' ), true );
		$draft  = function () {
			return 'draft';
		};
		add_filter( 'conecom_webhook_delete_behaviour', $draft );

		$mine   = self::factory()->post->create( array( 'post_type' => 'product', 'post_status' => 'publish' ) );
		$theirs = self::factory()->post->create( array( 'post_type' => 'product', 'post_status' => 'publish' ) );
		foreach ( array( $mine => 'holded', $theirs => 'odoo' ) as $post_id => $owner ) {
			update_post_meta( $post_id, 'connect_ecommerce_id', $delete['id'] );
			update_post_meta( $post_id, WEBHOOK::META_CONNECTOR, $owner );
		}

		$result = WEBHOOK::process( $this->connector_context( 'holded' ), $delete, array( 'x_holded_webhook_event' => 'product.delete' ) );
		$this->assertSame( $mine, $result['post_id'] );
		$this->assertSame( 'draft', get_post_status( $mine ) );
		$this->assertSame( 'publish', get_post_status( $theirs ) );

		// Untagged product of another connector with the same remote ID: ambiguous on a multi-connector site.
		delete_post_meta( $theirs, WEBHOOK::META_CONNECTOR );
		update_option(
			'connect_ecommerce',
			array(
				'connector'       => 'holded',
				'connectors_meta' => array(
					'holded' => array( 'type' => 'holded' ),
					'odoo'   => array( 'type' => 'odoo' ),
				),
			)
		);
		wp_delete_post( $mine, true );
		$result = WEBHOOK::process( $this->connector_context( 'holded' ), $delete, array( 'x_holded_webhook_event' => 'product.delete' ) );
		$this->assertSame( 'ignored', $result['status'] );
		$this->assertSame( 'publish', get_post_status( $theirs ) );

		remove_filter( 'conecom_webhook_delete_behaviour', $draft );
		delete_option( 'connect_ecommerce' );
	}

	/**
	 * Connector context with the Holded test translator.
	 *
	 * @param string $connector_id Connector ID.
	 * @return array
	 */
	private function connector_context( $connector_id ) {
		return array(
			'id'          => $connector_id,
			'connector'   => 'holded',
			'meta'        => array(
				'status'    => 'active',
				'workflows' => array( 'products' => 'yes' ),
			),
			'settings'    => array(),
			'connapi_erp' => new Webhook_Test_Holded_Connector(),
		);
	}

	/**
	 * A product excluded by the connector filters is not claimed by the connector.
	 */
	public function test_filtered_product_is_not_claimed() {
		$post_id = self::factory()->post->create( array( 'post_type' => 'product', 'post_status' => 'publish' ) );
		update_post_meta( $post_id, '_sku', 'EXCLUDED-1' );

		$connector             = $this->connector_context( 'holded' );
		$connector['settings'] = array( 'filter_sku' => 'ONLY-*' );
		$payload               = array(
			'id'    => 'erp-excluded',
			'name'  => 'Excluded',
			'kind'  => 'simple',
			'sku'   => 'EXCLUDED-1',
			'price' => '1',
		);

		WEBHOOK::process( $connector, $payload, array( 'x_holded_webhook_event' => 'product.update' ) );

		$this->assertSame( '', get_post_meta( $post_id, WEBHOOK::META_CONNECTOR, true ) );
	}

	/**
	 * A SKU changed in the ERP updates the existing product (found by remote ID) instead of duplicating it.
	 */
	public function test_sku_change_updates_existing_product() {
		$connector = $this->connector_context( 'holded' );
		$payload   = array(
			'id'    => 'erp-sku-change',
			'name'  => 'Renamed SKU',
			'kind'  => 'simple',
			'sku'   => 'OLD-SKU',
			'price' => '4',
		);

		$first = WEBHOOK::process( $connector, $payload, array( 'x_holded_webhook_event' => 'product.update' ) );
		$this->assertSame( 'ok', $first['status'], $first['message'] );

		$payload['sku'] = 'NEW-SKU';
		$second         = WEBHOOK::process( $connector, $payload, array( 'x_holded_webhook_event' => 'product.update' ) );

		$this->assertSame( $first['post_id'], $second['post_id'] );
		$this->assertSame( 'NEW-SKU', wc_get_product( $first['post_id'] )->get_sku() );
	}

	/**
	 * A delete carrying a SKU never touches another connector's product with the same SKU.
	 */
	public function test_sku_delete_is_scoped_to_the_connector() {
		$theirs = self::factory()->post->create( array( 'post_type' => 'product', 'post_status' => 'publish' ) );
		update_post_meta( $theirs, '_sku', 'SHARED-SKU' );
		update_post_meta( $theirs, 'connect_ecommerce_id', 'odoo-1' );
		update_post_meta( $theirs, WEBHOOK::META_CONNECTOR, 'odoo' );
		$draft = function () {
			return 'draft';
		};
		add_filter( 'conecom_webhook_delete_behaviour', $draft );

		$connapi   = new class() extends Webhook_Test_Connector {
			/**
			 * Delete with SKU.
			 *
			 * @param array $payload Payload.
			 * @param array $headers Headers.
			 * @return array
			 */
			public function parse_webhook_product( $payload, $headers = array() ) {
				return array(
					'action' => 'delete',
					'id'     => 'holded-1',
					'item'   => array( 'sku' => 'SHARED-SKU' ),
				);
			}
		};
		$connector = $this->connector_context( 'holded' );

		$connector['connapi_erp'] = $connapi;
		$result                   = WEBHOOK::process( $connector, array( 'id' => 'holded-1' ) );
		remove_filter( 'conecom_webhook_delete_behaviour', $draft );

		$this->assertSame( 'ignored', $result['status'] );
		$this->assertSame( 'publish', get_post_status( $theirs ) );
	}
}

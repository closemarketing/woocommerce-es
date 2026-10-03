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
}

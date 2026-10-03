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
}

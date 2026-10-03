<?php
/**
 * Tests the product webhook parsing and routing.
 *
 * Command: composer test -- --filter=WebhookTest
 *
 * @package Connect_Ecommerce
 */

use CLOSE\ConnectEcommerce\Connector\CONECOM_Abstract_Connector_API;
use CLOSE\ConnectEcommerce\Helpers\WEBHOOK;

require_once UNIT_TESTS_DATA_PLUGIN_DIR . 'webhook-test-connectors.php';

/**
 * Class WebhookTest.
 */
class WebhookTest extends WP_UnitTestCase {
	/**
	 * Tear down.
	 */
	public function tearDown(): void {
		delete_option( WEBHOOK::OPTION_TOKENS );
		delete_option( WEBHOOK::OPTION_SECRETS );
		delete_option( WEBHOOK::OPTION_LOGS );
		parent::tearDown();
	}

	/**
	 * Builds a connector context like HELPER::get_connector_by_id().
	 *
	 * @param object $connapi_erp Connector API.
	 * @param array  $meta        Connector meta.
	 * @return array
	 */
	private function connector( $connapi_erp, $meta = array() ) {
		return array(
			'id'          => 'holded',
			'connector'   => 'holded',
			'meta'        => array_merge(
				array(
					'type'      => 'holded',
					'status'    => 'active',
					'workflows' => array(
						'products' => 'yes',
						'orders'   => 'yes',
					),
				),
				$meta
			),
			'settings'    => array(),
			'connapi_erp' => $connapi_erp,
		);
	}

	/**
	 * Loads the Holded webhook fixture.
	 *
	 * @return array
	 */
	private function holded_payload() {
		return json_decode( file_get_contents( UNIT_TESTS_DATA_PLUGIN_DIR . 'webhook-holded-product.json' ), true );
	}

	/**
	 * The generic parser supports ?id=N, {"id": N} and nested payloads.
	 */
	public function test_extract_product_id_formats() {
		$this->assertSame( '15', WEBHOOK::extract_product_id( array( 'id' => 15 ) ) );
		$this->assertSame( 'abc', WEBHOOK::extract_product_id( array( 'product_id' => 'abc' ) ) );
		$this->assertSame( '7', WEBHOOK::extract_product_id( array( 'data' => array( 'id' => 7 ) ) ) );
		$this->assertSame( '', WEBHOOK::extract_product_id( array( 'foo' => 'bar' ) ) );
	}

	/**
	 * Completeness is detected from the item when the connector does not declare it.
	 */
	public function test_is_item_complete() {
		$this->assertFalse( WEBHOOK::is_item_complete( array( 'id' => '1' ) ) );
		$this->assertTrue(
			WEBHOOK::is_item_complete(
				array(
					'id'   => '1',
					'name' => 'Product',
					'sku'  => 'SKU',
				)
			)
		);
		$this->assertFalse(
			WEBHOOK::is_item_complete(
				array(
					'id'   => '1',
					'name' => 'Product',
					'kind' => 'variants',
				)
			)
		);
		$this->assertFalse(
			WEBHOOK::is_item_complete(
				array(
					'id'   => '1',
					'name' => 'Product',
					'sku'  => 'SKU',
				),
				false
			)
		);
	}

	/**
	 * Descriptions keep their HTML, other strings are sanitized and types are kept.
	 */
	public function test_sanitize_item_keeps_description_html() {
		$item = WEBHOOK::sanitize_item(
			array(
				'name'  => '<b>Name</b>',
				'desc'  => '<p>Text</p><script>alert(1)</script>',
				'price' => 10.5,
				'sku'   => null,
			)
		);
		$this->assertSame( 'Name', $item['name'] );
		$this->assertSame( '<p>Text</p>alert(1)', $item['desc'] );
		$this->assertSame( 10.5, $item['price'] );
		$this->assertNull( $item['sku'] );
	}

	/**
	 * A connector with a translator turns the Holded webhook into the universal item.
	 */
	public function test_holded_translator_returns_universal_item() {
		$parsed = WEBHOOK::parse_payload( $this->connector( new Webhook_Test_Holded_Connector() ), $this->holded_payload() );

		$this->assertSame( 'upsert', $parsed['action'] );
		$this->assertSame( '6ac0c0726e2bde0e6408ad90', $parsed['id'] );
		$this->assertArrayHasKey( 'desc', $parsed['item'] );
		$this->assertArrayNotHasKey( 'description', $parsed['item'] );
		$this->assertSame( array(), $parsed['item']['variants'] );
		$this->assertTrue( WEBHOOK::is_item_complete( $parsed['item'], $parsed['complete'] ) );
	}

	/**
	 * Without a translator, the core falls back to requesting the product by ID.
	 */
	public function test_generic_connector_requests_product_by_id() {
		$connapi = new Webhook_Test_Connector();
		$result  = WEBHOOK::process( $this->connector( $connapi ), array( 'id' => 'erp-42' ) );

		$this->assertSame( array( 'erp-42' ), $connapi->requested );
		$this->assertSame( 'error', $result['status'] );
		$this->assertSame( 'api', $result['source'] );

		$logs = WEBHOOK::get_logs( 'holded' );
		$this->assertCount( 1, $logs );
		$this->assertSame( 'webhook', $logs[0]['type'] );
		$this->assertSame( 'erp-42', $logs[0]['id'] );
	}

	/**
	 * A payload without a product ID is rejected.
	 */
	public function test_missing_product_id_is_rejected() {
		$result = WEBHOOK::process( $this->connector( new Webhook_Test_Connector() ), array( 'foo' => 'bar' ) );

		$this->assertSame( 'error', $result['status'] );
		$this->assertSame( 400, $result['code'] );
	}

	/**
	 * Connectors with the products workflow disabled ignore webhooks.
	 */
	public function test_products_workflow_disabled_is_ignored() {
		$connapi = new Webhook_Test_Connector();
		$result  = WEBHOOK::process(
			$this->connector(
				$connapi,
				array(
					'workflows' => array(
						'products' => 'no',
						'orders'   => 'yes',
					),
				)
			),
			array( 'id' => 'erp-42' )
		);

		$this->assertSame( 'ignored', $result['status'] );
		$this->assertSame( array(), $connapi->requested );
	}

	/**
	 * The endpoint rejects requests without the right token.
	 */
	public function test_permission_check_validates_token() {
		$token = WEBHOOK::get_token( 'holded' );

		$request = new WP_REST_Request( 'POST', '/conecom/v1/webhooks/products/holded' );
		$request->set_url_params( array( 'connector_id' => 'holded' ) );
		$request->set_query_params( array( 'token' => 'wrong' ) );
		$this->assertWPError( WEBHOOK::permission_check( $request ) );

		$request->set_query_params( array( 'token' => $token ) );
		$this->assertTrue( WEBHOOK::permission_check( $request ) );

		$request->set_query_params( array() );
		$request->set_header( 'X-Conecom-Token', $token );
		$this->assertTrue( WEBHOOK::permission_check( $request ) );

		WEBHOOK::regenerate_token( 'holded' );
		$this->assertWPError( WEBHOOK::permission_check( $request ) );
	}

	/**
	 * The token is not part of the payload passed to the connector.
	 */
	public function test_payload_excludes_token() {
		$request = new WP_REST_Request( 'POST', '/conecom/v1/webhooks/products/holded' );
		$request->set_query_params(
			array(
				'token' => 'secret',
				'id'    => '5',
			)
		);
		$payload = WEBHOOK::get_payload( $request );

		$this->assertSame( array( 'id' => '5' ), $payload );
	}

	/**
	 * Loads a JSON fixture.
	 *
	 * @param string $file File name in tests/Data.
	 * @return array
	 */
	private function fixture( $file ) {
		return json_decode( file_get_contents( UNIT_TESTS_DATA_PLUGIN_DIR . $file ), true );
	}

	/**
	 * The generic parser detects deletions from the event header or the payload.
	 */
	public function test_detect_action() {
		$this->assertSame( 'delete', WEBHOOK::detect_action( array( 'id' => '1' ), array( 'x_holded_webhook_event' => 'product.delete' ) ) );
		$this->assertSame( 'upsert', WEBHOOK::detect_action( array( 'id' => '1' ), array( 'x_holded_webhook_event' => 'product.update' ) ) );
		$this->assertSame( 'upsert', WEBHOOK::detect_action( array( 'id' => '1' ), array( 'x_holded_webhook_event' => 'product.create' ) ) );
		$this->assertSame( 'delete', WEBHOOK::detect_action( $this->fixture( 'webhook-holded-product-delete.json' ) ) );
		$this->assertSame( 'delete', WEBHOOK::detect_action( array( 'event' => 'product_deleted' ) ) );
		$this->assertSame( 'upsert', WEBHOOK::detect_action( array( 'event' => 'undeleted_flag' ) ) );
	}

	/**
	 * A Holded delete webhook never requests the (already deleted) product to the API.
	 */
	public function test_holded_delete_does_not_request_api() {
		$headers = array( 'x_holded_webhook_event' => 'product.delete' );
		foreach ( array( new Webhook_Test_Connector(), new Webhook_Test_Holded_Connector() ) as $connapi ) {
			$result = WEBHOOK::process( $this->connector( $connapi ), $this->fixture( 'webhook-holded-product-delete.json' ), $headers );

			$this->assertSame( 'delete', $result['action'] );
			$this->assertSame( '6ac0d41e5da01d285801216c', $result['id'] );
			$this->assertSame( 'ignored', $result['status'] );
			$this->assertSame( array(), $connapi->requested );
		}
	}

	/**
	 * A Holded product created without SKU is ignored until an update brings the SKU.
	 */
	public function test_holded_create_without_sku_is_ignored() {
		$connapi = new Webhook_Test_Holded_Connector();
		$result  = WEBHOOK::process(
			$this->connector( $connapi ),
			$this->fixture( 'webhook-holded-product-create.json' ),
			array( 'x_holded_webhook_event' => 'product.create' )
		);

		$this->assertSame( 'upsert', $result['action'] );
		$this->assertSame( 'ignored', $result['status'] );
		$this->assertSame( 'payload', $result['source'] );
		$this->assertSame( array(), $connapi->requested );
	}

	/**
	 * HMAC signatures are verified with or without the algorithm prefix.
	 */
	public function test_verify_hmac_signature() {
		$body      = '{"id":"6ac0d41e5da01d285801216c","kind":"simple","deletedAt":"2026-10-03T10:08:38+00:00"}';
		$signature = hash_hmac( 'sha256', $body, 'secret' );

		$this->assertTrue( WEBHOOK::verify_hmac_signature( $body, 'sha256=' . $signature, 'secret' ) );
		$this->assertTrue( WEBHOOK::verify_hmac_signature( $body, strtoupper( $signature ), 'secret' ) );
		$this->assertFalse( WEBHOOK::verify_hmac_signature( $body . ' ', 'sha256=' . $signature, 'secret' ) );
		$this->assertFalse( WEBHOOK::verify_hmac_signature( $body, 'sha256=' . $signature, 'other' ) );
		$this->assertFalse( WEBHOOK::verify_hmac_signature( $body, '', 'secret' ) );
	}

	/**
	 * The signing secret configured in the Webhooks tab is checked against the Holded signature.
	 */
	public function test_holded_signature_with_configured_secret() {
		$body    = file_get_contents( UNIT_TESTS_DATA_PLUGIN_DIR . 'webhook-holded-product-delete.json' );
		$payload = json_decode( $body, true );
		$secret  = 'whsec_' . str_repeat( 'ab', 32 );
		$headers = array(
			'x_holded_webhook_event'     => 'product.delete',
			'x_holded_webhook_signature' => 'sha256=' . hash_hmac( 'sha256', $body, $secret ),
		);
		$connector = $this->connector( new Webhook_Test_Holded_Connector() );

		// Without secret only the URL token protects the endpoint.
		$this->assertSame( 'ignored', WEBHOOK::process( $connector, $payload, array( 'x_holded_webhook_signature' => 'sha256=bad' ), $body )['status'] );

		WEBHOOK::save_signing_secret( 'holded', $secret );
		$this->assertSame( $secret, WEBHOOK::get_signing_secret( 'holded' ) );

		// Valid signature.
		$this->assertSame( 'ignored', WEBHOOK::process( $connector, $payload, $headers, $body )['status'] );

		// Tampered body or wrong signature.
		$result = WEBHOOK::process( $connector, $payload, $headers, $body . ' ' );
		$this->assertSame( 'error', $result['status'] );
		$this->assertSame( 401, $result['code'] );

		WEBHOOK::save_signing_secret( 'holded', '' );
		$this->assertSame( '', WEBHOOK::get_signing_secret( 'holded' ) );
	}

	/**
	 * Unsupported by default in the connector contract.
	 */
	public function test_contract_defaults() {
		$connector = new class() extends CONECOM_Abstract_Connector_API {};

		$this->assertSame( 'error', $connector->parse_webhook_product( array() )['status'] );
		$this->assertTrue( $connector->verify_webhook( '', array(), 'whsec_x' ) );
		$this->assertSame( '', $connector->get_webhook_instructions( 'https://example.com' ) );
		$this->assertFalse( $connector->supports_capability( 'parse_webhook_product' ) );
		$this->assertTrue( ( new Webhook_Test_Holded_Connector() )->supports_capability( 'parse_webhook_product' ) );
	}
}

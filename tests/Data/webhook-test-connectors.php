<?php
/**
 * Connector stubs for the webhook tests.
 *
 * @package Connect_Ecommerce
 */

use CLOSE\ConnectEcommerce\Connector\CONECOM_Abstract_Connector_API;
use CLOSE\ConnectEcommerce\Helpers\WEBHOOK;

/**
 * Connector stub that records API requests.
 */
class Webhook_Test_Connector extends CONECOM_Abstract_Connector_API {
	/**
	 * Product IDs requested to the API.
	 *
	 * @var array
	 */
	public $requested = array();

	/**
	 * Gets products from the remote API.
	 *
	 * @param string|int|null $product_id Remote product ID.
	 * @param string|int|null $period     Period.
	 * @return array
	 */
	public function get_products( $product_id = null, $period = null ) {
		$this->requested[] = $product_id;
		return array(
			'status'  => 'error',
			'message' => 'stub',
		);
	}
}

/**
 * Connector stub that translates the Holded webhook into the universal item.
 */
class Webhook_Test_Holded_Connector extends Webhook_Test_Connector {
	/**
	 * Translates the Holded webhook payload.
	 *
	 * @param array $payload Payload.
	 * @param array $headers Headers.
	 * @return array
	 */
	public function parse_webhook_product( $payload, $headers = array() ) {
		// Holded sends the event in the header: product.create, product.update or product.delete.
		$event = $headers['x_holded_webhook_event'] ?? '';
		if ( 'product.delete' === $event || ! empty( $payload['deletedAt'] ) ) {
			return array(
				'action' => 'delete',
				'id'     => $payload['id'] ?? '',
			);
		}

		$item = $payload;
		// Holded webhooks send "description" while the API (universal item) uses "desc".
		$item['desc'] = $payload['description'] ?? '';
		unset( $item['description'] );
		// Simple products carry a default variant with empty values: drop it.
		if ( 'simple' === ( $item['kind'] ?? '' ) ) {
			$item['variants'] = array();
		}

		return array(
			'action'   => 'upsert',
			'id'       => $payload['id'] ?? '',
			'item'     => $item,
			'complete' => true,
		);
	}

	/**
	 * Verifies the Holded signature: HMAC-SHA256 of the raw body with the full
	 * "whsec_..." secret, sent as "X-Holded-Webhook-Signature: sha256=<hex>".
	 *
	 * @param string $raw_body Raw body.
	 * @param array  $headers  Headers.
	 * @param string $secret   Signing secret.
	 * @return bool
	 */
	public function verify_webhook( $raw_body, $headers = array(), $secret = '' ) {
		if ( '' === $secret ) {
			return true;
		}

		return WEBHOOK::verify_hmac_signature( $raw_body, $headers['x_holded_webhook_signature'] ?? '', $secret );
	}
}

<?php
/**
 * Connector stubs for the webhook tests.
 *
 * @package Connect_Ecommerce
 */

use CLOSE\ConnectEcommerce\Connector\CONECOM_Abstract_Connector_API;

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
}

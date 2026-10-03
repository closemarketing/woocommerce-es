<?php
/**
 * Connector API contract.
 *
 * @package WordPress
 * @author Closetechnology
 */

namespace CLOSE\ConnectEcommerce\Connector;

defined( 'ABSPATH' ) || exit;

/**
 * Defines the API contract for ERP and CRM connectors.
 *
 * Connector add-ons should extend this class and override only the capabilities
 * they support. Every method the core can call is declared here. The defaults
 * deliberately report an unsupported capability or return an empty value so a
 * missing optional API feature never interrupts a synchronization.
 *
 * @since 3.4.1
 */
abstract class CONECOM_Abstract_Connector_API {
	/**
	 * Checks whether a connector overrides an optional contract method.
	 *
	 * @param string $method Method name.
	 * @return bool
	 */
	public function supports_capability( $method ) {
		if ( ! method_exists( $this, $method ) ) {
			return false;
		}

		$reflection = new \ReflectionMethod( $this, $method );
		return self::class !== $reflection->getDeclaringClass()->getName();
	}

	/**
	 * Checks whether the API credentials can synchronize data.
	 *
	 * @param array $settings Optional settings to validate before saving them.
	 * @return array{status: string, message: string} Example: array( 'status' => 'ok', 'message' => 'Connected.' ).
	 */
	public function check_can_sync( $settings = array() ) {
		unset( $settings );
		return $this->unsupported_capability( 'connection validation' );
	}

	/**
	 * Gets products from the remote API.
	 *
	 * @param string|int|null $product_id Remote product ID.
	 * @param string|int|null $period     Pagination cursor or synchronization period.
	 * @return array{id: string|int, name: string, sku: string, price: float|int, kind: string, full_info: array}|array<int, array{id: string|int, name: string, sku: string, price: float|int, kind: string, full_info: array}>|array{status: string, message: string} A single item when $product_id is not null, a catalogue otherwise, or an error response.
	 * @example array( array( 'id' => 'erp-123', 'name' => 'Product', 'sku' => 'SKU-123', 'price' => 12.5, 'kind' => 'simple', 'full_info' => array() ) ).
	 */
	public function get_products( $product_id = null, $period = null ) {
		unset( $product_id, $period );
		return array();
	}

	/**
	 * Gets products that have changed since a remote timestamp.
	 *
	 * @param string $modified_since_date Remote timestamp.
	 * @return array<int, string|int> Example: array( 'erp-123', 'erp-456' ).
	 */
	public function get_products_ids_since( $modified_since_date ) {
		unset( $modified_since_date );
		return array();
	}

	/**
	 * Gets remote stock changes.
	 *
	 * @param string|int|null $period Synchronization period.
	 * @return false|array<int, array{id: string|int, stock: float|int}> Example: array( array( 'id' => 'erp-123', 'stock' => 10 ) ).
	 */
	public function get_products_stock( $period = null ) {
		unset( $period );
		return false;
	}

	/**
	 * Gets a remote product by SKU.
	 *
	 * @param string $sku Product SKU.
	 * @return array{id: string|int, name: string, sku: string, price: float|int, kind: string, full_info: array}|array{status: string, message: string} A product, or an error response.
	 */
	public function get_product_by_sku( $sku ) {
		unset( $sku );
		return $this->unsupported_capability( 'product SKU lookup' );
	}

	/**
	 * Gets all remote product SKUs for import statistics.
	 *
	 * @return array{status: string, data: array<int, string>}|array{status: string, message: string} Example: array( 'status' => 'ok', 'data' => array( 'SKU-123' ) ).
	 */
	public function get_all_product_skus() {
		return $this->unsupported_capability( 'product import statistics' );
	}

	/**
	 * Creates an order in the remote API.
	 *
	 * @param array       $order      Normalized WooCommerce order data.
	 * @param string|int  $doc_id     Remote document ID.
	 * @param string|int  $invoice_id Existing remote invoice ID.
	 * @param bool|string $force      Whether to force a resend.
	 * @return array{status: 'ok', message: string, document_id: string|int, invoice_id?: string|int}|array{status: 'error', message: string} Example: array( 'status' => 'ok', 'message' => 'Order sent.', 'document_id' => '123' ).
	 */
	public function create_order( $order, $doc_id = '', $invoice_id = '', $force = false ) {
		unset( $order, $doc_id, $invoice_id, $force );
		return $this->unsupported_capability( 'order creation' );
	}

	/**
	 * Gets payment methods from the remote API.
	 *
	 * @return array<string, string> Example: array( 'paymentmethods|bank' => 'Bank transfer' ).
	 */
	public function get_payment_methods() {
		return array();
	}

	/**
	 * Gets treasury accounts from the remote API for payment mappings.
	 *
	 * @return array<string, string> Example: array( 'bank-1' => 'Main bank account' ).
	 */
	public function get_treasury_accounts() {
		return array();
	}

	/**
	 * Gets price rates from the remote API.
	 *
	 * @return array<string, string> Example: array( 'general' => 'General rate' ).
	 */
	public function get_rates() {
		return array();
	}

	/**
	 * Gets tax types from the remote API.
	 *
	 * @return array<int, array{id: string|int, name: string, rate?: float|int}> Example: array( array( 'id' => 'vat-21', 'name' => 'VAT 21%', 'rate' => 21 ) ).
	 */
	public function get_taxes() {
		return array();
	}

	/**
	 * Gets companies from the remote API.
	 *
	 * @return array{status: 'ok', data: array<string, string>}|array{status: 'error', message: string} Example: array( 'status' => 'ok', 'data' => array( 'company-1' => 'Main company' ) ).
	 */
	public function get_companies() {
		return array();
	}

	/**
	 * Gets remote document series for an order type.
	 *
	 * @param string $type Order document type.
	 * @return array<string, string> Example: array( 'A' => 'Series A' ).
	 */
	public function get_series_number( $type ) {
		unset( $type );
		return array();
	}

	/**
	 * Gets the attributes available from the remote API.
	 *
	 * @return array<string, string> Example: array( 'brand' => 'Brand' ).
	 */
	public function get_attributes() {
		return '';
	}

	/**
	 * Gets product fields available for merge variables.
	 *
	 * @return array<string, string> Example: array( 'factoryCode' => 'Factory code' ).
	 */
	public function get_product_attributes() {
		return array();
	}

	/**
	 * Gets an image for a product when the product response has no image data.
	 *
	 * @param array      $settings      Connector settings.
	 * @param string|int $product_id    Remote product ID.
	 * @param int        $attachment_id WordPress product ID.
	 * @return string|array{upload: array{url: string, file?: string, content_type?: string}}|array{errors: array<int, array{message: string}>} Empty when unsupported, or an upload/error response.
	 * @example array( 'upload' => array( 'url' => 'https://example.com/product.jpg', 'content_type' => 'image/jpeg' ) ).
	 */
	public function get_image_product( $settings = array(), $product_id = '', $attachment_id = 0 ) {
		unset( $settings, $product_id, $attachment_id );
		return '';
	}

	/**
	 * Gets the remote API URL for an order.
	 *
	 * @param \WC_Order $order WooCommerce order object.
	 * @return string Example: 'https://erp.example.com/orders/123'.
	 */
	public function get_url_link_api( $order = array() ) {
		unset( $order );
		return '';
	}

	/**
	 * Gets the PDF document for a remote order.
	 *
	 * @param array      $settings Connector settings.
	 * @param string     $type Remote document type.
	 * @param string|int $doc_id Remote document ID.
	 * @return string Absolute PDF file path, or an empty string when unsupported.
	 */
	public function get_order_pdf( $settings = array(), $type = '', $doc_id = '' ) {
		unset( $settings, $type, $doc_id );
		return '';
	}

	/**
	 * Translates an incoming product webhook into the universal product item.
	 *
	 * The universal item is the structure returned by get_products() for a single
	 * product (historically the Holded product format): id, name, desc, kind, sku,
	 * barcode, price, cost, stock, taxes, tags, attributes, images, variants and
	 * packItems. Map as much of the webhook payload as possible so the core can
	 * synchronize the product without a second API request. When the payload does
	 * not carry enough data, return only the remote ID (or set 'complete' to false)
	 * and the core will call get_products( $id ) as a fallback.
	 *
	 * @since 3.5.1
	 *
	 * @param array $payload Decoded webhook body merged with the query parameters (the token is removed).
	 * @param array $headers Request headers, keys lowercased with underscores (e.g. 'x_holded_webhook_event').
	 *                       Empty when the request is authenticated by signature (headers are not signed).
	 * @return array{action: string, id: string|int, item?: array, complete?: bool}|array{status: string, message: string}
	 *         'action' is 'upsert', 'delete' or 'ignore'. 'item' is the universal product item. 'complete' tells
	 *         the core whether 'item' can be synced as is (true) or must be fetched with get_products() (false).
	 * @example array( 'action' => 'upsert', 'id' => 'erp-123', 'complete' => true, 'item' => array( 'id' => 'erp-123', 'name' => 'Product', 'kind' => 'simple', 'sku' => 'SKU-123', 'price' => 10 ) ).
	 */
	public function parse_webhook_product( $payload, $headers = array() ) {
		unset( $payload, $headers );
		return $this->unsupported_capability( 'product webhooks' );
	}

	/**
	 * Verifies the authenticity of a webhook request (e.g. an HMAC signature header).
	 *
	 * Override this method only when the remote API signs its webhook deliveries.
	 * It is called only once a signing secret is saved in the Webhooks tab (e.g.
	 * Holded's "whsec_..." key). From then on it is the SOLE authentication of the
	 * endpoint: the URL token is no longer required, so it must return false for any
	 * missing or invalid signature. Use WEBHOOK::verify_hmac_signature() for HMAC.
	 * Headers are not covered by the signature, so in this mode
	 * parse_webhook_product() receives no headers: derive the action from the body.
	 *
	 * @since 3.5.1
	 *
	 * @param string $raw_body Raw request body.
	 * @param array  $headers  Request headers, keys lowercased with underscores.
	 * @param string $secret   Signing secret configured for this connector, empty when not set.
	 * @return bool True when the request is authentic.
	 */
	public function verify_webhook( $raw_body, $headers = array(), $secret = '' ) {
		unset( $raw_body, $headers, $secret );
		return true;
	}

	/**
	 * Gets the instructions shown in the Webhooks tab to configure the webhook in the remote API.
	 *
	 * @since 3.5.1
	 *
	 * @param string $webhook_url Webhook URL (token included) that the remote API must call.
	 * @return string HTML instructions, or an empty string to show the generic ones.
	 */
	public function get_webhook_instructions( $webhook_url = '' ) {
		unset( $webhook_url );
		return '';
	}

	/**
	 * Indicates whether the remote API can report changed products.
	 *
	 * @return bool True only when get_products_ids_since() is implemented.
	 */
	public function has_product_updated() {
		return false;
	}

	/**
	 * Returns a standardized response for unsupported core capabilities.
	 *
	 * @param string $capability Capability name.
	 * @return array
	 */
	protected function unsupported_capability( $capability ) {
		return array(
			'status'  => 'error',
			'message' => sprintf(
				/* translators: %s: connector capability. */
				__( 'This connector does not support %s.', 'woocommerce-es' ),
				$capability
			),
		);
	}
}

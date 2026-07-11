<?php
/**
 * Class Brevo Connector
 *
 * @package    WordPress
 * @author     David Perez <david@closemarketing.es>
 * @copyright  2026 Closemarketing
 * @version    1.0
 */

defined( 'ABSPATH' ) || exit;

/**
 * LoadsAPI.
 *
 * API Brevo.
 *
 * @since 1.0
 */
class Connect_Ecommerce_Brevo {
	/**
	 * Options of plugin.
	 *
	 * @var array
	 */
	private $settings;

	/**
	 * Options of plugin.
	 *
	 * @var array
	 */
	private $options;

	/**
	 * Constructor.
	 *
	 * @param array $options Options of plugin.
	 */
	public function __construct( $options ) {
		$this->options  = $options['brevo'];
		$this->settings = get_option( 'connect_ecommerce' )['brevo'] ?? array();
	}

	/**
	 * Checks if can sync
	 *
	 * @return boolean
	 */
	public function check_can_sync() {
		if ( ! isset( $this->settings['api'] ) ) {
			return false;
		}
		$result_login = $this->api( 'account', $this->settings['api'] );
		if ( 'error' === $result_login['status'] ) {
			return false;
		}
		return true;
	}

	/**
	 * Compatibility for Library
	 *
	 * @return string
	 */
	public function get_attributes() {
		return '';
	}

	/**
	 * Compatibility for Library
	 *
	 * @return string
	 */
	public function get_image_product() {
		return '';
	}

	/**
	 * URL for orders.
	 *
	 * @return string
	 */
	public function get_url_link_api() {
		return '';
	}

	/**
	 * Gets taxes from Brevo/API
	 *
	 * @return array Array of taxes with id, name, and code.
	 */
	public function get_taxes() {
		// Brevo has no taxes endpoint, it is only used for email marketing.
		return array();
	}

	/**
	 * Gets information from Brevo products
	 *
	 * @param string $id Id of product to get information.
	 * @param string $period Date to get YYYYMMDD.
	 * @return array Array of products imported via API.
	 */
	public function get_products( $id = null, $period = null ) {
		// Brevo has no product catalog, only orders are synced.
		return array();
	}

	/**
	 * Gets stock from Brevo products
	 *
	 * @param string $period Date YYYYMMDD for syncs.
	 * @return array Array of products imported via API.
	 */
	public function get_products_stock( $period = null ) {
		return false;
	}

	/**
	 * Gets information from Brevo
	 *
	 * @param string $endpoint Endpoint of API.
	 * @param string $apikey API Key of Brevo.
	 * @param string $method Method of API.
	 * @param array  $body Body of API.
	 *
	 * @return array
	 */
	private function api( $endpoint, $apikey, $method = 'GET', $body = array() ) {
		if ( ! $apikey ) {
			return array(
				'status' => 'error',
				'data'   => 'No API Key',
			);
		}
		$args = array(
			'method'  => $method,
			'headers' => array(
				'Content-Type' => 'application/json',
				'Accept'       => 'application/json',
				'api-key'      => $apikey,
			),
			'timeout' => 60,
		);
		if ( ! empty( $body ) ) {
			$args['body'] = wp_json_encode( $body );
		}
		$url        = 'https://api.brevo.com/v3/' . $endpoint;
		$result_api = wp_remote_request( $url, $args );

		if ( is_wp_error( $result_api ) ) {
			return array(
				'status' => 'error',
				'data'   => $result_api->get_error_message(),
			);
		}
		$code      = (int) wp_remote_retrieve_response_code( $result_api );
		$data      = json_decode( wp_remote_retrieve_body( $result_api ), true );
		$round_100 = (int) round( $code / 100, 0 );

		if ( 2 === $round_100 ) {
			return array(
				'status' => 'ok',
				'data'   => $data,
			);
		}

		$message = is_array( $data ) && isset( $data['message'] ) ? $data['message'] : wp_remote_retrieve_body( $result_api );
		return array(
			'status' => 'error',
			'data'   => $message,
		);
	}

	/**
	 * Creates the order to Brevo
	 *
	 * @param array  $order Order prepared to API.
	 * @param string $doc_id Document ID.
	 * @param string $invoice_id Invoice ID.
	 * @param string $force Force to create the order.
	 *
	 * @return array
	 */
	public function create_order( $order, $doc_id, $invoice_id, $force ) {
		$api_key = ! empty( $this->settings['api'] ) ? $this->settings['api'] : '';

		if ( empty( $order ) ) {
			return array(
				'status'  => 'error',
				'message' => __( 'Error order not found in WooCommerce.', 'woocommerce-es' ),
			);
		}

		$email = $order['contactEmail'] ?? '';
		if ( empty( $email ) ) {
			return array(
				'status'  => 'error',
				'message' => __( 'Error email not found in the order.', 'woocommerce-es' ),
			);
		}

		// Create or update the contact in Brevo.
		$brevo_contact = array(
			'email'         => $email,
			'attributes'    => array(
				'FIRSTNAME' => $order['contactFirstName'] ?? '',
				'LASTNAME'  => $order['contactLastName'] ?? '',
				'SMS'       => $order['contact_phone'] ?? '',
			),
			'updateEnabled' => true,
		);

		if ( ! empty( $this->settings['order_tags'] ) ) {
			$brevo_contact['attributes']['TAGS'] = $this->settings['order_tags'];
		}

		$result_contact = $this->api( 'contacts', $api_key, 'POST', $brevo_contact );
		if ( 'error' === $result_contact['status'] ) {
			$message_data = is_array( $result_contact['data'] ) ? wp_json_encode( $result_contact['data'] ) : $result_contact['data'];
			return array(
				'status'  => 'error',
				'message' => __( 'Error creating the contact in Brevo. Error: ', 'woocommerce-es' ) . $message_data,
			);
		}

		// Products are only sent as order event data, Brevo has no product catalog to keep in sync.
		$products = array();
		foreach ( $order['items'] as $item ) {
			$products[] = array(
				'name'     => $item['name'] ?? '',
				'sku'      => $item['sku'] ?? '',
				'quantity' => $item['units'] ?? 0,
				'price'    => $item['subtotal'] ?? 0,
			);
		}

		$order_id    = $order['woocommerceReference'] ?? $order['woocommerceOrderId'];
		$order_event = array(
			'event_name'        => 'order_completed',
			'identifiers'       => array(
				'email_id' => $email,
			),
			'event_properties'  => array(
				'order_id'  => $order_id,
				'total'     => $order['total'] ?? 0,
				'currency'  => $order['currency'] ?? 'EUR',
				'order_url' => $order['woocommerceOrderEdit'] ?? '',
				'products'  => $products,
			),
		);

		$result_event = $this->api( 'events', $api_key, 'POST', $order_event );
		if ( 'error' === $result_event['status'] ) {
			$message_data = is_array( $result_event['data'] ) ? wp_json_encode( $result_event['data'] ) : $result_event['data'];
			return array(
				'status'      => 'error',
				'message'     => __( 'Order error syncing with Brevo. Error: ', 'woocommerce-es' ) . $message_data,
				'document_id' => '',
				'invoice_id'  => '',
			);
		}

		return array(
			'status'      => 'ok',
			'message'     => __( 'The order was sent correctly to Brevo', 'woocommerce-es' ) . ' ' . $order_id,
			'document_id' => $order_id,
			'invoice_id'  => $order_id,
		);
	}
}

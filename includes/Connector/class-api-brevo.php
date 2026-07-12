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
	 * Normalizes a phone number for Brevo's SMS attribute
	 *
	 * Brevo validates the SMS attribute as an international number in E.164
	 * format, while WooCommerce commonly stores local formats. Numbers that
	 * are not already E.164 are omitted instead of sent, so they cannot make
	 * the whole contact upsert fail.
	 *
	 * @param string $phone Phone number as stored in the order.
	 * @return string
	 */
	private function normalize_phone_e164( $phone ) {
		$phone = trim( (string) $phone );
		if ( '' === $phone ) {
			return '';
		}
		return preg_match( '/^\+[1-9]\d{6,14}$/', $phone ) ? $phone : '';
	}

	/**
	 * Builds the products list for the Brevo order event
	 *
	 * Brevo limits the size of event properties, so long product names/SKUs
	 * are truncated and, if the encoded payload is still too large, the
	 * tail of the list is dropped rather than letting the whole request fail.
	 *
	 * @param array $items Order items.
	 * @return array
	 */
	private function build_event_products( $items ) {
		$products = array();
		foreach ( (array) $items as $item ) {
			$products[] = array(
				'name'     => mb_substr( (string) ( $item['name'] ?? '' ), 0, 200 ),
				'sku'      => mb_substr( (string) ( $item['sku'] ?? '' ), 0, 100 ),
				'quantity' => $item['units'] ?? 0,
				'price'    => $item['subtotal'] ?? 0,
			);
		}

		$max_bytes = 8000;
		while ( count( $products ) > 1 && strlen( wp_json_encode( $products ) ) > $max_bytes ) {
			array_pop( $products );
		}

		return $products;
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

		// Only report the order to Brevo once it is really final. With "All status orders"
		// this method also runs on pending/processing/failed/refunded/cancelled transitions,
		// and the first call always gets its returned id persisted as "synced" - reporting
		// order_completed there would mark the order as done before it truly is and the
		// real completion would never be sent.
		$ecstatus = ! empty( $this->settings['ecstatus'] ) ? $this->settings['ecstatus'] : ( $this->options['order_only_order_completed'] ?? 'completed' );
		$is_final = 'completed' === ( $order['woocommerceOrderStatus'] ?? '' ) || ( 'paid' === $ecstatus && ! empty( $order['is_paid'] ) );

		if ( ! $is_final ) {
			return array(
				'status'      => 'ok',
				'message'     => __( 'Order not completed yet, Brevo sync postponed until completion.', 'woocommerce-es' ),
				'document_id' => '',
				'invoice_id'  => '',
			);
		}

		// Create or update the contact in Brevo.
		$brevo_contact = array(
			'email'         => $email,
			'attributes'    => array(
				'FIRSTNAME' => $order['contactFirstName'] ?? '',
				'LASTNAME'  => $order['contactLastName'] ?? '',
			),
			'updateEnabled' => true,
		);

		$sms = $this->normalize_phone_e164( $order['contact_phone'] ?? '' );
		if ( ! empty( $sms ) ) {
			$brevo_contact['attributes']['SMS'] = $sms;
		}

		$result_contact = $this->api( 'contacts', $api_key, 'POST', $brevo_contact );
		if ( 'error' === $result_contact['status'] ) {
			$message_data = is_array( $result_contact['data'] ) ? wp_json_encode( $result_contact['data'] ) : $result_contact['data'];
			return array(
				'status'  => 'error',
				'message' => __( 'Error creating the contact in Brevo. Error: ', 'woocommerce-es' ) . $message_data,
			);
		}

		$order_id         = $order['woocommerceReference'] ?? $order['woocommerceOrderId'];
		$event_properties = array(
			'order_id'  => $order_id,
			'total'     => $order['total'] ?? 0,
			'currency'  => $order['currency'] ?? 'EUR',
			'order_url' => $order['woocommerceOrderEdit'] ?? '',
			// Products are only sent as order event data, Brevo has no product catalog to keep in sync.
			'products'  => $this->build_event_products( $order['items'] ),
		);

		if ( ! empty( $this->settings['order_tags'] ) ) {
			// Tags are sent as event data, not as a contact attribute: custom Brevo contact
			// attributes must already exist in the account, and "TAGS" is not a default one.
			$event_properties['tags'] = array_map( 'trim', explode( ',', $this->settings['order_tags'] ) );
		}

		$order_event = array(
			'event_name'       => 'order_completed',
			'identifiers'      => array(
				'email_id' => $email,
			),
			'event_date'       => ! empty( $order['date'] ) ? gmdate( 'c', (int) $order['date'] ) : gmdate( 'c' ),
			'event_properties' => $event_properties,
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

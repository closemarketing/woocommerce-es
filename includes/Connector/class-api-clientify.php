<?php
/**
 * Class Holded Connector
 *
 * @package    WordPress
 * @author     David Perez <david@closemarketing.es>
 * @copyright  2020 Closemarketing
 * @version    1.0
 */

defined( 'ABSPATH' ) || exit;

/**
 * LoadsAPI.
 *
 * API Holded.
 *
 * @since 1.0
 */
class Connect_Ecommerce_Clientify {
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
		$this->options  = $options['clientify'];
		$this->settings = get_option( 'connect_ecommerce' )['clientify'] ?? array();

		add_filter( 'woocommerce_checkout_fields', array( $this, 'clientify_cookie_checkout_field' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_scripts' ) );
		add_action( 'woocommerce_after_checkout_form', array( $this, 'script_cookie_clientify' ) );
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
		$result_login = $this->api( 'settings/my-account/', $this->settings['api'] );
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
	 * Gets taxes from Clientify/API
	 *
	 * @return array Array of taxes with id, name, and code.
	 */
	public function get_taxes() {
		// Clientify may not have a taxes endpoint, return empty array.
		// Override this in specific ERP connectors that support taxes.
		return array();
	}

	/**
	 * Converts product from API to SYNC
	 *
	 * @param array $products_original API Clientify Product.
	 * @return array Products converted to manage internally.
	 */
	private function convert_products( $products_original ) {
		$products = array();

		foreach ( $products_original as $product ) {
			$products[] = array(
				'id'        => ! empty( $product['id'] ) ? $product['id'] : 0,
				'name'      => ! empty( $product['name'] ) ? $product['name'] : '',
				'desc'      => ! empty( $product['description'] ) ? $product['description'] : '',
				'sku'       => ! empty( $product['sku'] ) ? $product['sku'] : '',
				'price'     => ! empty( $product['price'] ) ? $product['price'] : 0,
				'kind'      => 'simple',
				'full_info' => $product,
			);
		}
		return $products;
	}

	/**
	 * Gets information from Clientify CRM
	 *
	 * @param string $endpoint Endpoint of API.
	 * @param string $apikey API Key of Clientify.
	 * @param string $method Method of API.
	 * @param array  $query Query of API.
	 * @param string $type Type of API.
	 *
	 * @return array
	 */
	private function api( $endpoint, $apikey, $method = 'GET', $query = array(), $type = 'simple' ) {
		if ( ! $apikey ) {
			return array(
				'status' => 'error',
				'data'   => 'No API Key',
			);
		}
		$args = array(
			'method'  => $method,
			'headers' => array(
				'Content-Type'  => 'application/json',
				'Authorization' => 'Token ' . $apikey,
			),
			'timeout' => 120,
		);
		if ( ! empty( $query ) ) {
			$json         = wp_json_encode( $query );
			$json         = str_replace( '&amp;', '&', $json );
			$args['body'] = $json;
		}
		if ( 'GET' === $method ) {
			$endpoint .= '?page_size=' . $this->options['api_pagination'];
		}
		// Loop.
		$next          = true;
		$results_value = array();
		$url           = 'https://api.clientify.net/v1/' . $endpoint;

		while ( $next ) {
			$result_api = wp_remote_request( $url, $args );
			if ( is_wp_error( $result_api ) ) {
				return [
					'status' => 'error',
					'data'   => null,
				];
			}
			$results    = json_decode( wp_remote_retrieve_body( $result_api ), true );
			$code       = isset( $result_api['response']['code'] ) ? (int) round( $result_api['response']['code'] / 100, 0 ) : 0;

			if ( 2 === $code && 'simple' === $type ) {
				return array(
					'status' => 'ok',
					'data'   => $results,
				);
			} elseif ( 2 === $code && isset( $results['results'] ) ) {
				$results_value = array_merge( $results_value, $results['results'] );
			} else {
				$message = implode( ' ', $result_api['response'] ) . ' ';
				$body    = json_decode( $result_api['body'], true );

				if ( is_array( $body ) ) {
					foreach ( $body as $key => $value ) {
						$message_value = is_array( $value ) ? implode( '.', $value ) : $value;
						$message      .= $key . ': ' . $message_value;
					}
				}
				return array(
					'status' => 'error',
					'data'   => $message,
				);
			}

			if ( isset( $results['next'] ) && $results['next'] ) {
				$url = $results['next'];
			} else {
				$next = false;
			}
		}

		return array(
			'status' => 'ok',
			'data'   => isset( $results['results'] ) ? $results['results'] : array(),
		);
	}

	/**
	 * Gets information from Holded products
	 *
	 * @param string $id Id of product to get information.
	 * @param string $period Date to get YYYYMMDD.
	 * @return array Array of products imported via API.
	 */
	public function get_products( $id = null, $period = null ) {
		$api_key  = ! empty( $this->settings['api'] ) ? $this->settings['api'] : '';
		$products = $this->api( 'products/', $api_key, 'GET' );

		if ( 'error' === $products['status'] ) {
			return array(
				'status'  => 'error',
				'message' => __( 'Error getting products from Clientify', 'woocommerce-es' ),
			);
		}

		return $this->convert_products( $products['data']['results'] );
	}

	/**
	 * Gets information from Clientify products
	 *
	 * @param string $period Date YYYYMMDD for syncs.
	 * @return array Array of products imported via API.
	 */
	public function get_products_stock( $period = null ) {
		return false;
	}

	/**
	 * Creates the order to Clientify
	 *
	 * @param array  $order Order prepared to API.
	 * @param string $doc_id Document ID.
	 * @param string $invoice_id Invoice ID.
	 * @param string $force Force to create the order.
	 *
	 * @return array
	 */
	public function create_order( $order, $doc_id, $invoice_id, $force ) {
		$api_key   = ! empty( $this->settings['api'] ) ? $this->settings['api'] : '';

		if ( empty( $order ) ) {
			return array(
				'status'  => 'error',
				'message' => __( 'Error order not found in WooCommerce.', 'woocommerce-es' ),
			);
		}

		$clientify_contact = array(
			'first_name'  => $order['contactFirstName'] ?? '',
			'last_name'   => $order['contactLastName'] ?? '',
			'email'       => $order['contactEmail'] ?? '',
			'phone'       => $order['contact_phone'] ?? '',
			'status'      => 'client',
			'addresses'   => array(
				array(
					'street'      => $order['contactAddress'] ?? '',
					'city'        => $order['contactCity'] ?? '',
					'state'       => $order['contactProvince'] ?? '',
					'country'     => $order['contactCountryCode'] ?? '',
					'postal_code' => $order['contactCp'] ?? '',
					'type'        => 1,
				),
			),
			'visitor_key' => $order['clientify_vk'] ?? '',
		);
		if ( ! empty( $order['contactCompany'] ) ) {
			$clientify_contact['company'] = $order['contactCompany'];
		}

		if ( ! empty( $this->settings['order_tags'] ) ) {
			$clientify_contact['tags'] = explode( ',', $this->settings['order_tags'] );
		} else {
			$clientify_contact['tags'] = array();
		}

		// Order Clientify.
		$order_clientify = array(
			'status'     => 'ordered',
			'order_date' => isset( $order['date'] ) ? gmdate( 'c', $order['date'] ) : gmdate( 'c' ),
			'order_id'   => $order['woocommerceReference'] ?? $order['woocommerceOrderId'],
			'ecommerce'  => 'WooCommerce',
			'shop_name'  => get_bloginfo( 'name' ),
			'order_url'  => $order['woocommerceOrderEdit'] ?? '',
			'currency'   => $order['currency'] ?? 'EUR',
		);

		// Products.
		foreach ( $order['items'] as $item ) {
			$item_sku          = $item['sku'] ?? '';
			$product_clientify = array(
				'name'        => $item['name'] ?? '',
				'description' => $item['desc'] ?? '',
				'category'    => '',
				'sku'         => $item_sku,
				'image_url'   => $item['image_url'] ?? '',
				'item_url'    => $item['permalink'] ?? '',
				'price'       => $item['subtotal'] ?? 0,
				'quantity'    => $item['units'] ?? 0,
				'discount'    => 0,
			);

			if ( ! empty( $item_sku ) ) {
				$result_product = $this->api( 'products/?sku=' . $item_sku, $api_key );
				if ( 'error' !== $result_product['status'] && ! empty( $result_product['data']['results'][0]['id'] ) ) {
					$product_clientify['id'] = $result_product['data']['results'][0]['id'];
				}
				$clientify_contact['tags'][] = $item_sku;
			}
			$clientify_contact['tags'][] = sanitize_title( $item['name'] );
			$order_clientify['items'][]  = $product_clientify;
		}

		if ( empty( $order_clientify['items'] ) ) {
			return array(
				'status'  => 'error',
				'message' => $order['woocommerceOrderId'] . ' ' . __( 'Error items not valid in the order.', 'woocommerce-es' ),
			);
		}

		// Create sales order.
		$result_clientify = $this->api( 'contacts/', $api_key, 'POST', $clientify_contact );
		if ( 'error' === $result_clientify['status'] || ! isset( $result_clientify['data']['url'] ) ) {
			$order_msg = __( 'Error creating the contact in Clientify', 'woocommerce-es' );
			return array(
				'status'  => 'error',
				'message' => $order_msg,
			);
		}
		$order_clientify['contact'] = $result_clientify['data']['url'];
		$result_order               = $this->api( 'orders/', $api_key, 'POST', $order_clientify );
		if ( ! empty( $result_order['data']['id'] ) && 'error' !== $result_order['status'] ) {

			$clientify_sale_id = isset( $result_order['data']['id'] ) ? $result_order['data']['id'] : 0;
			return array(
				'status'      => 'ok',
				'message'     => __( 'The order was created correctly in Clientify', 'woocommerce-es' ) . $clientify_sale_id,
				'document_id' => $clientify_sale_id,
				'invoice_id'  => $clientify_sale_id,
			);
		} else {
			$message_data = is_array( $result_order['data'] ) ? implode( ' ', $result_order['data'] ) : $result_order['data'];
			$order_msg    = __( 'Order error syncing with Clientify. Error: ', 'woocommerce-es' ) . $message_data;

			// Log Error and return.
			return array(
				'status'      => 'error',
				'message'     => $order_msg,
				'document_id' => '',
				'invoice_id'  => '',
			);
		}
	}

	/**
	 * Adds field checkout
	 *
	 * @param array $fields Fields of checkout.
	 * @return array
	 */
	public function clientify_cookie_checkout_field( $fields ) {
		$fields['billing']['clientify_vk'] = array(
			'type'  => 'hidden',
			'class' => array( 'clientify_cookie' ),
		);

		return $fields;
	}

	/**
	 * Enqueue Scripts
	 *
	 * @return void
	 */
	public function enqueue_scripts() {
		wp_register_script(
			'connectwoo-clientify-field',
			CONECOM_PLUGIN_URL . 'includes/assets/clientify-field.js',
			array(),
			CONECOM_VERSION,
			true
		);
	}

	/**
	 * Loads script Cookie
	 *
	 * @return void
	 */
	public function script_cookie_clientify() {
		wp_enqueue_script( 'connectwoo-clientify-field' );
	}
}

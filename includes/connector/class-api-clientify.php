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
		$this->settings = get_option( $this->options['slug'] );

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
	 * Converts product from API to SYNC
	 *
	 * @param array $products_original API Clientify Product.
	 * @return array Products converted to manage internally.
	 */
	private function convert_products( $products_original ) {
		$products_converted = array();
		$i                  = 0;

		foreach ( $products_original as $product ) {
			$products_converted[ $i ] = array(
				'id'    => ! empty( $product['id'] ) ? $product['id'] : 0,
				'name'  => ! empty( $product['name'] ) ? $product['name'] : '',
				'desc'  => ! empty( $product['description'] ) ? $product['description'] : '',
				'sku'   => ! empty( $product['sku'] ) ? $product['sku'] : '',
				'price' => ! empty( $product['price'] ) ? $product['price'] : 0,
				'kind'  => 'simple',
			);
			$i++;
		}
		return $products_converted;
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
		$apikey = isset( $this->settings['api'] ) ? $this->settings['api'] : '';
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
		// Loop.
		$next          = true;
		$results_value = array();
		$url           = 'https://api.clientify.net/v1/' . $endpoint;

		while ( $next ) {
			$result_api = wp_remote_request( $url, $args );
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
		$api_key  = ! empty( $settings['api'] ) ? $settings['api'] : '';
		$products = $this->api( 'products/', $api_key, 'GET', array(), 'all' );

		return $this->convert_products( $products['data'] );
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
	 * @param string $meta_key String to save in order.
	 *
	 * @return array
	 */
	public function create_order( $order, $meta_key ) {
		$api_key   = ! empty( $this->settings['api'] ) ? $this->settings['api'] : '';
		$order_id  = $order['woocommerceOrderId'] ?? 0;
		$order_woo = wc_get_order( $order_id );

		if ( empty( $order_woo ) ) {
			return array(
				'status'  => 'error',
				'message' => $order_id . ' ' . __( 'Error order not found in WooCommerce.', 'connect-woocommerce-clientify' ),
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
			'visitor_key' => $order_woo->get_meta( 'clientify_vk' ) ?? '',
		);
		if ( $order_woo->get_billing_company() ) {
			$clientify_contact['company'] = $order_woo->get_billing_company();
		}

		if ( ! empty( $this->settings['order_tags'] ) ) {
			$clientify_contact['tags'] = explode( ',', $this->settings['order_tags'] );
		} else {
			$clientify_contact['tags'] = array();
		}

		// Calculates Prefix.
		$shop_url  = get_bloginfo( 'url' );
		$shop_host = parse_url( $shop_url, PHP_URL_HOST );
		$shop_host = str_replace( 'www.', '', $shop_host );
		$shop_tld  = end( explode( '.', $shop_host ) );
		$prefix    = strtoupper( substr( $shop_host, 0, 3 ) . $shop_tld ) . '_';

		// Order Clientify.
		$order_clientify = array(
			'status'     => 'ordered',
			'order_date' => isset( $order['date'] ) ? gmdate( 'c', $order['date s'] ) : gmdate( 'c' ),
			'order_id'   => $prefix . $order_id,
			'ecommerce'  => 'WooCommerce',
			'shop_name'  => get_bloginfo( 'name' ),
			'order_url'  => $order_woo->get_edit_order_url(),
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
				'quantity'    => $order['units'] ?? 0,
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
				'message' => $order_id . ' ' . __( 'Error items not valid in the order.', 'connect-woocommerce-clientify' ),
			);
		}

		// Create sales order.
		$result_clientify = $this->api( 'contacts/', $api_key, 'POST', $clientify_contact );
		if ( 'error' === $result_clientify['status'] || ! isset( $result_clientify['data']['url'] ) ) {
			$order_msg = __( 'Error creating the contact in Clientify', 'connect-woocommerce-clientify' );
			$order_woo->add_order_note( $order_msg );
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
				'message'     => __( 'The order was created correctly in Clientify', 'connect-woocommerce-clientify' ) . $clientify_sale_id,
				'document_id' => $clientify_sale_id,
				'invoice_id'  => $clientify_sale_id,
			);
		} else {
			$message_data = is_array( $result_order['data'] ) ? implode( ' ', $result_order['data'] ) : $result_order['data'];
			$order_msg    = __( 'Order error syncing with Clientify. Error: ', 'connect-woocommerce-clientify' ) . $message_data;

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
			CONCLI_PLUGIN_URL . 'includes/assets/clientify-field.js',
			array(),
			CONCLI_VERSION,
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

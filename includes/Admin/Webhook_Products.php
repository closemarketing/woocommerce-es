<?php
/**
 * Webhook endpoint for ERP product synchronization
 *
 * @package    WordPress
 * @author     David Perez <david@closemarketing.es>
 * @copyright  2024 Closemarketing
 * @version    1.0
 */

namespace CLOSE\ConnectEcommerce\Admin;

defined( 'ABSPATH' ) || exit;

use CLOSE\ConnectEcommerce\Helpers\PROD;

/**
 * Registers the REST endpoint that ERPs call to trigger a product sync.
 *
 * Endpoint: POST /wp-json/connect-ecommerce/v1/webhook/product
 *
 * Accepted payloads
 *   - GET/POST query param:  ?id=N
 *   - POST JSON body:        {"id": N}
 */
class Webhook_Products {

	/**
	 * Connector settings.
	 *
	 * @var array
	 */
	private $settings;

	/**
	 * ERP API object.
	 *
	 * @var object|null
	 */
	private $connapi_erp;

	/**
	 * Plugin options.
	 *
	 * @var array
	 */
	private $options;

	/**
	 * Constructor.
	 *
	 * @param array $connector Connector config from HELPER::get_connector().
	 */
	public function __construct( $connector ) {
		if ( empty( $connector ) || empty( $connector['connector'] ) || empty( $connector['options'] ) ) {
			return;
		}
		$this->options     = $connector['options'];
		$this->connapi_erp = $connector['connapi_erp'] ?? null;
		$this->settings    = $connector['settings'] ?? array();

		add_action( 'rest_api_init', array( $this, 'register_rest_route' ) );
	}

	/**
	 * Register the REST route.
	 *
	 * @return void
	 */
	public function register_rest_route() {
		register_rest_route(
			'connect-ecommerce/v1',
			'/webhook/product',
			array(
				'methods'             => array( 'GET', 'POST' ),
				'callback'            => array( $this, 'handle_webhook' ),
				'permission_callback' => '__return_true',
			)
		);
	}

	/**
	 * Handle incoming webhook request.
	 *
	 * @param \WP_REST_Request $request Incoming request.
	 * @return \WP_REST_Response
	 */
	public function handle_webhook( $request ) {
		if ( empty( $this->connapi_erp ) ) {
			$this->log_webhook( '', 'error', __( 'No connector configured.', 'woocommerce-es' ) );
			return new \WP_REST_Response(
				array(
					'success' => false,
					'message' => 'No connector configured.',
				),
				500
			);
		}

		$product_id = $this->extract_product_id( $request );

		if ( empty( $product_id ) ) {
			$this->log_webhook( '', 'error', __( 'Missing product ID in request.', 'woocommerce-es' ) );
			return new \WP_REST_Response(
				array(
					'success' => false,
					'message' => 'Missing product ID.',
				),
				400
			);
		}

		$product_id = apply_filters( 'conecom_webhook_product_id', $product_id, $request );

		$product_api = $this->connapi_erp->get_products( $product_id );

		if ( empty( $product_api ) || ( isset( $product_api['status'] ) && 'error' === $product_api['status'] ) ) {
			$err_msg = isset( $product_api['message'] ) ? $product_api['message'] : __( 'Product not found in ERP.', 'woocommerce-es' );
			$this->log_webhook( $product_id, 'error', $err_msg );
			return new \WP_REST_Response(
				array(
					'success' => false,
					'message' => $err_msg,
				),
				404
			);
		}

		$result = PROD::sync_product_item( $this->settings, $product_api, $this->connapi_erp );

		$status  = isset( $result['status'] ) ? $result['status'] : 'error';
		$message = isset( $result['message'] ) ? $result['message'] : '';

		$this->log_webhook( $product_id, $status, $message );

		if ( 'error' === $status ) {
			return new \WP_REST_Response(
				array(
					'success' => false,
					'message' => $message,
				),
				500
			);
		}

		return new \WP_REST_Response(
			array(
				'success' => true,
				'message' => $message,
			),
			200
		);
	}

	/**
	 * Extract product ID from request (query param or JSON body).
	 *
	 * @param \WP_REST_Request $request Incoming request.
	 * @return string
	 */
	private function extract_product_id( $request ) {
		$id = $request->get_param( 'id' );
		if ( ! empty( $id ) ) {
			return sanitize_text_field( $id );
		}

		$body = $request->get_json_params();
		if ( ! empty( $body['id'] ) ) {
			return sanitize_text_field( $body['id'] );
		}

		$body = apply_filters( 'conecom_webhook_parse_request', array(), $request );
		return isset( $body['id'] ) ? sanitize_text_field( $body['id'] ) : '';
	}

	/**
	 * Save a webhook execution log entry.
	 *
	 * @param string $product_id ERP product ID.
	 * @param string $status     'success' or 'error'.
	 * @param string $message    Optional technical detail.
	 * @return void
	 */
	private function log_webhook( $product_id, $status, $message = '' ) {
		$logs   = get_option( 'conecom_webhook_logs', array() );
		$logs[] = array(
			'type'       => 'webhook',
			'product_id' => $product_id,
			'status'     => $status,
			'message'    => $message,
			'timestamp'  => current_time( 'mysql' ),
		);

		// Keep only the last 50 entries.
		if ( count( $logs ) > 50 ) {
			$logs = array_slice( $logs, -50 );
		}

		update_option( 'conecom_webhook_logs', $logs, false );
	}

	/**
	 * Return stored webhook logs (newest first).
	 *
	 * @return array
	 */
	public static function get_logs() {
		$logs = get_option( 'conecom_webhook_logs', array() );
		return array_reverse( $logs );
	}

	/**
	 * Build the webhook endpoint URL for display.
	 *
	 * @return string
	 */
	public static function get_endpoint_url() {
		return rest_url( 'connect-ecommerce/v1/webhook/product' );
	}
}

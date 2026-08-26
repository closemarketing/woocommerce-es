<?php
/**
 * Orders Widget
 *
 * @package    WordPress
 * @author     David Perez <david@close.technology>
 * @copyright  2023 Closemarketing
 * @version    1.0
 */

namespace CLOSE\ConnectEcommerce\Admin;

defined( 'ABSPATH' ) || exit;

use CLOSE\ConnectEcommerce\Helpers\HELPER;

/**
 * Widget in Orders
 *
 * Description.
 *
 * @since 1.0.0
 */
class Widget_Order {
	/**
	 * Options of plugin.
	 *
	 * @var array
	 */
	private $options;

	/**
	 * Settings saved by the user (credentials, debug_log, etc.).
	 *
	 * @var array
	 */
	private $settings;

	/**
	 * API Object
	 *
	 * @var object
	 */
	private $connapi_erp;

	/**
	 * Construct of Class
	 *
	 * @param array $connector Connector.
	 */
	public function __construct( $connector ) {
		if ( empty( $connector ) || empty( $connector['connector'] ) || empty( $connector['options'] ) || empty( $connector['connapi_erp'] ) ) {
			return;
		}
		$this->options     = $connector['options'];
		$this->settings    = $connector['settings'] ?? array();
		$this->connapi_erp = $connector['connapi_erp'];
		// Register Meta box for post type product.
		add_action( 'add_meta_boxes', array( $this, 'metabox_orders' ) );
	}
	/**
	 * Adds metabox
	 *
	 * @return void
	 */
	public function metabox_orders() {
		$screen = get_current_screen()->id == 'woocommerce_page_wc-orders' ? wc_get_page_screen_id( 'shop-order' ) : 'shop_order';

		add_meta_box(
			'cw-order-checker',
			__( 'Connect with ', 'woocommerce-es' ) . $this->options['name'],
			array( $this, 'metabox_show_order' ),
			$screen,
			'side',
			'core'
		);

		// Register log payload metabox only when the meta is not empty.
		add_action( 'add_meta_boxes_' . $screen, array( $this, 'maybe_add_log_payload_metabox' ) );
	}

	/**
	 * Metabox inputs for post type.
	 *
	 * @param object $post Post object.
	 * @return void
	 */
	public function metabox_show_order( $post ) {
		$order_id = $post->ID;
		$order    = wc_get_order( $post->ID );

		echo '<table>';
		// Send Order.
		echo '<tr><td><strong>' . esc_html__( 'Order', 'woocommerce-es' ) . '</strong></td>';
		$order_key  = '_' . $this->options['slug'] . '_invoice_id';
		$invoice_id = $order->get_meta( $order_key, true );
		echo '<td>Web: #' . esc_html( $order_id ) . '<br/>';
		echo 'ERP: ';

		$edit_url = $this->connapi_erp->get_url_link_api( $order );
		if ( $edit_url ) {
			echo '<a href="' . esc_url( $edit_url ) . '" target="_blank">';
		}
		echo esc_html( $invoice_id );
		if ( $edit_url ) {
			echo '</a>';
		}

		$label = $invoice_id ? __( 'Update to ERP', 'woocommerce-es' ) : __( 'Send to ERP', 'woocommerce-es' );

		echo '<br/><br/><div name="sync-erp-orders" id="sync-erp-orders-' . esc_html( $order_id ) . '" ';
		echo 'class="button button-primary" onclick="syncOrderERP(' . esc_html( $order_id );
		echo ',this.id,\'erp-post\')">' . esc_html( $label ) . '</div>';
		echo '</td></tr>';

		$this->show_document_download_row( $order );

		echo '</table>';
	}

	/**
	 * Shows the document download link row, when a PDF document is available for the order.
	 *
	 * @param \WC_Order $order Order object.
	 * @return void
	 */
	private function show_document_download_row( $order ) {
		$api_doc_id = $order->get_meta( '_' . $this->options['slug'] . '_doc_id' );

		if ( empty( $api_doc_id ) || empty( $this->connapi_erp ) || ! HELPER::connector_supports( $this->connapi_erp, 'get_order_pdf' ) ) {
			return;
		}

		$nonce        = wp_create_nonce( 'cwc-document-nonce' );
		$download_url = admin_url( 'admin-ajax.php?action=cwc_document_download&order_id=' . $order->get_id() . '&nonce=' . $nonce );

		echo '<tr><td><strong>' . esc_html__( 'Document', 'woocommerce-es' ) . '</strong></td>';
		echo '<td><a href="' . esc_url( $download_url ) . '" class="button button-primary" target="_blank">';
		echo esc_html__( 'Download', 'woocommerce-es' ) . '</a></td></tr>';
	}

	/**
	 * Conditionally registers the log payload metabox when the meta is not empty.
	 *
	 * @param \WP_Post $post Post object.
	 * @return void
	 */
	public function maybe_add_log_payload_metabox( $post ) {
		$is_debug = isset( $this->settings['debug_log'] ) && 'on' === $this->settings['debug_log'];

		if ( ! $is_debug ) {
			return;
		}

		$order       = wc_get_order( $post->ID );
		$payload_key = '_' . $this->options['slug'] . '_log_payload';

		if ( empty( $order ) || empty( $order->get_meta( $payload_key, true ) ) ) {
			return;
		}

		$screen = get_current_screen()->id == 'woocommerce_page_wc-orders' ? wc_get_page_screen_id( 'shop-order' ) : 'shop_order';

		add_meta_box(
			'cw-order-log-payload',
			__( 'Connect Log Payload', 'woocommerce-es' ),
			array( $this, 'metabox_show_log_payload' ),
			$screen,
			'normal',
			'low'
		);
	}

	/**
	 * Metabox showing the log payload JSON for the order.
	 *
	 * @param object $post Post object.
	 * @return void
	 */
	public function metabox_show_log_payload( $post ) {
		$order       = wc_get_order( $post->ID );
		$payload_key = '_' . $this->options['slug'] . '_log_payload';
		$log_payload = $order->get_meta( $payload_key, true );

		if ( empty( $log_payload ) ) {
			return;
		}

		$decoded = json_decode( $log_payload, true );
		$json    = null !== $decoded ? wp_json_encode( $decoded, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE ) : $log_payload;

		echo '<pre style="overflow:auto;max-height:300px;font-size:11px;background:#f6f7f7;padding:8px;border:1px solid #ddd;border-radius:3px;white-space:pre-wrap;word-break:break-all;">';
		echo esc_html( $json );
		echo '</pre>';
	}
}

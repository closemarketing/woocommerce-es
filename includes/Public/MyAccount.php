<?php
/**
 * Public my account class
 *
 * @package    WordPress
 * @author     David Perez <david@close.technology>
 * @copyright  2024 CLOSE
 * @version    1.0.0
 */

namespace CLOSE\ConnectEcommerce\Public;

defined( 'ABSPATH' ) || exit;

/**
 * My Account.
 *
 * @since 1.6.0
 */
class MyAccount {
	/**
	 * Options of plugin.
	 *
	 * @var array
	 */
	private $options;

	/**
	 * Construct of Class
	 *
	 * @param array $options Options of plugin.
	 */
	public function __construct( $options ) {
		$this->options = $options;

		add_filter( 'woocommerce_account_orders_columns', array( $this, 'add_account_orders_column' ), 10, 1 );
		add_action( 'woocommerce_my_account_my_orders_column_custom-column', array( $this, 'add_account_orders_column_rows' ) );
		add_action( 'wp_ajax_cwc_document_download', array( $this, 'cwc_document_download' ) );
	}

	/**
	 * Add custom column to my account orders.
	 *
	 * @param array $columns Columns of orders.
	 * @return array
	 */
	public function add_account_orders_column( $columns ) {
		$order_actions  = $columns['order-actions']; // Save Order actions.
		unset( $columns['order-actions'] ); // Remove Order actions.

		// Add your custom column key / label.
		$columns['custom-column'] = __( 'Invoice', 'connect-woocommerce-library' );

		// Add back previously saved "Order actions".
		$columns['order-actions'] = $order_actions;

		return $columns;
	}

	/**
	 * Add custom column rows to my account orders.
	 *
	 * @param WC_Order $order Order object.
	 */
	public function add_account_orders_column_rows( $order ) {
		$api_doc_id = $order->get_meta( '_' . $this->options['slug'] . '_doc_id' );

		if ( ! empty( $api_doc_id ) ) {
			$api_doc_type = $order->get_meta( '_' . $this->options['slug'] . '_doc_type' );
			$nonce        = wp_create_nonce( 'cwc-document-nonce' );
			echo '<a href=' . esc_url( admin_url( 'admin-ajax.php?action=cwc_document_download&doc_id=' . esc_attr( $api_doc_id ) . '&doc_type=' . esc_attr( $api_doc_type ) . '&nonce=' . $nonce ) ) . ' class="button button-primary" target="_blank">';
			echo esc_html__( 'Download', 'connect-woocommerce-library' ) . '</a>';

		}
	}

	/**
	 * Ajax to download the file
	 *
	 * @return void
	 */
	public function cwc_document_download() {
		check_ajax_referer( 'cwc-document-nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( "Hmmm, you're not supposed to be here." );
		}
		$api_doc_id   = isset( $_GET['doc_id'] ) ? sanitize_text_field( wp_unslash( $_GET['doc_id'] ) ) : '';
		$api_doc_type = isset( $_GET['doc_type'] ) ? sanitize_text_field( wp_unslash( $_GET['doc_type'] ) ) : '';

		$file_document_path = false;
		if ( $api_doc_id ) {
			$settings           = get_option( $this->options['slug'] );
			$apiname            = 'Connect_Ecommerce_' . $this->options['name'];
			$apikey             = isset( $settings['api'] ) ? $settings['api'] : '';
			$connapi_erp        = new $apiname( $this->options );
			$file_document_path = $connapi_erp->get_order_pdf( $apikey, $api_doc_type, $api_doc_id );
		}

		if ( ! file_exists( $file_document_path ) ) {
			wp_die();
		}

		$basename = basename( $file_document_path );
		$filesize = filesize( $file_document_path );

		header( 'Content-Description: File Transfer' );
		header( 'Content-Type: text/plain' );
		header( 'Cache-Control: no-cache, must-revalidate' );
		header( 'Expires: 0' );
		header( 'Content-Disposition: attachment; filename=' . $basename );
		header( 'Content-Length: ' . $filesize );
		header( 'Pragma: public' );

		flush();

		readfile( $file_document_path );

		wp_die();
	}
}

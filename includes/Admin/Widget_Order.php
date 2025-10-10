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
	 * API Object
	 *
	 * @var object
	 */
	private $connapi_erp;

	/**
	 * Construct of Class
	 *
	 * @param array $options Options of plugin.
	 */
	public function __construct( $options = array() ) {
		$settings_base = get_option( 'connect_ecommerce' );
		$connector     = ! empty( $settings_base['connector'] ) ? $settings_base['connector'] : '';
		if ( empty( $connector ) ) {
			return;
		}
		$this->options     = $options[ $connector ];
		$apiname           = 'Connect_Ecommerce_' . $this->options['name'];
		$this->connapi_erp = new $apiname( $options );
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

		echo '<br/><br/><div name="grao-sync-order" id="sync-erp-orders-' . esc_html( $order_id ) . '" ';
		echo 'class="button button-primary" onclick="syncOrderERP(' . esc_html( $order_id );
		echo ',this.id,\'erp-post\')">' . esc_html( $label ) . '</div>';
		echo '</td></tr>';

		echo '</table>';
	}
}

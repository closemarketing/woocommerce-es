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

use CLOSE\ConnectEcommerce\Helpers\ORDER;

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

		echo '<br/><br/><div name="connect-ecommerce-sync-order" id="sync-erp-orders-' . esc_html( $order_id ) . '" ';
		echo 'class="button button-primary" onclick="syncOrderERP(' . esc_html( $order_id );
		echo ',this.id,\'erp-post\')">' . esc_html( $label ) . '</div>';
		echo '</td></tr>';

		// Show refunds if exist.
		$refunds = $order->get_refunds();
		if ( ! empty( $refunds ) ) {
			echo '<tr><td colspan="2"><hr/></td></tr>';
			echo '<tr><td colspan="2"><strong>' . esc_html__( 'Refunds', 'woocommerce-es' ) . '</strong></td></tr>';

			// Check if order has VAT number.
			$contact_vat = ORDER::get_billing_vat( $order );
			$has_vat     = ! empty( $contact_vat );

			if ( ! $has_vat ) {
				echo '<tr><td colspan="2" style="background:#fff3cd;padding:10px;border-left:4px solid #ffc107;">';
				echo '<strong>⚠️ ' . esc_html__( 'Warning', 'woocommerce-es' ) . ':</strong> ';
				echo esc_html__( 'Contact does not have a VAT number. Cannot send refund to ERP.', 'woocommerce-es' );
				echo '</td></tr>';
			}

			foreach ( $refunds as $refund ) {
				$refund_id     = $refund->get_id();
				$refund_amount = $refund->get_amount();
				$refund_key    = '_' . $this->options['slug'] . '_refund_doc_id';
				$refund_doc_id = $refund->get_meta( $refund_key, true );

				echo '<tr><td>';
				echo esc_html__( 'Refund', 'woocommerce-es' ) . ' #' . esc_html( $refund_id );
				echo '</td><td>';
				echo esc_html__( 'Amount', 'woocommerce-es' ) . ': ' . wp_kses_post( wc_price( $refund_amount ) ) . '<br/>';

				if ( $refund_doc_id ) {
					echo 'ERP: ';
					$refund_edit_url = $this->connapi_erp->get_url_link_api( $refund );
					if ( $refund_edit_url ) {
						echo '<a href="' . esc_url( $refund_edit_url ) . '" target="_blank">';
					}
					echo esc_html( $refund_doc_id );
					if ( $refund_edit_url ) {
						echo '</a>';
					}
					echo '<br/>';
				}

				$refund_label = $refund_doc_id ? __( 'Resend Refund', 'woocommerce-es' ) : __( 'Send Refund', 'woocommerce-es' );

				echo '<div name="connect-ecommerce-sync-refund" id="sync-erp-refund-' . esc_html( $refund_id ) . '" ';
				echo 'class="button button-secondary' . ( ! $has_vat ? ' disabled' : '' ) . '" style="margin-top:5px;"';
				if ( $has_vat ) {
					echo ' onclick="syncOrderERP(' . esc_html( $refund_id ) . ',this.id,\'erp-refund\')"';
				}
				echo '>' . esc_html( $refund_label ) . '</div>';
				echo '</td></tr>';
			}
		}

		echo '</table>';
	}
}

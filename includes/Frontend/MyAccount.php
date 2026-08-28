<?php
/**
 * Public my account class
 *
 * @package    WordPress
 * @author     David Perez <david@close.technology>
 * @copyright  2024 CLOSE
 * @version    1.0.0
 */

namespace CLOSE\ConnectEcommerce\Frontend;

defined( 'ABSPATH' ) || exit;

use CLOSE\ConnectEcommerce\Base;
use CLOSE\ConnectEcommerce\Helpers\HELPER;
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
	 * Settings all
	 *
	 * @var array
	 */
	private $settings_all;

	/**
	 * Settings
	 *
	 * @var array
	 */
	private $settings;

	/**
	 * All options
	 *
	 * @var array
	 */
	private $all_options;

	/**
	 * API Object
	 *
	 * @var object
	 */
	private $connapi_erp;

	/**
	 * Connector
	 *
	 * @var object
	 */
	private $connector;

	/**
	 * Construct of Class
	 *
	 * @param array $connector Connector.
	 */
	public function __construct( $connector ) {
		$this->settings_all = $connector['settings_all'] ?? get_option( 'connect_ecommerce' );
		$this->connector    = $connector['connector'] ?? '';
		$this->settings     = $connector['settings'] ?? array();
		$this->all_options  = $connector['all_options'];
		$this->options      = $connector['options'] ?? array();
		$this->connapi_erp  = $connector['connapi_erp'] ?? null;

		if ( ! empty( $this->connector ) && empty( $this->options ) ) {
			return;
		}

		add_filter( 'woocommerce_account_orders_columns', array( $this, 'add_account_orders_column' ), 10, 1 );
		add_action( 'woocommerce_my_account_my_orders_column_custom-column', array( $this, 'add_account_orders_column_rows' ) );
		add_action( 'wp_ajax_cwc_document_download', array( $this, 'cwc_document_download' ) );

		// VAT field on user profile and pre-population.
		$settings_public = get_option( 'connect_ecommerce_public' );
		$show_vat        = isset( $settings_public['vat_show'] ) ? $settings_public['vat_show'] : 'yes';
		if ( 'yes' === $show_vat ) {
			add_action( 'show_user_profile', array( $this, 'show_vat_profile_field' ) );
			add_action( 'edit_user_profile', array( $this, 'show_vat_profile_field' ) );
			add_action( 'personal_options_update', array( $this, 'save_vat_profile_field' ) );
			add_action( 'edit_user_profile_update', array( $this, 'save_vat_profile_field' ) );
			add_action( 'woocommerce_checkout_update_customer', array( $this, 'sync_vat_to_user_meta' ), 10, 2 );
		}
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
		$columns['custom-column'] = __( 'Invoice', 'woocommerce-es' );

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

		if ( ! empty( $api_doc_id ) && ! empty( $this->connapi_erp ) && HELPER::connector_supports( $this->connapi_erp, 'get_order_pdf' ) ) {
			$nonce = wp_create_nonce( 'cwc-document-nonce' );
			echo '<a href=' . esc_url( admin_url( 'admin-ajax.php?action=cwc_document_download&order_id=' . esc_attr( $order->get_id() ) . '&nonce=' . $nonce ) ) . ' class="button button-primary" target="_blank">';
			echo esc_html__( 'Download', 'woocommerce-es' ) . '</a>';
		} else {
			echo esc_html__( 'Not available', 'woocommerce-es' );
		}
	}

	/**
	 * Ajax to download the file
	 *
	 * @return void
	 */
	public function cwc_document_download() {
		check_ajax_referer( 'cwc-document-nonce', 'nonce' );

		$order_id = isset( $_GET['order_id'] ) ? absint( $_GET['order_id'] ) : 0;
		$order    = $order_id ? wc_get_order( $order_id ) : false;

		$can_manage_order = current_user_can( 'manage_woocommerce' );
		$is_order_owner   = $order && get_current_user_id() && $order->get_customer_id() === get_current_user_id();

		if ( ! $order || ( ! $can_manage_order && ! $is_order_owner ) ) {
			wp_die( "Hmmm, you're not supposed to be here." );
		}

		// Read the document identifiers from the authorized order itself, never from the request,
		// so a valid order_id can't be paired with another customer's doc_id/doc_type.
		$api_doc_id   = $order->get_meta( '_' . $this->options['slug'] . '_doc_id' );
		$api_doc_type = $order->get_meta( '_' . $this->options['slug'] . '_doc_type' );

		$file_document = false;
		if ( $api_doc_id && $this->connapi_erp ) {
			$file_document = $this->connapi_erp->get_order_pdf( $this->settings, $api_doc_type, $api_doc_id );
		}

		if ( empty( $file_document ) ) {
			wp_die();
		}

		$basename = sanitize_file_name( $api_doc_type . '-' . $api_doc_id . '.pdf' );

		header( 'Content-Description: File Transfer' );
		header( 'Content-Type: application/pdf' );
		header( 'Cache-Control: no-cache, must-revalidate' );
		header( 'Expires: 0' );
		header( 'Content-Disposition: attachment; filename=' . $basename );
		header( 'Content-Length: ' . strlen( $file_document ) );
		header( 'Pragma: public' );

		flush();

		echo $file_document; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped

		wp_die();
	}

	/**
	 * Display the VAT number field on the user profile page.
	 *
	 * @param WP_User $user User object.
	 * @return void
	 */
	public function show_vat_profile_field( $user ) {
		$vat_number = get_user_meta( $user->ID, 'billing_vat', true );
		?>
		<h3><?php esc_html_e( 'Billing VAT', 'woocommerce-es' ); ?></h3>
		<table class="form-table">
			<tr>
				<th><label for="billing_vat"><?php esc_html_e( 'VAT Number', 'woocommerce-es' ); ?></label></th>
				<td>
					<input type="text"
						name="billing_vat"
						id="billing_vat"
						value="<?php echo esc_attr( $vat_number ); ?>"
						class="regular-text"
					/>
				</td>
			</tr>
		</table>
		<?php
	}

	/**
	 * Save the VAT number from the user profile page.
	 *
	 * @param int $user_id User ID being saved.
	 * @return void
	 */
	public function save_vat_profile_field( $user_id ) {
		if ( ! current_user_can( 'edit_user', $user_id ) ) {
			return;
		}
		$vat_number = isset( $_POST['billing_vat'] ) ? sanitize_text_field( wp_unslash( $_POST['billing_vat'] ) ) : '';
		update_user_meta( $user_id, 'billing_vat', $vat_number );
	}

	/**
	 * Sync billing_vat to user meta after checkout so future orders pre-populate the field.
	 *
	 * @param WC_Customer $customer  WooCommerce customer object.
	 * @param array       $data      Posted checkout data.
	 * @return void
	 */
	public function sync_vat_to_user_meta( $customer, $data ) {
		$user_id = $customer->get_id();
		if ( ! $user_id ) {
			return;
		}

		$vat_number = '';
		foreach ( CONECOM_VAT_FIELD_SLUGS as $slug ) {
			if ( 'VAT Number' === $slug ) {
				continue;
			}
			$key   = str_replace( '_billing_', 'billing_', ltrim( $slug, '_' ) );
			$value = isset( $data[ $key ] ) ? $data[ $key ] : '';
			if ( empty( $value ) ) {
				// Also check the namespaced blocks field.
				$value = isset( $data['connect_ecommerce/billing_vat'] ) ? $data['connect_ecommerce/billing_vat'] : '';
			}
			if ( ! empty( $value ) ) {
				$vat_number = $value;
				break;
			}
		}

		if ( ! empty( $vat_number ) ) {
			update_user_meta( $user_id, 'billing_vat', sanitize_text_field( $vat_number ) );
		}
	}
}

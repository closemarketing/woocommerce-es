<?php
/**
 * EU VAT on admin orders
 *
 * @package    WordPress
 * @author     David Perez <david@close.technology>
 * @copyright  2026 CLOSE
 * @version    1.0.0
 */

namespace CLOSE\ConnectEcommerce\Admin;

defined( 'ABSPATH' ) || exit;

use CLOSE\ConnectEcommerce\Helpers\ORDER;
use CLOSE\ConnectEcommerce\Helpers\VAT;

/**
 * Validates the VAT number of orders created or edited in the admin,
 * applies the B2B intra-community exemption and shows its status.
 */
class Order_VAT {
	/**
	 * AJAX action / nonce name.
	 *
	 * @var string
	 */
	const AJAX_ACTION = 'conecom_admin_order_vat';

	/**
	 * Construct of class
	 */
	public function __construct() {
		$settings     = get_option( 'connect_ecommerce_public' );
		$vies_enabled = isset( $settings['vat_vies_enabled'] ) ? $settings['vat_vies_enabled'] : 'yes';
		if ( 'yes' !== $vies_enabled ) {
			return;
		}

		add_action( 'add_meta_boxes', array( $this, 'add_metabox' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_scripts' ) );
		add_action( 'wp_ajax_' . self::AJAX_ACTION, array( $this, 'ajax_validate_order_vat' ) );

		// After WooCommerce saves the order addresses (priority 40).
		add_action( 'woocommerce_process_shop_order_meta', array( $this, 'maybe_update_exemption_on_save' ), 50, 1 );
	}

	/**
	 * Order edit screen IDs (legacy posts and HPOS).
	 *
	 * @return array
	 */
	private function get_order_screens() {
		$screens = array( 'shop_order' );
		if ( function_exists( 'wc_get_page_screen_id' ) ) {
			$screens[] = wc_get_page_screen_id( 'shop-order' );
		}
		return array_unique( $screens );
	}

	/**
	 * Adds the EU VAT metabox to the order sidebar.
	 *
	 * @return void
	 */
	public function add_metabox() {
		foreach ( $this->get_order_screens() as $screen ) {
			add_meta_box(
				'conecom-order-vat',
				__( 'EU VAT', 'woocommerce-es' ),
				array( $this, 'render_metabox' ),
				$screen,
				'side',
				'high'
			);
		}
	}

	/**
	 * Renders the EU VAT metabox.
	 *
	 * @param \WP_Post|\WC_Order $post_or_order Post (legacy) or order (HPOS).
	 * @return void
	 */
	public function render_metabox( $post_or_order ) {
		$order = $post_or_order instanceof \WC_Order ? $post_or_order : wc_get_order( $post_or_order->ID );
		if ( ! $order ) {
			return;
		}

		$result     = VAT::get_vat_validation_result( $order );
		$validated  = $order->get_meta( '_vat_number_validated' );
		$is_exempt  = 'yes' === $order->get_meta( 'is_vat_exempt' );
		$vat_number = ORDER::get_billing_vat( $order );

		if ( 'yes' === $validated ) {
			$status_class = 'valid';
			$status_label = __( 'Valid VAT number', 'woocommerce-es' );
		} elseif ( 'no' === $validated ) {
			$status_class = 'invalid';
			$status_label = __( 'Invalid VAT number', 'woocommerce-es' );
		} else {
			$status_class = 'none';
			$status_label = '' === $vat_number ? __( 'No VAT number', 'woocommerce-es' ) : __( 'Not validated yet', 'woocommerce-es' );
		}
		?>
		<style>
			.conecom-order-vat-status--valid { color: #008a20; font-weight: 600; }
			.conecom-order-vat-status--invalid { color: #d63638; font-weight: 600; }
		</style>
		<div id="conecom-order-vat-box"data-order-id="<?php echo esc_attr( $order->get_id() ); ?>">
			<p>
				<strong><?php esc_html_e( 'VAT number', 'woocommerce-es' ); ?>:</strong>
				<span class="conecom-order-vat-number"><?php echo esc_html( '' !== $vat_number ? $vat_number : '—' ); ?></span>
			</p>
			<p>
				<strong><?php esc_html_e( 'Status', 'woocommerce-es' ); ?>:</strong>
				<span class="conecom-order-vat-status conecom-order-vat-status--<?php echo esc_attr( $status_class ); ?>"><?php echo esc_html( $status_label ); ?></span>
			</p>
			<?php if ( ! empty( $result['name'] ) ) : ?>
				<p>
					<strong><?php esc_html_e( 'Company', 'woocommerce-es' ); ?>:</strong>
					<?php echo esc_html( $result['name'] ); ?>
				</p>
			<?php endif; ?>
			<?php if ( ! empty( $result['request_date'] ) ) : ?>
				<p>
					<strong><?php esc_html_e( 'Checked on', 'woocommerce-es' ); ?>:</strong>
					<?php echo esc_html( $result['request_date'] ); ?>
				</p>
			<?php endif; ?>
			<p>
				<strong><?php esc_html_e( 'VAT exempt', 'woocommerce-es' ); ?>:</strong>
				<span class="conecom-order-vat-exempt"><?php echo $is_exempt ? esc_html__( 'Yes', 'woocommerce-es' ) : esc_html__( 'No', 'woocommerce-es' ); ?></span>
			</p>
			<p class="conecom-order-vat-message" aria-live="polite"></p>
			<p>
				<button type="button" class="button conecom-order-vat-validate"><?php esc_html_e( 'Validate VAT number', 'woocommerce-es' ); ?></button>
			</p>
			<p class="description">
				<?php esc_html_e( 'Uses the VAT No field of the billing address. After validating, click "Recalculate" in the order items to update the taxes.', 'woocommerce-es' ); ?>
			</p>
		</div>
		<?php
	}

	/**
	 * Enqueue admin script on the order edit screen.
	 *
	 * @return void
	 */
	public function enqueue_scripts() {
		$screen = get_current_screen();
		if ( ! $screen || ! in_array( $screen->id, $this->get_order_screens(), true ) ) {
			return;
		}

		wp_enqueue_script(
			'conecom-admin-order-vat',
			CONECOM_PLUGIN_URL . 'includes/assets/admin-order-vat.js',
			array(),
			CONECOM_VERSION,
			true
		);

		wp_localize_script(
			'conecom-admin-order-vat',
			'conecomOrderVAT',
			array(
				'ajaxUrl'  => admin_url( 'admin-ajax.php' ),
				'action'   => self::AJAX_ACTION,
				'nonce'    => wp_create_nonce( self::AJAX_ACTION ),
				'messages' => array(
					'checking'    => __( 'Validating VAT number...', 'woocommerce-es' ),
					'error'       => __( 'Error validating VAT number', 'woocommerce-es' ),
					'valid'       => __( 'Valid VAT number', 'woocommerce-es' ),
					'invalid'     => __( 'Invalid VAT number', 'woocommerce-es' ),
					'empty'       => __( 'No VAT number', 'woocommerce-es' ),
					'yes'         => __( 'Yes', 'woocommerce-es' ),
					'no'          => __( 'No', 'woocommerce-es' ),
					'recalculate' => __( 'Click "Recalculate" in the order items to update the taxes.', 'woocommerce-es' ),
				),
			)
		);
	}

	/**
	 * Validates the VAT number typed in the order screen and stores the exemption.
	 *
	 * @return void
	 */
	public function ajax_validate_order_vat() {
		check_ajax_referer( self::AJAX_ACTION, 'security' );

		$order_id = isset( $_POST['order_id'] ) ? absint( $_POST['order_id'] ) : 0;
		if ( ! $order_id || ! current_user_can( 'edit_shop_orders' ) ) {
			wp_send_json_error( array( 'message' => __( 'You do not have permission to edit this order.', 'woocommerce-es' ) ), 403 );
		}

		$order = wc_get_order( $order_id );
		if ( ! $order ) {
			wp_send_json_error( array( 'message' => __( 'Order not found.', 'woocommerce-es' ) ), 404 );
		}

		$vat_number       = isset( $_POST['vat_number'] ) ? sanitize_text_field( wp_unslash( $_POST['vat_number'] ) ) : '';
		$billing_country  = isset( $_POST['billing_country'] ) ? strtoupper( sanitize_text_field( wp_unslash( $_POST['billing_country'] ) ) ) : '';
		$shipping_country = isset( $_POST['shipping_country'] ) ? strtoupper( sanitize_text_field( wp_unslash( $_POST['shipping_country'] ) ) ) : '';

		// Country typed in the screen (not saved yet), following the "Calculate tax based on" setting.
		$tax_based_on = get_option( 'woocommerce_tax_based_on', 'shipping' );
		if ( 'base' === $tax_based_on ) {
			$country = WC()->countries->get_base_country();
		} elseif ( 'shipping' === $tax_based_on && '' !== $shipping_country ) {
			$country = $shipping_country;
		} else {
			$country = '' !== $billing_country ? $billing_country : VAT::get_order_tax_country( $order );
		}

		// Store the VAT number now, so the native "Recalculate" uses the exemption.
		$order->update_meta_data( '_billing_vat', $vat_number );
		$response = VAT::update_order_vat_exemption( $order, $vat_number, $country );
		$order->save_meta_data();

		unset( $response['result']['cached'] );
		wp_send_json_success( $response );
	}

	/**
	 * Re-validates the VAT number when the order is saved in the admin and,
	 * if the exemption changes on an editable order, recalculates its taxes.
	 *
	 * @param int $order_id Order ID.
	 * @return void
	 */
	public function maybe_update_exemption_on_save( $order_id ) {
		$order = wc_get_order( $order_id );
		if ( ! $order ) {
			return;
		}

		$vat_number = VAT::clean_vat_number( ORDER::get_billing_vat( $order ) );
		$country    = VAT::get_order_tax_country( $order );
		$stored     = VAT::get_vat_validation_result( $order );
		$was_exempt = 'yes' === $order->get_meta( 'is_vat_exempt' );

		// Nothing to do for orders without VAT number nor exemption from this plugin.
		if ( '' === $vat_number && empty( $stored ) && 'yes' !== $order->get_meta( '_vat_exempt_applied' ) ) {
			return;
		}

		// Skip the VIES request when the same number and country were already checked.
		if ( ! empty( $stored['vat_number'] ) && ! empty( $stored['country_code'] )
			&& VAT::strip_country_prefix( $vat_number, $country ) === $stored['vat_number']
			&& $country === $stored['country_code'] ) {
			return;
		}

		$response = VAT::update_order_vat_exemption( $order, $vat_number, $country );

		if ( $was_exempt !== $response['vat_exempt'] && $order->is_editable() ) {
			$order->calculate_totals( true );
			$order->add_order_note(
				$response['vat_exempt']
					? __( 'VAT exemption applied (valid intra-community VAT number). Taxes recalculated.', 'woocommerce-es' )
					: __( 'VAT exemption removed. Taxes recalculated.', 'woocommerce-es' )
			);
		}

		$order->save();
	}
}

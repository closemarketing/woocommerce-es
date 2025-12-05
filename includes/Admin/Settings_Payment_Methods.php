<?php
/**
 * Payment methods settings page.
 *
 * @package    WordPress
 */

namespace CLOSE\ConnectEcommerce\Admin;

use CLOSE\ConnectEcommerce\Helpers\PAYMENTS;

defined( 'ABSPATH' ) || exit;

/**
 * Class Settings_Payment_Methods.
 */
class Settings_Payment_Methods {
	/**
	 * Option name for mappings.
	 *
	 * @var string
	 */
	private $option_name = 'connect_ecommerce_payment_methods';

	/**
	 * Connector API instance.
	 *
	 * @var object|null
	 */
	private $connector_api;

	/**
	 * Connector slug.
	 *
	 * @var string
	 */
	private $connector_slug = '';

	/**
	 * Connector options.
	 *
	 * @var array
	 */
	private $connector_options = array();

	/**
	 * Connector type slug.
	 *
	 * @var string
	 */
	private $connector_type = '';

	/**
	 * Construct of class.
	 *
	 * @param object|null $connector_api Connector API instance.
	 * @param string      $connector_slug Connector identifier.
	 * @param array       $connector_options Connector specific options.
	 * @param string      $connector_type Connector type slug.
	 *
	 * @return void
	 */
	public function __construct( $connector_api = null, $connector_slug = '', $connector_options = array(), $connector_type = '' ) {
		$this->connector_api     = $connector_api;
		$this->connector_slug    = $connector_slug;
		$this->connector_options = $connector_options;
		$this->connector_type    = $connector_type;

		add_action( 'admin_post_connect_ecommerce_save_payment_methods', array( $this, 'handle_form_submission' ) );
	}

	/**
	 * Render payment methods page.
	 *
	 * @return void
	 */
	public function render_page() {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			?>
			<div class="notice notice-error">
				<p><?php esc_html_e( 'You do not have permission to manage payment method mappings.', 'woocommerce-es' ); ?></p>
			</div>
			<?php
			return;
		}

		$connector_label = isset( $this->connector_options['name'] ) ? (string) $this->connector_options['name'] : $this->connector_slug;
		if ( '' === $connector_label ) {
			$connector_label = __( 'Connector', 'woocommerce-es' );
		}

		$updated_flag = isset( $_GET['pm_updated'] ) ? sanitize_text_field( wp_unslash( $_GET['pm_updated'] ) ) : '';

		$connector_methods    = $this->get_connector_methods();
		$connector_treasuries = $this->get_connector_treasuries();
		$gateways             = $this->get_wc_gateways();
		$saved_mappings       = $this->get_saved_mappings();
		?>
		<div class="connect-payment-methods">
			<h2>
				<?php
				echo esc_html(
					sprintf(
						/* translators: %s: Connector name. */
						__( 'Payment Method Mapping (%s)', 'woocommerce-es' ),
						$connector_label
					)
				);
				?>
			</h2>
			<p><?php esc_html_e( 'Assign each WooCommerce payment gateway to the corresponding connector payment method used for synchronization.', 'woocommerce-es' ); ?></p>
			<?php
			if ( '1' === $updated_flag ) {
				?>
				<div class="notice notice-success is-dismissible">
					<p><?php esc_html_e( 'Payment method mapping saved successfully.', 'woocommerce-es' ); ?></p>
				</div>
				<?php
			}

			if ( empty( $connector_methods ) ) {
				?>
				<div class="notice notice-warning inline">
					<p><?php esc_html_e( 'No payment methods were returned by the connector. Please verify the connector configuration.', 'woocommerce-es' ); ?></p>
				</div>
				<?php
				return;
			}

			if ( empty( $gateways ) ) {
				?>
				<div class="notice notice-warning inline">
					<p><?php esc_html_e( 'No WooCommerce payment gateways are available to map.', 'woocommerce-es' ); ?></p>
				</div>
				<?php
				return;
			}
			?>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="connect_ecommerce_save_payment_methods" />
				<input type="hidden" name="connector" value="<?php echo esc_attr( $this->connector_slug ); ?>" />
				<?php wp_nonce_field( 'connect_ecommerce_save_payment_methods', 'connect_ecommerce_payment_methods_nonce' ); ?>
				<?php if ( empty( $connector_treasuries ) ) { ?>
					<div class="notice notice-info inline">
						<p><?php esc_html_e( 'No treasury accounts were returned by the connector. You can still map payment methods.', 'woocommerce-es' ); ?></p>
					</div>
				<?php } ?>
				<table class="widefat striped">
					<thead>
						<tr>
							<th scope="col"><?php esc_html_e( 'WooCommerce Gateway', 'woocommerce-es' ); ?></th>
							<th scope="col"><?php esc_html_e( 'Connector Payment Method', 'woocommerce-es' ); ?></th>
							<th scope="col"><?php esc_html_e( 'Connector Treasury', 'woocommerce-es' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php
						foreach ( $gateways as $gateway_id => $gateway ) {
							if ( ! is_object( $gateway ) ) {
								continue;
							}

							$sanitized_gateway_id = sanitize_text_field( (string) $gateway_id );
							if ( '' === $sanitized_gateway_id ) {
								continue;
							}

							$gateway_title = '';
							if ( is_callable( array( $gateway, 'get_method_title' ) ) ) {
								$gateway_title = (string) $gateway->get_method_title();
							} elseif ( isset( $gateway->method_title ) ) {
								$gateway_title = (string) $gateway->method_title;
							}
							if ( '' === $gateway_title && isset( $gateway->title ) ) {
								$gateway_title = (string) $gateway->title;
							}
							if ( '' === $gateway_title ) {
								$gateway_title = $sanitized_gateway_id;
							}
							$gateway_title = wp_strip_all_tags( $gateway_title );

							$gateway_description = '';
							if ( is_callable( array( $gateway, 'get_method_description' ) ) ) {
								$gateway_description = (string) $gateway->get_method_description();
							} elseif ( isset( $gateway->description ) ) {
								$gateway_description = (string) $gateway->description;
							}
							$gateway_description = wp_strip_all_tags( $gateway_description );

							$selected_method   = '';
							$selected_treasury = '';
							if ( isset( $saved_mappings[ $sanitized_gateway_id ] ) ) {
								if ( isset( $saved_mappings[ $sanitized_gateway_id ]['method'] ) ) {
									$selected_method = sanitize_text_field( (string) $saved_mappings[ $sanitized_gateway_id ]['method'] );
								}
								if ( isset( $saved_mappings[ $sanitized_gateway_id ]['treasury'] ) ) {
									$selected_treasury = sanitize_text_field( (string) $saved_mappings[ $sanitized_gateway_id ]['treasury'] );
								}
							}
							?>
							<tr>
								<td>
									<strong><?php echo esc_html( $gateway_title ); ?></strong>
									<br />
									<code><?php echo esc_html( $sanitized_gateway_id ); ?></code>
								</td>
								<td>
									<select name="payment_methods[<?php echo esc_attr( $sanitized_gateway_id ); ?>]" class="regular-text">
										<option value=""><?php esc_html_e( '— Select —', 'woocommerce-es' ); ?></option>
										<?php
										foreach ( $connector_methods as $method_id => $method_label ) {
											$sanitized_method_id = sanitize_text_field( (string) $method_id );
											$sanitized_method_label = sanitize_text_field( (string) $method_label );
											?>
											<option value="<?php echo esc_attr( $sanitized_method_id ); ?>" <?php echo selected( $selected_method, $sanitized_method_id, false ); ?>>
												<?php echo esc_html( $sanitized_method_label ); ?>
											</option>
											<?php
										}
										?>
									</select>
								</td>
								<td>
									<select name="treasury_accounts[<?php echo esc_attr( $sanitized_gateway_id ); ?>]" class="regular-text" <?php disabled( empty( $connector_treasuries ) ); ?>>
										<option value=""><?php esc_html_e( '— Select —', 'woocommerce-es' ); ?></option>
										<?php
										foreach ( $connector_treasuries as $treasury_id => $treasury_label ) {
											$sanitized_treasury_id    = sanitize_text_field( (string) $treasury_id );
											$sanitized_treasury_label = sanitize_text_field( (string) $treasury_label );
											?>
											<option value="<?php echo esc_attr( $sanitized_treasury_id ); ?>" <?php echo selected( $selected_treasury, $sanitized_treasury_id, false ); ?>>
												<?php echo esc_html( $sanitized_treasury_label ); ?>
											</option>
											<?php
										}
										?>
									</select>
								</td>
							</tr>
							<?php
						}
						?>
					</tbody>
				</table>
				<?php submit_button( __( 'Save payment methods', 'woocommerce-es' ) ); ?>
			</form>
		</div>
		<?php
	}

	/**
	 * Handle form submission.
	 *
	 * @return void
	 */
	public function handle_form_submission() {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( esc_html__( 'You do not have permission to manage payment method mappings.', 'woocommerce-es' ) );
		}

		check_admin_referer( 'connect_ecommerce_save_payment_methods', 'connect_ecommerce_payment_methods_nonce' );

		$connector = isset( $_POST['connector'] ) ? sanitize_text_field( wp_unslash( $_POST['connector'] ) ) : '';

		$raw_methods    = array();
		$raw_treasuries = array();

		$post_input = filter_input_array(
			INPUT_POST,
			array(
				'payment_methods'   => array(
					'filter' => FILTER_DEFAULT,
					'flags'  => FILTER_REQUIRE_ARRAY,
				),
				'treasury_accounts' => array(
					'filter' => FILTER_DEFAULT,
					'flags'  => FILTER_REQUIRE_ARRAY,
				),
			)
		);

		if ( isset( $post_input['payment_methods'] ) && is_array( $post_input['payment_methods'] ) ) {
			$raw_methods = wp_unslash( $post_input['payment_methods'] );
		}

		if ( isset( $post_input['treasury_accounts'] ) && is_array( $post_input['treasury_accounts'] ) ) {
			$raw_treasuries = wp_unslash( $post_input['treasury_accounts'] );
		}

		$submitted_mappings = array();
		$gateway_ids        = array_unique(
			array_merge(
				array_keys( $raw_methods ),
				array_keys( $raw_treasuries )
			)
		);

		foreach ( $gateway_ids as $gateway_id ) {
			$sanitized_gateway_id = sanitize_text_field( (string) $gateway_id );

			if ( '' === $sanitized_gateway_id ) {
				continue;
			}

			$mapping_entry = array();

			if ( isset( $raw_methods[ $gateway_id ] ) ) {
				$sanitized_method_id = sanitize_text_field( (string) $raw_methods[ $gateway_id ] );
				if ( '' !== $sanitized_method_id ) {
					$mapping_entry['method'] = $sanitized_method_id;
				}
			}

			if ( isset( $raw_treasuries[ $gateway_id ] ) ) {
				$sanitized_treasury_id = sanitize_text_field( (string) $raw_treasuries[ $gateway_id ] );
				if ( '' !== $sanitized_treasury_id ) {
					$mapping_entry['treasury'] = $sanitized_treasury_id;
				}
			}

			if ( ! empty( $mapping_entry ) ) {
				$submitted_mappings[ $sanitized_gateway_id ] = $mapping_entry;
			}
		}

		$existing = get_option( $this->option_name, array() );
		if ( ! is_array( $existing ) ) {
			$existing = array();
		}

		if ( '' === $connector ) {
			$connector = $this->connector_slug;
		}

		if ( empty( $submitted_mappings ) ) {
			if ( isset( $existing[ $connector ] ) ) {
				unset( $existing[ $connector ] );
			}
		} else {
			$existing[ $connector ] = $submitted_mappings;
		}

		update_option( $this->option_name, $existing );

		$redirect_url = add_query_arg(
			array(
				'page'       => 'connect_ecommerce',
				'tab'        => 'settings',
				'subtab'     => 'payment_methods',
				'pm_updated' => '1',
			),
			admin_url( 'admin.php' )
		);

		wp_safe_redirect( $redirect_url );
		exit;
	}

	/**
	 * Get WooCommerce payment gateways.
	 *
	 * @return array
	 */
	private function get_wc_gateways() {
		if ( ! function_exists( 'WC' ) ) {
			return array();
		}

		$gateways_controller = WC()->payment_gateways();
		if ( ! $gateways_controller || ! method_exists( $gateways_controller, 'payment_gateways' ) ) {
			return array();
		}

		$gateways = $gateways_controller->payment_gateways();
		if ( ! is_array( $gateways ) ) {
			return array();
		}

		return $gateways;
	}

	/**
	 * Get connector payment methods.
	 *
	 * @return array
	 */
	private function get_connector_methods() {
		if ( ! is_object( $this->connector_api ) || ! method_exists( $this->connector_api, 'get_payment_methods' ) ) {
			return array();
		}

		$methods = $this->connector_api->get_payment_methods();
		if ( ! is_array( $methods ) || empty( $methods ) ) {
			return array();
		}

		$sanitized_methods = array();
		foreach ( $methods as $method_id => $method_label ) {
			if ( ! is_scalar( $method_id ) || ! is_scalar( $method_label ) ) {
				continue;
			}

			$sanitized_method_id    = sanitize_text_field( (string) $method_id );
			$sanitized_method_label = sanitize_text_field( (string) $method_label );

			if ( '' === $sanitized_method_id ) {
				continue;
			}

			if ( '' === $sanitized_method_label ) {
				continue;
			}

			$sanitized_methods[ $sanitized_method_id ] = $sanitized_method_label;
		}

		return $sanitized_methods;
	}

	/**
	 * Get connector treasury accounts.
	 *
	 * @return array
	 */
	private function get_connector_treasuries() {
		if ( ! is_object( $this->connector_api ) || ! method_exists( $this->connector_api, 'get_treasury_accounts' ) ) {
			return array();
		}

		$treasuries = $this->connector_api->get_treasury_accounts();
		if ( ! is_array( $treasuries ) || empty( $treasuries ) ) {
			return array();
		}

		$sanitized_treasuries = array();
		foreach ( $treasuries as $treasury_id => $treasury_label ) {
			if ( ! is_scalar( $treasury_id ) || ! is_scalar( $treasury_label ) ) {
				continue;
			}

			$sanitized_treasury_id    = sanitize_text_field( (string) $treasury_id );
			$sanitized_treasury_label = sanitize_text_field( (string) $treasury_label );

			if ( '' === $sanitized_treasury_id ) {
				continue;
			}

			if ( '' === $sanitized_treasury_label ) {
				continue;
			}

			$sanitized_treasuries[ $sanitized_treasury_id ] = $sanitized_treasury_label;
		}

		return $sanitized_treasuries;
	}

	/**
	 * Get saved mappings for connector.
	 *
	 * @return array
	 */
	private function get_saved_mappings() {
		$mappings = PAYMENTS::get_payment_method_mappings( $this->connector_slug, $this->connector_type );

		// Convert to the format expected by render_page.
		$prepared = array();
		foreach ( $mappings['payment_methods'] as $gateway_id => $method_id ) {
			$prepared[ $gateway_id ] = array( 'method' => $method_id );
		}

		foreach ( $mappings['treasury_accounts'] as $gateway_id => $treasury_id ) {
			if ( isset( $prepared[ $gateway_id ] ) ) {
				$prepared[ $gateway_id ]['treasury'] = $treasury_id;
			} else {
				$prepared[ $gateway_id ] = array( 'treasury' => $treasury_id );
			}
		}

		return $prepared;
	}
}


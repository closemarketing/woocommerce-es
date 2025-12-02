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

use CLOSE\ConnectEcommerce\Helpers\VAT;
use CLOSE\ConnectEcommerce\Helpers\ORDER;

/**
 * Public class
 */
class Checkout {
	/**
	 * Options
	 *
	 * @var array
	 */
	public $options = array();

	/**
	 * Settings public
	 *
	 * @var array
	 */
	public $setttings_public = array();

	/**
	 * Construct method
	 *
	 * @param array $options
	 */
	public function __construct( $options = array() ) {
		$this->options          = $options;
		$this->setttings_public = get_option( 'connect_ecommerce_public' );

		// EU VAT.
		add_filter( 'woocommerce_billing_fields', array( $this, 'add_billing_fields' ) );
		add_filter( 'woocommerce_admin_billing_fields', array( $this, 'add_billing_shipping_fields_admin' ) );
		add_filter( 'woocommerce_admin_shipping_fields', array( $this, 'add_billing_shipping_fields_admin' ) );
		add_filter( 'woocommerce_load_order_data', array( $this, 'add_var_load_order_data' ) );
		add_action( 'woocommerce_email_after_order_table', array( $this, 'email_key_notification' ), 10, 1 );
		add_filter( 'wpo_wcpdf_billing_address', array( $this, 'add_vat_invoices' ), 10, 2 );

		$show_vat = isset( $this->setttings_public['vat_show'] ) ? $this->setttings_public['vat_show'] : 'yes';
		if ( 'yes' === $show_vat ) {
			add_filter( 'woocommerce_checkout_fields', array( $this, 'custom_override_checkout_fields' ) );

			// Gutenberg compatibility.
			add_action( 'woocommerce_init', array( $this, 'add_vat_field_to_checkout' ), 99 );
			add_action( 'wp_loaded', array( $this, 'add_vat_field_to_checkout' ), 10 );
		}
		
		// Save and display additional checkout fields.
		add_action( 'woocommerce_checkout_update_order_meta', array( $this, 'save_additional_checkout_fields' ) );
		add_action( 'woocommerce_admin_order_data_after_billing_address', array( $this, 'display_additional_checkout_fields_admin' ) );

		$remove_free = isset( $this->setttings_public['remove_free'] ) ? $this->setttings_public['remove_free'] : 'no';
		if ( 'yes' === $remove_free ) {
			// Hide shipping rates when free shipping is available.
			add_filter( 'woocommerce_package_rates', array( $this, 'shipping_when_free_is_available' ), 100 );
		}

		$terms_registration = isset( $this->setttings_public['terms_registration'] ) ? $this->setttings_public['terms_registration'] : 'no';
		if ( 'yes' === $terms_registration ) {
			add_action( 'woocommerce_register_form', array( $this, 'add_terms_and_conditions_to_registration' ), 20 );
			add_action( 'woocommerce_register_post', array( $this, 'terms_and_conditions_validation' ), 20, 3 );
		}

		// VAT validation via VIES.
		$vat_vies_enabled = isset( $this->setttings_public['vat_vies_enabled'] ) ? $this->setttings_public['vat_vies_enabled'] : 'yes';
		if ( 'yes' === $vat_vies_enabled ) {
			// Classic checkout validation.
			add_action( 'woocommerce_after_checkout_validation', array( $this, 'validate_vat_number_checkout' ), 10, 2 );
			add_action( 'woocommerce_checkout_order_processed', array( $this, 'save_vat_validation_result' ), 10, 1 );
			
			// Gutenberg Blocks checkout validation.
			add_action( 'woocommerce_store_api_checkout_update_order_from_request', array( $this, 'validate_vat_number_checkout_blocks' ), 10, 2 );
			
			// Real-time VAT validation.
			$vat_realtime = isset( $this->setttings_public['vat_realtime_validation'] ) ? $this->setttings_public['vat_realtime_validation'] : 'yes';
			if ( 'yes' === $vat_realtime ) {
				add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_vat_validation_scripts' ) );
			}
			
			// Initialize AJAX hooks.
			VAT::init_ajax_hooks();
			
			// Apply zero-rate tax class when VAT exempt.
			add_filter( 'woocommerce_product_tax_class', array( $this, 'apply_zero_rate_tax_class' ), 10, 2 );
			add_filter( 'woocommerce_product_get_tax_class', array( $this, 'apply_zero_rate_tax_class' ), 10, 2 );
			add_filter( 'woocommerce_product_variation_get_tax_class', array( $this, 'apply_zero_rate_tax_class' ), 10, 2 );
		}
	}

	/**
	 * Insert element before of a specific array position
	 *
	 * @param array  $source   Source array.
	 * @param array  $need     Array with the element to insert.
	 * @param string $previous Key of the array where insert the new element.
	 *
	 * @return array
	 * @since 1.0.0
	 */
	public function array_splice_assoc( &$source, $need, $previous ) {
		$return = array();

		foreach ( $source as $key => $value ) {
			if ( $key == $previous ) {
				$need_key   = array_keys( $need );
				$key_need   = array_shift( $need_key );
				$value_need = $need[ $key_need ];

				$return[ $key_need ] = $value_need;
			}

			$return[ $key ] = $value;
		}

		return $return;
	}

	public function add_billing_fields( $fields ) {
		$fields['billing_company']['class'] = array( 'form-row-first' );
		$fields['billing_company']['clear'] = false;

		$vatinfo_mandatory = isset( $this->setttings_public['vat_mandatory'] ) ? $this->setttings_public['vat_mandatory'] : 'no';
		$vatinfo_show      = isset( $this->setttings_public['vat_show'] ) ? $this->setttings_public['vat_show'] : 'no';

		if ( $vatinfo_show != 'yes' ) {
			return $fields;
		}

		if ( $vatinfo_mandatory == 'yes' ) {
			$mandatory = true;
		} else {
			$mandatory = false;
		}

		$field = array(
			'billing_vat' => array(
				'label'       => apply_filters( 'conecom_vatssn_label', __( 'VAT No', 'woocommerce-es' ) ),
				'placeholder' => apply_filters( 'conecom_vatssn_label_x', __( 'VAT No', 'woocommerce-es' ) ),
				'required'    => $mandatory,
				'class'       => array( 'form-row-last' ),
				'clear'       => true,
			),
		);

		return $this->array_splice_assoc( $fields, $field, 'billing_company' );
	}

	/**
	 * Custom override checkout fields.
	 *
	 * @param array $fields Fields.
	 * @return array
	 */
	public function custom_override_checkout_fields( $fields ) {
		$company_field = isset( $this->setttings_public['company_field'] ) ? $this->setttings_public['company_field'] : 'no';

		if ( 'yes' !== $company_field ) {
			unset( $fields['billing']['billing_company'] );
		}
		// Move billing_company after billing_last_name if both exist
		if ( isset( $fields['billing']['billing_company'] ) && isset( $fields['billing']['billing_last_name'] ) ) {
			$billing_fields = $fields['billing'];
			$new_billing_fields = array();
			foreach ( $billing_fields as $key => $value ) {
				$new_billing_fields[ $key ] = $value;
				if ( 'billing_last_name' === $key && isset( $billing_fields['billing_company'] ) ) {
					$new_billing_fields['billing_company'] = $billing_fields['billing_company'];
					unset( $billing_fields['billing_company'] );
				}
			}
			$fields['billing'] = $new_billing_fields;
		}

		return $fields;
	}

	/**
	 * Add VAT field to checkout.
	 *
	 * @return void
	 */
	public function add_vat_field_to_checkout() {
		$required = isset( $this->setttings_public['vat_mandatory'] ) ? $this->setttings_public['vat_mandatory'] : 'no';
		$required = $required === 'yes' ? true : false;

		woocommerce_register_additional_checkout_field(
			array(
				'id'            => 'connect_ecommerce/billing_vat',
				'label'         => __( 'VAT Number', 'connect-ecommerce' ),
				'optionalLabel' => __( 'VAT Number (optional)', 'connect-ecommerce' ),
				'location'      => 'address',
				'priority'      => 10,
				'required'      => $required,
				'attributes'    => array(
					'autocomplete' => 'billing_vat',
					'title'        => __( 'VAT number', 'connect-ecommerce' ),
				),
			)
		);
	}

	/**
	 * Add VAT field to admin order page.
	 *
	 * @param array $fields Fields.
	 * @return array
	 */
	public function add_billing_shipping_fields_admin( $fields ) {
		$fields['vat'] = array(
			'label' => apply_filters( 'conecom_vatssn_label', __( 'VAT No', 'woocommerce-es' ) ),
		);

		return $fields;
	}

	/**
	 * Add VAT field to order data.
	 *
	 * @param array $fields Fields.
	 * @return array
	 */
	public function add_var_load_order_data( $fields ) {
		$fields['billing_vat'] = '';
		return $fields;
	}

	/**
	 * Adds NIF in email notification
	 *
	 * @param object $order Order object.
	 * @return void
	 */
	public function email_key_notification( $order ) {
		echo '<p><strong>' . esc_html__( 'VAT No', 'woocommerce-es' ) . ':</strong> ';
		echo esc_html( get_post_meta( $order->get_id(), '_billing_vat', true ) ) . '</p>';
	}

	/**
	 * Adds VAT info in WooCommerce PDF Invoices & Packing Slips
	 *
	 * @param string $address Address.
	 * @param object $document Document object.
	 *
	 * @return string
	 */
	public function add_vat_invoices( $address, $document ) {
		$vat_number = '';
		if ( $document && is_callable( array( $document, 'get_custom_field' ) ) ) {
			foreach ( CONECOM_VAT_FIELD_SLUGS as $vat_field_slug ) {
				$vat_number = $document->get_custom_field( $vat_field_slug );
				if ( ! empty( $vat_number ) ) {
					$address .= sprintf( '<p>%s %s</p>', __( 'VAT info:', 'woocommerce-es' ), $vat_number );
					break;
				}
			}
		}
		if ( ! empty( $vat_number ) ) {
			return $address;
		}
		// Get VAT number from order.
		if ( empty( $document->order ) ) {
			return $address;
		}
		$vat_number = ORDER::get_billing_vat( $document->order );
		if ( ! empty( $vat_number ) ) {
			$address .= sprintf(
				/* translators: 1: VAT info label, 2: VAT number */
				'<p>%s %s</p>',
				__( 'VAT info:', 'woocommerce-es' ),
				$vat_number
			);
		}
		return $address;
	}

	/**
	 * Hide shipping rates when free shipping is available.
	 * Updated to support WooCommerce 2.6 Shipping Zones.
	 *
	 * @param array $rates Array of rates found for the package.
	 * @return array
	 */
	public function shipping_when_free_is_available( $rates ) {
		$free = array();
		foreach ( $rates as $rate_id => $rate ) {
			if ( 'free_shipping' === $rate->method_id ) {
				$free[ $rate_id ] = $rate;
				break;
			}
		}
		return ! empty( $free ) ? $free : $rates;
	}

	/**
	 * Add terms and conditions in registration page
	 *
	 * @return void
	 */
	public function add_terms_and_conditions_to_registration() {
		if ( wc_get_page_id( 'terms' ) > 0 && is_account_page() ) {
			?>
			<p class="form-row terms wc-terms-and-conditions">
				<label class="woocommerce-form__label woocommerce-form__label-for-checkbox checkbox">
					<input type="checkbox" class="woocommerce-form__input woocommerce-form__input-checkbox input-checkbox" name="terms" <?php checked( apply_filters( 'woocommerce_terms_is_checked_default', isset( $_POST['terms'] ) ), true ); ?> id="terms" /> <span>
					<?php
					echo sprintf(
						/* translators: 1: Terms and conditions page link */
						__( 'I&rsquo;ve read and accept the <a href="%s" target="_blank" class="woocommerce-terms-and-conditions-link">terms &amp; conditions</a>', 'woocommerce-es' ),
						esc_url( wc_get_page_permalink( 'terms' ) )
					);
					?>
					</span> <span class="required">*</span>
				</label>
				<input type="hidden" name="terms-field" value="1" />
			</p>
			<?php
		}
	}

	/**
	 * Validate required term and conditions check box
	 * Not applies in Admin
	 *
	 * @param string $username Username.
	 * @param string $email Email.
	 * @param object $validation_errors Object of validation errors.
	 *
	 * @return void
	 */
	public function terms_and_conditions_validation( $username, $email, $validation_errors ) {
		$action = isset( $_POST['action'] ) ? sanitize_text_field( wp_unslash( $_POST['action'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing
		if ( 'yith_wcaf_create_affiliate' === $action || is_admin() ) {
			return;
		}
		if ( ! isset( $_POST['terms'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
			$validation_errors->add( 'terms_error', __( 'Terms and conditions are not accepted!', 'woocommerce-es' ) );
		}
	}

	/**
	 * Save additional checkout field data to order.
	 *
	 * @param int $order_id Order ID.
	 * @return void
	 */
	public function save_additional_checkout_fields( $order_id ) {
		// Save marketing opt-in field.
		if ( ! empty( $_POST['namespace/marketing-opt-in'] ) ) {
			update_post_meta( $order_id, '_marketing_opt_in', sanitize_text_field( wp_unslash( $_POST['namespace/marketing-opt-in'] ) ) );
		}

		// Save government ID field.
		if ( ! empty( $_POST['namespace/gov-id'] ) ) {
			update_post_meta( $order_id, '_government_id', sanitize_text_field( wp_unslash( $_POST['namespace/gov-id'] ) ) );
		}
	}

	/**
	 * Display additional checkout field data in admin order page.
	 *
	 * @param object $order Order object.
	 * @return void
	 */
	public function display_additional_checkout_fields_admin( $order ) {
		$marketing_opt_in = get_post_meta( $order->get_id(), '_marketing_opt_in', true );
		$government_id    = get_post_meta( $order->get_id(), '_government_id', true );

		if ( ! empty( $marketing_opt_in ) ) {
			echo '<p><strong>' . esc_html__( 'Marketing Opt-in', 'connect-ecommerce' ) . ':</strong> ';
			echo esc_html( $marketing_opt_in ) . '</p>';
		}

		if ( ! empty( $government_id ) ) {
			echo '<p><strong>' . esc_html__( 'Government ID', 'connect-ecommerce' ) . ':</strong> ';
			echo esc_html( $government_id ) . '</p>';
		}
	}

	/**
	 * Validate VAT number during checkout.
	 *
	 * @param array    $data Posted data.
	 * @param WP_Error $errors Validation errors.
	 * @return void
	 */
	public function validate_vat_number_checkout( $data, $errors ) {
		$vat_number = '';

		// Check standard fields.
		foreach ( CONECOM_VAT_FIELD_SLUGS as $field ) {
			if ( isset( $data[ $field ] ) && ! empty( $data[ $field ] ) ) {
				$vat_number = sanitize_text_field( wp_unslash( $data[ $field ] ) );
				break;
			}
		}

		// Check custom checkout field (Gutenberg compatible).
		if ( empty( $vat_number ) && isset( $_POST['connect_ecommerce/billing_vat'] ) ) {
			$vat_number = sanitize_text_field( wp_unslash( $_POST['connect_ecommerce/billing_vat'] ) );
		}

		// If no VAT number provided, skip validation.
		if ( empty( $vat_number ) ) {
			return;
		}

		// Get country code.
		$country_code = isset( $data['billing_country'] ) ? $data['billing_country'] : '';

		// Validate VAT number.
		$validation_result = VAT::validate_vat_number( $vat_number, $country_code );

		// Store validation result in session for later use.
		WC()->session->set( 'vat_validation_result', $validation_result );

		// Apply or remove VAT exemption based on validation result.
		$is_valid = isset( $validation_result['valid'] ) && $validation_result['valid'];
		
		if ( $is_valid ) {
			// Apply VAT exemption if conditions are met.
			VAT::apply_vat_exemption( $country_code, $vat_number, true );
			
			// Check if exemption was applied.
			if ( VAT::is_customer_vat_exempt() ) {
				wc_add_notice(
					sprintf(
						// translators: 1: VAT number, 2: country code.
						__( 'VAT exemption applied. Valid B2B transaction for %1$s (%2$s).', 'woocommerce-es' ),
						$vat_number,
						$country_code
					),
					'success'
				);
			}
		} else {
			// Remove any existing exemption.
			VAT::remove_vat_exemption();
		}

		// Check if validation is mandatory.
		$vat_vies_mandatory = isset( $this->setttings_public['vat_vies_mandatory'] ) ? $this->setttings_public['vat_vies_mandatory'] : 'no';

		if ( 'yes' === $vat_vies_mandatory && ! $validation_result['valid'] ) {
			$errors->add(
				'vat_validation',
				sprintf(
					// translators: %s is the error message.
					__( 'VAT number validation failed: %s', 'woocommerce-es' ),
					$validation_result['message']
				)
			);
		} elseif ( ! $validation_result['valid'] ) {
			// Show notice but allow checkout.
			wc_add_notice(
				sprintf(
					// translators: %s is the error message.
					__( 'VAT number validation warning: %s. Your order will be processed but may require verification.', 'woocommerce-es' ),
					$validation_result['message']
				),
				'notice'
			);
		}
	}

	/**
	 * Save VAT validation result to order meta.
	 *
	 * @param int $order_id Order ID.
	 * @return void
	 */
	public function save_vat_validation_result( $order_id ) {
		// Get validation result from session.
		$validation_result = WC()->session->get( 'vat_validation_result' );

		if ( ! empty( $validation_result ) ) {
			VAT::save_vat_validation_result( $order_id, $validation_result );

			// Clear session.
			WC()->session->set( 'vat_validation_result', null );
		}

		// Save VAT exemption info if applied.
		if ( VAT::is_customer_vat_exempt() ) {
			$exemption_info = VAT::get_vat_exemption_info();
			
			if ( $exemption_info ) {
				update_post_meta( $order_id, '_vat_exempt_applied', 'yes' );
				update_post_meta( $order_id, '_vat_exempt_country', $exemption_info['country'] );
				update_post_meta( $order_id, '_vat_exempt_vat_number', $exemption_info['vat_number'] );
				
				// Add order note.
				$order = wc_get_order( $order_id );
				if ( $order ) {
					$order->add_order_note(
						sprintf(
							// translators: 1: VAT number, 2: country code.
							__( 'VAT exemption applied for B2B intra-community transaction. VAT: %1$s (%2$s)', 'woocommerce-es' ),
							$exemption_info['vat_number'],
							$exemption_info['country']
						)
					);
				}
			}
		}
	}

	/**
	 * Enqueue VAT validation scripts and styles
	 *
	 * @return void
	 */
	public function enqueue_vat_validation_scripts() {
		// Only load on checkout page.
		if ( ! is_checkout() ) {
			return;
		}

		$plugin_url = plugin_dir_url( dirname( dirname( __FILE__ ) ) );
		$version    = defined( 'WP_DEBUG' ) && WP_DEBUG ? time() : '1.0.0';

		// Enqueue CSS.
		wp_enqueue_style(
			'conecom-vat-validation',
			$plugin_url . 'includes/assets/vat-validation.css',
			array(),
			$version
		);

		// Enqueue JavaScript.
		wp_enqueue_script(
			'conecom-vat-validation',
			$plugin_url . 'includes/assets/vat-validation.js',
			array(),
			$version,
			true
		);

		// Prepare configuration and messages.
		$config = array(
			'ajaxUrl' => admin_url( 'admin-ajax.php' ),
			'nonce'   => wp_create_nonce( 'conecom_vat_validation' ),
			'enabled' => true,
			'minLengths' => array(
				'ES' => 9,
				'FR' => 11,
				'DE' => 9,
				'IT' => 11,
				'PT' => 9,
				'NL' => 12,
				'BE' => 10,
				'AT' => 9,
				'SE' => 12,
				'PL' => 10,
			),
			'messages' => array(
				'checking'       => __( 'Validating VAT number...', 'woocommerce-es' ),
				'valid'          => __( 'Valid VAT number', 'woocommerce-es' ),
				'invalid'        => __( 'Invalid VAT number', 'woocommerce-es' ),
				'too_short'      => __( 'VAT number is too short', 'woocommerce-es' ),
				'invalid_format' => __( 'Invalid VAT number format', 'woocommerce-es' ),
				'unknown'        => __( 'Could not validate VAT number', 'woocommerce-es' ),
				'error'          => __( 'Error validating VAT number', 'woocommerce-es' ),
			),
		);

		// Localize script with configuration.
		wp_localize_script( 'conecom-vat-validation', 'conecomVATConfig', $config );
	}

	/**
	 * Apply zero-rate tax class to products when VAT exempt
	 *
	 * @param string            $tax_class Tax class.
	 * @param \WC_Product|mixed $product Product object.
	 * @return string
	 */
	public function apply_zero_rate_tax_class( $tax_class, $product = null ) {
		// Check if customer has VAT exemption applied.
		if ( VAT::is_customer_vat_exempt() ) {
			return 'zero-rate';
		}

		return $tax_class;
	}

	/**
	 * Validate VAT number during checkout for WooCommerce Blocks.
	 *
	 * @param \WC_Order        $order Order object.
	 * @param \WP_REST_Request $request Request object.
	 * @return void
	 */
	public function validate_vat_number_checkout_blocks( $order, $request ) {
		$vat_number = '';

		// Get billing data from request.
		$billing_address = $request->get_param( 'billing_address' );
		
		// Check standard fields.
		foreach ( CONECOM_VAT_FIELD_SLUGS as $field ) {
			$field_name = str_replace( 'billing_', '', $field );
			if ( isset( $billing_address[ $field_name ] ) && ! empty( $billing_address[ $field_name ] ) ) {
				$vat_number = sanitize_text_field( $billing_address[ $field_name ] );
				break;
			}
		}
		if ( empty( $vat_number ) && isset( $billing_address['connect_ecommerce/billing_vat'] ) ) {
			$vat_number = sanitize_text_field( $billing_address['connect_ecommerce/billing_vat'] );
		}

		// If no VAT number provided, skip validation.
		if ( empty( $vat_number ) ) {
			return;
		}

		// Get country code.
		$country_code = isset( $billing_address['country'] ) ? $billing_address['country'] : '';

		// Validate VAT number.
		$validation_result = VAT::validate_vat_number( $vat_number, $country_code );

		// Save validation result to order meta.
		VAT::save_vat_validation_result( $order->get_id(), $validation_result );

		// Apply or remove VAT exemption based on validation result.
		$is_valid = isset( $validation_result['valid'] ) && $validation_result['valid'];
		
		if ( $is_valid ) {
			// Apply VAT exemption if conditions are met.
			VAT::apply_vat_exemption( $country_code, $vat_number, true );
			
			// Save exemption info to order if applied.
			if ( VAT::is_customer_vat_exempt() ) {
				update_post_meta( $order->get_id(), '_vat_exempt_applied', 'yes' );
				update_post_meta( $order->get_id(), '_vat_exempt_country', $country_code );
				update_post_meta( $order->get_id(), '_vat_exempt_vat_number', $vat_number );
				
				$order->add_order_note(
					sprintf(
						// translators: 1: VAT number, 2: country code.
						__( 'VAT exemption applied for B2B intra-community transaction. VAT: %1$s (%2$s)', 'woocommerce-es' ),
						$vat_number,
						$country_code
					)
				);
			}
		} else {
			// Remove any existing exemption.
			VAT::remove_vat_exemption();
		}

		// Check if validation is mandatory.
		$vat_vies_mandatory = isset( $this->setttings_public['vat_vies_mandatory'] ) ? $this->setttings_public['vat_vies_mandatory'] : 'no';

		if ( 'yes' === $vat_vies_mandatory && ! $validation_result['valid'] ) {
			throw new \Exception(
				sprintf(
					// translators: %s is the error message.
					__( 'VAT number validation failed: %s', 'woocommerce-es' ),
					$validation_result['message']
				)
			);
		} elseif ( ! $validation_result['valid'] ) {
			$order->add_order_note(
				sprintf(
					// translators: %s is the error message.
					__( 'VAT number validation warning: %s', 'woocommerce-es' ),
					$validation_result['message']
				)
			);
		}
	}
}


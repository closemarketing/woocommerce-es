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
		add_filter( 'wpo_wcpdf_billing_address', array( $this, 'add_vat_invoices' ) );

		/* Options for the plugin */
		//add_filter( 'woocommerce_checkout_fields', array( $this, 'custom_override_checkout_fields' ) );
		// Register additional checkout fields for Gutenberg compatibility.
		add_action( 'woocommerce_init', array( $this, 'add_vat_field_to_checkout' ), 99 );
		add_action( 'wp_loaded', array( $this, 'add_vat_field_to_checkout' ), 10 );
		
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

		$source = $return;
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
				'label'       => apply_filters( 'conecom_vatssn_label', __( 'VAT No', 'connect-ecommerce' ) ),
				'placeholder' => apply_filters( 'conecom_vatssn_label_x', __( 'VAT No', 'connect-ecommerce' ) ),
				'required'    => $mandatory,
				'class'       => array( 'form-row-last' ),
				'clear'       => true,
			),
		);

		$this->array_splice_assoc( $fields, $field, 'billing_address_1' );
		return $fields;
	}

	// Our hooked in function - $fields is passed via the filter!
	public function custom_override_checkout_fields( $fields ) {
		$company_field = isset( $this->setttings_public['company_field'] ) ? $this->setttings_public['company_field'] : 'no';

		if ( 'yes' !== $company_field ) {
			unset( $fields['billing']['billing_company'] );
		}

			return $fields;
	}

	public function add_vat_field_to_checkout() {
		// Register government ID field.
		woocommerce_register_additional_checkout_field(
			array(
				'id'            => 'connect_ecommerce/billing_vat',
				'label'         => __( 'VAT Number', 'connect-ecommerce' ),
				'optionalLabel' => __( 'VAT Number (optional)', 'connect-ecommerce' ),
				'location'      => 'address',
				'priority'      => 10,
				'required'      => true,
				'attributes'    => array(
					'autocomplete' => 'billing_vat',
					'pattern'      => '[A-Z0-9]{12}', // A 5-character string of capital letters and numbers.
					'title'        => __( 'VAT number', 'connect-ecommerce' ),
				),
			)
		);
		// Register government ID field.
		woocommerce_register_additional_checkout_field(
			array(
				'id'            => 'namespace/gov-id',
				'label'         => 'Government ID',
				'optionalLabel' => 'Government ID (optional)',
				'location'      => 'address',
				'priority'      => 10,
				'required'      => true,
				'attributes'    => array(
					'autocomplete'     => 'government-id',
					'aria-describedby' => 'some-element',
					'aria-label'       => 'custom aria label',
					'pattern'          => '[A-Z0-9]{5}', // A 5-character string of capital letters and numbers.
					'title'            => 'Title to show on hover',
					'data-custom'      => 'custom data',
				),
			)
		);
	}

	public function add_billing_shipping_fields_admin( $fields ) {
		$fields['vat'] = array(
			'label' => apply_filters( 'conecom_vatssn_label', __( 'VAT No', 'connect-ecommerce' ) ),
		);

		return $fields;
	}

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
		echo '<p><strong>' . esc_html__( 'VAT No', 'connect-ecommerce' ) . ':</strong> ';
		echo esc_html( get_post_meta( $order->get_id(), '_billing_vat', true ) ) . '</p>';
	}

	/**
	 * Adds VAT info in WooCommerce PDF Invoices & Packing Slips
	 *
	 * @param string $address Address.
	 * @return void
	 */
	public function add_vat_invoices( $address ) {
		echo wp_kses( $address, array(
			'p' => array(),
			'strong' => array(),
			'br' => array(),
			'span' => array('class' => array()),
			'div' => array('class' => array()),
		) ) . '<p>';
		echo '</p>';
	}

	/* END EU VAT*/

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
					printf(
						/* translators: 1: Terms and conditions page link */
						esc_html__( 'I&rsquo;ve read and accept the <a href="%s" target="_blank" class="woocommerce-terms-and-conditions-link">terms &amp; conditions</a>', 'connect-ecommerce' ),
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
	 *
	 * @param string $username Username.
	 * @param string $email Email.
	 * @param object $validation_errors Object of validation errors.
	 * @return object $validation_errors
	 */
	public function terms_and_conditions_validation( $username, $email, $validation_errors ) {
		if ( ! isset( $_POST['terms'] ) ) {
			$validation_errors->add( 'terms_error', __( 'Terms and condition are not checked!', 'connect-ecommerce' ) );
		}

		return $validation_errors;
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
}


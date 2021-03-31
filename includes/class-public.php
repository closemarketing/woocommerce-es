<?php
/**
 * Class for loading functions
 */
class WooCommerceESPlugin {
	/**
	 * The current langauge
	 *
	 * @var string
	 */
	private $language;

	/**
	 * Flag for the spanish langauge, true if current langauge is spanish, false otherwise
	 *
	 * @var boolean
	 */
	private $is_spa;


	/**
	 * Bootstrap
	 */
	public function __construct( $file ) {
		$this->file = $file;
		$wces_settings = get_option( 'wces_settings' );

		// Filters and actions.
		add_action( 'plugins_loaded', array( $this, 'wces_plugins_loaded' ) );

		add_filter( 'load_textdomain_mofile', array( $this, 'wces_load_mo_file' ), 10, 2 );

		// EU VAT.
		add_filter( 'woocommerce_billing_fields', array( $this, 'wces_add_billing_fields' ) );
		add_filter( 'woocommerce_admin_billing_fields', array( $this, 'wces_add_billing_shipping_fields_admin' ) );
		add_filter( 'woocommerce_admin_shipping_fields', array( $this, 'wces_add_billing_shipping_fields_admin' ) );
		add_filter( 'woocommerce_load_order_data', array( $this, 'wces_add_var_load_order_data' ) );
		add_action( 'woocommerce_email_after_order_table', array( $this, 'woocommerce_email_key_notification' ), 10, 1 );
		add_filter( 'wpo_wcpdf_billing_address', array( $this, 'wces_add_vat_invoices' ) );

		/* Options for the plugin */
		add_filter( 'woocommerce_checkout_fields', array( $this, 'custom_override_checkout_fields' ) );

		$remove_free = isset( $wces_settings['remove_free'] ) ? $wces_settings['remove_free'] : 'no';
		if ( 'yes' === $remove_free ) {
			// Hide shipping rates when free shipping is available.
			add_filter( 'woocommerce_package_rates', array( $this, 'shipping_when_free_is_available' ), 100 );
		}

		$op_checkout = isset( $wces_settings['opt_checkout'] ) ? $wces_settings['opt_checkout'] : 'no';
		if ( 'yes' === $op_checkout ) {
			add_action( 'woocommerce_before_checkout_form', array( $this, 'wces_style' ), 5 );
		}

		$terms_registration = isset( $wces_settings['terms_registration'] ) ? $wces_settings['terms_registration'] : 'no';
		if ( 'yes' === $terms_registration ) {
			add_action( 'woocommerce_register_form', array( $this, 'add_terms_and_conditions_to_registration' ), 20 );
			add_action( 'woocommerce_register_post', array( $this, 'terms_and_conditions_validation' ), 20, 3 );
		}

		/*
		 * WooThemes/WooCommerce don't execute the load_plugin_textdomain() in the 'init'
		 * action, therefor we have to make sure this plugin will load first
		 *
		 * @see http://stv.whtly.com/2011/09/03/forcing-a-wordpress-plugin-to-be-loaded-before-all-other-plugins/
		 */
		add_action( 'activated_plugin', array( $this, 'activated_plugin' ) );
	}


	/**
	 * Activated plugin
	 */
	public function activated_plugin() {
		$path = str_replace( WP_PLUGIN_DIR . '/', '', $this->file );

		if ( $plugins = get_option( 'active_plugins' ) ) {
			if ( $key = array_search( $path, $plugins ) ) {
				array_splice( $plugins, $key, 1 );
				array_unshift( $plugins, $path );

				update_option( 'active_plugins', $plugins );
			}
		}
	}


	/**
	 * Plugins loaded
	 */
	public function wces_plugins_loaded() {
		$rel_path = dirname( plugin_basename( $this->file ) ) . '/languages/';

		// Load plugin text domain - WooCommerce ES
		// WooCommerce mixed use of 'wc_gf_addons' and 'wc_gravityforms'
		load_plugin_textdomain( 'wces', false, $rel_path );
	}


	/**
	 * Load text domain MO file
	 *
	 * @param string $moFile
	 * @param string $domain
	 */
	public function wces_load_mo_file( $mo_file, $domain ) {
		if ( $this->language == null ) {
			$this->language = defined( 'WPLANG' ) ? WPLANG : get_locale();
			$this->is_spa   = ( $this->language == 'es' || $this->language == 'es_ES' );
		}

		// The ICL_LANGUAGE_CODE constant is defined from an plugin, so this constant
		// is not always defined in the first 'load_textdomain_mofile' filter call
		if ( defined( 'ICL_LANGUAGE_CODE' ) ) {
			$this->is_spa = ( ICL_LANGUAGE_CODE == 'es' );
		}

		if ( $this->is_spa ) {
			$domains = array(
				// @see https://github.com/woothemes/woocommerce/tree/v2.0.5
				'woocommerce-shipping-table-rate'  => array(
					'languages/woocommerce-shipping-table-rate-es_ES.mo'           => 'woocommerce-shipping-table-rate/es_ES.mo',
				),

				'woocommerce-product-enquiry-form' => array(
					'languages/woothemes-es_ES.mo' => 'woocommerce-product-enquiry-form/es_ES.mo',
				),

				'email-cart'                       => array(
					'email-cart-es_ES.mo' => 'woocommerce-email-cart/es_ES.mo',
				),

				'wcva'                             => array(
					'languages/wcva-es_ES.mo' => 'woocommerce-colororimage-variation-select/es_ES.mo',
				),

				'wc_brands'                        => array(
					'languages/wc_brands-es_ES.mo' => 'woocommerce-brands/wc_brands-es_ES.mo',
				),

				'woocommerce-memberships'          => array(
					'languages/woocommerce-memberships-es_ES.mo'        => 'woocommerce-memberships/woocommerce-memberships-es_ES.mo',
				),

				'woocommerce-subscriptions'        => array(
					'languages/woocommerce-subscriptions-es_ES.mo'        => 'woocommerce-subscriptions/woocommerce-subscriptions-es_ES.mo',
				),

				'woocommerce-brands'               => array(
					'languages/woocommerce-brands-es_ES.mo' => 'woocommerce-brands/woocommerce-brands-es_ES.mo',
				),

				'spwcsp'                           => array(
					'languages/spwcsp-es_ES.mo' => 'sp-wc-gateway-sepa/spwcsp-es_ES.mo',
				),
			);

			if ( isset( $domains[ $domain ] ) ) {
				$paths = $domains[ $domain ];

				foreach ( $paths as $path => $file ) {
					if ( substr( $mo_file, -strlen( $path ) ) == $path ) {
						$new_file = dirname( $this->file ) . '/languages/' . $file;

						if ( is_readable( $new_file ) ) {
							$mo_file = $new_file;
						}
					}
				}
			}
		}

		return $mo_file;
	}


	// EU VAT
	/**
	 * Insert element before of a specific array position
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

	public function wces_add_billing_fields( $fields ) {
		$fields['billing_company']['class'] = array( 'form-row-first' );
		$fields['billing_company']['clear'] = false;

		$wces_settings     = get_option( 'wces_settings' );
		$vatinfo_mandatory = isset( $wces_settings['vat_mandatory'] ) ? $wces_settings['vat_mandatory'] : 'no';
		$vatinfo_show      = isset( $wces_settings['vat_show'] ) ? $wces_settings['vat_show'] : 'no';

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
				'label'       => apply_filters( 'vatssn_label', __( 'VAT No', 'woocommerce-es' ) ),
				'placeholder' => apply_filters( 'vatssn_label_x', __( 'VAT No', 'woocommerce-es' ) ),
				'required'    => $mandatory,
				'class'       => array( 'form-row-last' ),
				'clear'       => true,
			),
		);

		$this->array_splice_assoc( $fields, $field, 'billing_address_1' );
		return $fields;
	}

	// Our hooked in function - $fields is passed via the filter!
	function custom_override_checkout_fields( $fields ) {
		$wces_settings = get_option( 'wces_settings' );
		$company_field = isset( $wces_settings['company_field'] ) ? $wces_settings['company_field'] : 'no';

		if ( $company_field != 'yes' ) {
			unset( $fields['billing']['billing_company'] );
		}

			return $fields;
	}

	public function wces_add_billing_shipping_fields_admin( $fields ) {
		$fields['vat'] = array(
			'label' => apply_filters( 'vatssn_label', __( 'VAT No', 'woocommerce-es' ) ),
		);

		return $fields;
	}

	public function wces_add_var_load_order_data( $fields ) {
		$fields['billing_vat'] = '';
		return $fields;
	}

	/**
	 * Adds NIF in email notification
	 *
	 * @param object $order Order object.
	 * @return void
	 */
	public function woocommerce_email_key_notification( $order ) {
		echo '<p><strong>' . __( 'VAT No', 'woocommerce-es' ) .':</strong> ';
		echo esc_html( get_post_meta( $order->get_id(), '_billing_vat', true ) ) . '</p>';
	}

	/**
	 * Adds VAT info in WooCommerce PDF Invoices & Packing Slips
	 */
	public function wces_add_vat_invoices( $address ) {
		global $wpo_wcpdf;

		echo $address . '<p>';
		$wpo_wcpdf->custom_field( 'billing_vat', __( 'VAT info:', 'woocommerce-es' ) );
		echo '</p>';
	}

	/* END EU VAT*/

	function wces_style() {
		echo '<style>@media (min-width: 993px) {
			body .woocommerce .col2-set .col-1{width:100%;}
			.woocommerce .col2-set, .woocommerce-page .col2-set {width:44%;float:left;margin-right:2%;}
			.woocommerce .col2-set .col-2 { width:100%; clear:both; margin-top: 40px; }
			#order_review_heading, .woocommerce #order_review, .woocommerce-page #order_review{float:left;width:48%;margin-left:2%;}
			#billing_country_field { float:left; width:48%; }
			#billing_postcode_field, #billing_city_field, #billing_state_field { width:33%; float:left; clear:none;}
			#billing_phone_field, #billing_email_field { float:left; width:48%; clear:none;}
		}</style>';
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
	function add_terms_and_conditions_to_registration() {

		if ( wc_get_page_id( 'terms' ) > 0 && is_account_page() ) {
			?>
			<p class="form-row terms wc-terms-and-conditions">
				<label class="woocommerce-form__label woocommerce-form__label-for-checkbox checkbox">
				<input type="checkbox" class="woocommerce-form__input woocommerce-form__input-checkbox input-checkbox" name="terms" <?php checked( apply_filters( 'woocommerce_terms_is_checked_default', isset( $_POST['terms'] ) ), true ); ?> id="terms" /> <span><?php printf( __( 'I&rsquo;ve read and accept the <a href="%s" target="_blank" class="woocommerce-terms-and-conditions-link">terms &amp; conditions</a>', 'woocommerce-es' ), esc_url( wc_get_page_permalink( 'terms' ) ) ); ?></span> <span class="required">*</span>
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
	function terms_and_conditions_validation( $username, $email, $validation_errors ) {
		if ( ! isset( $_POST['terms'] ) ) {
			$validation_errors->add( 'terms_error', __( 'Terms and condition are not checked!', 'woocommerce-es' ) );
		}

		return $validation_errors;
	}


} //from class

global $wces_plugin;
$wces_plugin = new WooCommerceESPlugin( __FILE__ );

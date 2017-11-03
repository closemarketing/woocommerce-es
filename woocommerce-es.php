<?php
/*
Plugin Name: WooCommerce (ES)
Plugin URI: http://www.closemarketing.es/portafolio/plugin-woocommerce-espanol/
Description: Extends the WooCommerce plugin for Spanish needs: EU VAT included in form and order, and add-ons with the Spanish language.

Version: 1.4
Requires at least: 4.6

Author: Closemarketing
Author URI: http://www.closemarketing.es/

Text Domain: woocommerce-es
Domain Path: /languages/

License: GPL
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

	////////////////////////////////////////////////////////////

	/**
	 * Bootstrap
	 */
	public function __construct( $file ) {
		$this->file = $file;

		// Filters and actions
		add_action( 'plugins_loaded', array( $this, 'wces_plugins_loaded' ) );

		add_filter( 'load_textdomain_mofile', array( $this, 'wces_load_mo_file' ), 10, 2 );

	    	/* EU VAT */
		add_filter( 'woocommerce_billing_fields' , array( $this, 'wces_add_billing_fields' ) );
		add_filter( 'woocommerce_admin_billing_fields', array( $this, 'wces_add_billing_shipping_fields_admin') );
		add_filter( 'woocommerce_admin_shipping_fields', array( $this, 'wces_add_billing_shipping_fields_admin') );
		add_filter( 'woocommerce_load_order_data', array( $this, 'wces_add_var_load_order_data') );
		add_filter( 'woocommerce_email_order_meta_keys', array( $this, 'woocommerce_email_notification'));
	   	add_filter( 'wpo_wcpdf_billing_address', array( $this, 'wces_add_vat_invoices') );

		/* Options for the plugin */
		add_filter( 'woocommerce_general_settings', array( $this, 'wces_add_opt_option') );
		add_filter( 'woocommerce_checkout_fields' , array( $this, 'custom_override_checkout_fields') );

		$op_checkout = get_option( 'wces_opt_checkout', 1 );
		if($op_checkout=='yes') {
			add_filter( 'woocommerce_locate_template', array($this,'wces_woocommerce_locate_template'), 10, 3 );
		}

		/*
		 * WooThemes/WooCommerce don't execute the load_plugin_textdomain() in the 'init'
		 * action, therefor we have to make sure this plugin will load first
		 *
		 * @see http://stv.whtly.com/2011/09/03/forcing-a-wordpress-plugin-to-be-loaded-before-all-other-plugins/
		 */
		add_action( 'activated_plugin',       array( $this, 'activated_plugin' ) );
	}

	////////////////////////////////////////////////////////////

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

	////////////////////////////////////////////////////////////

	/**
	 * Plugins loaded
	 */
	public function wces_plugins_loaded() {
		$rel_path = dirname( plugin_basename( $this->file ) ) . '/languages/';

		// Load plugin text domain - WooCommerce ES
		// WooCommerce mixed use of 'wc_gf_addons' and 'wc_gravityforms'
		load_plugin_textdomain( 'wces', false, $rel_path );
	}

	////////////////////////////////////////////////////////////

	/**
	 * Load text domain MO file
	 *
	 * @param string $moFile
	 * @param string $domain
	 */
	public function wces_load_mo_file( $mo_file, $domain ) {
		if ( $this->language == null ) {
			$this->language = get_option( 'WPLANG', WPLANG );
			$this->is_spa = ( $this->language == 'es' || $this->language == 'es_ES' );
		}

		// The ICL_LANGUAGE_CODE constant is defined from an plugin, so this constant
		// is not always defined in the first 'load_textdomain_mofile' filter call
		if ( defined( 'ICL_LANGUAGE_CODE' ) ) {
			$this->is_spa = ( ICL_LANGUAGE_CODE == 'es' );
		}

		if ( $this->is_spa ) {
			$domains = array(
				// @see https://github.com/woothemes/woocommerce/tree/v2.0.5

				'woocommerce-shipping-table-rate'               => array(
					'languages/woocommerce-shipping-table-rate-es_ES.mo'           => 'woocommerce-shipping-table-rate/es_ES.mo'
				),

				'woocommerce-product-enquiry-form'            => array(
					'languages/woothemes-es_ES.mo'        => 'woocommerce-product-enquiry-form/es_ES.mo'
				),

				'email-cart' => array(
					'email-cart-es_ES.mo' => 'woocommerce-email-cart/es_ES.mo'
				),

				'wcva'               => array(
					'languages/wcva-es_ES.mo'           => 'woocommerce-colororimage-variation-select/es_ES.mo'
				),

				'wc_brands'            => array(
					'languages/wc_brands-es_ES.mo'        => 'woocommerce-brands/wc_brands-es_ES.mo'
				),

				'woocommerce-memberships' => array(
					'languages/woocommerce-memberships-es_ES.mo'        => 'woocommerce-memberships/woocommerce-memberships-es_ES.mo'
				),

				'woocommerce-subscriptions' => array(
					'languages/woocommerce-subscriptions-es_ES.mo'        => 'woocommerce-subscriptions/woocommerce-subscriptions-es_ES.mo'
				),

				'woocommerce-brands' => array(
					'languages/woocommerce-brands-es_ES.mo' => 'woocommerce-brands/woocommerce-brands-es_ES.mo'
				),

				'spwcsp' => array(
					'languages/spwcsp-es_ES.mo' => 'sp-wc-gateway-sepa/spwcsp-es_ES.mo'
				),
			);

			if ( isset( $domains[$domain] ) ) {
				$paths = $domains[$domain];

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


    //EU VAT
    /**
     * Insert element before of a specific array position
     *
     * @return array
     * @since 1.0.0
     */
	public function array_splice_assoc( &$source, $need, $previous ) {
	    $return = array();

	    foreach( $source as $key => $value ) {
	        if( $key == $previous ) {
                $need_key = array_keys( $need );
                $key_need = array_shift( $need_key );
	            $value_need = $need[$key_need];

	            $return[$key_need] = $value_need;
	        }

	        $return[$key] = $value;
	    }

	    $source = $return;
	}

	public function wces_add_billing_fields( $fields ) {
		$fields['billing_company']['class'] = array('form-row-first');
		$fields['billing_company']['clear'] = false;
        //$fields['billing_country']['clear'] = true;

		$vatinfo_mandatory = get_option( 'wces_vat_mandatory', 1 );
		$vatinfo_show = get_option( 'wces_vat_show', 1 );

		if($vatinfo_show!='yes')
			return $fields;


		if($vatinfo_mandatory=='yes') $mandatory= true; else $mandatory = false;

		$field = array('billing_vat' => array(
	        'label'       => apply_filters( 'vatssn_label', __('VAT No', 'woocommerce-es') ),
		    'placeholder' => apply_filters( 'vatssn_label_x', __('VAT No', 'placeholder', 'woocommerce-es') ),
		    'required'    => $mandatory,
		    'class'       => array('form-row-last'),
		    'clear'       => true
	     ));

		$this->array_splice_assoc( $fields, $field, 'billing_address_1');
		return $fields;
	}

	// Our hooked in function - $fields is passed via the filter!
	function custom_override_checkout_fields( $fields ) {
		if (get_option('wces_company', 1)!='yes')
	     	unset($fields['billing']['billing_company']);

	     	return $fields;
	}

    public function wces_add_billing_shipping_fields_admin( $fields ) {
        $fields['vat'] = array(
            'label' => apply_filters( 'vatssn_label', __('VAT No', 'woocommerce-es') )
        );

        return $fields;
    }

    public function wces_add_var_load_order_data( $fields ) {
        $fields['billing_vat'] = '';
        return $fields;
    }

	/**
	 * Adds NIF in email notification
	 */

	public function woocommerce_email_notification( $keys ) {
	    $keys[] = 'billing_vat';
	    return $keys;
	}

	/**
	* Adds VAT info in WooCommerce PDF Invoices & Packing Slips
	*/
	public function wces_add_vat_invoices( $address ){
	  global $wpo_wcpdf;

	  echo $address . '<p>';
	  $wpo_wcpdf->custom_field( 'billing_vat', __('VAT info:', 'woocommerce-es') );
	  echo '</p>';
	}

    /* END EU VAT*/



	/**
	 * Add options for WooCommerce
	 */

	public function wces_add_opt_option( $settings ) {

		$updated_settings = array();

	    foreach ( $settings as $section ) {
	      // at the bottom of the General Options section
	      if ( isset( $section['id'] ) && 'general_options' == $section['id'] &&
	         isset( $section['type'] ) && 'sectionend' == $section['type'] ) {
	        $updated_settings[] = array(
			    'name'    => __( 'Ask for VAT in Checkout?', 'woocommerce-es' ),
			    'desc'    => __( 'This controls if VAT field will be shown in checkout.', 'woocommerce-es' ),
			    'id'      => 'wces_vat_show',
			    'std'     => 'yes', // WooCommerce < 2.0
			    'default' => 'yes', // WooCommerce >= 2.0
			    'type'    => 'checkbox'
	        );
	        $updated_settings[] = array(
			    'name'    => __( 'VAT info mandatory?', 'woocommerce-es' ),
			    'desc'    => __( 'This controls if VAT info would be mandatory in checkout.', 'woocommerce-es' ),
			    'id'      => 'wces_vat_mandatory',
			    'std'     => 'no', // WooCommerce < 2.0
			    'default' => 'no', // WooCommerce >= 2.0
			    'type'    => 'checkbox'
	        );
	        $updated_settings[] = array(
			    'name'    => __( 'Show Company field?', 'woocommerce-es' ),
			    'desc'    => __( 'This controls if company field will be shown', 'woocommerce-es' ),
			    'id'      => 'wces_company',
			    'std'     => 'no', // WooCommerce < 2.0
			    'default' => 'no', // WooCommerce >= 2.0
			    'type'    => 'checkbox'
	        );
	        $updated_settings[] = array(
			    'name'    => __( 'Optimize Checkout?', 'woocommerce-es' ),
			    'desc'    => __( 'Optimizes your checkout to better conversion.', 'woocommerce-es' ),
			    'id'      => 'wces_opt_checkout',
			    'std'     => 'no', // WooCommerce < 2.0
			    'default' => 'no', // WooCommerce >= 2.0
			    'type'    => 'checkbox'
	        );
	      }
	      $updated_settings[] = $section;
	    }
	    return $updated_settings;
	}

  function wces_woocommerce_locate_template( $template, $template_name, $template_path ) {
    global $woocommerce;

    $_template = $template;
    if ( ! $template_path ) $template_path = $woocommerce->template_url;
    $plugin_path  = untrailingslashit( plugin_dir_path( __FILE__ ) ) . '/woocommerce/';

    // Look within passed path within the theme - this is priority
    $template = locate_template(

      array(
        $template_path . $template_name,
        $template_name
      )
    );

    // Modification: Get the template from this plugin, if it exists
    if ( ! $template && file_exists( $plugin_path . $template_name ) )
      $template = $plugin_path . $template_name;
    // Use default template

    if ( ! $template )
      $template = $_template;
    // Return what we found

    return $template;
  }

} //from class

global $wces_plugin;

$wces_plugin = new WooCommerceESPlugin( __FILE__ );

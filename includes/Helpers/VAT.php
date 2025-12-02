<?php
/**
 * VAT Helper Class for VIES Validation
 *
 * @package    WordPress
 * @author     David Perez <david@close.technology>
 * @copyright  2025 CLOSE
 * @version    1.0.0
 */

namespace CLOSE\ConnectEcommerce\Helpers;

defined( 'ABSPATH' ) || exit;

use DragonBe\Vies\Vies;
use DragonBe\Vies\ViesException;
use DragonBe\Vies\ViesServiceException;

/**
 * VAT Validation Helper
 */
class VAT {

	/**
	 * Cache group name for VAT validation
	 *
	 * @var string
	 */
	const CACHE_GROUP = 'conecom_vat_validation';

	/**
	 * Cache expiration time in seconds (24 hours)
	 *
	 * @var int
	 */
	const CACHE_EXPIRATION = DAY_IN_SECONDS;

	/**
	 * Validate VAT number using VIES service
	 *
	 * @param string $vat_number VAT number to validate.
	 * @param string $country_code Country code (2 letters).
	 * @return array Validation result with status and message.
	 */
	public static function validate_vat_number( $vat_number, $country_code = '' ) {
		// Check if VIES validation is enabled.
		$settings = get_option( 'connect_ecommerce_public' );
		$enabled  = isset( $settings['vat_vies_enabled'] ) ? $settings['vat_vies_enabled'] : 'yes';

		if ( 'yes' !== $enabled ) {
			return array(
				'valid'   => true,
				'message' => __( 'VAT validation is disabled', 'woocommerce-es' ),
			);
		}

		// Clean VAT number.
		$vat_number = self::clean_vat_number( $vat_number );

		if ( empty( $vat_number ) ) {
			return array(
				'valid'   => false,
				'message' => __( 'VAT number is empty', 'woocommerce-es' ),
			);
		}

		// Extract country code from VAT number if not provided.
		if ( empty( $country_code ) ) {
			$country_code = self::extract_country_code( $vat_number );
			$vat_number   = substr( $vat_number, 2 );
		}

		// Check cache first.
		$cache_key    = md5( $country_code . $vat_number );
		$cached_result = wp_cache_get( $cache_key, self::CACHE_GROUP );

		if ( false !== $cached_result ) {
			return $cached_result;
		}

		// Try to validate using VIES.
		try {
			// Check if VIES library is available.
			if ( ! class_exists( 'DragonBe\Vies\Vies' ) ) {
				return array(
					'valid'   => true,
					'message' => __( 'VIES library not installed. Please run composer update.', 'woocommerce-es' ),
					'cached'  => false,
				);
			}

			$vies = new Vies();

			// Check if VIES service is available.
			if ( ! $vies->getHeartBeat()->isAlive() ) {
				return array(
					'valid'   => true,
					'message' => __( 'VIES service is currently unavailable. VAT number accepted.', 'woocommerce-es' ),
					'cached'  => false,
				);
			}

			// Validate VAT number.
			$result = $vies->validateVat( $country_code, $vat_number );

			$validation_result = array(
				'valid'        => $result->isValid(),
				'country_code' => $country_code,
				'vat_number'   => $vat_number,
				'request_date' => $result->getRequestDate()->format( 'Y-m-d' ),
				'name'         => $result->getName(),
				'address'      => $result->getAddress(),
				'message'      => $result->isValid() ? __( 'VAT number is valid', 'woocommerce-es' ) : __( 'VAT number is invalid', 'woocommerce-es' ),
				'cached'       => false,
			);

			// Cache the result.
			wp_cache_set( $cache_key, $validation_result, self::CACHE_GROUP, self::CACHE_EXPIRATION );

			return $validation_result;

		} catch ( ViesServiceException $e ) {
			// VIES service error - accept VAT number.
			self::log_error( 'VIES Service Error: ' . $e->getMessage() );

			return array(
				'valid'   => true,
				'message' => __( 'VIES service error. VAT number accepted.', 'woocommerce-es' ),
				'error'   => $e->getMessage(),
				'cached'  => false,
			);

		} catch ( ViesException $e ) {
			// Invalid VAT format or other VIES error.
			self::log_error( 'VIES Validation Error: ' . $e->getMessage() );

			return array(
				'valid'   => false,
				'message' => sprintf(
					// translators: %s is the error message.
					__( 'VAT validation error: %s', 'woocommerce-es' ),
					$e->getMessage()
				),
				'error'   => $e->getMessage(),
				'cached'  => false,
			);

		} catch ( \Exception $e ) {
			// Generic error - accept VAT number.
			self::log_error( 'VAT Validation Error: ' . $e->getMessage() );

			return array(
				'valid'   => true,
				'message' => __( 'Error validating VAT number. VAT number accepted.', 'woocommerce-es' ),
				'error'   => $e->getMessage(),
				'cached'  => false,
			);
		}
	}

	/**
	 * Clean VAT number from spaces and special characters
	 *
	 * @param string $vat_number VAT number.
	 * @return string Cleaned VAT number.
	 */
	public static function clean_vat_number( $vat_number ) {
		// Remove spaces, dots, dashes.
		$vat_number = str_replace( array( ' ', '.', '-', '/' ), '', $vat_number );

		// Convert to uppercase.
		return strtoupper( trim( $vat_number ) );
	}

	/**
	 * Extract country code from VAT number
	 *
	 * @param string $vat_number VAT number.
	 * @return string Country code.
	 */
	public static function extract_country_code( $vat_number ) {
		$vat_number = self::clean_vat_number( $vat_number );

		// Get first 2 characters.
		$country_code = substr( $vat_number, 0, 2 );

		// Check if it's a valid EU country code.
		$eu_countries = self::get_eu_countries();

		if ( in_array( $country_code, $eu_countries, true ) ) {
			return $country_code;
		}

		return '';
	}

	/**
	 * Get list of EU countries
	 *
	 * @return array List of EU country codes.
	 */
	public static function get_eu_countries() {
		return array(
			'AT', // Austria.
			'BE', // Belgium.
			'BG', // Bulgaria.
			'CY', // Cyprus.
			'CZ', // Czech Republic.
			'DE', // Germany.
			'DK', // Denmark.
			'EE', // Estonia.
			'EL', // Greece.
			'ES', // Spain.
			'FI', // Finland.
			'FR', // France.
			'HR', // Croatia.
			'HU', // Hungary.
			'IE', // Ireland.
			'IT', // Italy.
			'LT', // Lithuania.
			'LU', // Luxembourg.
			'LV', // Latvia.
			'MT', // Malta.
			'NL', // Netherlands.
			'PL', // Poland.
			'PT', // Portugal.
			'RO', // Romania.
			'SE', // Sweden.
			'SI', // Slovenia.
			'SK', // Slovakia.
		);
	}

	/**
	 * Check if VAT validation is required for checkout
	 *
	 * @return bool True if validation is required.
	 */
	public static function is_vat_validation_required() {
		$settings = get_option( 'connect_ecommerce_public' );

		$vat_show       = isset( $settings['vat_show'] ) ? $settings['vat_show'] : 'yes';
		$vies_enabled   = isset( $settings['vat_vies_enabled'] ) ? $settings['vat_vies_enabled'] : 'yes';
		$vies_mandatory = isset( $settings['vat_vies_mandatory'] ) ? $settings['vat_vies_mandatory'] : 'no';

		return 'yes' === $vat_show && 'yes' === $vies_enabled && 'yes' === $vies_mandatory;
	}

	/**
	 * Log error message
	 *
	 * @param string $message Error message.
	 * @return void
	 */
	private static function log_error( $message ) {
		$settings  = get_option( 'connect_ecommerce' );
		$connector = isset( $settings['connector'] ) ? $settings['connector'] : '';
		if ( empty( $connector ) ) {
			return;
		}

		$debug_log = isset( $settings[ $connector ]['debug_log'] ) ? $settings[ $connector ]['debug_log'] : 'no';

		if ( 'on' === $debug_log ) {
			error_log( '[WooCommerce ES - VAT Validation] ' . $message );
		}
	}

	/**
	 * Log debug message
	 *
	 * @param string $message Debug message.
	 * @return void
	 */
	private static function log_debug( $message ) {
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			error_log( '[WooCommerce ES - VAT Debug] ' . $message );
		}
	}

	/**
	 * Save VAT validation result to order meta
	 *
	 * @param int   $order_id Order ID.
	 * @param array $validation_result Validation result.
	 * @return void
	 */
	public static function save_vat_validation_result( $order_id, $validation_result ) {
		if ( empty( $order_id ) || empty( $validation_result ) ) {
			return;
		}

		update_post_meta( $order_id, '_vat_validation_result', $validation_result );
		update_post_meta( $order_id, '_vat_number_validated', $validation_result['valid'] ? 'yes' : 'no' );

		if ( isset( $validation_result['request_date'] ) ) {
			update_post_meta( $order_id, '_vat_validation_date', $validation_result['request_date'] );
		}
	}

	/**
	 * Get VAT validation result from order meta
	 *
	 * @param int $order_id Order ID.
	 * @return array|null Validation result or null.
	 */
	public static function get_vat_validation_result( $order_id ) {
		if ( empty( $order_id ) ) {
			return null;
		}

		return get_post_meta( $order_id, '_vat_validation_result', true );
	}

	/**
	 * Clear VAT validation cache
	 *
	 * @return void
	 */
	public static function clear_cache() {
		wp_cache_flush_group( self::CACHE_GROUP );
	}

	/**
	 * Get minimum VAT number length for a country
	 *
	 * @param string $country_code Country code.
	 * @return int Minimum length.
	 */
	public static function get_min_vat_length( $country_code ) {
		$lengths = array(
			'AT' => 9,  // Austria: ATU99999999.
			'BE' => 10, // Belgium: BE0999999999.
			'BG' => 9,  // Bulgaria: BG999999999 or BG9999999999.
			'CY' => 9,  // Cyprus: CY99999999L.
			'CZ' => 8,  // Czech Republic: CZ99999999 or CZ999999999 or CZ9999999999.
			'DE' => 9,  // Germany: DE999999999.
			'DK' => 8,  // Denmark: DK99 99 99 99.
			'EE' => 9,  // Estonia: EE999999999.
			'EL' => 9,  // Greece: EL999999999.
			'ES' => 9,  // Spain: ESX9999999X.
			'FI' => 8,  // Finland: FI99999999.
			'FR' => 11, // France: FRXX 999999999.
			'GB' => 9,  // United Kingdom: GB999 9999 99 or GB999 9999 99 999 or GBGD999 or GBHA999.
			'HR' => 11, // Croatia: HR99999999999.
			'HU' => 8,  // Hungary: HU99999999.
			'IE' => 8,  // Ireland: IE9S99999L.
			'IT' => 11, // Italy: IT99999999999.
			'LT' => 9,  // Lithuania: LT999999999 or LT999999999999.
			'LU' => 8,  // Luxembourg: LU99999999.
			'LV' => 11, // Latvia: LV99999999999.
			'MT' => 8,  // Malta: MT99999999.
			'NL' => 12, // Netherlands: NL999999999B99.
			'PL' => 10, // Poland: PL9999999999.
			'PT' => 9,  // Portugal: PT999999999.
			'RO' => 2,  // Romania: RO999999999.
			'SE' => 12, // Sweden: SE999999999999.
			'SI' => 8,  // Slovenia: SI99999999.
			'SK' => 10, // Slovakia: SK9999999999.
		);

		return isset( $lengths[ $country_code ] ) ? $lengths[ $country_code ] : 8;
	}

	/**
	 * AJAX handler for real-time VAT validation
	 *
	 * @return void
	 */
	public static function ajax_validate_vat() {
		// Check nonce for security.
		check_ajax_referer( 'conecom_vat_validation', 'security' );

		$vat_number   = isset( $_POST['vat_number'] ) ? sanitize_text_field( wp_unslash( $_POST['vat_number'] ) ) : '';
		$country_code = isset( $_POST['country_code'] ) ? sanitize_text_field( wp_unslash( $_POST['country_code'] ) ) : '';

		// Early validations.
		if ( empty( $vat_number ) ) {
			wp_send_json_success(
				array(
					'status'  => 'empty',
					'message' => '',
				)
			);
		}

		// Clean VAT number.
		$vat_clean = self::clean_vat_number( $vat_number );

		// Extract country if not provided.
		if ( empty( $country_code ) ) {
			$country_code = self::extract_country_code( $vat_clean );
			if ( empty( $country_code ) ) {
				wp_send_json_success(
					array(
						'status'  => 'invalid_format',
						'message' => __( 'Please enter a valid VAT number with country code (e.g., ES12345678A)', 'woocommerce-es' ),
					)
				);
			}
			$vat_clean = substr( $vat_clean, 2 );
		}

		// Check minimum length.
		$min_length = self::get_min_vat_length( $country_code );
		if ( strlen( $vat_clean ) < $min_length ) {
			wp_send_json_success(
				array(
					'status'  => 'too_short',
					'message' => sprintf(
						// translators: %d is the minimum number of characters.
						__( 'VAT number is too short. Minimum %d characters required.', 'woocommerce-es' ),
						$min_length
					),
				)
			);
		}

		// Validate with VIES.
		$result = self::validate_vat_number( $vat_clean, $country_code );

		$response = array(
			'status'       => $result['valid'] ? 'valid' : 'invalid',
			'message'      => $result['message'],
			'country_code' => $country_code,
			'vat_number'   => $vat_clean,
		);

		if ( isset( $result['name'] ) ) {
			$response['company_name'] = $result['name'];
		}

		if ( isset( $result['address'] ) ) {
			$response['company_address'] = $result['address'];
		}

		// Apply or remove VAT exemption based on validation.
		$is_valid = isset( $result['valid'] ) && $result['valid'];
		
		if ( $is_valid ) {
			self::apply_vat_exemption( $country_code, $vat_clean, true );
			
			// Check if exemption was applied and add to response.
			if ( self::is_customer_vat_exempt() ) {
				$response['vat_exempt'] = true;
				$response['exempt_message'] = sprintf(
					// translators: %s is the country code.
					__( 'B2B intra-community transaction - Zero VAT rate applied (%s)', 'woocommerce-es' ),
					$country_code
				);
			} else {
				$response['vat_exempt'] = false;
				
				// Check why exemption was not applied.
				$base_country = WC()->countries->get_base_country();
				if ( $country_code === $base_country ) {
					$response['exempt_message'] = __( 'Domestic transaction - Standard VAT rate applies', 'woocommerce-es' );
				}
			}
		} else {
			// Validation failed - remove any exemption and restore normal VAT.
			self::remove_vat_exemption();
			$response['vat_exempt'] = false;
			$response['exempt_message'] = sprintf(
				// translators: %s is the country code.
				__( 'VAT validation failed - Standard VAT rate applies for %s', 'woocommerce-es' ),
				$country_code
			);
			
			self::log_debug( sprintf(
				'VAT Exemption REMOVED - Validation failed for %s: %s',
				$country_code,
				$vat_clean
			) );
		}

		wp_send_json_success( $response );
	}

	/**
	 * Initialize AJAX hooks
	 *
	 * @return void
	 */
	public static function init_ajax_hooks() {
		add_action( 'wp_ajax_conecom_validate_vat', array( __CLASS__, 'ajax_validate_vat' ) );
		add_action( 'wp_ajax_nopriv_conecom_validate_vat', array( __CLASS__, 'ajax_validate_vat' ) );
	}

	/**
	 * Check if VAT exemption should apply (B2B intra-community)
	 *
	 * @param string $customer_country Customer country code.
	 * @param string $vat_number VAT number.
	 * @param bool   $is_validated Whether VAT was successfully validated.
	 * @return bool True if exemption should apply.
	 */
	public static function should_apply_vat_exemption( $customer_country, $vat_number, $is_validated = false ) {
		// Get base country of the store.
		$base_country = WC()->countries->get_base_country();

		// Debug log.
		self::log_debug( sprintf(
			'VAT Exemption Check - Base: %s, Customer: %s, VAT: %s, Validated: %s',
			$base_country,
			$customer_country,
			$vat_number,
			$is_validated ? 'yes' : 'no'
		) );

		// Check if both countries are in EU.
		$eu_countries = self::get_eu_countries();

		$customer_in_eu = in_array( $customer_country, $eu_countries, true );
		$base_in_eu     = in_array( $base_country, $eu_countries, true );

		// Both must be in EU.
		if ( ! $customer_in_eu || ! $base_in_eu ) {
			self::log_debug( 'VAT Exemption NOT applied - One or both countries not in EU' );
			return false;
		}

		// Countries must be different.
		if ( $customer_country === $base_country ) {
			self::log_debug( 'VAT Exemption NOT applied - Same country (domestic transaction)' );
			return false;
		}

		// VAT number must be provided and validated.
		if ( empty( $vat_number ) || ! $is_validated ) {
			self::log_debug( 'VAT Exemption NOT applied - VAT number empty or not validated' );
			return false;
		}

		self::log_debug( 'VAT Exemption APPLIED - All conditions met' );
		return true;
	}

	/**
	 * Apply VAT exemption to customer
	 *
	 * @param string $customer_country Customer country code.
	 * @param string $vat_number VAT number.
	 * @param bool   $is_validated Whether VAT was successfully validated.
	 * @return void
	 */
	public static function apply_vat_exemption( $customer_country, $vat_number, $is_validated ) {
		// First, always remove any existing exemption.
		self::remove_vat_exemption();
		
		// Then check if we should apply new exemption.
		if ( ! self::should_apply_vat_exemption( $customer_country, $vat_number, $is_validated ) ) {
			return;
		}

		// Ensure zero-rate tax class exists.
		self::ensure_zero_rate_tax_class();

		// Set customer tax class to zero-rate for the specific country.
		if ( WC()->customer ) {
			WC()->customer->set_taxable_address( $customer_country, '', '', '' );
		}

		// Store in session for persistence.
		if ( WC()->session ) {
			WC()->session->set( 'vat_exempt_applied', true );
			WC()->session->set( 'vat_exempt_country', $customer_country );
			WC()->session->set( 'vat_exempt_number', $vat_number );
			WC()->session->set( 'customer_tax_class', 'zero-rate' );
		}
	}

	/**
	 * Remove VAT exemption from customer
	 *
	 * @return void
	 */
	public static function remove_vat_exemption() {
		// Reset customer tax class to default.
		if ( WC()->session ) {
			WC()->session->set( 'customer_tax_class', '' );
		}

		// Clear session data.
		if ( WC()->session ) {
			WC()->session->set( 'vat_exempt_applied', false );
			WC()->session->set( 'vat_exempt_country', null );
			WC()->session->set( 'vat_exempt_number', null );
		}
	}

	/**
	 * Check if customer is currently VAT exempt
	 *
	 * @return bool
	 */
	public static function is_customer_vat_exempt() {
		if ( WC()->session && WC()->session->get( 'vat_exempt_applied' ) ) {
			return true;
		}

		if ( WC()->session && 'zero-rate' === WC()->session->get( 'customer_tax_class' ) ) {
			return true;
		}

		return false;
	}

	/**
	 * Get VAT exemption info from session
	 *
	 * @return array|null
	 */
	public static function get_vat_exemption_info() {
		if ( ! WC()->session ) {
			return null;
		}

		$exempt = WC()->session->get( 'vat_exempt_applied' );
		if ( ! $exempt ) {
			return null;
		}

		return array(
			'country'    => WC()->session->get( 'vat_exempt_country' ),
			'vat_number' => WC()->session->get( 'vat_exempt_number' ),
		);
	}

	/**
	 * Ensure zero-rate tax class and rates exist
	 *
	 * @return void
	 */
	public static function ensure_zero_rate_tax_class() {
		global $wpdb;

		// Check if zero-rate tax class exists.
		$tax_classes = WC_Tax::get_tax_classes();
		$tax_classes = array_map( 'sanitize_title', $tax_classes );

		if ( ! in_array( 'zero-rate', $tax_classes, true ) ) {
			// Add zero-rate tax class.
			$tax_classes = WC_Tax::get_tax_class_slugs();
			$tax_classes['Zero Rate'] = 'zero-rate';
			update_option( 'woocommerce_tax_classes', implode( "\n", array_keys( $tax_classes ) ) );
		}

		// Check if zero-rate tax rates exist for EU countries.
		$eu_countries = self::get_eu_countries();

		foreach ( $eu_countries as $country_code ) {
			// Check if rate already exists.
			$existing_rate = $wpdb->get_var(
				$wpdb->prepare(
					"SELECT tax_rate_id FROM {$wpdb->prefix}woocommerce_tax_rates 
					WHERE tax_rate_country = %s 
					AND tax_rate_class = %s 
					LIMIT 1",
					$country_code,
					'zero-rate'
				)
			);

			if ( ! $existing_rate ) {
				// Insert zero-rate tax for this country.
				$wpdb->insert(
					$wpdb->prefix . 'woocommerce_tax_rates',
					array(
						'tax_rate_country'  => $country_code,
						'tax_rate_state'    => '',
						'tax_rate'          => '0.0000',
						'tax_rate_name'     => sprintf( 'Zero Rate %s', $country_code ),
						'tax_rate_priority' => 1,
						'tax_rate_compound' => 0,
						'tax_rate_shipping' => 1,
						'tax_rate_order'    => 0,
						'tax_rate_class'    => 'zero-rate',
					),
					array( '%s', '%s', '%s', '%s', '%d', '%d', '%d', '%d', '%s' )
				);
			}
		}

		// Clear WooCommerce tax cache.
		WC_Cache_Helper::invalidate_cache_group( 'taxes' );
		delete_transient( 'wc_tax_rates' );
	}
}


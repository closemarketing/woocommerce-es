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
}


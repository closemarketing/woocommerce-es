<?php
/**
 * Taxes helpers
 *
 * @package    WordPress
 * @author     David Perez <david@close.technology>
 * @copyright  2025 Closemarketing
 * @version    3.2.0
 */

namespace CLOSE\ConnectEcommerce\Helpers;

defined( 'ABSPATH' ) || exit;

/**
 * Taxes helpers.
 *
 * @since 3.2.0
 */
class TAXES {
	/**
	 * Get tax types map.
	 *
	 * @param int|null $tax_rate_id Tax rate ID.
	 * @return array|string Tax types map or single tax type if tax rate ID is provided.
	 */
	public static function get_tax_types_map( $tax_rate_id = null ) {
		// Get all existing ERP tax types from database.
		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$existing_values = $wpdb->get_results(
			"SELECT tax_rate_id, erp_tax_type 
			FROM {$wpdb->prefix}woocommerce_tax_rates 
			WHERE erp_tax_type IS NOT NULL AND erp_tax_type != ''",
			ARRAY_A
		);

		// Convert to associative array tax_rate_id => erp_tax_type.
		$tax_types_map = array();
		foreach ( $existing_values as $row ) {
			$tax_types_map[ (int) $row['tax_rate_id'] ] = $row['erp_tax_type'];
		}

		if ( $tax_rate_id ) {
			return $tax_types_map[ $tax_rate_id ];
		}

		return $tax_types_map;
	}

	/**
	 * Update tax type.
	 *
	 * @param int    $tax_rate_id  Tax rate ID.
	 * @param string $erp_tax_type ERP tax type.
	 * @return void
	 */
	public static function update_tax_type( $tax_rate_id, $erp_tax_type ) {
		$erp_tax_type = sanitize_text_field( $erp_tax_type );
		$tax_rate_id  = absint( $tax_rate_id );

		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->update(
			$wpdb->prefix . 'woocommerce_tax_rates',
			array( 'erp_tax_type' => $erp_tax_type ),
			array( 'tax_rate_id' => $tax_rate_id )
		);
	}

	/**
	 * Get VAT rates by country code.
	 *
	 * @param string $country Country code (e.g., 'ES', 'FR', 'DE').
	 * @return array VAT rates for the country or empty array if not found.
	 */
	public static function get_rates_by_country( $country ) {
		$country   = strtoupper( $country );
		$vat_rates = array(
			'AT' => array(
				'country'            => __( 'Austria', 'connect-ecommerce' ),
				'standard_rate'      => 20.00,
				'reduced_rate'       => 10.00,
				'reduced_rate_alt'   => 13.00,
				'super_reduced_rate' => false,
				'parking_rate'       => 12.00,
			),
			'BE' => array(
				'country'            => __( 'Belgium', 'connect-ecommerce' ),
				'standard_rate'      => 21.00,
				'reduced_rate'       => 12.00,
				'reduced_rate_alt'   => 6.00,
				'super_reduced_rate' => false,
				'parking_rate'       => 12.00,
			),
			'BG' => array(
				'country'            => __( 'Bulgaria', 'connect-ecommerce' ),
				'standard_rate'      => 20.00,
				'reduced_rate'       => 9.00,
				'reduced_rate_alt'   => false,
				'super_reduced_rate' => false,
				'parking_rate'       => false,
			),
			'CY' => array(
				'country'            => __( 'Cyprus', 'connect-ecommerce' ),
				'standard_rate'      => 19.00,
				'reduced_rate'       => 9.00,
				'reduced_rate_alt'   => 5.00,
				'super_reduced_rate' => false,
				'parking_rate'       => false,
			),
			'CZ' => array(
				'country'            => __( 'Czech Republic', 'connect-ecommerce' ),
				'standard_rate'      => 21.00,
				'reduced_rate'       => 15.00,
				'reduced_rate_alt'   => 12.00,
				'super_reduced_rate' => false,
				'parking_rate'       => false,
			),
			'DK' => array(
				'country'            => __( 'Denmark', 'connect-ecommerce' ),
				'standard_rate'      => 25.00,
				'reduced_rate'       => false,
				'reduced_rate_alt'   => false,
				'super_reduced_rate' => false,
				'parking_rate'       => false,
			),
			'DE' => array(
				'country'            => __( 'Germany', 'connect-ecommerce' ),
				'standard_rate'      => 19.00,
				'reduced_rate'       => 7.00,
				'reduced_rate_alt'   => false,
				'super_reduced_rate' => false,
				'parking_rate'       => false,
			),
			'EE' => array(
				'country'            => __( 'Estonia', 'connect-ecommerce' ),
				'standard_rate'      => 24.00,
				'reduced_rate'       => 9.00,
				'reduced_rate_alt'   => false,
				'super_reduced_rate' => false,
				'parking_rate'       => false,
			),
			'EL' => array(
				'country'            => __( 'Greece', 'connect-ecommerce' ),
				'iso_duplicate'      => 'GR',
				'standard_rate'      => 24.00,
				'reduced_rate'       => 13.00,
				'reduced_rate_alt'   => 6.00,
				'super_reduced_rate' => false,
				'parking_rate'       => false,
			),
			'GR' => array(
				'country'            => __( 'Greece', 'connect-ecommerce' ),
				'iso_duplicate_of'   => 'EL',
				'standard_rate'      => 24.00,
				'reduced_rate'       => 13.00,
				'reduced_rate_alt'   => 6.00,
				'super_reduced_rate' => false,
				'parking_rate'       => false,
			),
			'ES' => array(
				'country'            => __( 'Spain', 'connect-ecommerce' ),
				'standard_rate'      => 21.00,
				'reduced_rate'       => 10.00,
				'reduced_rate_alt'   => false,
				'super_reduced_rate' => 4.00,
				'parking_rate'       => false,
			),
			'FI' => array(
				'country'            => __( 'Finland', 'connect-ecommerce' ),
				'standard_rate'      => 25.50,
				'reduced_rate'       => 14.00,
				'reduced_rate_alt'   => 10.00,
				'super_reduced_rate' => false,
				'parking_rate'       => false,
			),
			'FR' => array(
				'country'            => __( 'France', 'connect-ecommerce' ),
				'standard_rate'      => 20.00,
				'reduced_rate'       => 10.00,
				'reduced_rate_alt'   => 5.50,
				'super_reduced_rate' => 2.10,
				'parking_rate'       => false,
			),
			'HR' => array(
				'country'            => __( 'Croatia', 'connect-ecommerce' ),
				'standard_rate'      => 25.00,
				'reduced_rate'       => 13.00,
				'reduced_rate_alt'   => 5.00,
				'super_reduced_rate' => false,
				'parking_rate'       => false,
			),
			'IT' => array(
				'country'            => __( 'Italy', 'connect-ecommerce' ),
				'standard_rate'      => 22.00,
				'reduced_rate'       => 10.00,
				'reduced_rate_alt'   => 5.00,
				'super_reduced_rate' => 4.00,
				'parking_rate'       => false,
			),
			'LV' => array(
				'country'            => __( 'Latvia', 'connect-ecommerce' ),
				'standard_rate'      => 21.00,
				'reduced_rate'       => 5.00,
				'reduced_rate_alt'   => false,
				'super_reduced_rate' => false,
				'parking_rate'       => false,
			),
			'LT' => array(
				'country'            => __( 'Lithuania', 'connect-ecommerce' ),
				'standard_rate'      => 21.00,
				'reduced_rate'       => 9.00,
				'reduced_rate_alt'   => 5.00,
				'super_reduced_rate' => false,
				'parking_rate'       => false,
			),
			'LU' => array(
				'country'            => __( 'Luxembourg', 'connect-ecommerce' ),
				'standard_rate'      => 17.00,
				'reduced_rate'       => 14.00,
				'reduced_rate_alt'   => 8.00,
				'super_reduced_rate' => 3.00,
				'parking_rate'       => 12.00,
			),
			'HU' => array(
				'country'            => __( 'Hungary', 'connect-ecommerce' ),
				'standard_rate'      => 27.00,
				'reduced_rate'       => 18.00,
				'reduced_rate_alt'   => 5.00,
				'super_reduced_rate' => false,
				'parking_rate'       => false,
			),
			'IE' => array(
				'country'            => __( 'Ireland', 'connect-ecommerce' ),
				'standard_rate'      => 23.00,
				'reduced_rate'       => 13.50,
				'reduced_rate_alt'   => 9.00,
				'super_reduced_rate' => 4.80,
				'parking_rate'       => 13.50,
			),
			'MT' => array(
				'country'            => __( 'Malta', 'connect-ecommerce' ),
				'standard_rate'      => 18.00,
				'reduced_rate'       => 7.00,
				'reduced_rate_alt'   => 5.00,
				'super_reduced_rate' => false,
				'parking_rate'       => false,
			),
			'NL' => array(
				'country'            => __( 'Netherlands', 'connect-ecommerce' ),
				'standard_rate'      => 21.00,
				'reduced_rate'       => 9.00,
				'reduced_rate_alt'   => false,
				'super_reduced_rate' => false,
				'parking_rate'       => false,
			),
			'PL' => array(
				'country'            => __( 'Poland', 'connect-ecommerce' ),
				'standard_rate'      => 23.00,
				'reduced_rate'       => 8.00,
				'reduced_rate_alt'   => 5.00,
				'super_reduced_rate' => false,
				'parking_rate'       => false,
			),
			'PT' => array(
				'country'            => __( 'Portugal', 'connect-ecommerce' ),
				'standard_rate'      => 23.00,
				'reduced_rate'       => 13.00,
				'reduced_rate_alt'   => 6.00,
				'super_reduced_rate' => false,
				'parking_rate'       => 13.00,
			),
			'RO' => array(
				'country'            => __( 'Romania', 'connect-ecommerce' ),
				'standard_rate'      => 19.00,
				'reduced_rate'       => 9.00,
				'reduced_rate_alt'   => 5.00,
				'super_reduced_rate' => false,
				'parking_rate'       => false,
			),
			'SI' => array(
				'country'            => __( 'Slovenia', 'connect-ecommerce' ),
				'standard_rate'      => 22.00,
				'reduced_rate'       => 9.50,
				'reduced_rate_alt'   => 5.00,
				'super_reduced_rate' => false,
				'parking_rate'       => false,
			),
			'SK' => array(
				'country'            => __( 'Slovakia', 'connect-ecommerce' ),
				'standard_rate'      => 23.00,
				'reduced_rate'       => 19.00,
				'reduced_rate_alt'   => 5.00,
				'super_reduced_rate' => false,
				'parking_rate'       => false,
			),
			'SE' => array(
				'country'            => __( 'Sweden', 'connect-ecommerce' ),
				'standard_rate'      => 25.00,
				'reduced_rate'       => 12.00,
				'reduced_rate_alt'   => 6.00,
				'super_reduced_rate' => false,
				'parking_rate'       => false,
			),
			'UK' => array(
				'country'            => __( 'United Kingdom', 'connect-ecommerce' ),
				'standard_rate'      => 20.00,
				'reduced_rate'       => 5.00,
				'reduced_rate_alt'   => false,
				'super_reduced_rate' => false,
				'parking_rate'       => false,
			),
			'GB' => array(
				'country'            => __( 'United Kingdom', 'connect-ecommerce' ),
				'standard_rate'      => 20.00,
				'reduced_rate'       => 5.00,
				'reduced_rate_alt'   => false,
				'super_reduced_rate' => false,
				'parking_rate'       => false,
			),
		);

		if ( isset( $vat_rates[ $country ] ) ) {
			return $vat_rates[ $country ];
		}

		return array();
	}

	/**
	 * Get special regions for a country with custom tax rates.
	 *
	 * @param string $country Country code (e.g., 'ES').
	 * @return array Special regions with their tax rates or empty array if no special regions.
	 */
	public static function get_special_regions( $country ) {
		$country         = strtoupper( $country );
		$special_regions = array();

		// Spain special regions with 0% VAT.
		if ( 'ES' === $country ) {
			$special_regions = array(
				'CE' => array(
					'name'               => __( 'Ceuta', 'connect-ecommerce' ),
					'standard_rate'      => 0.00,
					'reduced_rate'       => 0.00,
					'reduced_rate_alt'   => 0.00,
					'super_reduced_rate' => 0.00,
				),
				'GC' => array(
					'name'               => __( 'Las Palmas', 'connect-ecommerce' ),
					'standard_rate'      => 0.00,
					'reduced_rate'       => 0.00,
					'reduced_rate_alt'   => 0.00,
					'super_reduced_rate' => 0.00,
				),
				'ML' => array(
					'name'               => __( 'Melilla', 'connect-ecommerce' ),
					'standard_rate'      => 0.00,
					'reduced_rate'       => 0.00,
					'reduced_rate_alt'   => 0.00,
					'super_reduced_rate' => 0.00,
				),
				'TF' => array(
					'name'               => __( 'Santa Cruz de Tenerife', 'connect-ecommerce' ),
					'standard_rate'      => 0.00,
					'reduced_rate'       => 0.00,
					'reduced_rate_alt'   => 0.00,
					'super_reduced_rate' => 0.00,
				),
			);
		}

		return $special_regions;
	}
}

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
		self::ensure_tax_type_column();

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
			return ! empty( $tax_types_map[ $tax_rate_id ] ) ? $tax_types_map[ $tax_rate_id ] : '';
		}

		return $tax_types_map;
	}

	/**
	 * Update tax type.
	 *
	 * @param int    $tax_rate_id  Tax rate ID.
	 * @param string $erp_tax_type ERP tax type.
	 * @return int|false Number of rows updated, or false on failure.
	 */
	public static function update_tax_type( $tax_rate_id, $erp_tax_type ) {
		$erp_tax_type = sanitize_text_field( $erp_tax_type );
		$tax_rate_id  = absint( $tax_rate_id );

		self::ensure_tax_type_column();

		global $wpdb;

		$table_name = $wpdb->prefix . 'woocommerce_tax_rates';
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		return $wpdb->update(
			$table_name,
			array( 'erp_tax_type' => $erp_tax_type ),
			array( 'tax_rate_id' => $tax_rate_id )
		);
	}

	/**
	 * Get WooCommerce tax class slug mapped to a given ERP tax ID.
	 *
	 * Reverse lookup of get_tax_types_map(): finds the WooCommerce tax rate
	 * whose "ERP Tax Type" column (WooCommerce > Settings > Tax) matches the
	 * given ERP (e.g. Odoo) tax ID, and returns its tax_rate_class slug so it
	 * can be assigned to a product's tax_class.
	 *
	 * @param int|string $erp_tax_id ERP tax ID (e.g. Odoo account.tax id).
	 * @return string|null Tax class slug ('' for standard rate), or null if no mapping exists.
	 */
	public static function get_tax_class_by_erp_id( $erp_tax_id ) {
		if ( '' === (string) $erp_tax_id ) {
			return null;
		}

		self::ensure_tax_type_column();

		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$row = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT tax_rate_class FROM {$wpdb->prefix}woocommerce_tax_rates WHERE erp_tax_type = %s LIMIT 1",
				(string) $erp_tax_id
			),
			ARRAY_A
		);

		// get_var() would return null both when no row matches and when the
		// matched row's tax_rate_class is '' (standard rate) — get_row() lets
		// us tell "no mapping" apart from "mapped to the standard class".
		return null === $row ? null : $row['tax_rate_class'];
	}

	/**
	 * Ensure ERP tax type column exists in WooCommerce tax rates table.
	 *
	 * @return void
	 */
	public static function ensure_tax_type_column() {
		static $checked = false;

		if ( $checked ) {
			return;
		}

		global $wpdb;

		$table_name  = $wpdb->prefix . 'woocommerce_tax_rates';
		$column_name = 'erp_tax_type';

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared
		$column_exists = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS
				WHERE TABLE_SCHEMA = %s
				AND TABLE_NAME = %s
				AND COLUMN_NAME = %s",
				DB_NAME,
				$table_name,
				$column_name
			)
		);

		if ( empty( $column_exists ) ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange
			$wpdb->query(
				"ALTER TABLE {$table_name}
				ADD COLUMN {$column_name} VARCHAR(50) NULL AFTER tax_rate_class"
			);
		}

		$checked = true;
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
				'country'            => __( 'Austria', 'woocommerce-es' ),
				'standard_rate'      => 20.00,
				'reduced_rate'       => 10.00,
				'reduced_rate_alt'   => 13.00,
				'super_reduced_rate' => false,
				'parking_rate'       => 12.00,
			),
			'BE' => array(
				'country'            => __( 'Belgium', 'woocommerce-es' ),
				'standard_rate'      => 21.00,
				'reduced_rate'       => 12.00,
				'reduced_rate_alt'   => 6.00,
				'super_reduced_rate' => false,
				'parking_rate'       => 12.00,
			),
			'BG' => array(
				'country'            => __( 'Bulgaria', 'woocommerce-es' ),
				'standard_rate'      => 20.00,
				'reduced_rate'       => 9.00,
				'reduced_rate_alt'   => false,
				'super_reduced_rate' => false,
				'parking_rate'       => false,
			),
			'CY' => array(
				'country'            => __( 'Cyprus', 'woocommerce-es' ),
				'standard_rate'      => 19.00,
				'reduced_rate'       => 9.00,
				'reduced_rate_alt'   => 5.00,
				'super_reduced_rate' => false,
				'parking_rate'       => false,
			),
			'CZ' => array(
				'country'            => __( 'Czech Republic', 'woocommerce-es' ),
				'standard_rate'      => 21.00,
				'reduced_rate'       => 15.00,
				'reduced_rate_alt'   => 12.00,
				'super_reduced_rate' => false,
				'parking_rate'       => false,
			),
			'DK' => array(
				'country'            => __( 'Denmark', 'woocommerce-es' ),
				'standard_rate'      => 25.00,
				'reduced_rate'       => false,
				'reduced_rate_alt'   => false,
				'super_reduced_rate' => false,
				'parking_rate'       => false,
			),
			'DE' => array(
				'country'            => __( 'Germany', 'woocommerce-es' ),
				'standard_rate'      => 19.00,
				'reduced_rate'       => 7.00,
				'reduced_rate_alt'   => false,
				'super_reduced_rate' => false,
				'parking_rate'       => false,
			),
			'EE' => array(
				'country'            => __( 'Estonia', 'woocommerce-es' ),
				'standard_rate'      => 24.00,
				'reduced_rate'       => 9.00,
				'reduced_rate_alt'   => false,
				'super_reduced_rate' => false,
				'parking_rate'       => false,
			),
			'EL' => array(
				'country'            => __( 'Greece', 'woocommerce-es' ),
				'iso_duplicate'      => 'GR',
				'standard_rate'      => 24.00,
				'reduced_rate'       => 13.00,
				'reduced_rate_alt'   => 6.00,
				'super_reduced_rate' => false,
				'parking_rate'       => false,
			),
			'GR' => array(
				'country'            => __( 'Greece', 'woocommerce-es' ),
				'iso_duplicate_of'   => 'EL',
				'standard_rate'      => 24.00,
				'reduced_rate'       => 13.00,
				'reduced_rate_alt'   => 6.00,
				'super_reduced_rate' => false,
				'parking_rate'       => false,
			),
			'ES' => array(
				'country'            => __( 'Spain', 'woocommerce-es' ),
				'standard_rate'      => 21.00,
				'reduced_rate'       => 10.00,
				'reduced_rate_alt'   => false,
				'super_reduced_rate' => 4.00,
				'parking_rate'       => false,
			),
			'FI' => array(
				'country'            => __( 'Finland', 'woocommerce-es' ),
				'standard_rate'      => 25.50,
				'reduced_rate'       => 14.00,
				'reduced_rate_alt'   => 10.00,
				'super_reduced_rate' => false,
				'parking_rate'       => false,
			),
			'FR' => array(
				'country'            => __( 'France', 'woocommerce-es' ),
				'standard_rate'      => 20.00,
				'reduced_rate'       => 10.00,
				'reduced_rate_alt'   => 5.50,
				'super_reduced_rate' => 2.10,
				'parking_rate'       => false,
			),
			'HR' => array(
				'country'            => __( 'Croatia', 'woocommerce-es' ),
				'standard_rate'      => 25.00,
				'reduced_rate'       => 13.00,
				'reduced_rate_alt'   => 5.00,
				'super_reduced_rate' => false,
				'parking_rate'       => false,
			),
			'IT' => array(
				'country'            => __( 'Italy', 'woocommerce-es' ),
				'standard_rate'      => 22.00,
				'reduced_rate'       => 10.00,
				'reduced_rate_alt'   => 5.00,
				'super_reduced_rate' => 4.00,
				'parking_rate'       => false,
			),
			'LV' => array(
				'country'            => __( 'Latvia', 'woocommerce-es' ),
				'standard_rate'      => 21.00,
				'reduced_rate'       => 5.00,
				'reduced_rate_alt'   => false,
				'super_reduced_rate' => false,
				'parking_rate'       => false,
			),
			'LT' => array(
				'country'            => __( 'Lithuania', 'woocommerce-es' ),
				'standard_rate'      => 21.00,
				'reduced_rate'       => 9.00,
				'reduced_rate_alt'   => 5.00,
				'super_reduced_rate' => false,
				'parking_rate'       => false,
			),
			'LU' => array(
				'country'            => __( 'Luxembourg', 'woocommerce-es' ),
				'standard_rate'      => 17.00,
				'reduced_rate'       => 14.00,
				'reduced_rate_alt'   => 8.00,
				'super_reduced_rate' => 3.00,
				'parking_rate'       => 12.00,
			),
			'HU' => array(
				'country'            => __( 'Hungary', 'woocommerce-es' ),
				'standard_rate'      => 27.00,
				'reduced_rate'       => 18.00,
				'reduced_rate_alt'   => 5.00,
				'super_reduced_rate' => false,
				'parking_rate'       => false,
			),
			'IE' => array(
				'country'            => __( 'Ireland', 'woocommerce-es' ),
				'standard_rate'      => 23.00,
				'reduced_rate'       => 13.50,
				'reduced_rate_alt'   => 9.00,
				'super_reduced_rate' => 4.80,
				'parking_rate'       => 13.50,
			),
			'MT' => array(
				'country'            => __( 'Malta', 'woocommerce-es' ),
				'standard_rate'      => 18.00,
				'reduced_rate'       => 7.00,
				'reduced_rate_alt'   => 5.00,
				'super_reduced_rate' => false,
				'parking_rate'       => false,
			),
			'NL' => array(
				'country'            => __( 'Netherlands', 'woocommerce-es' ),
				'standard_rate'      => 21.00,
				'reduced_rate'       => 9.00,
				'reduced_rate_alt'   => false,
				'super_reduced_rate' => false,
				'parking_rate'       => false,
			),
			'PL' => array(
				'country'            => __( 'Poland', 'woocommerce-es' ),
				'standard_rate'      => 23.00,
				'reduced_rate'       => 8.00,
				'reduced_rate_alt'   => 5.00,
				'super_reduced_rate' => false,
				'parking_rate'       => false,
			),
			'PT' => array(
				'country'            => __( 'Portugal', 'woocommerce-es' ),
				'standard_rate'      => 23.00,
				'reduced_rate'       => 13.00,
				'reduced_rate_alt'   => 6.00,
				'super_reduced_rate' => false,
				'parking_rate'       => 13.00,
			),
		'RO' => array(
			'country'            => __( 'Romania', 'woocommerce-es' ),
			'standard_rate'      => 19.00,
			'reduced_rate'       => 11.00,
			'reduced_rate_alt'   => 5.00,
			'super_reduced_rate' => false,
			'parking_rate'       => false,
		),
			'SI' => array(
				'country'            => __( 'Slovenia', 'woocommerce-es' ),
				'standard_rate'      => 22.00,
				'reduced_rate'       => 9.50,
				'reduced_rate_alt'   => 5.00,
				'super_reduced_rate' => false,
				'parking_rate'       => false,
			),
			'SK' => array(
				'country'            => __( 'Slovakia', 'woocommerce-es' ),
				'standard_rate'      => 23.00,
				'reduced_rate'       => 19.00,
				'reduced_rate_alt'   => 5.00,
				'super_reduced_rate' => false,
				'parking_rate'       => false,
			),
			'SE' => array(
				'country'            => __( 'Sweden', 'woocommerce-es' ),
				'standard_rate'      => 25.00,
				'reduced_rate'       => 12.00,
				'reduced_rate_alt'   => 6.00,
				'super_reduced_rate' => false,
				'parking_rate'       => false,
			),
			'UK' => array(
				'country'            => __( 'United Kingdom', 'woocommerce-es' ),
				'standard_rate'      => 20.00,
				'reduced_rate'       => 5.00,
				'reduced_rate_alt'   => false,
				'super_reduced_rate' => false,
				'parking_rate'       => false,
			),
			'GB' => array(
				'country'            => __( 'United Kingdom', 'woocommerce-es' ),
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
					'name'               => __( 'Ceuta', 'woocommerce-es' ),
					'standard_rate'      => 0.00,
					'reduced_rate'       => 0.00,
					'reduced_rate_alt'   => 0.00,
					'super_reduced_rate' => 0.00,
				),
				'GC' => array(
					'name'               => __( 'Las Palmas', 'woocommerce-es' ),
					'standard_rate'      => 0.00,
					'reduced_rate'       => 0.00,
					'reduced_rate_alt'   => 0.00,
					'super_reduced_rate' => 0.00,
				),
				'ML' => array(
					'name'               => __( 'Melilla', 'woocommerce-es' ),
					'standard_rate'      => 0.00,
					'reduced_rate'       => 0.00,
					'reduced_rate_alt'   => 0.00,
					'super_reduced_rate' => 0.00,
				),
				'TF' => array(
					'name'               => __( 'Santa Cruz de Tenerife', 'woocommerce-es' ),
					'standard_rate'      => 0.00,
					'reduced_rate'       => 0.00,
					'reduced_rate_alt'   => 0.00,
					'super_reduced_rate' => 0.00,
				),
			);
		}

		return $special_regions;
	}

	/**
	 * Returns the WooCommerce tax class that matches the first applicable ERP tax key.
	 *
	 * Iterates the supplied ERP tax keys, resolves each key's percentage via get_taxes(),
	 * then finds the WooCommerce tax rate (across all tax classes) whose rate equals that
	 * percentage. Returns the class slug of the first match, or an empty string (Standard).
	 *
	 * @param string $tax_slug String of ERP tax keys.
	 * @return string WooCommerce tax class slug ('' = Standard).
	 */
	public static function get_wc_tax_class_by_erp_taxes( string $tax_slug ): string {
		if ( empty( $tax_slug ) ) {
			return '';
		}

		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$result = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT tax_rate_class FROM {$wpdb->prefix}woocommerce_tax_rates WHERE erp_tax_type = %s LIMIT 1",
				$tax_slug
			)
		);

		return $result ?? '';
	}

	/**
	 * Get all WooCommerce tax rates grouped by tax class.
	 *
	 * @return array<string, array<int, array<string, mixed>>>
	 */
	public static function get_all_wc_tax_rates(): array {
			$classes = array_merge( [ '' ], array_keys( \WC_Tax::get_tax_classes() ) );
			$all     = [];

			foreach ( $classes as $class ) {
				$rates = \WC_Tax::get_rates_for_tax_class( $class );

				if ( empty( $rates ) ) {
					continue;
				}

				$all += $rates;
			}

			return $all;
	}
}

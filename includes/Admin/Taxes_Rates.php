<?php
/**
 * Library for admin settings
 *
 * @package    WordPress
 * @author     David Perez <david@closemarketing.es>
 * @copyright  2025 Closemarketing
 * @version    3.2.0
 */

namespace CLOSE\ConnectEcommerce\Admin;

defined( 'ABSPATH' ) || exit;

use CLOSE\ConnectEcommerce\Helpers\TAXES;

/**
 * Class Admin Connect WooCommerce.
 */
class Taxes_Rates {
	/**
	 * Connector options.
	 *
	 * @var array
	 */
	private $connector;

	/**
	 * Construct of class
	 *
	 * @param array $connector Connector options.
	 * @return void
	 */
	public function __construct( $connector ) {
		$this->connector = $connector;
		add_action( 'wp_ajax_connect_update_tax_rates', array( $this, 'ajax_update_tax_rates' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_scripts' ) );
	}

	/**
	 * Enqueue scripts for tax rates update.
	 *
	 * @param string $hook Current admin page hook.
	 * @return void
	 */
	public function enqueue_scripts( $hook ) {
		if ( 'woocommerce_page_wc-settings' !== $hook ) {
			return;
		}

		// Check if we're on the tax tab.
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( ! isset( $_GET['tab'] ) || 'tax' !== $_GET['tab'] ) {
			return;
		}

		// Get current section (tax class).
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$current_section = isset( $_GET['section'] ) ? sanitize_text_field( wp_unslash( $_GET['section'] ) ) : '';
		if ( empty( $current_section ) ) {
			return;
		}

		// Map section names to rate types for preselection.
		$section_to_rate_map = array(
			'standard'           => 'standard_rate',
			'reduced-rate'       => 'reduced_rate',
			'reduced-rate-alt'   => 'reduced_rate_alt',
			'super-reduced-rate' => 'super_reduced_rate',
			'zero-rate'          => 'zero_rate',
		);

		// Get the default rate type based on section.
		$default_rate_type = isset( $section_to_rate_map[ $current_section ] ) ? $section_to_rate_map[ $current_section ] : 'all';

		$inline_script = "
		document.addEventListener('DOMContentLoaded', function() {
			// Wait a bit for the page to fully render.
			setTimeout(function() {
				// Find the tax rates table.
				const taxRatesTable = document.querySelector('.wc_tax_rates');
				if (!taxRatesTable) return;

				// Create the button wrapper.
				const wrapper = document.createElement('div');
				wrapper.className = 'connect-update-tax-rates-wrapper';
				wrapper.style.marginBottom = '15px';
				wrapper.innerHTML = `
					<div style=\"display: flex; align-items: center; gap: 10px; margin-bottom: 10px;\">
						<label for=\"connect-rate-type\" style=\"font-weight: bold;\">
							" . esc_html__( 'Rate Type:', 'woocommerce-es' ) . "
						</label>
						<select id=\"connect-rate-type\" class=\"regular-text\" style=\"width: auto;\">
							<option value=\"standard_rate\">" . esc_html__( 'Standard Rate', 'woocommerce-es' ) . "</option>
							<option value=\"reduced_rate\">" . esc_html__( 'Reduced Rate', 'woocommerce-es' ) . "</option>
							<option value=\"reduced_rate_alt\">" . esc_html__( 'Reduced Rate Alt', 'woocommerce-es' ) . "</option>
							<option value=\"super_reduced_rate\">" . esc_html__( 'Super Reduced Rate', 'woocommerce-es' ) . "</option>
							<option value=\"zero_rate\">" . esc_html__( 'Zero Rate', 'woocommerce-es' ) . "</option>
							<option value=\"all\">" . esc_html__( 'All Rates', 'woocommerce-es' ) . "</option>
						</select>
						<button type=\"button\" id=\"connect-update-tax-rates\" class=\"button button-primary\">
							" . esc_html__( 'Update Tax Rates from EU Database', 'woocommerce-es' ) . "
						</button>
					</div>
					<div id=\"connect-tax-rates-message\"></div>
				`;

				// Insert before the table.
				taxRatesTable.parentNode.insertBefore(wrapper, taxRatesTable);

				// Add click event.
				const button = document.getElementById('connect-update-tax-rates');
				const rateTypeSelect = document.getElementById('connect-rate-type');
				const messageDiv = document.getElementById('connect-tax-rates-message');

				// Set default rate type based on current section.
				rateTypeSelect.value = '" . esc_js( $default_rate_type ) . "';

				button.addEventListener('click', function(e) {
					e.preventDefault();
					
					const originalText = button.textContent;
					const rateType = rateTypeSelect.value;
					const taxClass = '" . esc_js( $current_section ) . "';
					
					button.disabled = true;
					button.textContent = '" . esc_js( __( 'Updating...', 'woocommerce-es' ) ) . "';
					messageDiv.innerHTML = '';

					fetch(ajaxurl, {
						method: 'POST',
						headers: {
							'Content-Type': 'application/x-www-form-urlencoded',
						},
						body: 'action=connect_update_tax_rates&nonce=' + '" . wp_create_nonce( 'connect_update_tax_rates' ) . "' + '&rate_type=' + encodeURIComponent(rateType) + '&tax_class=' + encodeURIComponent(taxClass)
					})
					.then(response => response.json())
					.then(data => {
						button.disabled = false;
						button.textContent = originalText;
						
						if (data.success) {
							messageDiv.innerHTML = '<div class=\"notice notice-success inline\"><p>' + data.data.message + '</p></div>';
							// Reload page after 2 seconds to show updated rates.
							setTimeout(function() {
								location.reload();
							}, 2000);
						} else {
							messageDiv.innerHTML = '<div class=\"notice notice-error inline\"><p>' + data.data.message + '</p></div>';
						}
					})
					.catch(error => {
						button.disabled = false;
						button.textContent = originalText;
						messageDiv.innerHTML = '<div class=\"notice notice-error inline\"><p>" . esc_js( __( 'An error occurred. Please try again.', 'woocommerce-es' ) ) . "</p></div>';
					});
				});
			}, 500);
		});
		";

		wp_add_inline_script( 'jquery', $inline_script );
	}

	/**
	 * AJAX handler to update tax rates.
	 *
	 * @return void
	 */
	public function ajax_update_tax_rates() {
		check_ajax_referer( 'connect_update_tax_rates', 'nonce' );

		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_send_json_error(
				array(
					'message' => __( 'You do not have permission to perform this action.', 'woocommerce-es' ),
				)
			);
		}

		// Get rate type and tax class from request.
		$rate_type = isset( $_POST['rate_type'] ) ? sanitize_text_field( wp_unslash( $_POST['rate_type'] ) ) : 'all';
		$tax_class = isset( $_POST['tax_class'] ) ? sanitize_text_field( wp_unslash( $_POST['tax_class'] ) ) : '';

		$result = $this->update_woocommerce_tax_rates( $rate_type, $tax_class );

		if ( is_wp_error( $result ) ) {
			wp_send_json_error(
				array(
					'message' => $result->get_error_message(),
				)
			);
		}

		wp_send_json_success(
			array(
				'message' => $result['message'],
				'updated' => $result['updated'],
			)
		);
	}

	/**
	 * Update WooCommerce tax rates with EU rates.
	 *
	 * @param string $rate_type Type of rate to update (standard_rate, reduced_rate, reduced_rate_alt, super_reduced_rate, or all).
	 * @param string $tax_class Tax class to update (standard, reduced-rate, etc.). Empty string for standard.
	 * @return array|\WP_Error
	 */
	private function update_woocommerce_tax_rates( $rate_type = 'all', $tax_class = '' ) {
		// EU country codes.
		$eu_countries = array(
			'AT',
			'BE',
			'BG',
			'CY',
			'CZ',
			'DK',
			'DE',
			'EE',
			'EL',
			'GR',
			'ES',
			'FI',
			'FR',
			'HR',
			'IT',
			'LV',
			'LT',
			'LU',
			'HU',
			'IE',
			'MT',
			'NL',
			'PL',
			'PT',
			'RO',
			'SI',
			'SK',
			'SE',
			'GB',
			'UK',
		);

		$updated_count = 0;
		$tax_class     = 'standard' === $tax_class ? '' : $tax_class;

		$shop_country = get_option( 'woocommerce_default_country' );
		if ( strpos( $shop_country, ':' ) !== false ) {
			$shop_country = explode( ':', $shop_country )[0];
		}

		foreach ( $eu_countries as $country_code ) {
			// Zero rate.
			if ( 'zero_rate' === $rate_type && $shop_country !== $country_code ) {
				$this->insert_or_update_tax_rate(
					$country_code,
					0.00,
					__( 'Zero VAT', 'woocommerce-es' ),
					1,
					'',
					$tax_class
				);
				++$updated_count;
				continue;
			} elseif ( 'zero_rate' === $rate_type && $shop_country === $country_code ) {
				continue;
			}

			// Standard rate.
			$rates = TAXES::get_rates_by_country( $country_code );

			if ( empty( $rates ) ) {
				continue;
			}

			// Insert or update standard rate.
			if ( ( 'all' === $rate_type || 'standard_rate' === $rate_type ) && isset( $rates['standard_rate'] ) && $rates['standard_rate'] ) {
				$this->insert_or_update_tax_rate(
					$country_code,
					$rates['standard_rate'],
					__( 'VAT', 'woocommerce-es' ),
					1,
					'',
					$tax_class
				);
				++$updated_count;
			}

			// Insert or update reduced rate.
			if ( ( 'all' === $rate_type || 'reduced_rate' === $rate_type ) && isset( $rates['reduced_rate'] ) && $rates['reduced_rate'] ) {
				$this->insert_or_update_tax_rate(
					$country_code,
					$rates['reduced_rate'],
					__( 'Reduced VAT', 'woocommerce-es' ),
					2,
					'',
					$tax_class
				);
				++$updated_count;
			}

			// Insert or update alternative reduced rate.
			if ( ( 'all' === $rate_type || 'reduced_rate_alt' === $rate_type ) && isset( $rates['reduced_rate_alt'] ) && $rates['reduced_rate_alt'] ) {
				$this->insert_or_update_tax_rate(
					$country_code,
					$rates['reduced_rate_alt'],
					__( 'Reduced Alt VAT', 'woocommerce-es' ),
					3,
					'',
					$tax_class
				);
				++$updated_count;
			}

			// Insert or update super reduced rate.
			if ( ( 'all' === $rate_type || 'super_reduced_rate' === $rate_type ) && isset( $rates['super_reduced_rate'] ) && $rates['super_reduced_rate'] ) {
				$this->insert_or_update_tax_rate(
					$country_code,
					$rates['super_reduced_rate'],
					__( 'Super Reduced VAT', 'woocommerce-es' ),
					4,
					'',
					$tax_class
				);
				++$updated_count;
			}

			// Special handling for countries with special regions.
			$special_regions = TAXES::get_special_regions( $country_code );
			if ( ! empty( $special_regions ) ) {
				foreach ( $special_regions as $state_code => $region_data ) {
					// Insert or update standard rate for special regions.
					if ( ( 'all' === $rate_type || 'standard_rate' === $rate_type ) && isset( $region_data['standard_rate'] ) ) {
						$this->insert_or_update_tax_rate(
							$country_code,
							$region_data['standard_rate'],
							/* translators: %s: Region name */
							sprintf( __( 'VAT %s', 'woocommerce-es' ), $region_data['name'] ),
							1,
							$state_code,
							$tax_class
						);
						++$updated_count;
					}

					// Insert or update reduced rate for special regions.
					if ( ( 'all' === $rate_type || 'reduced_rate' === $rate_type ) && isset( $region_data['reduced_rate'] ) ) {
						$this->insert_or_update_tax_rate(
							$country_code,
							$region_data['reduced_rate'],
							/* translators: %s: Region name */
							sprintf( __( 'Reduced VAT %s', 'woocommerce-es' ), $region_data['name'] ),
							2,
							$state_code,
							$tax_class
						);
						++$updated_count;
					}

					// Insert or update alternative reduced rate for special regions.
					if ( ( 'all' === $rate_type || 'reduced_rate_alt' === $rate_type ) && isset( $region_data['reduced_rate_alt'] ) ) {
						$this->insert_or_update_tax_rate(
							$country_code,
							$region_data['reduced_rate_alt'],
							/* translators: %s: Region name */
							sprintf( __( 'Reduced Alt VAT %s', 'woocommerce-es' ), $region_data['name'] ),
							3,
							$state_code,
							$tax_class
						);
						++$updated_count;
					}

					// Insert or update super reduced rate for special regions.
					if ( ( 'all' === $rate_type || 'super_reduced_rate' === $rate_type ) && isset( $region_data['super_reduced_rate'] ) ) {
						$this->insert_or_update_tax_rate(
							$country_code,
							$region_data['super_reduced_rate'],
							/* translators: %s: Region name */
							sprintf( __( 'Super Reduced VAT %s', 'woocommerce-es' ), $region_data['name'] ),
							4,
							$state_code,
							$tax_class
						);
						++$updated_count;
					}
				}
			}
		}

		return array(
			'message' => sprintf(
				/* translators: %d: number of tax rates updated */
				__( 'Successfully updated %d tax rates.', 'woocommerce-es' ),
				$updated_count
			),
			'updated' => $updated_count,
		);
	}

	/**
	 * Insert or update a tax rate in WooCommerce.
	 *
	 * @param string $country_code Country code.
	 * @param float  $rate Tax rate.
	 * @param string $rate_name Rate name.
	 * @param int    $rate_order Rate order.
	 * @param string $state_code Optional. State code.
	 * @param string $tax_class Optional. Tax class.
	 * @return void
	 */
	private function insert_or_update_tax_rate( $country_code, $rate, $rate_name, $rate_order, $state_code = '', $tax_class = '' ) {
		global $wpdb;

		// Check if rate already exists.
		$query        = "SELECT tax_rate_id FROM {$wpdb->prefix}woocommerce_tax_rates 
				WHERE tax_rate_country = %s 
				AND tax_rate_class = %s";
		$query_params = array( $country_code, $tax_class );

		if ( $state_code ) {
			$query         .= ' AND tax_rate_state = %s';
			$query_params[] = $state_code;
		} else {
			$query         .= ' AND tax_rate_state = %s';
			$query_params[] = '';
		}

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		$existing_rate = $wpdb->get_row(
			// phpcs:ignore WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare
			$wpdb->prepare(
				$query, // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
				...$query_params
			)
		);

		if ( $existing_rate ) {
			// Update existing rate.
			$wpdb->update(
				$wpdb->prefix . 'woocommerce_tax_rates',
				array(
					'tax_rate' => $rate,
				),
				array(
					'tax_rate_id' => $existing_rate->tax_rate_id,
				),
				array( '%f' ),
				array( '%d' )
			);
		} else {
			// Insert new rate.
			$wpdb->insert(
				$wpdb->prefix . 'woocommerce_tax_rates',
				array(
					'tax_rate_country'  => $country_code,
					'tax_rate_state'    => $state_code,
					'tax_rate'          => $rate,
					'tax_rate_name'     => $rate_name,
					'tax_rate_priority' => $rate_order,
					'tax_rate_order'    => $rate_order,
					'tax_rate_class'    => $tax_class,
				),
				array(
					'%s',
					'%s',
					'%f',
					'%s',
					'%d',
					'%d',
					'%s',
				)
			);
		}

		// Clear tax rate cache.
		\WC_Cache_Helper::invalidate_cache_group( 'taxes' );
	}
}

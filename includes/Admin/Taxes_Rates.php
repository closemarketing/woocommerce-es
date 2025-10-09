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
	 * Construct of class
	 *
	 * @return void
	 */
	public function __construct() {
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

		// Check if we're on the standard section.
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$current_section = isset( $_GET['section'] ) ? sanitize_text_field( wp_unslash( $_GET['section'] ) ) : '';
		if ( 'standard' !== $current_section ) {
			return;
		}

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
							" . esc_js( __( 'Rate Type:', 'connect-ecommerce' ) ) . "
						</label>
						<select id=\"connect-rate-type\" class=\"regular-text\" style=\"width: auto;\">
							<option value=\"standard_rate\">" . esc_js( __( 'Standard Rate', 'connect-ecommerce' ) ) . "</option>
							<option value=\"reduced_rate\">" . esc_js( __( 'Reduced Rate', 'connect-ecommerce' ) ) . "</option>
							<option value=\"reduced_rate_alt\">" . esc_js( __( 'Reduced Rate Alt', 'connect-ecommerce' ) ) . "</option>
							<option value=\"super_reduced_rate\">" . esc_js( __( 'Super Reduced Rate', 'connect-ecommerce' ) ) . "</option>
							<option value=\"all\">" . esc_js( __( 'All Rates', 'connect-ecommerce' ) ) . "</option>
						</select>
						<button type=\"button\" id=\"connect-update-tax-rates\" class=\"button button-primary\">
							" . esc_js( __( 'Update Tax Rates from EU Database', 'connect-ecommerce' ) ) . "
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

				button.addEventListener('click', function(e) {
					e.preventDefault();
					
					const originalText = button.textContent;
					const rateType = rateTypeSelect.value;
					
					button.disabled = true;
					button.textContent = '" . esc_js( __( 'Updating...', 'connect-ecommerce' ) ) . "';
					messageDiv.innerHTML = '';

					fetch(ajaxurl, {
						method: 'POST',
						headers: {
							'Content-Type': 'application/x-www-form-urlencoded',
						},
						body: 'action=connect_update_tax_rates&nonce=' + '" . wp_create_nonce( 'connect_update_tax_rates' ) . "' + '&rate_type=' + encodeURIComponent(rateType)
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
						messageDiv.innerHTML = '<div class=\"notice notice-error inline\"><p>" . esc_js( __( 'An error occurred. Please try again.', 'connect-ecommerce' ) ) . "</p></div>';
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
					'message' => __( 'You do not have permission to perform this action.', 'connect-ecommerce' ),
				)
			);
		}

		// Get rate type from request.
		$rate_type = isset( $_POST['rate_type'] ) ? sanitize_text_field( wp_unslash( $_POST['rate_type'] ) ) : 'all';

		$result = $this->update_woocommerce_tax_rates( $rate_type );

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
	 * @return array|\WP_Error
	 */
	private function update_woocommerce_tax_rates( $rate_type = 'all' ) {
		global $wpdb;

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

		foreach ( $eu_countries as $country_code ) {
			$rates = TAXES::get_rates_by_country( $country_code );

			if ( empty( $rates ) ) {
				continue;
			}

			// Insert or update standard rate.
			if ( ( 'all' === $rate_type || 'standard_rate' === $rate_type ) && isset( $rates['standard_rate'] ) && $rates['standard_rate'] ) {
				$this->insert_or_update_tax_rate(
					$country_code,
					$rates['standard_rate'],
					__( 'VAT', 'connect-ecommerce' ),
					1
				);
				++$updated_count;
			}

			// Insert or update reduced rate.
			if ( ( 'all' === $rate_type || 'reduced_rate' === $rate_type ) && isset( $rates['reduced_rate'] ) && $rates['reduced_rate'] ) {
				$this->insert_or_update_tax_rate(
					$country_code,
					$rates['reduced_rate'],
					__( 'Reduced VAT', 'connect-ecommerce' ),
					2
				);
				++$updated_count;
			}

			// Insert or update alternative reduced rate.
			if ( ( 'all' === $rate_type || 'reduced_rate_alt' === $rate_type ) && isset( $rates['reduced_rate_alt'] ) && $rates['reduced_rate_alt'] ) {
				$this->insert_or_update_tax_rate(
					$country_code,
					$rates['reduced_rate_alt'],
					__( 'Reduced Alt VAT', 'connect-ecommerce' ),
					3
				);
				++$updated_count;
			}

			// Insert or update super reduced rate.
			if ( ( 'all' === $rate_type || 'super_reduced_rate' === $rate_type ) && isset( $rates['super_reduced_rate'] ) && $rates['super_reduced_rate'] ) {
				$this->insert_or_update_tax_rate(
					$country_code,
					$rates['super_reduced_rate'],
					__( 'Super Reduced VAT', 'connect-ecommerce' ),
					4
				);
				++$updated_count;
			}
		}

		return array(
			'message' => sprintf(
				/* translators: %d: number of tax rates updated */
				__( 'Successfully updated %d tax rates.', 'connect-ecommerce' ),
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
	 * @return void
	 */
	private function insert_or_update_tax_rate( $country_code, $rate, $rate_name, $rate_order ) {
		global $wpdb;

		// Check if rate already exists.
		$existing_rate = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT tax_rate_id FROM {$wpdb->prefix}woocommerce_tax_rates 
				WHERE tax_rate_country = %s 
				AND tax_rate_name = %s 
				AND tax_rate_class = ''",
				$country_code,
				$rate_name
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
					'tax_rate'          => $rate,
					'tax_rate_name'     => $rate_name,
					'tax_rate_priority' => $rate_order,
					'tax_rate_order'    => $rate_order,
					'tax_rate_class'    => '',
				),
				array(
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

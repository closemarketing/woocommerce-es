<?php
/**
 * ERP Tax Types Admin
 *
 * Adds ERP Tax Type column to WooCommerce Tax Rates table
 *
 * @package    WordPress
 * @author     David Perez <david@closemarketing.es>
 * @copyright  2025 Closemarketing
 * @version    3.2.1
 */

namespace CLOSE\ConnectEcommerce\Admin;

defined( 'ABSPATH' ) || exit;

use CLOSE\ConnectEcommerce\Helpers\TAXES;

/**
 * Class ERP Tax Types
 */
class Taxes_Types_ERP {
	/**
	 * Connector instance
	 *
	 * @var object
	 */
	private $connector;

	/**
	 * Options
	 *
	 * @var array
	 */
	private $options;

	/**
	 * Construct of class
	 *
	 * @param array $connector Connector options.
	 * @return void
	 */
	public function __construct( $connector ) {
		$this->connector = $connector;

		// Enqueue scripts.
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_scripts' ) );

		// Hook into WooCommerce AJAX save to handle our custom field.
		add_action( 'wp_ajax_woocommerce_tax_rates_save_changes', array( $this, 'intercept_tax_save' ), 5 );
	}

	/**
	 * Get ERP taxes options HTML.
	 *
	 * @return array Array with options_html and debug_info.
	 */
	private function get_erp_taxes_options() {
		// Get ERP taxes.
		$taxes      = array();
		$debug_info = array(
			'connector_type'      => is_object( $this->connector ) ? get_class( $this->connector ) : ( is_array( $this->connector ) ? 'array' : gettype( $this->connector ) ),
			'connector_structure' => is_array( $this->connector ) ? array_keys( $this->connector ) : array(),
			'has_connector'       => ! empty( $this->connector ),
			'has_method'          => false,
			'taxes_count'         => 0,
		);

		// Check if connector is an object or array.
		$connector_instance = null;
		if ( is_object( $this->connector ) && method_exists( $this->connector, 'get_taxes' ) ) {
			$connector_instance = $this->connector;
		} elseif ( is_array( $this->connector ) && ! empty( $this->connector['connapi_erp'] ) && method_exists( $this->connector['connapi_erp'], 'get_taxes' ) ) {
			$connector_instance = $this->connector['connapi_erp'];
		}

		if ( $connector_instance ) {
			$debug_info['has_method']      = true;
			$debug_info['connector_class'] = get_class( $connector_instance );
			$taxes                         = $connector_instance->get_taxes();
			if ( is_wp_error( $taxes ) ) {
				$debug_info['error'] = $taxes->get_error_message();
				$taxes               = array();
			} else {
				$debug_info['taxes_count']  = count( $taxes );
				$debug_info['taxes_sample'] = ! empty( $taxes[0] ) ? $taxes[0] : null;
			}
		}

		// Build options HTML.
		$erp_name      = isset( $this->connector['options']['name'] ) ? $this->connector['options']['name'] : 'ERP';
		$options_html  = '<option value="">';
		$options_html .= sprintf(
			/* translators: %s: ERP name */
			__( 'Select %s Tax', 'woocommerce-es' ),
			esc_html( $erp_name )
		);
		$options_html .= '</option>';
		if ( ! empty( $taxes ) && is_array( $taxes ) ) {
			// Odoo can have several taxes sharing the same display name (e.g.
			// different companies/fiscal positions). Count labels first so
			// duplicates get their ID appended and the user can tell them apart.
			$label_counts = array();
			foreach ( $taxes as $tax ) {
				$label                  = isset( $tax['name'] ) ? $tax['name'] : '';
				$label_counts[ $label ] = ( isset( $label_counts[ $label ] ) ? $label_counts[ $label ] : 0 ) + 1;
			}

			foreach ( $taxes as $tax ) {
				$id    = isset( $tax['id'] ) ? $tax['id'] : '';
				$label = isset( $tax['name'] ) ? $tax['name'] : $id;

				if ( $id ) {
					$option_label = $label_counts[ $label ] > 1 ? $label . ' (ID: ' . $id . ')' : $label;
					$options_html .= '<option value="' . esc_attr( $id ) . '">' . esc_html( $option_label ) . '</option>';
				}
			}
		}

		return array(
			'options_html' => $options_html,
			'debug_info'   => $debug_info,
		);
	}

	/**
	 * Enqueue scripts for ERP tax types.
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

		// Get ERP taxes options.
		$erp_data      = $this->get_erp_taxes_options();
		$options_html  = $erp_data['options_html'];
		$tax_types_map = TAXES::get_tax_types_map();

		// Enqueue our script.
		wp_enqueue_script(
			'conecom-erp-tax-types',
			CONECOM_PLUGIN_URL . 'includes/assets/erp-tax-types-admin.js',
			array(),
			CONECOM_VERSION,
			true
		);

		// Pass data to JavaScript.
		wp_localize_script(
			'conecom-erp-tax-types',
			'conecomErpTaxTypesData',
			array(
				'optionsHtml' => $options_html,
				'ajaxUrl'     => admin_url( 'admin-ajax.php' ),
				'nonce'       => wp_create_nonce( 'conecom_erp_tax_types' ),
				'headerText'  => __( 'ERP Tax Type', 'woocommerce-es' ),
				'taxTypesMap' => $tax_types_map,
			)
		);
	}

	/**
	 * Intercept WooCommerce tax save to add our custom field.
	 *
	 * This hooks into the WooCommerce AJAX save with priority 5 (before WC processes at 10)
	 * to collect our custom field data and add it to the $_POST for WooCommerce to process.
	 *
	 * @return void
	 */
	public function intercept_tax_save() {
		if ( ! isset( $_POST['changes'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
			return;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		$changes = wp_unslash( $_POST['changes'] );

		foreach ( $changes as $tax_rate_id => $data ) {
			if ( empty( $data['erp_tax_type'] ) ) {
				continue;
			}
			TAXES::update_tax_type( $tax_rate_id, $data['erp_tax_type'] );
		}
	}
}

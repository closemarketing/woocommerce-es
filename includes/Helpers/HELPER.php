<?php
/**
 * Sync Products
 *
 * @package    WordPress
 * @author     David Perez <david@close.technology>
 * @copyright  2023 Closemarketing
 * @version    1.0
 */

namespace CLOSE\ConnectEcommerce\Helpers;

use CLOSE\ConnectEcommerce\Helpers\PAYMENTS;
use CLOSE\ConnectEcommerce\Connector\CONECOM_Abstract_Connector_API;

defined( 'ABSPATH' ) || exit;

/**
 * Sync Products.
 *
 * @since 1.0.0
 */
class HELPER {
	/**
	 * Emails products with errors
	 *
	 * @param array  $product_errors Array of errors.
	 * @param string $option_name Name of option.
	 *
	 * @return void
	 */
	public static function send_product_errors( $product_errors, $option_name = '' ) {
		// Send to WooCommerce Logger.
		$logger      = wc_get_logger();
		$option_name = sanitize_title( $option_name );

		$error_content = '';
		if ( empty( $product_errors ) ) {
			return;
		}
		foreach ( $product_errors as $error ) {
			$error_prod  = ' ' . __( 'Error:', 'woocommerce-es' ) . $error['error'];
			$error_prod .= ' ' . __( 'SKU:', 'woocommerce-es' ) . $error['sku'];
			$error_prod .= ' ' . __( 'Name:', 'woocommerce-es' ) . $error['name'];

			if ( 'holded' === $option_name ) {
				$error_prod .= ' <a href="https://app.holded.com/products/' . $error['prod_id'] . '">';
				$error_prod .= __( 'Edit:', 'woocommerce-es' ) . '</a>';
			} else {
				$error_prod .= ' ' . __( 'Prod ID:', 'woocommerce-es' ) . $error['prod_id'];
			}
			// Sends to WooCommerce Log.
			$logger->warning(
				$error_prod,
				array(
					'source' => 'woocommerce-es',
				),
			);
			$error_content .= $error_prod . '<br/>';
		}
		// Sends an email to admin.
		$headers = array( 'Content-Type: text/html; charset=UTF-8' );
		wp_mail( get_option( 'admin_email' ), __( 'Error in Products Synced in', 'woocommerce-es' ) . ' ' . get_option( 'blogname' ), $error_content, $headers );

		// Send alert notification using the new alert system.
		ALERT::send_product_errors_alert( $product_errors );
	}
	/**
	 * Sends errors to admin
	 *
	 * @param string $subject Subject of Email.
	 * @param array  $errors  Array of errors.
	 * @return void
	 */
	public static function send_email_errors( $subject, $errors ) {
		$body    = implode( '<br/>', $errors );
		$headers = array( 'Content-Type: text/html; charset=UTF-8' );

		wp_mail( get_option( 'admin_email' ), 'IMPORT: ' . $subject, $body, $headers );
	}

	/**
	 * Write Log
	 *
	 * @param string $log String log.
	 * @return void
	 */
	public static function write_log( $log ) {
		if ( true === WP_DEBUG ) {
			if ( is_array( $log ) || is_object( $log ) ) {
				error_log( print_r( $log, true ) );
			} else {
				error_log( $log );
			}
		}
	}

	/**
	 * Sanitize array recursively
	 *
	 * @param array $array Array to sanitize.
	 * @return array
	 */
	public static function sanitize_array_recursive( $array ) {
		if ( ! is_array( $array ) ) {
			return sanitize_text_field( $array );
		}
		foreach ( $array as $key => $value ) {
			if ( is_array( $value ) ) {
				$array[ $key ] = self::sanitize_array_recursive( $value );
			} else {
				$array[ $key ] = sanitize_text_field( $value );
			}
		}
		return $array;
	}

	/**
	 * Saves log in WooCommerce
	 *
	 * @param string $action Action to save.
	 * @param array  $source_data Source data.
	 * @param array  $result Result of action.
	 *
	 * @return void
	 */
	public static function save_log( $action, $source_data, $result ) {
		$logger      = wc_get_logger();
		$source_data = is_array( $source_data ) ? $source_data : array( $source_data );
		$result      = is_array( $result ) ? $result : array( $result );
		$message     = $action . ': ' . wp_json_encode( $source_data );
		$message_res = 'result: ' . wp_json_encode( $result );
		$logger->debug( $message, array( 'source' => 'connect_ecommerce' ) );
		$logger->debug( $message_res, array( 'source' => 'connect_ecommerce' ) );
	}

	/**
	 * Creates the table
	 *
	 * @since  1.0
	 * @access private
	 * @param string $table_name Name of table.
	 * @return void
	 */
	public static function create_sync_table( $table_name ) {
		global $wpdb;
		$charset_collate = $wpdb->get_charset_collate();

		// DB Tasks.
		$sql = "CREATE TABLE $table_name (
				prod_id varchar(100) NOT NULL,
				synced boolean,
						UNIQUE KEY prod_id (prod_id)
				) $charset_collate;";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql );
	}

	/**
	 * Check if table sync exists
	 *
	 * @param string $table_name Name of table.
	 * @return void
	 */
	public static function check_table_sync( $table_name ) {
		if ( empty( $table_name ) ) {
			return;
		}
		global $wpdb;
		$check_table = $wpdb->get_var( "SHOW TABLES LIKE '$table_name'" );

		if ( $check_table !== $table_name ) {
			self::create_sync_table( $table_name );
		}
	}

	/**
	 * Time to text
	 *
	 * @param float $time_start Start time.
	 * @return string
	 */
	public static function time_total_text( $time_start ) {
		$time_end = microtime( true );

		$execution_time = round( $time_end - $time_start, 2 );
		$end = "seg";

		if ( $execution_time > 3600 ) {
			$execution_time = round( $execution_time / 3600, 2 );
			$end = "horas";
		} elseif ( $execution_time > 60 ) {
			$execution_time = round( $execution_time / 60, 2 );
			$end = "min";
		}
		return $execution_time . ' ' . $end;
	}

	/**
	 * Move settings from old plugin to new plugin
	 *
	 * @return void
	 */
	public static function move_settings() {
		$old_settings = get_option( 'wces_settings' );

		if ( empty( $old_settings ) ) {
			return;
		}
		$new_settings = array();
		foreach ( $old_settings as $key => $value ) {
			$new_settings[ $key ] = $value;
		}
		update_option( 'connect_ecommerce_public', $new_settings );
		delete_option( 'wces_settings' );
	}

	/**
	 * Get connector of plugin.
	 *
	 * @param array $options Options of plugin.
	 * @return array
	 */
	public static function get_connector( $options ) {
		$connector                 = array();
		$connector['settings_all'] = get_option( 'connect_ecommerce' );
		$connector['connector']    = isset( $connector['settings_all']['connector'] ) ? $connector['settings_all']['connector'] : '';
		$connector['settings']     = $connector['settings_all'][ $connector['connector'] ] ?? array();
		$connector['all_options']  = $options;

		// Defensive fallback for sites that haven't re-saved settings since upgrading.
		// Connector add-ons (e.g. connect-woocommerce-neo) read `tax_option` straight
		// from get_option( 'connect_ecommerce' ) in their own constructor rather than
		// from this resolved array, so the fallback must be persisted back to the
		// option itself — not just applied to this in-memory copy — or those add-ons
		// keep seeing the raw/missing value.
		$tax_option_pref = self::get_tax_option_pref( $connector['settings'] );
		$tax_option      = self::resolve_tax_option( $tax_option_pref );
		if ( ( $connector['settings']['tax_option_pref'] ?? null ) !== $tax_option_pref
			|| ( $connector['settings']['tax_option'] ?? null ) !== $tax_option
		) {
			$connector['settings']['tax_option_pref'] = $tax_option_pref;
			$connector['settings']['tax_option']      = $tax_option;
			if ( ! empty( $connector['connector'] ) ) {
				$connector['settings_all'][ $connector['connector'] ]['tax_option_pref'] = $tax_option_pref;
				$connector['settings_all'][ $connector['connector'] ]['tax_option']      = $tax_option;
				update_option( 'connect_ecommerce', $connector['settings_all'] );
			}
		}

		$connector['settings']['prod_mergevars'] = get_option( 'connect_ecommerce_prod_mergevars' )['prod_mergevars'] ?? array();

		// Initialize payment method mappings.
		$connector['settings']['payment_methods']   = array();
		$connector['settings']['treasury_accounts'] = array();

		if ( ! empty( $connector['connector'] ) ) {
			if ( ! isset( $options[ $connector['connector'] ] ) ) {
				return $connector;
			}

			$connector['options']    = $options[ $connector['connector'] ];
			$payment_methods_enabled = ! array_key_exists( 'payment_methods', $connector['options'] ) || ! empty( $connector['options']['payment_methods'] );
			if ( $payment_methods_enabled ) {
				$payment_mappings                           = PAYMENTS::get_payment_method_mappings( $connector['connector'] );
				$connector['settings']['payment_methods']   = $payment_mappings['payment_methods'];
				$connector['settings']['treasury_accounts'] = $payment_mappings['treasury_accounts'];
			}

			if ( empty( $connector['options']['name'] ) ) {
				$connector['settings_all']['connector'] = '';
				update_option( 'connect_ecommerce', $connector['settings_all'] );
				return $connector;
			}
			$apiname = 'Connect_Ecommerce_' . $connector['options']['name'];

			if ( ! class_exists( $apiname ) ) {
				return $connector;
			}
			$connector['connapi_erp']        = new $apiname( $options );
			$connector['is_mergevars']       = self::connector_supports( $connector['connapi_erp'], 'get_product_attributes' );
			$connector['is_disabled_orders'] = isset( $connector['options']['disable_modules'] ) && in_array( 'order', $connector['options']['disable_modules'], true ) ? true : false;
			$connector['is_disabled_ai']     = isset( $connector['options']['disable_modules'] ) && in_array( 'ai', $connector['options']['disable_modules'], true ) ? true : false;
		}

		return $connector;
	}

	/**
	 * Checks whether a connector implements an optional capability.
	 *
	 * Legacy connectors keep their method-based capability detection. Connectors
	 * using the shared contract must override an optional method to enable it.
	 *
	 * @param object $connector Connector API instance.
	 * @param string $method Connector method name.
	 * @return bool
	 */
	public static function connector_supports( $connector, $method ) {
		if ( ! is_object( $connector ) || ! method_exists( $connector, $method ) ) {
			return false;
		}

		if ( $connector instanceof CONECOM_Abstract_Connector_API ) {
			return $connector->supports_capability( $method );
		}

		return true;
	}

	/**
	 * Determine the "Get prices with Tax?" preference from a connector settings array,
	 * migrating the pre-"Default" `tax_option` value and the short-lived 3.3.4 `tax_price` key.
	 *
	 * @param array $settings Connector settings array, as stored.
	 * @return string 'default', 'yes' or 'no'.
	 */
	public static function get_tax_option_pref( $settings ) {
		if ( isset( $settings['tax_option_pref'] ) ) {
			return $settings['tax_option_pref'];
		}
		if ( isset( $settings['tax_option'] ) ) {
			return $settings['tax_option'];
		}
		if ( isset( $settings['tax_price'] ) ) {
			return $settings['tax_price'];
		}
		return 'default';
	}

	/**
	 * Resolve a "Get prices with Tax?" preference to a concrete 'yes'/'no', following
	 * WooCommerce's own "Prices entered with tax" setting when the preference is "default".
	 *
	 * @param string $preference 'default', 'yes' or 'no'.
	 * @return string 'yes' or 'no'.
	 */
	public static function resolve_tax_option( $preference ) {
		if ( 'yes' === $preference || 'no' === $preference ) {
			return $preference;
		}
		return 'yes' === get_option( 'woocommerce_prices_include_tax' ) ? 'yes' : 'no';
	}

	/**
	 * Keep the "Get prices with Tax?" setting in sync with WooCommerce's own tax setting
	 * whenever the active connector's preference is "Default". Hooked on
	 * `update_option_woocommerce_prices_include_tax` so connector add-ons that read
	 * `tax_option` straight from the `connect_ecommerce` option always get 'yes'/'no',
	 * even without the plugin's settings form being re-saved.
	 *
	 * @return void
	 */
	public static function sync_tax_option_with_woocommerce() {
		$settings_all = get_option( 'connect_ecommerce' );
		$connector    = is_array( $settings_all ) && isset( $settings_all['connector'] ) ? $settings_all['connector'] : '';

		if ( empty( $connector ) || empty( $settings_all[ $connector ] ) ) {
			return;
		}

		$tax_option_pref = self::get_tax_option_pref( $settings_all[ $connector ] );
		if ( 'default' !== $tax_option_pref ) {
			return;
		}

		$settings_all[ $connector ]['tax_option_pref'] = $tax_option_pref;
		$settings_all[ $connector ]['tax_option']      = self::resolve_tax_option( $tax_option_pref );
		update_option( 'connect_ecommerce', $settings_all );
	}
}

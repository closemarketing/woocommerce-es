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

		// @phpstan-ignore-next-line
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
}

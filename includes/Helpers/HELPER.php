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

defined( 'ABSPATH' ) || exit;

/**
 * Sync Products.
 *
 * @since 1.0.0
 */
class HELPER {
	/**
	 * Default workflows supported by the plugin.
	 *
	 * @var array
	 */
	private static $default_workflows = array( 'products', 'orders' );

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
	 * Retrieves the list of workflows supported by connectors.
	 *
	 * @return array
	 */
	public static function get_workflows() {
		return self::$default_workflows;
	}

	/**
	 * Whether a connector is active and has a given workflow enabled.
	 *
	 * Mirrors the filtering used by the connector selectors in the admin UI
	 * (Widget_Product/Widget_Order/Settings::page_get_sync), so that sync
	 * entry points (AJAX handlers, cron) enforce the same rule: a connector
	 * that is inactive, or that has a workflow disabled, must not run that
	 * workflow's sync even if a connector_id is passed explicitly.
	 *
	 * @param array  $connector_meta Connector meta (the 'meta' key of a get_connectors() item, or a connectors_meta entry).
	 * @param string $workflow       Workflow to check: 'products' or 'orders'.
	 * @return bool
	 */
	public static function is_workflow_enabled_for_connector( $connector_meta, $workflow ) {
		if ( ! is_array( $connector_meta ) ) {
			return false;
		}
		if ( 'active' !== ( $connector_meta['status'] ?? 'active' ) ) {
			return false;
		}
		return 'yes' === ( $connector_meta['workflows'][ $workflow ] ?? 'yes' );
	}

	/**
	 * Get connector of plugin (legacy helper).
	 *
	 * @param array $options Options of plugin.
	 * @return array
	 */
	public static function get_connector( $options ) {
		$connectors_data = self::get_connectors( $options );
		$connectors      = $connectors_data['items'];

		if ( empty( $connectors ) ) {
			return array(
				'id'           => '',
				'connector'    => '',
				'settings_all' => $connectors_data['settings_all'],
				'settings'     => array(
					'prod_mergevars'    => get_option( 'connect_ecommerce_prod_mergevars' )['prod_mergevars'] ?? array(),
					'payment_methods'   => array(),
					'treasury_accounts' => array(),
				),
				'all_options'  => $options,
			);
		}

		$active_id = $connectors_data['active'];
		if ( empty( $active_id ) || ! isset( $connectors[ $active_id ] ) ) {
			$active_id = array_key_first( $connectors );
		}

		return $connectors[ $active_id ];
	}

	/**
	 * Returns a specific connector by ID.
	 *
	 * @param string $connector_id Connector ID.
	 * @param array  $options Connector definitions.
	 * @return array|null Connector data or null if not found.
	 */
	public static function get_connector_by_id( $connector_id, $options ) {
		$connectors_data = self::get_connectors( $options );
		$connectors      = $connectors_data['items'];

		if ( ! isset( $connectors[ $connector_id ] ) ) {
			return null;
		}

		return $connectors[ $connector_id ];
	}

	/**
	 * Returns every connector context configured in the site.
	 *
	 * @param array $options Connector definitions.
	 * @return array
	 */
	public static function get_connectors( $options ) {
		$settings_all = get_option( 'connect_ecommerce', array() );
		$normalized   = self::normalize_connectors_structure( $settings_all, $options );

		if ( $normalized['dirty'] ) {
			update_option( 'connect_ecommerce', $normalized['settings_all'] );
		}

		$connectors    = array();
		$meta          = $normalized['connectors_meta'];
		$active        = $normalized['active_connector'];
		$prod_mergevar = get_option( 'connect_ecommerce_prod_mergevars' )['prod_mergevars'] ?? array();

		foreach ( $meta as $connector_id => $connector_meta ) {
			$connectors[ $connector_id ] = self::build_connector_context(
				$connector_id,
				$connector_meta,
				$options,
				$normalized['settings_all'],
				$prod_mergevar
			);
		}

		return array(
			'items'        => $connectors,
			'active'       => $active,
			'settings_all' => $normalized['settings_all'],
			'meta'         => $meta,
		);
	}

	/**
	 * Normalize connectors setup to the new structure.
	 *
	 * @param array $settings_all Raw option value.
	 * @param array $options Connector definitions.
	 * @return array
	 */
	private static function normalize_connectors_structure( $settings_all, $options ) {
		$settings_all   = is_array( $settings_all ) ? $settings_all : array();
		$connectors_meta = $settings_all['connectors_meta'] ?? array();
		$dirty           = false;

		if ( empty( $connectors_meta ) ) {
			$legacy_connector = isset( $settings_all['connector'] ) ? $settings_all['connector'] : '';
			if ( $legacy_connector && isset( $settings_all[ $legacy_connector ] ) ) {
				$connectors_meta[ $legacy_connector ] = array(
					'type'      => $legacy_connector,
					'label'     => $options[ $legacy_connector ]['name'] ?? ucfirst( $legacy_connector ),
					'workflows' => array(
						'products' => 'yes',
						'orders'   => 'yes',
					),
					'status'    => 'active',
				);
				$dirty = true;
			}
		}

		// Ensure every connector meta entry has defaults.
		foreach ( $connectors_meta as $connector_id => $meta ) {
			$new_meta = array(
				'type'      => $meta['type'] ?? $connector_id,
				'label'     => $meta['label'] ?? ( $options[ $meta['type'] ?? $connector_id ]['name'] ?? $connector_id ),
				'workflows' => $meta['workflows'] ?? array(),
				'status'    => $meta['status'] ?? 'active',
			);

			foreach ( self::$default_workflows as $workflow ) {
				if ( ! isset( $new_meta['workflows'][ $workflow ] ) ) {
					$new_meta['workflows'][ $workflow ] = 'yes';
				}
			}

			if ( $new_meta !== $meta ) {
				$connectors_meta[ $connector_id ] = $new_meta;
				$dirty                            = true;
			}
		}

		$settings_all['connectors_meta'] = $connectors_meta;

		if ( empty( $settings_all['connector'] ) && ! empty( $connectors_meta ) ) {
			$settings_all['connector'] = array_key_first( $connectors_meta );
			$dirty                     = true;
		}

		return array(
			'settings_all'    => $settings_all,
			'connectors_meta' => $connectors_meta,
			'active_connector'=> $settings_all['connector'] ?? '',
			'dirty'           => $dirty,
		);
	}

	/**
	 * Build connector context consumed across the plugin.
	 *
	 * @param string $connector_id Connector identifier.
	 * @param array  $meta         Metadata for connector.
	 * @param array  $options      Connector definitions.
	 * @param array  $settings_all Raw settings option.
	 * @param array  $prod_mergevar Merge vars settings.
	 * @return array
	 */
	private static function build_connector_context( $connector_id, $meta, $options, $settings_all, $prod_mergevar ) {
		$connector_type   = $meta['type'] ?? $connector_id;
		$connector_options = $options[ $connector_type ] ?? array();
		$connector         = array(
			'id'            => $connector_id,
			'connector'     => $connector_type,
			'meta'          => $meta,
			'settings_all'  => $settings_all,
			'settings'      => $settings_all[ $connector_id ] ?? array(),
			'all_options'   => $options,
		);

		$connector['settings']['prod_mergevars']    = $prod_mergevar;
		$connector['settings']['payment_methods']   = array();
		$connector['settings']['treasury_accounts'] = array();
		$connector['is_disabled_orders']            = false;
		$connector['is_disabled_ai']                = false;
		$connector['is_mergevars']                  = false;

		$payment_mappings = PAYMENTS::get_payment_method_mappings( $connector_id, $connector_type );
		$connector['settings']['payment_methods']   = $payment_mappings['payment_methods'];
		$connector['settings']['treasury_accounts'] = $payment_mappings['treasury_accounts'];

		if ( empty( $connector_options ) || empty( $connector_options['name'] ) ) {
			$connector['actions'] = array(
				'sync_products' => self::get_connector_action_name( 'connect_ecommerce_sync_products', $connector_id ),
				'sync_orders'   => self::get_connector_action_name( 'connect_ecommerce_sync_orders', $connector_id ),
				'single_order'  => self::get_connector_action_name( 'sync_erp_order', $connector_id ),
			);
			return $connector;
		}

		$connector['options']          = $connector_options;
		$apiname                       = 'Connect_Ecommerce_' . $connector_options['name'];
		$connector['is_disabled_orders'] = isset( $connector_options['disable_modules'] ) && in_array( 'order', $connector_options['disable_modules'], true );
		$connector['is_disabled_ai']     = isset( $connector_options['disable_modules'] ) && in_array( 'ai', $connector_options['disable_modules'], true );

		if ( class_exists( $apiname ) ) {
			$connector['connapi_erp']  = new $apiname( $options, $connector_id );
			$connector['is_mergevars'] = method_exists( $connector['connapi_erp'], 'get_product_attributes' );
		}

		$connector['actions'] = array(
			'sync_products' => self::get_connector_action_name( 'connect_ecommerce_sync_products', $connector_id ),
			'sync_orders'   => self::get_connector_action_name( 'connect_ecommerce_sync_orders', $connector_id ),
			'single_order'  => self::get_connector_action_name( 'sync_erp_order', $connector_id ),
		);

		return $connector;
	}

	/**
	 * Generates a sanitized action name for a connector.
	 *
	 * @param string $base_action Base action name.
	 * @param string $connector_id Connector identifier.
	 * @return string
	 */
	public static function get_connector_action_name( $base_action, $connector_id ) {
		$connector_id = sanitize_key( $connector_id );
		if ( empty( $connector_id ) ) {
			return sanitize_key( $base_action );
		}
		return sanitize_key( $base_action . '_' . $connector_id );
	}
}

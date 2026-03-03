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

use CLOSE\ConnectEcommerce\Helpers\PROD;

/**
 * Sync Products.
 *
 * @since 1.0.0
 */
class CRON {
	/**
	 * Save in options errors founded.
	 *
	 * @param array  $errors Errors sync.
	 * @param string $option_prefix Prefix of options.
	 *
	 * @return void
	 */
	public static function save_sync_errors( $errors, $option_prefix ) {
		$option_errors = get_option( $option_prefix . '_sync_errors' );
		$save_errors[] = $errors;
		if ( false !== $option_errors && ! empty( $option_errors ) ) {
			$save_errors = array_merge( $save_errors, $option_errors );
		}
		update_option( $option_prefix . '_sync_errors', $save_errors );
	}

	/**
	 * Fills table to sync
	 *
	 * @param array  $settings Settings of plugin.
	 * @param string $table_sync Table name.
	 * @param object $api_erp API Object.
	 * @param string $option_prefix Prefix of options.
	 *
	 * @return boolean
	 */
	public static function fill_table_sync( $settings, $options, $api_erp ) {
		global $wpdb;
		$table_sync = isset( $options['table_sync'] ) ? $options['table_sync'] : '';
		if ( empty( $table_sync ) ) {
			return false;
		}
		$wpdb->query( "TRUNCATE TABLE $table_sync;" );

		// Get ALL products from API.
		$products       = array();
		$api_pagination = ! empty( $options['api_pagination'] ) ? (int) $options['api_pagination'] : false;
		if ( $api_pagination ) {
			$sync_loop = 0;

			do {
				$loop_page    = $sync_loop * $api_pagination;
				$api_products = $api_erp->get_products( null, $loop_page );
				if ( empty( $api_products ) ) {
					break;
				}
				$sync_loop++;
				$count_products = count( $api_products );
				$products       = array_merge( $products, $api_products );
			} while ( $count_products === $api_pagination );

		} else {
			$products = $api_erp->get_products();
		}
			
		if ( ! is_array( $products ) ) {
			return false;
		}

		update_option( 'conecom_total_api_products', count( $products ) );
		update_option( 'conecom_sync_start_time', strtotime( 'now' ) );
		update_option( 'conecom_sync_errors', array() );

		foreach ( $products as $product ) {
			$is_filtered_product = ! empty( $product['tags'] ) ? PROD::filter_product( $settings, $product['tags'] ) : false;

			if ( ! $is_filtered_product ) {
				$db_values = array(
					'prod_id' => $product['id'],
					'synced'  => false,
				);
				if ( ! self::check_exist_valuedb( $table_sync, $product['id'] ) ) {
					$wpdb->insert(
						$table_sync,
						$db_values
					);
				}
			}
		}
		return true;
	}

	/**
	 * Get products to sync
	 *
	 * @param array  $settings Settings of plugin.
	 * @param string $options Options of plugin.
	 * @param object $connapi_erp API Object.
	 *
	 * @return array results;
	 */
	public static function get_products_sync( $settings, $options, $connapi_erp ) {
		// Method with modified products.
		if ( empty( $options['table_sync'] ) ) {
			$period              = self::get_active_period( $settings );
			$modified_since_date = isset( $period['interval'] ) ? strtotime( '-' . $period['interval'] . ' seconds' ) : strtotime( '-1 day' );
			if ( ! method_exists( $connapi_erp, 'get_products_ids_since' ) ) {
				return false;
			}
			return $connapi_erp->get_products_ids_since( $modified_since_date );
		}
		// Method with table sync.
		global $wpdb;
		$limit = isset( $settings['sync_num'] ) ? (int) $settings['sync_num'] : 50;

		$results = $wpdb->get_results(
			$wpdb->prepare(
				'SELECT prod_id FROM %i WHERE synced = 0 LIMIT %d',
				$options['table_sync'],
				$limit
			),
			ARRAY_A
		);

		if ( ! empty( $results ) ) {
			return $results;
		}

		return false;
	}

	/**
	 * Checks if the value already exists in db
	 *
	 * @param  string $table_sync Table name.
	 * @param  string $gid Task ID.
	 *
	 * @return boolean Exist the value
	 */
	public static function check_exist_valuedb( $table_sync, $gid ) {
		global $wpdb;
		if ( empty( $gid ) ) {
			return false;
		}
		$results = $wpdb->get_row(
			$wpdb->prepare( 'SELECT prod_id FROM %i WHERE prod_id = %s', $table_sync, $gid )
		);

		if ( $results ) {
			return true;
		} else {
			return false;
		}
	}

	/**
	 * Saves synced products
	 *
	 * @param string $table_sync Table name.
	 * @param string $product_id Product ID that synced.
	 * @param string $option_prefix Prefix of options.
	 *
	 * @return void
	 */
	public static function save_product_sync( $table_sync, $product_id, $option_prefix ) {
		global $wpdb;
		$db_values = array(
			'prod_id' => $product_id,
			'synced'  => true,
		);
		$update    = $wpdb->update(
			$table_sync,
			$db_values,
			array(
				'prod_id' => $product_id,
			)
		);
		if ( ! $update && $wpdb->last_error ) {
			self::save_sync_errors(
				array(
					'Import Product Sync Error',
					'Product ID:' . $product_id,
					'DB error:' . $wpdb->last_error,
				),
				$option_prefix
			);

			// Logs in WooCommerce.
			$logger = new \WC_Logger();
			$logger->debug(
				'Import Product Sync Error Product ID:' . $product_id . 'DB error:' . $wpdb->last_error,
				array(
					'source' => $option_prefix,
				)
			);
		}
	}

	/**
	 * Sends an email when is finished the sync
	 *
	 * @param array  $settings Settings of plugin.
	 * @param string $table_sync Table name.
	 * @param string $option_name Name of plugin.
	 * @param string $option_prefix Prefix of options.
	 *
	 * @return void
	 */
	public static function send_sync_ended_products( $settings, $table_sync, $option_name, $option_prefix ) {
		global $wpdb;
		$send_email  = isset( $settings['sync_email'] ) ? strval( $settings['sync_email'] ) : 'yes';
		$total_count = $wpdb->get_var( "SELECT COUNT(*) FROM $table_sync WHERE synced = 1" );

		if ( $total_count > 0 && 'yes' === $send_email ) {
			$subject = __( 'All products synced with ', 'woocommerce-es' ) . $option_name;
			$headers = array( 'Content-Type: text/html; charset=UTF-8' );
			$body    = '<h2>' . __( 'All products synced with ', 'woocommerce-es' ) . $option_name . '</h2> ';
			$body   .= '<br/><strong>' . __( 'Total products:', 'woocommerce-es' ) . '</strong> ';
			$body   .= $total_count;

			$total_api_products = (int) get_option( $option_prefix . '_total_api_products' );
			if ( $total_api_products || $total_count !== $total_api_products ) {
				$body .= ' ' . esc_html__( 'filtered', 'woocommerce-es' );
				$body .= ' ( ' . $total_api_products . ' ' . esc_html__( 'total', 'woocommerce-es' ) . ' )';
			}

			$body .= '<br/><strong>' . __( 'Time:', 'woocommerce-es' ) . '</strong> ';
			$body .= date_i18n( 'Y-m-d H:i', current_time( 'timestamp' ) );

			$start_time = get_option( $option_prefix . '_sync_start_time' );
			if ( $start_time ) {
				$body .= '<br/><strong>' . __( 'Total Time:', 'woocommerce-es' ) . '</strong> ';
				$body .= round( ( strtotime( 'now' ) - $start_time ) / 60 / 60, 1 );
				$body .= 'h';
			}

			$products_errors = get_option( $option_prefix . '_sync_errors' );
			if ( false !== $products_errors && ! empty( $products_errors ) ) {
				$body .= '<h2>' . __( 'Errors founded', 'woocommerce-es' ) . '</h2>';

				foreach ( $products_errors as $error ) {
					$body .= '<br/><strong>' . $error['error'] . '</strong>';
					$body .= '<br/><strong>' . __( 'Product id: ', 'woocommerce-es' ) . '</strong>' . $error['id'];

					if ( 'Holded' === $option_name ) {
						$body .= ' <a href="https://app.holded.com/products/' . $error['id'] . '">' . __( 'View in Holded', 'woocommerce-es' ) . '</a>';
					}
					$body .= '<br/><strong>' . __( 'Product name: ', 'woocommerce-es' ) . '</strong>' . $error['name'];
					$body .= '<br/><strong>' . __( 'Product sku: ', 'woocommerce-es' ) . '</strong>' . $error['sku'];
					$body .= '<br/>';
				}
			}
			wp_mail( get_option( 'admin_email' ), $subject, $body, $headers );
		}
	}

	/**
	 * Returns the active period for sync.
	 *
	 * @param array $settings|value Settings of plugin or period.
	 *
	 * @return array|false
	 */
	public static function get_active_period( $sync_period ) {
		$sync_period = is_array( $sync_period ) ? $sync_period['sync'] : $sync_period;

		if ( 'no' === $sync_period ) {
			return false;
		}
		$periods     = self::get_cron_periods();
		$pos         = array_search( $sync_period, array_column( $periods, 'cron' ), true );

		return false !== $pos ? $periods[ $pos ] : end( $periods );
	}

	/**
	 * Returns the cron periods.
	 *
	 * @return array
	 */
	public static function get_cron_periods() {
		return array(
			array(
				'key'      => 'every_five_minutes',
				'interval' => 300,
				'display'  => __( 'Every 5 minutes', 'woocommerce-es' ),
				'cron'     => 'conecom_sync_five_minutes',
			),
			array(
				'key'      => 'every_fifteen_minutes',
				'interval' => 900,
				'display'  => __( 'Every 15 minutes', 'woocommerce-es' ),
				'cron'     => 'conecom_sync_fifteen_minutes',
			),
			array(
				'key'      => 'every_thirty_minutes',
				'interval' => 1800,
				'display'  => __( 'Every 30 Minutes', 'woocommerce-es' ),
				'cron'     => 'conecom_sync_thirty_minutes',
			),
			array(
				'key'      => 'every_one_hour',
				'interval' => 3600,
				'display'  => __( 'Every 1 Hour', 'woocommerce-es' ),
				'cron'     => 'conecom_sync_one_hour',
			),
			array(
				'key'      => 'every_three_hours',
				'interval' => 10800,
				'display'  => __( 'Every 3 Hours', 'woocommerce-es' ),
				'cron'     => 'conecom_sync_three_hours',
			),
			array(
				'key'      => 'every_six_hours',
				'interval' => 21600,
				'display'  => __( 'Every 6 Hours', 'woocommerce-es' ),
				'cron'     => 'conecom_sync_six_hours',
			),
			array(
				'key'      => 'every_twelve_hours',
				'interval' => 43200,
				'display'  => __( 'Every 12 Hours', 'woocommerce-es' ),
				'cron'     => 'conecom_sync_twelve_hours',
			),
		);
	}

	/**
	 * Returns recent Action Scheduler runs for conecom_sync_* hooks.
	 *
	 * @return array { status: 'success'|'error', actions?: array, message?: string }
	 */
	public static function get_sync_logs() {
		global $wpdb;
		$actions_table = $wpdb->prefix . 'actionscheduler_actions';
		$logs_table    = $wpdb->prefix . 'actionscheduler_logs';

		if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $actions_table ) ) !== $actions_table ) {
			return array(
				'status'  => 'error',
				'message' => __( 'Action Scheduler is not active.', 'woocommerce-es' ),
			);
		}

		$hook_labels = array();
		foreach ( self::get_cron_periods() as $period ) {
			$hook_labels[ $period['cron'] ] = $period['display'];
		}

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name from prefix.
		$action_rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT id, hook, status, scheduled_date_gmt, last_attempt_gmt
				 FROM {$actions_table}
				 WHERE hook LIKE %s
				 ORDER BY scheduled_date_gmt DESC
				 LIMIT 25",
				'conecom_sync_%'
			),
			ARRAY_A
		);

		if ( empty( $action_rows ) ) {
			return array(
				'status'  => 'success',
				'actions' => array(),
			);
		}

		$action_ids = implode( ',', array_map( 'intval', array_column( $action_rows, 'id' ) ) );
		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- Table names from prefix, IDs intval'd.
		$log_rows = $wpdb->get_results(
			"SELECT action_id, message, log_date_gmt
			 FROM {$logs_table}
			 WHERE action_id IN ({$action_ids})
			 ORDER BY log_date_gmt ASC",
			ARRAY_A
		);

		$logs_by_action = array();
		foreach ( $log_rows as $log ) {
			$logs_by_action[ $log['action_id'] ][] = array(
				'message' => $log['message'],
				'date'    => get_date_from_gmt( $log['log_date_gmt'], get_option( 'date_format' ) . ' ' . get_option( 'time_format' ) ),
			);
		}

		$actions = array();
		foreach ( $action_rows as $row ) {
			$actions[] = array(
				'id'             => $row['id'],
				'hook'           => $row['hook'],
				'hook_label'     => $hook_labels[ $row['hook'] ] ?? $row['hook'],
				'status'         => $row['status'],
				'scheduled_date' => get_date_from_gmt( $row['scheduled_date_gmt'], get_option( 'date_format' ) . ' ' . get_option( 'time_format' ) ),
				'last_attempt'   => ! empty( $row['last_attempt_gmt'] ) ? get_date_from_gmt( $row['last_attempt_gmt'], get_option( 'date_format' ) . ' ' . get_option( 'time_format' ) ) : '',
				'logs'           => $logs_by_action[ $row['id'] ] ?? array(),
			);
		}

		return array(
			'status'  => 'success',
			'actions' => $actions,
		);
	}
}

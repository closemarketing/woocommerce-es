<?php
/**
 * Library for importing products
 *
 * @package    WordPress
 * @author     David Perez <david@closemarketing.es>
 * @copyright  2019 Closemarketing
 * @version    1.0
 */

defined( 'ABSPATH' ) || exit;

use CLOSE\ConnectEcommerce\Helpers\PROD;
use CLOSE\ConnectEcommerce\Helpers\HELPER;

/**
 * WPCLI Command.
 *
 * @since 3.1.0
 * @package CLOSE\ConnectEcommerce\Admin
 */
class Import_Products_Command {
	/**
	 * Import products.
	 *
	 * Command: wp conecom products --update --ai=none,new,all
	 *
	 * @param array $args array values.
	 * @param array $assoc_args array values.
	 * @return void
	 */
	public function products( $args, $assoc_args ) {
		$assoc_args = wp_parse_args(
			$assoc_args,
			array(
				'update' => false,
				'ai'     => 'none',
			)
		);
		$conecom_options = conecom_get_options();
		$connector_data  = HELPER::get_connector( $conecom_options );
		$connector       = $connector_data['connector'] ?? '';
		$settings        = $connector_data['settings'] ?? array();
		$time_start      = microtime( true );

		if ( empty( $connector ) ) {
			WP_CLI::line( $this->cli_header_line() . __( 'There is no connector actived' ) );
			return;
		}
		if ( empty( $connector_data['options'] ) || empty( $connector_data['connapi_erp'] ) ) {
			WP_CLI::line( $this->cli_header_line() . __( 'Connector configuration is not complete', 'woocommerce-es' ) );
			return;
		}
		$subject = sprintf(
			/* translators: %s connector name. */
			__( 'Connect Ecommerce: Importing products from %s', 'woocommerce-es' ),
			$connector
		);
		WP_CLI::line( $this->cli_header_line() . $subject );

		$options        = $connector_data['options'];
		$connapi_erp    = $connector_data['connapi_erp'];
		$api_pagination  = defined( 'CONECOM_SYNC_PRODUCTS_PER_BATCH' ) ? CONECOM_SYNC_PRODUCTS_PER_BATCH : 50;
		$api_is_paginated = ! array_key_exists( 'product_api_pagination', $options ) || ! empty( $options['product_api_pagination'] );
		$generate_ai     = $assoc_args['ai'] ?? 'none';

		// Loop Products.
		$sync_loop       = 0;
		$continue        = false;
		$page            = 0;
		$synced_products = 0;
		do {
			$message = sprintf(
				__( 'Fetching %s products from %s', 'woocommerce-es' ),
				$api_pagination,
				$connector
			);
			WP_CLI::line( $this->cli_header_line() . $message );

			// Get products from API.
			$api_products = $connapi_erp->get_products( null, $api_is_paginated ? $sync_loop : null );
			$res_status   = $api_products['status'] ?? 'ok';

			if ( 'error' === $res_status ) {
				WP_CLI::line( $this->cli_header_line() . __( 'We couldn\'t connect to the API. Error: ', 'woocommerce-es' ) . $api_products['message'] );
				WP_CLI::line( $this->cli_header_line() . __( 'Please check your connection settings.', 'woocommerce-es' ) );
				break;
			}

			$products_count = count( $api_products );
			foreach ( $api_products as $key => $item ) {
				$item        = HELPER::sanitize_array_recursive( $item );
				$page        = intval( $sync_loop / $api_pagination, 0 );
				$result_sync = PROD::sync_product_item( $settings, $item, $connapi_erp, $generate_ai );

				$sync_loop   = $page * $api_pagination + $key;
				$message = '[' . $sync_loop + 1 . '/' . $page . '] ';
				$message .= $result_sync['status'] . ' ';
				$message .= wp_strip_all_tags($result_sync['message']);
				$message .= ! empty( $result_sync['post_id'] ) ? ' POSTID: ' . $result_sync['post_id'] : '';
				WP_CLI::line( $this->cli_header_line() . $message );

				if ( ! empty( $result_sync['post_id'] ) ) {
					$synced_products++;
				}

				++$sync_loop;
			}

			$continue = $api_is_paginated && $products_count >= $api_pagination;

		} while ( $continue );

		// Resume.
		$message = sprintf(
			__( 'Products imported: %s / %s . Total time: %s', 'woocommerce-es' ),
			$synced_products,
			$sync_loop,
			HELPER::time_total_text( $time_start )
		);
		WP_CLI::line( $this->cli_header_line() . $message );
	}

	/**
	 * Prints the header line for CLI output.
	 *
	 * @return string
	 */
	private function cli_header_line() {
		return '[' . gmdate( 'H:i:s' ) . '] ';
	}
}

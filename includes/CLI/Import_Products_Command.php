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
		$settings_all = get_option( 'connect_ecommerce' );
		$connector    = isset( $settings_all['connector'] ) ? $settings_all['connector'] : '';
		$settings     = $settings_all[ $connector ] ?? array();
		$time_start   = microtime( true );

		if ( empty( $connector ) ) {
			WP_CLI::line( $this->cli_header_line() . __( 'There is no connector actived' ) );
			return;
		}
		$subject = sprintf(
			__( 'Connect Ecommerce: Importing products from %s' ),
			$connector
		);
		WP_CLI::line( $this->cli_header_line() . $subject );

		$conecom_options = conecom_get_options();
		$options         = $conecom_options[ $connector ];
		$apiname         = 'Connect_Ecommerce_' . $options['name'];
		$connapi_erp     = new $apiname( $options );
		$api_pagination  = ! empty( $options['api_pagination'] ) ? $options['api_pagination'] : false;
		$generate_ai     = $assoc_args['ai'] ?? 'none';

		// Loop Products.
		$sync_loop       = 0;
		$continue        = false;
		$page            = 0;
		$synced_products = 0;
		do {
			$message = sprintf(
				__( 'Fetching %s products from %s', 'connect-ecommerce' ),
				$api_pagination,
				$connector
			);
			WP_CLI::line( $this->cli_header_line() . $message );

			// Get products from API.
			$api_products = $connapi_erp->get_products( null, $sync_loop );

			if ( 'error' === $api_products['status'] ) {
				WP_CLI::line( $this->cli_header_line() . __( 'We couldn\'t connect to the API. Error: ', 'connect-ecommerce' ) . $api_products['message'] );
				WP_CLI::line( $this->cli_header_line() . __( 'Please check your connection settings.', 'connect-ecommerce' ) );
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

			$continue = $products_count < $api_pagination ? false : true;

		} while ( $continue );

		// Resume.
		$message = sprintf(
			__( 'Products imported: %s / %s . Total time: %s', 'connect-ecommerce' ),
			$synced_products,
			$sync_loop,
			HELPER::time_total_text( $time_start )
		);
		WP_CLI::line( $this->cli_header_line() . $message );
	}

	/**
	 * Prints the header line for CLI output.
	 *
	 * @return void
	 */
	private function cli_header_line() {
		return '[' . gmdate( 'H:i:s' ) . '] ';
	}
}

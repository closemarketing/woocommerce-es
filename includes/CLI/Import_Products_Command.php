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
use CLOSE\ConnectEcommerce\Helpers\CRON;

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
		$sync_loop = 0;
		do {
			$api_products   = $connapi_erp->get_products( null, $sync_loop );
			$api_products   = HELPER::sanitize_array_recursive( $api_products );
			$products_count = count( $api_products );
			$page           = intval( $sync_loop / $api_pagination, 0 );
			$item           = $api_products[ $sync_loop - ( $api_pagination * $page ) ];

			$result_sync = PROD::sync_product_item( $settings, $item, $connapi_erp, $options['slug'], $generate_ai );
		

		} while ( $sync_loop < count( $api_products ) );
	}

	private function cli_header_line() {
		return '[' . gmdate( 'H:i:s' ) . '] ';
	}
}

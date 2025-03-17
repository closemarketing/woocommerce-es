<?php
/**
 * Sync Products
 *
 * @package    WordPress
 * @author     David Perez <david@close.technology>
 * @copyright  2023 Closemarketing
 * @version    1.0
 */

namespace CLOSE\WooCommerce\Library\Helpers;

defined( 'ABSPATH' ) || exit;

/**
 * Sync Products.
 *
 * @since 1.0.0
 */
class AI {

	/**
	 * Get models from provider
	 *
	 * @param string $provider Provider.
	 * @param string $token Token.
	 * @return array
	 */
	public static function get_models( $provider = 'chatgpt', $token = '' ) {
		$models = array();
		switch ( $provider ) {
			case 'chatgpt':
				$models = self::get_models_chatgpt( $token );
				break;
		}
		return $models;
	}

	/**
	 * Get models from ChatGPT
	 *
	 * @param string $api_key API Key.
	 * @return array
	 */
	private static function get_models_chatgpt( $api_key = '' ) {
		$models = get_transient( 'wpat_query_chatgpt_models' );
		if ( ! $models ) {
			// Generate value for chatgpt_models.
			$api_key = empty( $api_key ) ? get_site_option( 'wpat_chatgpt_token' ) : $api_key;

			$args       = array(
				'headers' => array(
					'Content-Type'  => 'application/json',
					'Authorization' => 'Bearer ' . $api_key,
				),
			);
			$models_api = wp_remote_get( 'https://api.openai.com/v1/models', $args );
			$code       = (int) round( wp_remote_retrieve_response_code( $models_api ) / 100, 0 );
			$models     = array();

			if ( 2 === $code ) {
				$response = json_decode( wp_remote_retrieve_body( $models_api ), true );

				foreach ( $response['data'] as $model ) {
					if ( 'model' === $model['object'] && ( strpos( $model['id'], 'gpt' ) !== false || strpos( $model['id'], 'davinci' ) !== false ) ) {
						$models[ $model['id'] ] = $model['id'];
					}
				}
			}
			set_transient( 'wpat_query_chatgpt_models', $models, DAY_IN_SECONDS );
		}

		return $models;
	}

}
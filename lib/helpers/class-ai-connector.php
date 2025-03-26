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

	public static function generate_seo_post( $options_slug, $item ) {
		$options  = get_option( $options_slug . '_ai' );
		$provider = isset( $options['provider'] ) ? $options['provider'] : 'chatgpt';
		$token    = isset( $options['token'] ) ? $options['token'] : '';
		$model    = isset( $options['model'] ) ? $options['model'] : '';

		switch ( $provider ) {
			case 'chatgpt':
				$post_info = self::generate_seo_post_chatgpt( $token );
				break;
		}

		return $post_info;
	}

	private static function generate_seo_post_chatgpt( $text, $type, $lang_from, $lang_to, $credentials ) {
		$prompt = empty( $credentials['prompt'] ) ? 'Translate this ' . $type . ' from ' . $lang_from . ' to ' . $lang_to . ': ' : $credentials['prompt'];

		$prompt = str_replace( '[source]', $lang_from, $prompt );
		$prompt = str_replace( '[target]', $lang_to, $prompt );
		$prompt = str_replace( '[type]', $type, $prompt );

		if ( empty( $credentials['token'] ) ) {
			return array(
				'api'           => 'chatgpt',
				'translation'   => $text,
				'language_from' => $lang_from,
				'language_to'   => $lang_to,
				'status'        => 0,
				'error'         => 'Error no credentials',
			);
		}

		$args = array(
			'headers' => array(
				'Content-Type'  => 'application/json',
				'Authorization' => 'Bearer ' . $credentials['token'],
			),
			'body'    => wp_json_encode(
				array(
					'model'             => ! empty( $credentials['model'] ) ? $credentials['model'] : 'gpt-3.5-turbo',
					'messages'          => array(
						array(
							'role'    => 'user',
							'content' => $prompt . $text,
						),
					),
					'temperature'       => 1,
					'top_p'             => 1,
					'n'                 => 1,
					'stream'            => false,
					'max_tokens'        => 250,
					'presence_penalty'  => 0,
					'frequency_penalty' => 0,
				)
			),
		);

		$response      = wp_remote_post( 'https://api.openai.com/v1/chat/completions', $args );
		$response_code = wp_remote_retrieve_response_code( $response );
		$response      = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( 200 !== $response_code ) {
			return array(
				'api'           => 'chatgpt',
				'translation'   => $text,
				'language_from' => $lang_from,
				'language_to'   => $lang_to,
				'status'        => 0,
				'error'         => isset( $response['error']['message'] ) ? sanitize_text_field( $response['error']['message'] ) : __( 'Unknown error', 'wpautotranslate' ),
			);
		}

		return array(
			'api'           => 'chatgpt',
			'translation'   => isset( $response['choices'][0]['message']['content'] ) ? $response['choices'][0]['message']['content'] : $text,
			'language_from' => $lang_from,
			'language_to'   => $lang_to,
			'status'        => 1,
			'error'         => '',
		);
	}
}
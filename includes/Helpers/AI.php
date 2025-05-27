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
			case 'deepseek':
				$models = array(
					'deepseek-chat' => 'DeepSeek Chat',
				);
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
		$models = get_transient( 'connwoo_query_chatgpt_models' );
		if ( ! $models ) {
			// Generate value for chatgpt_models.
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
			set_transient( 'connwoo_query_chatgpt_models', $models, DAY_IN_SECONDS );
		}

		return $models;
	}

	/**
	 * Generate SEO post
	 *
	 * @param array $settings Settings.
	 * @param array $item Item.
	 * @return array
	 */
	public static function generate_description( $settings, $item ) {
		$provider     = isset( $settings['provider'] ) ? $settings['provider'] : 'chatgpt';
		$prompt       = isset( $settings['prompt'] ) ? $settings['prompt'] : '';
		$product_info = isset( $item['full_info'] ) ? $item['full_info'] : $item;
		$message      = '';

		$content  = $prompt . PHP_EOL . __( 'I have a product with the following information in JSON:', 'connect-ecommerce' ) . wp_json_encode( $product_info );
		$language = get_locale();
		$content .= PHP_EOL . sprintf(
			/* translators: %s: language */
			__( 'Please respond in %s language.', 'connect-ecommerce' ),
			$language
		);
		$content .= PHP_EOL . __( 'Generate a Title, Content, Title SEO and SEO description and export it in format JSON, with elements: title, body, seo_title, seo_description', 'connect-ecommerce' );
		$content .= PHP_EOL . __( 'Return only a valid and complete JSON object. If the content is too long, split it into multiple parts and clearly indicate when to continue. Do not include any text outside of the JSON.', 'connect-ecommerce' );

		$token = isset( $settings['token'] ) ? $settings['token'] : '';

		if ( empty( $token ) ) {
			return array(
				'status'  => 'error',
				'message' => __( 'Error no credentials', 'connect-ecommerce' ),
			);
		}

		switch ( $provider ) {
			case 'chatgpt':
				$model   = isset( $settings['model'] ) ? $settings['model'] : 'gpt-3.5-turbo';
				$api_url = 'https://api.openai.com/v1/chat/completions';
				break;
			case 'deepseek':
				$model   = isset( $settings['model'] ) ? $settings['model'] : 'deepseek-chat';
				$api_url = 'https://api.deepseek.com/v1/chat/completions';
				break;
		}

		$args = array(
			'headers' => array(
				'Content-Type'  => 'application/json',
				'Authorization' => 'Bearer ' . $token,
			),
			'timeout' => 90,
			'body'    => wp_json_encode(
				array(
					'model'             => $model,
					'messages'          => array(
						array(
							'role'    => 'user',
							'content' => $content,
						),
					),
					'temperature'       => 1,
					'top_p'             => 1,
					'n'                 => 1,
					'stream'            => false,
					'max_tokens'        => 1024,
					'presence_penalty'  => 0,
					'frequency_penalty' => 0,
				)
			),
		);

		$response      = wp_remote_post( $api_url, $args );
		$response_code = wp_remote_retrieve_response_code( $response );
		$body          = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( 200 !== $response_code ) {
			$message .= sanitize_text_field( $response['error']['message'] ) ?? '';
			$message .= sanitize_text_field( $response['errors']['http_request_failed'] ) ?? '';
			$message .= $message ?? __( 'Unknown error', 'connect-ecommerce' );

			return array(
				'status'  => 'error',
				'message' => $message,
			);
		}
		$content = array();
		if ( isset( $body['choices'][0]['message']['content'] ) ) {
			error_log( '$content: ' . print_r( $body['choices'][0]['message']['content'], true ) );
			$content = str_replace( '```json', '', $body['choices'][0]['message']['content'] );
			$content = preg_replace( '/```[\w]*\s*/', '', $content );
			$content = trim( $content );
			$content = json_decode( $content, true );
			if ( json_last_error() !== JSON_ERROR_NONE ) {
				return array(
					'status'  => 'error',
					'message' => __( 'Error decoding JSON', 'connect-ecommerce' ) . ': ' . json_last_error_msg(),
				);
			}
			if ( ! is_array( $content ) ) {
				$content = array();
			}
			$message = __( 'Spent tokens: ', 'connect-ecommerce' ) . ( isset( $body['usage']['total_tokens'] ) ? $body['usage']['total_tokens'] : 0 );
		}

		return array(
			'data'    => $content,
			'status'  => 'ok',
			'message' => $message,
		);
	}
}

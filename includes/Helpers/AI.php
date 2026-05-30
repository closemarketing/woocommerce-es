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
	 * Whether the WordPress AI Services API is available.
	 *
	 * @return bool
	 */
	public static function has_wp_ai_services() {
		return function_exists( 'ai_services' );
	}

	/**
	 * Get models from provider
	 *
	 * When WordPress AI Services is available the list is sourced from registered
	 * services; otherwise falls back to querying the provider API directly.
	 *
	 * @param string $provider Provider (legacy, ignored when AI Services active).
	 * @param string $token    Token (legacy, ignored when AI Services active).
	 * @return array
	 */
	public static function get_models( $provider = 'chatgpt', $token = '' ) {
		if ( self::has_wp_ai_services() ) {
			return self::get_models_from_wp_ai_services();
		}

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
	 * List models available through the WordPress AI Services API.
	 *
	 * @return array  slug => label pairs.
	 */
	private static function get_models_from_wp_ai_services() {
		$models = array();
		try {
			$services_api = ai_services();
			$args         = array( 'capabilities' => array( \Felix_Arntz\AI_Services\Services\API\Enums\AI_Capability::TEXT_GENERATION ) );
			if ( ! $services_api->has_available_services( $args ) ) {
				return $models;
			}
			$service = $services_api->get_available_service( $args );
			// The service slug acts as the model identifier at this level.
			$slug            = $service->get_service_slug();
			$models[ $slug ] = $slug;
		} catch ( \Exception $e ) {
			// Silently degrade — caller renders an empty select.
			error_log( 'conecom AI Services error: ' . $e->getMessage() ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
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
	 * Uses the WordPress AI Services API when available; falls back to direct
	 * provider API calls so the feature works without the AI Services plugin.
	 *
	 * @param array $settings Settings.
	 * @param array $item     Item.
	 * @return array
	 */
	public static function generate_description( $settings, $item ) {
		if ( self::has_wp_ai_services() ) {
			return self::generate_description_wp_ai_services( $settings, $item );
		}

		return self::generate_description_legacy( $settings, $item );
	}

	/**
	 * Generate description via the WordPress AI Services API.
	 *
	 * @param array $settings Settings.
	 * @param array $item     Item.
	 * @return array
	 */
	private static function generate_description_wp_ai_services( $settings, $item ) {
		$prompt       = isset( $settings['prompt'] ) ? $settings['prompt'] : '';
		$product_info = isset( $item['full_info'] ) ? $item['full_info'] : $item;

		$content  = $prompt . PHP_EOL . __( 'I have a product with the following information in JSON:', 'woocommerce-es' ) . wp_json_encode( $product_info );
		$language = get_locale();
		$content .= PHP_EOL . sprintf(
			/* translators: %s: language */
			__( 'Please respond in %s language.', 'woocommerce-es' ),
			$language
		);
		$content .= PHP_EOL . __( 'Generate a Title, Content, Title SEO, SEO description and SEO Focus keyword and export it in format JSON, with elements: title, body, seo_title, seo_description, seo_keyword', 'woocommerce-es' );
		$content .= PHP_EOL . __( 'Return only a valid and complete JSON object. If the content is too long, split it into multiple parts and clearly indicate when to continue. Do not include any text outside of the JSON.', 'woocommerce-es' );

		try {
			$services_api = ai_services();
			$args         = array( 'capabilities' => array( \Felix_Arntz\AI_Services\Services\API\Enums\AI_Capability::TEXT_GENERATION ) );
			if ( ! $services_api->has_available_services( $args ) ) {
				return array(
					'status'  => 'error',
					'message' => __( 'No AI service with text generation capability is available. Please configure one in Settings > AI Services.', 'woocommerce-es' ),
				);
			}

			$candidates = $services_api
				->get_available_service( $args )
				->get_model( array( 'feature' => 'conecom-product-description' ) )
				->generate_text( $content );

			$raw_text = \Felix_Arntz\AI_Services\Services\API\Helpers::get_text_from_contents(
				\Felix_Arntz\AI_Services\Services\API\Helpers::get_candidate_contents( $candidates )
			);

			$raw_text = str_replace( '```json', '', $raw_text );
			$raw_text = preg_replace( '/```[\w]*\s*/', '', $raw_text );
			$raw_text = trim( $raw_text );
			$decoded  = json_decode( $raw_text, true );

			if ( json_last_error() !== JSON_ERROR_NONE ) {
				return array(
					'status'  => 'error',
					'message' => __( 'Error decoding JSON', 'woocommerce-es' ) . ': ' . json_last_error_msg(),
				);
			}

			return array(
				'data'    => is_array( $decoded ) ? $decoded : array(),
				'status'  => 'ok',
				'message' => '',
			);
		} catch ( \Exception $e ) {
			return array(
				'status'  => 'error',
				'message' => $e->getMessage(),
			);
		}
	}

	/**
	 * Generate description via direct provider API calls (legacy path).
	 *
	 * @param array $settings Settings.
	 * @param array $item     Item.
	 * @return array
	 */
	private static function generate_description_legacy( $settings, $item ) {
		$provider     = isset( $settings['provider'] ) ? $settings['provider'] : 'chatgpt';
		$prompt       = isset( $settings['prompt'] ) ? $settings['prompt'] : '';
		$product_info = isset( $item['full_info'] ) ? $item['full_info'] : $item;
		$model        = isset( $settings['model'] ) ? $settings['model'] : '';
		$message      = '';
		$api_url      = '';

		$content  = $prompt . PHP_EOL . __( 'I have a product with the following information in JSON:', 'woocommerce-es' ) . wp_json_encode( $product_info );
		$language = get_locale();
		$content .= PHP_EOL . sprintf(
			/* translators: %s: language */
			__( 'Please respond in %s language.', 'woocommerce-es' ),
			$language
		);
		$content .= PHP_EOL . __( 'Generate a Title, Content, Title SEO, SEO description and SEO Focus keyword and export it in format JSON, with elements: title, body, seo_title, seo_description, seo_keyword', 'woocommerce-es' );
		$content .= PHP_EOL . __( 'Return only a valid and complete JSON object. If the content is too long, split it into multiple parts and clearly indicate when to continue. Do not include any text outside of the JSON.', 'woocommerce-es' );

		$token = isset( $settings['token'] ) ? $settings['token'] : '';

		if ( empty( $token ) ) {
			return array(
				'status'  => 'error',
				'message' => __( 'Error no credentials', 'woocommerce-es' ),
			);
		}

		switch ( $provider ) {
			case 'chatgpt':
				$model   = ! empty( $model ) ? $model : 'gpt-3.5-turbo';
				$api_url = 'https://api.openai.com/v1/chat/completions';
				break;
			case 'deepseek':
				$model   = ! empty( $model ) ? $model : 'deepseek-chat';
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
			$message .= sanitize_text_field( $body['error']['message'] ) ?? '';
			$message .= sanitize_text_field( $body['errors']['http_request_failed'] ) ?? '';

			if ( empty( $message ) ) {
				$message = __( 'Unknown error', 'woocommerce-es' );
			}

			return array(
				'status'  => 'error',
				'message' => $message,
			);
		}
		$content = array();
		if ( isset( $body['choices'][0]['message']['content'] ) ) {
			$content = str_replace( '```json', '', $body['choices'][0]['message']['content'] );
			$content = preg_replace( '/```[\w]*\s*/', '', $content );
			$content = trim( $content );
			$content = json_decode( $content, true );
			if ( json_last_error() !== JSON_ERROR_NONE ) {
				return array(
					'status'  => 'error',
					'message' => __( 'Error decoding JSON', 'woocommerce-es' ) . ': ' . json_last_error_msg(),
				);
			}
			if ( ! is_array( $content ) ) {
				$content = array();
			}
			$message = __( 'Spent tokens: ', 'woocommerce-es' ) . ( isset( $body['usage']['total_tokens'] ) ? $body['usage']['total_tokens'] : 0 );
		}

		return array(
			'data'    => $content,
			'status'  => 'ok',
			'message' => $message,
		);
	}
}

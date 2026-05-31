<?php
/**
 * AI Helper
 *
 * @package    WordPress
 * @author     David Perez <david@close.technology>
 * @copyright  2023 Closemarketing
 * @version    1.0
 */

namespace CLOSE\ConnectEcommerce\Helpers;

defined( 'ABSPATH' ) || exit;

use WordPress\AiClient\AiClient;

/**
 * AI helper — delegates to the WordPress 7.0 core AI API.
 *
 * @since 1.0.0
 */
class AI {
	/**
	 * Whether the WordPress core AI API is available.
	 *
	 * @return bool
	 */
	public static function has_wp_ai() {
		return function_exists( 'wp_supports_ai' ) && wp_supports_ai();
	}

	/**
	 * Get available AI models from the core registry, grouped by provider.
	 *
	 * Returns an array of optgroups: label => [ ['value' => 'provider::model', 'label' => '...'], ... ]
	 *
	 * @return array<string, array<int, array<string, string>>>
	 */
	public static function get_available_models() {
		if ( ! class_exists( AiClient::class ) ) {
			return array();
		}

		$grouped = array();

		try {
			$registry = AiClient::defaultRegistry();

			foreach ( $registry->getRegisteredProviderIds() as $provider_id ) {
				if ( ! $registry->isProviderConfigured( $provider_id ) ) {
					continue;
				}

				$class_name    = $registry->getProviderClassName( $provider_id );
				$provider_meta = $class_name::metadata();
				$provider_label = (string) $provider_meta->getName();

				foreach ( $class_name::modelMetadataDirectory()->listModelMetadata() as $model_meta ) {
					if ( ! self::supports_text_generation( $model_meta ) ) {
						continue;
					}

					if ( ! isset( $grouped[ $provider_label ] ) ) {
						$grouped[ $provider_label ] = array();
					}
					$grouped[ $provider_label ][] = array(
						'value' => $provider_id . '::' . (string) $model_meta->getId(),
						'label' => (string) $model_meta->getName(),
					);
				}
			}
		} catch ( \Throwable $e ) {
			return array();
		}

		return $grouped;
	}

	/**
	 * Generate SEO product description via the WordPress core AI API.
	 *
	 * @param array $settings Plugin AI settings ('model' and 'prompt').
	 * @param array $item     Product data.
	 * @return array{status: string, message: string, data?: array<mixed>}
	 */
	public static function generate_description( $settings, $item ) {
		if ( ! self::has_wp_ai() ) {
			return array(
				'status'  => 'error',
				'message' => __( 'WordPress AI is not available. Please upgrade to WordPress 7.0 or later and ensure AI support is enabled.', 'woocommerce-es' ),
			);
		}

		$prompt       = isset( $settings['prompt'] ) ? $settings['prompt'] : '';
		$product_info = isset( $item['full_info'] ) ? $item['full_info'] : $item;
		$language     = get_locale();

		$content  = $prompt . PHP_EOL . __( 'I have a product with the following information in JSON:', 'woocommerce-es' ) . wp_json_encode( $product_info );
		$content .= PHP_EOL . sprintf(
			/* translators: %s: language locale */
			__( 'Please respond in %s language.', 'woocommerce-es' ),
			$language
		);
		$content .= PHP_EOL . __( 'Generate a Title, Content, Title SEO, SEO description and SEO Focus keyword and export it in format JSON, with elements: title, body, seo_title, seo_description, seo_keyword', 'woocommerce-es' );
		$content .= PHP_EOL . __( 'Return only a valid and complete JSON object. If the content is too long, split it into multiple parts and clearly indicate when to continue. Do not include any text outside of the JSON.', 'woocommerce-es' );

		try {
			$builder  = wp_ai_client_prompt( $content );
			$model    = isset( $settings['model'] ) ? trim( $settings['model'] ) : '';
			if ( $model ) {
				$preference = self::normalize_model_preference( $model );
				$builder    = $builder->usingModelPreference( $preference );
			}

			$raw_text = $builder->generateText();

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
	 * Normalise a model preference string coming from settings or POST.
	 *
	 * Accepts "provider::model", "provider|model", "provider:model".
	 *
	 * @param string $preference Raw preference value.
	 * @return string
	 */
	private static function normalize_model_preference( $preference ) {
		return str_replace( array( '::', '|', ':' ), '::', $preference );
	}

	/**
	 * Check whether a model's metadata indicates text input + output support.
	 *
	 * Excludes audio/transcription/speech/realtime-only models.
	 *
	 * @param mixed $model_meta Model metadata object.
	 * @return bool
	 */
	private static function supports_text_generation( $model_meta ) {
		$id = strtolower( (string) $model_meta->getId() );
		foreach ( array( 'transcri', 'tts', 'whisper', 'realtime', 'speech' ) as $exclude ) {
			if ( false !== strpos( $id, $exclude ) ) {
				return false;
			}
		}
		return true;
	}
}

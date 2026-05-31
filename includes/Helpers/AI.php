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

/**
 * AI helper — delegates entirely to the WordPress AI Services API.
 *
 * Requires the "AI Services" plugin (felixarntz/ai-services). When it is not
 * active, all methods return an error so callers can surface an upgrade notice.
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
	 * Generate SEO product description via the WordPress AI Services API.
	 *
	 * @param array $settings Plugin AI settings (only 'prompt' is used).
	 * @param array $item     Product data.
	 * @return array{status: string, message: string, data?: array<mixed>}
	 */
	public static function generate_description( $settings, $item ) {
		if ( ! self::has_wp_ai_services() ) {
			return array(
				'status'  => 'error',
				'message' => __( 'The AI Services plugin is required. Please install and activate it.', 'woocommerce-es' ),
			);
		}

		$prompt       = isset( $settings['prompt'] ) ? $settings['prompt'] : '';
		$product_info = isset( $item['full_info'] ) ? $item['full_info'] : $item;

		$content  = $prompt . PHP_EOL . __( 'I have a product with the following information in JSON:', 'woocommerce-es' ) . wp_json_encode( $product_info );
		$language = get_locale();
		$content .= PHP_EOL . sprintf(
			/* translators: %s: language locale */
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
					'message' => __( 'No AI service with text generation is configured. Please add credentials in Settings > AI Services.', 'woocommerce-es' ),
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
}

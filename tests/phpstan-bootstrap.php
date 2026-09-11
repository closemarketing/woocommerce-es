<?php
/**
 * PHPStan Bootstrap File
 *
 * This file defines constants and functions that PHPStan needs to understand
 * but are not available during static analysis.
 */

namespace WordPress\AiClient {

	if ( ! class_exists( 'WordPress\AiClient\PromptBuilder' ) ) {
		class PromptBuilder {
			/** @param mixed ...$preferredModels */
			public function usingModelPreference( ...$preferredModels ): self {
				return $this;
			}
			public function generateText(): string {
				return '';
			}
		}
	}

	// Minimal stubs for dynamic model discovery via AiClient::defaultRegistry().
	if ( ! class_exists( 'WordPress\AiClient\AiClient' ) ) {
		class AiClient {
			public static function defaultRegistry(): ProviderRegistry {
				return new ProviderRegistry();
			}
		}
	}

	if ( ! class_exists( 'WordPress\AiClient\ProviderRegistry' ) ) {
		class ProviderRegistry {
			/** @return array<string> */
			public function getRegisteredProviderIds(): array {
				return array();
			}
			public function isProviderConfigured( string $providerId ): bool {
				return false;
			}
			public function getProviderClassName( string $providerId ): string {
				return '';
			}
		}
	}
}

namespace {

	// Define plugin constants that are used throughout the codebase.
	if ( ! defined( 'CONECOM_PLUGIN_URL' ) ) {
		define( 'CONECOM_PLUGIN_URL', 'http://localhost/wp-content/plugins/connect-ecommerce/' );
	}

	if ( ! defined( 'CONECOM_VERSION' ) ) {
		define( 'CONECOM_VERSION', '1.0.0' );
	}

	if ( ! defined( 'CONECOM_FILE' ) ) {
		define( 'CONECOM_FILE', __FILE__ );
	}

	// Define WordPress constants that might be missing.
	if ( ! defined( 'DOING_AJAX' ) ) {
		define( 'DOING_AJAX', false );
	}

	if ( ! defined( 'DB_NAME' ) ) {
		define( 'DB_NAME', 'hola' );
	}

	if ( ! defined( 'WP_DEBUG' ) ) {
		define( 'WP_DEBUG', false );
	}

	if ( ! defined( 'ABSPATH' ) ) {
		define( 'ABSPATH', '/path/to/wordpress/' );
	}

	// Mock WordPress functions that PHPStan can't find.
	if ( ! function_exists( 'wp_doing_ajax' ) ) {
		function wp_doing_ajax() {
			return defined( 'DOING_AJAX' ) && DOING_AJAX;
		}
	}

	if ( ! function_exists( 'conecom_get_options' ) ) {
		function conecom_get_options() {
			return array();
		}
	}

	// Mock WordPress 7.0 core AI functions.
	if ( ! function_exists( 'wp_supports_ai' ) ) {
		function wp_supports_ai(): bool {
			return false;
		}
	}

	if ( ! function_exists( 'wp_ai_client_prompt' ) ) {
		/**
		 * @param string|null $prompt
		 * @return \WordPress\AiClient\PromptBuilder
		 */
		function wp_ai_client_prompt( $prompt = null ): \WordPress\AiClient\PromptBuilder {
			return new \WordPress\AiClient\PromptBuilder();
		}
	}

	// Mock Action Scheduler functions.
	if ( ! function_exists( 'as_schedule_recurring_action' ) ) {
		function as_schedule_recurring_action( $timestamp, $interval_in_seconds, $hook, $args = array(), $group = '' ) {
			return true;
		}
	}

	if ( ! function_exists( 'as_get_scheduled_actions' ) ) {
		/**
		 * @param array<string, mixed> $args
		 * @param string               $return_format
		 * @return array<int>|array<object>
		 */
		function as_get_scheduled_actions( $args = array(), $return_format = 'objects' ) {
			return array();
		}
	}

	if ( ! class_exists( 'ActionScheduler_Store' ) ) {
		class ActionScheduler_Store {
			const STATUS_PENDING = 'pending';
		}
	}

	// Mock WP_CLI class.
	if ( ! class_exists( 'WP_CLI' ) ) {
		class WP_CLI {
			public static function line( $message ) {
				echo $message . "\n";
			}
			public static function add_command( $command, $class ) {
				return true;
			}
		}
	}
}

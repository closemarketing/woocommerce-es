<?php
/**
 * PHPStan Bootstrap File
 *
 * This file defines constants and functions that PHPStan needs to understand
 * but are not available during static analysis.
 */

namespace Felix_Arntz\AI_Services\Services\API\Enums {
	// Stub for the WordPress AI Services optional dependency.
	if ( ! class_exists( 'Felix_Arntz\AI_Services\Services\API\Enums\AI_Capability' ) ) {
		class AI_Capability {
			const TEXT_GENERATION  = 'text_generation';
			const IMAGE_GENERATION = 'image_generation';
			const TEXT_TO_SPEECH   = 'text_to_speech';
		}
	}
}

namespace Felix_Arntz\AI_Services\Services\API {
	if ( ! class_exists( 'Felix_Arntz\AI_Services\Services\API\Helpers' ) ) {
		class Helpers {
			/** @param array<mixed> $contents */
			public static function get_text_from_contents( array $contents ): string {
				return '';
			}
			/**
			 * @param mixed $candidates
			 * @return array<mixed>
			 */
			public static function get_candidate_contents( $candidates ): array {
				return array();
			}
		}
	}
}

namespace Felix_Arntz\AI_Services\Services {
	if ( ! class_exists( 'Felix_Arntz\AI_Services\Services\Services_API' ) ) {
		class Services_API {
			/** @param array<string, mixed> $args */
			public function has_available_services( array $args = array() ): bool {
				return false;
			}
			/**
			 * @param array<string, mixed>|string $args
			 * @return AI_Service_Stub
			 */
			public function get_available_service( $args = array() ): AI_Service_Stub {
				return new AI_Service_Stub();
			}
			public function is_service_available( string $slug ): bool {
				return false;
			}
		}
	}
	if ( ! class_exists( 'Felix_Arntz\AI_Services\Services\AI_Service_Stub' ) ) {
		class AI_Service_Stub {
			public function get_service_slug(): string {
				return '';
			}
			/** @param array<string, mixed> $args */
			public function get_model( array $args = array() ): AI_Model_Stub {
				return new AI_Model_Stub();
			}
		}
	}
	if ( ! class_exists( 'Felix_Arntz\AI_Services\Services\AI_Model_Stub' ) ) {
		class AI_Model_Stub {
			/** @return mixed */
			public function generate_text( string $prompt ) {
				return null;
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

	// Mock WordPress AI Services function (optional dependency).
	if ( ! function_exists( 'ai_services' ) ) {
		/**
		 * @return \Felix_Arntz\AI_Services\Services\Services_API
		 */
		function ai_services() {
			return new \Felix_Arntz\AI_Services\Services\Services_API();
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

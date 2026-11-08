<?php
/**
 * Credentials mirror for the WordPress Secrets API
 *
 * Mirrors the plugin credentials (connector passwords and API keys, VATSense
 * key, webhook tokens and signing secrets) into the Secrets API introduced in
 * WordPress 7.2, while the options keep storing them as before. Options stay
 * the source of truth until every supported WordPress version ships the API.
 *
 * @package    WordPress
 * @author     Closetechnology
 * @copyright  2026 Closemarketing
 * @version    1.0
 */

namespace CLOSE\ConnectEcommerce\Helpers;

defined( 'ABSPATH' ) || exit;

/**
 * Secrets API mirror.
 *
 * @since 3.5.2
 */
class SECRETS {
	/**
	 * Namespace of every secret name, following the "plugin-slug/secret-name" convention.
	 */
	const PREFIX = 'woocommerce-es/';

	/**
	 * Option flagging that every credential has been mirrored into secrets.
	 */
	const OPTION_SYNCED = 'conecom_secrets_synced';

	/**
	 * Version of the mirror. Bump it to force a new full sync.
	 */
	const SCHEMA = '1';

	/**
	 * Transient throttling the full sync retries after a failure.
	 */
	const RETRY_TRANSIENT = 'conecom_secrets_retry';

	/**
	 * Credential keys stored inside the settings of each connector.
	 */
	const CONNECTOR_FIELDS = array( 'password', 'api' );

	/**
	 * Hooks the mirror into the credential options.
	 *
	 * @return void
	 */
	public static function init() {
		foreach ( self::get_options() as $option ) {
			add_action( 'add_option_' . $option, array( __CLASS__, 'on_add_option' ), 10, 2 );
			add_action( 'update_option_' . $option, array( __CLASS__, 'on_update_option' ), 10, 3 );
		}
		add_action( 'delete_option', array( __CLASS__, 'on_delete_option' ) );
		add_action( 'admin_init', array( __CLASS__, 'on_admin_init' ) );
	}

	/**
	 * Checks whether the Secrets API is available and enabled.
	 *
	 * @return bool
	 */
	public static function is_available() {
		$available = function_exists( 'wp_set_secret' )
			&& function_exists( 'wp_get_secret' )
			&& function_exists( 'wp_delete_secret' )
			&& class_exists( 'WP_Secret' );

		/**
		 * Filters whether credentials are mirrored into the WordPress Secrets API.
		 *
		 * @param bool $use_secrets_api True when the API exists.
		 */
		return (bool) apply_filters( 'conecom_use_secrets_api', $available );
	}

	/**
	 * Options that hold credentials.
	 *
	 * @return string[]
	 */
	public static function get_options() {
		return array(
			'connect_ecommerce',
			'connect_ecommerce_public',
			WEBHOOK::OPTION_TOKENS,
			WEBHOOK::OPTION_SECRETS,
		);
	}

	/**
	 * Extracts the credentials of an option value as secret name => value.
	 *
	 * Names are relative to PREFIX. Empty values are left out.
	 *
	 * @param string $option Option name.
	 * @param mixed  $value  Option value.
	 * @return array<string,string>
	 */
	public static function extract( $option, $value ) {
		$credentials = array();
		if ( ! is_array( $value ) ) {
			return $credentials;
		}

		switch ( $option ) {
			case 'connect_ecommerce':
				foreach ( $value as $connector_id => $settings ) {
					if ( ! is_array( $settings ) || in_array( $connector_id, array( 'connectors_meta', 'connector' ), true ) ) {
						continue;
					}
					foreach ( self::CONNECTOR_FIELDS as $field ) {
						if ( isset( $settings[ $field ] ) && is_scalar( $settings[ $field ] ) ) {
							$credentials[ sanitize_key( $connector_id ) . '/' . $field ] = (string) $settings[ $field ];
						}
					}
				}
				break;

			case 'connect_ecommerce_public':
				if ( isset( $value['vatsense_api_key'] ) && is_scalar( $value['vatsense_api_key'] ) ) {
					$credentials['vatsense-api-key'] = (string) $value['vatsense_api_key'];
				}
				break;

			case WEBHOOK::OPTION_TOKENS:
			case WEBHOOK::OPTION_SECRETS:
				$suffix = WEBHOOK::OPTION_TOKENS === $option ? 'token' : 'signing-secret';
				foreach ( $value as $connector_id => $credential ) {
					if ( is_scalar( $credential ) ) {
						$credentials[ 'webhook/' . sanitize_key( $connector_id ) . '/' . $suffix ] = (string) $credential;
					}
				}
				break;
		}

		return array_filter(
			$credentials,
			function ( $credential ) {
				return '' !== $credential;
			}
		);
	}

	/**
	 * Mirrors a newly added credential option.
	 *
	 * @param string $option Option name.
	 * @param mixed  $value  Option value.
	 * @return void
	 */
	public static function on_add_option( $option, $value ) {
		self::mirror( $option, array(), $value );
	}

	/**
	 * Mirrors an updated credential option.
	 *
	 * @param mixed  $old_value Previous value.
	 * @param mixed  $value     New value.
	 * @param string $option    Option name.
	 * @return void
	 */
	public static function on_update_option( $old_value, $value, $option ) {
		self::mirror( $option, $old_value, $value );
	}

	/**
	 * Deletes the secrets of a credential option that is about to be deleted.
	 *
	 * @param string $option Option name.
	 * @return void
	 */
	public static function on_delete_option( $option ) {
		if ( in_array( $option, self::get_options(), true ) ) {
			self::mirror( $option, get_option( $option, array() ), array() );
		}
	}

	/**
	 * Applies the credential changes between two option values to the secrets.
	 *
	 * Never blocks the option write: a failure is reported and the full sync is
	 * scheduled again, while the option keeps the credential.
	 *
	 * @param string $option    Option name.
	 * @param mixed  $old_value Previous value.
	 * @param mixed  $value     New value.
	 * @return bool True when every change reached the secrets.
	 */
	public static function mirror( $option, $old_value, $value ) {
		$old = self::extract( $option, $old_value );
		$new = self::extract( $option, $value );
		if ( $old === $new ) {
			return true;
		}

		if ( ! self::is_available() ) {
			// The secrets will be stale once the API is back: sync everything again then.
			self::mark_unsynced();
			return false;
		}

		$success = true;
		foreach ( $new as $name => $credential ) {
			if ( ! isset( $old[ $name ] ) || $old[ $name ] !== $credential ) {
				$success = self::set( $name, $credential ) && $success;
			}
		}
		foreach ( array_diff_key( $old, $new ) as $name => $credential ) {
			$success = self::delete( $name ) && $success;
		}

		if ( ! $success ) {
			self::mark_unsynced();
		}

		return $success;
	}

	/**
	 * Runs the pending full sync on admin requests.
	 *
	 * @return void
	 */
	public static function on_admin_init() {
		self::maybe_sync_all();
	}

	/**
	 * Mirrors every stored credential once the API becomes available.
	 *
	 * Runs on admin_init until a full sync succeeds; failures retry after an hour.
	 *
	 * @return bool True when the secrets are in sync.
	 */
	public static function maybe_sync_all() {
		if ( self::SCHEMA === get_option( self::OPTION_SYNCED ) ) {
			return true;
		}
		if ( ! self::is_available() || get_transient( self::RETRY_TRANSIENT ) ) {
			return false;
		}

		return self::sync_all();
	}

	/**
	 * Writes every stored credential into the secrets, skipping the ones already equal.
	 *
	 * @return bool True when every credential reached the secrets.
	 */
	public static function sync_all() {
		if ( ! self::is_available() ) {
			return false;
		}

		$success = true;
		foreach ( self::get_options() as $option ) {
			foreach ( self::extract( $option, get_option( $option, array() ) ) as $name => $credential ) {
				if ( self::get( $name ) !== $credential ) {
					$success = self::set( $name, $credential ) && $success;
				}
			}
		}

		if ( $success ) {
			update_option( self::OPTION_SYNCED, self::SCHEMA, false );
			delete_transient( self::RETRY_TRANSIENT );
		} else {
			set_transient( self::RETRY_TRANSIENT, 1, HOUR_IN_SECONDS );
		}

		return $success;
	}

	/**
	 * Deletes the connector credentials kept in secrets.
	 *
	 * Needed where connect_ecommerce is written straight to the database and the
	 * option hooks do not run.
	 *
	 * @param string $connector_id Connector ID.
	 * @return void
	 */
	public static function forget_connector( $connector_id ) {
		if ( ! self::is_available() ) {
			return;
		}
		foreach ( self::CONNECTOR_FIELDS as $field ) {
			self::delete( sanitize_key( $connector_id ) . '/' . $field );
		}
	}

	/**
	 * Reads a credential from the secrets.
	 *
	 * @param string $name Secret name, relative to PREFIX.
	 * @return string|null Null when the API is unavailable, the secret is missing or cannot be decrypted.
	 */
	public static function get( $name ) {
		if ( ! self::is_available() ) {
			return null;
		}

		$secret = wp_get_secret( self::PREFIX . $name );
		if ( is_wp_error( $secret ) ) {
			self::report_error( $name, $secret );
			return null;
		}
		if ( ! is_object( $secret ) || ! method_exists( $secret, 'reveal' ) ) {
			return null;
		}

		$value = $secret->reveal();

		return is_string( $value ) ? $value : null;
	}

	/**
	 * Writes a credential into the secrets.
	 *
	 * @param string $name  Secret name, relative to PREFIX.
	 * @param string $value Credential.
	 * @return bool
	 */
	private static function set( $name, $value ) {
		$result = wp_set_secret( self::PREFIX . $name, (string) $value );
		if ( is_wp_error( $result ) || false === $result ) {
			self::report_error( $name, $result );
			return false;
		}

		return true;
	}

	/**
	 * Deletes a credential from the secrets.
	 *
	 * @param string $name Secret name, relative to PREFIX.
	 * @return bool
	 */
	private static function delete( $name ) {
		$result = wp_delete_secret( self::PREFIX . $name );
		if ( is_wp_error( $result ) ) {
			self::report_error( $name, $result );
			return false;
		}

		// A missing secret is not an error: the goal is that it does not exist.
		return true;
	}

	/**
	 * Schedules a new full sync.
	 *
	 * @return void
	 */
	private static function mark_unsynced() {
		if ( false !== get_option( self::OPTION_SYNCED ) ) {
			delete_option( self::OPTION_SYNCED );
		}
	}

	/**
	 * Reports a Secrets API failure. The credential value is never included.
	 *
	 * @param string $name   Secret name, relative to PREFIX.
	 * @param mixed  $result WP_Error or false.
	 * @return void
	 */
	private static function report_error( $name, $result ) {
		$message = is_wp_error( $result ) ? $result->get_error_message() : 'unknown error';

		/**
		 * Fires when a credential cannot be written to, read from or deleted in the Secrets API.
		 *
		 * @param string $name   Secret name, relative to the woocommerce-es/ namespace.
		 * @param mixed  $result WP_Error or false.
		 */
		do_action( 'conecom_secrets_error', $name, $result );

		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			error_log( sprintf( '[woocommerce-es] Secrets API error on "%s": %s', self::PREFIX . $name, $message ) ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
		}
	}
}

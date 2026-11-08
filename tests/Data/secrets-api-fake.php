<?php
/**
 * In-memory fake of the WordPress 7.2 Secrets API for the test suite.
 *
 * Only defined when the WordPress under test does not ship the API.
 *
 * @package Connect_Ecommerce
 */

if ( ! function_exists( 'wp_set_secret' ) && ! class_exists( 'WP_Secret' ) ) {
	define( 'CONECOM_FAKE_SECRETS_API', true );

	$GLOBALS['conecom_fake_secrets']      = array();
	$GLOBALS['conecom_fake_secrets_fail'] = false;

	/**
	 * Fake secret value object.
	 */
	final class WP_Secret {
		/**
		 * Raw value.
		 *
		 * @var string
		 */
		private $value;

		/**
		 * Constructor.
		 *
		 * @param string $value Raw value.
		 */
		public function __construct( $value ) {
			$this->value = $value;
		}

		/**
		 * Returns the raw value.
		 *
		 * @return string
		 */
		public function reveal() {
			return $this->value;
		}

		/**
		 * Returns a fingerprint of the value.
		 *
		 * @return string
		 */
		public function fingerprint() {
			return substr( hash( 'sha256', $this->value ), 0, 12 );
		}
	}

	/**
	 * Fake wp_set_secret().
	 *
	 * @param string $name  Name.
	 * @param string $value Value.
	 * @return bool|WP_Error
	 */
	function wp_set_secret( $name, $value ) {
		if ( $GLOBALS['conecom_fake_secrets_fail'] ) {
			return new WP_Error( 'secret_store_unavailable', 'Store unavailable.' );
		}
		$GLOBALS['conecom_fake_secrets'][ $name ] = (string) $value;
		return true;
	}

	/**
	 * Fake wp_get_secret().
	 *
	 * @param string $name Name.
	 * @return WP_Secret|null|WP_Error
	 */
	function wp_get_secret( $name ) {
		if ( $GLOBALS['conecom_fake_secrets_fail'] ) {
			return new WP_Error( 'secret_undecryptable', 'Cannot decrypt.' );
		}
		return isset( $GLOBALS['conecom_fake_secrets'][ $name ] ) ? new WP_Secret( $GLOBALS['conecom_fake_secrets'][ $name ] ) : null;
	}

	/**
	 * Fake wp_delete_secret().
	 *
	 * @param string $name Name.
	 * @return bool|WP_Error
	 */
	function wp_delete_secret( $name ) {
		if ( $GLOBALS['conecom_fake_secrets_fail'] ) {
			return new WP_Error( 'secret_store_unavailable', 'Store unavailable.' );
		}
		unset( $GLOBALS['conecom_fake_secrets'][ $name ] );
		return true;
	}
}

<?php
/**
 * Tests the credentials mirror into the WordPress Secrets API.
 *
 * Command: composer test -- --filter=SecretsTest
 *
 * @package Connect_Ecommerce
 */

use CLOSE\ConnectEcommerce\Helpers\SECRETS;
use CLOSE\ConnectEcommerce\Helpers\WEBHOOK;

require_once UNIT_TESTS_DATA_PLUGIN_DIR . 'secrets-api-fake.php';

/**
 * Class SecretsTest.
 */
class SecretsTest extends WP_UnitTestCase {
	/**
	 * Set up.
	 */
	public function setUp(): void {
		parent::setUp();
		if ( ! defined( 'CONECOM_FAKE_SECRETS_API' ) ) {
			$this->markTestSkipped( 'The WordPress under test ships its own Secrets API.' );
		}
		$GLOBALS['conecom_fake_secrets']      = array();
		$GLOBALS['conecom_fake_secrets_fail'] = false;
		SECRETS::init();
	}

	/**
	 * Tear down.
	 */
	public function tearDown(): void {
		remove_all_filters( 'conecom_use_secrets_api' );
		foreach ( array_merge( SECRETS::get_options(), array( SECRETS::OPTION_SYNCED ) ) as $option ) {
			delete_option( $option );
		}
		delete_transient( SECRETS::RETRY_TRANSIENT );
		$GLOBALS['conecom_fake_secrets']      = array();
		$GLOBALS['conecom_fake_secrets_fail'] = false;
		parent::tearDown();
	}

	/**
	 * Secrets currently stored by the fake API.
	 *
	 * @return array
	 */
	private function secrets() {
		return $GLOBALS['conecom_fake_secrets'];
	}

	/**
	 * Only credential keys are extracted, empty values are skipped.
	 */
	public function test_extract_credentials() {
		$value = array(
			'connector'       => 'holded',
			'connectors_meta' => array( 'holded' => array( 'type' => 'holded' ) ),
			'holded'          => array(
				'api'      => 'key-1',
				'password' => '',
				'company'  => 'ACME',
			),
			'odoo'            => array(
				'username' => 'admin',
				'password' => 'pass-2',
			),
		);

		$this->assertSame(
			array(
				'holded/api'    => 'key-1',
				'odoo/password' => 'pass-2',
			),
			SECRETS::extract( 'connect_ecommerce', $value )
		);
		$this->assertSame(
			array( 'vatsense-api-key' => 'vs' ),
			SECRETS::extract( 'connect_ecommerce_public', array( 'vatsense_api_key' => 'vs', 'vat_show' => 'yes' ) )
		);
		$this->assertSame(
			array( 'webhook/holded/signing-secret' => 'whsec_1' ),
			SECRETS::extract( WEBHOOK::OPTION_SECRETS, array( 'holded' => 'whsec_1' ) )
		);
		$this->assertSame( array(), SECRETS::extract( 'connect_ecommerce', 'not-an-array' ) );
	}

	/**
	 * Saving the settings writes both the option and the secret; removing a credential deletes the secret.
	 */
	public function test_option_writes_are_mirrored() {
		update_option( 'connect_ecommerce', array( 'holded' => array( 'api' => 'key-1' ) ) );

		$this->assertSame( array( 'woocommerce-es/holded/api' => 'key-1' ), $this->secrets() );
		$this->assertSame( 'key-1', get_option( 'connect_ecommerce' )['holded']['api'] );
		$this->assertSame( 'key-1', SECRETS::get( 'holded/api' ) );

		update_option( 'connect_ecommerce', array( 'holded' => array( 'api' => 'key-2' ) ) );
		$this->assertSame( 'key-2', SECRETS::get( 'holded/api' ) );

		update_option( 'connect_ecommerce', array( 'holded' => array( 'api' => '' ) ) );
		$this->assertSame( array(), $this->secrets() );
	}

	/**
	 * Deleting a credential option deletes its secrets.
	 */
	public function test_option_delete_removes_secrets() {
		update_option( 'connect_ecommerce_public', array( 'vatsense_api_key' => 'vs' ) );
		$this->assertArrayHasKey( 'woocommerce-es/vatsense-api-key', $this->secrets() );

		delete_option( 'connect_ecommerce_public' );
		$this->assertSame( array(), $this->secrets() );
	}

	/**
	 * Webhook tokens and signing secrets are mirrored and forgotten with the connector.
	 */
	public function test_webhook_credentials_are_mirrored() {
		$token = WEBHOOK::get_token( 'holded' );
		WEBHOOK::save_signing_secret( 'holded', 'whsec_1' );

		$this->assertSame( $token, SECRETS::get( 'webhook/holded/token' ) );
		$this->assertSame( 'whsec_1', SECRETS::get( 'webhook/holded/signing-secret' ) );

		WEBHOOK::forget_connector( 'holded' );
		$this->assertSame( array(), $this->secrets() );
	}

	/**
	 * Removing a connector through the direct database write also drops its secrets.
	 */
	public function test_forget_connector() {
		update_option( 'connect_ecommerce', array( 'holded' => array( 'api' => 'key-1', 'password' => 'p' ) ) );
		SECRETS::forget_connector( 'holded' );

		$this->assertSame( array(), $this->secrets() );
	}

	/**
	 * Without the API the options keep working and a full sync runs once it is back.
	 */
	public function test_sync_after_api_becomes_available() {
		add_filter( 'conecom_use_secrets_api', '__return_false' );
		update_option( 'connect_ecommerce', array( 'holded' => array( 'api' => 'key-1' ) ) );
		update_option( 'connect_ecommerce_public', array( 'vatsense_api_key' => 'vs' ) );

		$this->assertSame( array(), $this->secrets() );
		$this->assertFalse( SECRETS::maybe_sync_all() );

		remove_all_filters( 'conecom_use_secrets_api' );
		$this->assertTrue( SECRETS::maybe_sync_all() );
		$this->assertSame(
			array(
				'woocommerce-es/holded/api'       => 'key-1',
				'woocommerce-es/vatsense-api-key' => 'vs',
			),
			$this->secrets()
		);
		$this->assertSame( SECRETS::SCHEMA, get_option( SECRETS::OPTION_SYNCED ) );
	}

	/**
	 * A credential changed while the API is off flags a new sync, so the secret is not left stale.
	 */
	public function test_change_while_unavailable_resyncs() {
		update_option( 'connect_ecommerce', array( 'holded' => array( 'api' => 'key-1' ) ) );
		$this->assertTrue( SECRETS::sync_all() );

		add_filter( 'conecom_use_secrets_api', '__return_false' );
		update_option( 'connect_ecommerce', array( 'holded' => array( 'api' => 'key-2' ) ) );
		$this->assertFalse( get_option( SECRETS::OPTION_SYNCED ) );

		remove_all_filters( 'conecom_use_secrets_api' );
		$this->assertTrue( SECRETS::maybe_sync_all() );
		$this->assertSame( 'key-2', SECRETS::get( 'holded/api' ) );
	}

	/**
	 * A failing secret store never blocks the option write and retries later.
	 */
	public function test_store_failure_keeps_option() {
		$errors = array();
		add_action(
			'conecom_secrets_error',
			function ( $name ) use ( &$errors ) {
				$errors[] = $name;
			}
		);
		$GLOBALS['conecom_fake_secrets_fail'] = true;

		update_option( 'connect_ecommerce', array( 'holded' => array( 'api' => 'key-1' ) ) );

		$this->assertSame( 'key-1', get_option( 'connect_ecommerce' )['holded']['api'] );
		$this->assertContains( 'holded/api', $errors );
		$this->assertNull( SECRETS::get( 'holded/api' ) );
		$this->assertFalse( SECRETS::sync_all() );
		$this->assertNotFalse( get_transient( SECRETS::RETRY_TRANSIENT ) );
		$this->assertFalse( SECRETS::maybe_sync_all() );

		$GLOBALS['conecom_fake_secrets_fail'] = false;
		delete_transient( SECRETS::RETRY_TRANSIENT );
		$this->assertTrue( SECRETS::maybe_sync_all() );
		$this->assertSame( 'key-1', SECRETS::get( 'holded/api' ) );
	}
}

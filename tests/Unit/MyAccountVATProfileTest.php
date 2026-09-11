<?php
/**
 * Class MyAccountVATProfileTest
 *
 * Command: composer test -- --filter=MyAccountVATProfileTest
 *
 * @package Connect_Ecommerce
 */

use CLOSE\ConnectEcommerce\Frontend\MyAccount;

class MyAccountVATProfileTest extends WP_UnitTestCase {
	/**
	 * MyAccount instance.
	 *
	 * @var MyAccount
	 */
	private $my_account;

	/**
	 * Test user ID.
	 *
	 * @var int
	 */
	private $user_id;

	/**
	 * Set up test environment.
	 */
	public function setUp(): void {
		parent::setUp();

		update_option(
			'connect_ecommerce_public',
			array(
				'vat_show'      => 'yes',
				'vat_mandatory' => 'no',
			)
		);

		$this->user_id = self::factory()->user->create(
			array(
				'role' => 'customer',
			)
		);

		$this->my_account = new MyAccount(
			array(
				'settings_all' => array(),
				'connector'    => '',
				'settings'     => array(),
				'all_options'  => array(),
				'options'      => array(),
				'connapi_erp'  => null,
			)
		);
	}

	/**
	 * Tear down test environment.
	 */
	public function tearDown(): void {
		wp_delete_user( $this->user_id );
		parent::tearDown();
	}

	/**
	 * Saving a VAT number via save_vat_profile_field() persists it as user meta.
	 */
	public function test_save_vat_profile_field_stores_user_meta() {
		wp_set_current_user( $this->user_id );
		$_POST['billing_vat'] = 'ESB12345678';

		$this->my_account->save_vat_profile_field( $this->user_id );

		$stored = get_user_meta( $this->user_id, 'billing_vat', true );
		$this->assertEquals( 'ESB12345678', $stored );
	}

	/**
	 * save_vat_profile_field() sanitises the input (strips tags/scripts).
	 */
	public function test_save_vat_profile_field_sanitises_value() {
		wp_set_current_user( $this->user_id );
		$_POST['billing_vat'] = '<script>alert(1)</script>ESB12345678';

		$this->my_account->save_vat_profile_field( $this->user_id );

		$stored = get_user_meta( $this->user_id, 'billing_vat', true );
		$this->assertEquals( 'ESB12345678', $stored );
	}

	/**
	 * save_vat_profile_field() clears the meta when an empty value is submitted.
	 */
	public function test_save_vat_profile_field_clears_empty_value() {
		update_user_meta( $this->user_id, 'billing_vat', 'ESB12345678' );
		wp_set_current_user( $this->user_id );
		$_POST['billing_vat'] = '';

		$this->my_account->save_vat_profile_field( $this->user_id );

		$stored = get_user_meta( $this->user_id, 'billing_vat', true );
		$this->assertEmpty( $stored );
	}

	/**
	 * show_vat_profile_field() outputs an input with the current stored value.
	 */
	public function test_show_vat_profile_field_renders_existing_value() {
		update_user_meta( $this->user_id, 'billing_vat', 'ESB12345678' );
		$user = get_userdata( $this->user_id );

		ob_start();
		$this->my_account->show_vat_profile_field( $user );
		$output = ob_get_clean();

		$this->assertStringContainsString( 'billing_vat', $output );
		$this->assertStringContainsString( 'ESB12345678', $output );
	}

	/**
	 * show_vat_profile_field() renders an empty input when no meta is stored.
	 */
	public function test_show_vat_profile_field_renders_empty_when_no_meta() {
		$user = get_userdata( $this->user_id );

		ob_start();
		$this->my_account->show_vat_profile_field( $user );
		$output = ob_get_clean();

		$this->assertStringContainsString( 'billing_vat', $output );
		$this->assertStringContainsString( 'value=""', $output );
	}

	/**
	 * sync_vat_to_user_meta() writes billing_vat to user meta from checkout data.
	 */
	public function test_sync_vat_to_user_meta_saves_from_checkout_data() {
		$customer = $this->getMockBuilder( 'stdClass' )
			->addMethods( array( 'get_id' ) )
			->getMock();
		$customer->method( 'get_id' )->willReturn( $this->user_id );

		$data = array( 'billing_vat' => 'ESB87654321' );

		$this->my_account->sync_vat_to_user_meta( $customer, $data );

		$stored = get_user_meta( $this->user_id, 'billing_vat', true );
		$this->assertEquals( 'ESB87654321', $stored );
	}

	/**
	 * sync_vat_to_user_meta() does nothing when customer has no user ID (guest).
	 */
	public function test_sync_vat_to_user_meta_skips_guest() {
		$customer = $this->getMockBuilder( 'stdClass' )
			->addMethods( array( 'get_id' ) )
			->getMock();
		$customer->method( 'get_id' )->willReturn( 0 );

		$data = array( 'billing_vat' => 'ESB87654321' );

		$this->my_account->sync_vat_to_user_meta( $customer, $data );

		// No user meta should be written for a guest (user ID 0).
		$stored = get_user_meta( 0, 'billing_vat', true );
		$this->assertEmpty( $stored );
	}

	/**
	 * Profile hooks are registered when vat_show is enabled.
	 */
	public function test_profile_hooks_registered_when_vat_enabled() {
		$this->assertGreaterThan(
			0,
			has_action( 'show_user_profile', array( $this->my_account, 'show_vat_profile_field' ) )
		);
		$this->assertGreaterThan(
			0,
			has_action( 'edit_user_profile', array( $this->my_account, 'show_vat_profile_field' ) )
		);
		$this->assertGreaterThan(
			0,
			has_action( 'personal_options_update', array( $this->my_account, 'save_vat_profile_field' ) )
		);
		$this->assertGreaterThan(
			0,
			has_action( 'edit_user_profile_update', array( $this->my_account, 'save_vat_profile_field' ) )
		);
	}

	/**
	 * Profile hooks are NOT registered when vat_show is disabled.
	 */
	public function test_profile_hooks_not_registered_when_vat_disabled() {
		update_option( 'connect_ecommerce_public', array( 'vat_show' => 'no' ) );

		$instance = new MyAccount(
			array(
				'settings_all' => array(),
				'connector'    => '',
				'settings'     => array(),
				'all_options'  => array(),
				'options'      => array(),
				'connapi_erp'  => null,
			)
		);

		$this->assertFalse(
			has_action( 'show_user_profile', array( $instance, 'show_vat_profile_field' ) )
		);
		$this->assertFalse(
			has_action( 'personal_options_update', array( $instance, 'save_vat_profile_field' ) )
		);
	}
}

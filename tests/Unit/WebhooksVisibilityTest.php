<?php
/**
 * Tests that the webhooks panel is opt-in per connector.
 *
 * Command: composer test -- --filter=WebhooksVisibilityTest
 *
 * @package Connect_Ecommerce
 */

use CLOSE\ConnectEcommerce\Admin\Settings;
use CLOSE\ConnectEcommerce\Admin\Webhooks;

/**
 * Class WebhooksVisibilityTest.
 */
class WebhooksVisibilityTest extends WP_UnitTestCase {

	/**
	 * Tear down environment.
	 *
	 * @return void
	 */
	public function tearDown(): void {
		delete_option( 'connect_ecommerce' );
		parent::tearDown();
	}

	/**
	 * Builds a connector context like HELPER::get_connectors() does.
	 *
	 * @param array $type_options Options registered by the connector type.
	 * @return array
	 */
	private function make_connector( $type_options ) {
		return array(
			'id'          => 'test-instance',
			'connector'   => 'test',
			'all_options' => array( 'test' => $type_options ),
		);
	}

	/**
	 * Builds the Settings screen data for a single, unconnected connector.
	 *
	 * @param array $type_options Options registered by the connector type.
	 * @return Settings
	 */
	private function make_settings( $type_options ) {
		return new Settings(
			array(
				'active'       => 'test-instance',
				'settings_all' => array(),
				'meta'         => array(
					'test-instance' => array(
						'type'      => 'test',
						'label'     => 'Test',
						'workflows' => array(
							'products' => 'yes',
							'orders'   => 'yes',
						),
						'status'    => 'active',
					),
				),
				'items'        => array(
					'test-instance' => array_merge(
						$this->make_connector( $type_options ),
						array(
							'settings'    => array(),
							'options'     => $type_options,
							// Not callable check_can_sync(), so the connector shows as not connected.
							'connapi_erp' => new stdClass(),
						)
					),
				),
			)
		);
	}

	/**
	 * Data provider for is_enabled().
	 *
	 * @return array
	 */
	public function data_is_enabled() {
		return array(
			'opted in'        => array( array( 'name' => 'Test', 'webhooks' => true ), true ),
			'explicit false'  => array( array( 'name' => 'Test', 'webhooks' => false ), false ),
			'key missing'     => array( array( 'name' => 'Test' ), false ),
			'empty options'   => array( array(), false ),
			'null value'      => array( array( 'webhooks' => null ), false ),
		);
	}

	/**
	 * Only connectors that register webhooks => true are enabled.
	 *
	 * @dataProvider data_is_enabled
	 *
	 * @param array $type_options Options registered by the connector type.
	 * @param bool  $expected     Expected result.
	 * @return void
	 */
	public function test_is_enabled( $type_options, $expected ) {
		$this->assertSame( $expected, Webhooks::is_enabled( $this->make_connector( $type_options ) ) );
	}

	/**
	 * Missing connector context data is treated as disabled.
	 *
	 * @return void
	 */
	public function test_is_enabled_is_false_without_connector_context() {
		$this->assertFalse( Webhooks::is_enabled( array() ) );
		$this->assertFalse( Webhooks::is_enabled( array( 'connector' => 'unknown', 'all_options' => array() ) ) );
	}

	/**
	 * The option of another connector type does not enable this one.
	 *
	 * @return void
	 */
	public function test_is_enabled_reads_only_the_connector_type_options() {
		$connector = array(
			'id'          => 'test-instance',
			'connector'   => 'test',
			'all_options' => array(
				'test'  => array( 'name' => 'Test' ),
				'other' => array( 'webhooks' => true ),
			),
		);

		$this->assertFalse( Webhooks::is_enabled( $connector ) );
	}

	/**
	 * The product import screen shows the webhooks panel for opted-in connectors.
	 *
	 * @return void
	 */
	public function test_import_screen_shows_webhooks_when_enabled() {
		$settings = $this->make_settings( array( 'name' => 'Test', 'webhooks' => true ) );

		ob_start();
		$settings->page_get_sync( 'sync_products', 'test-instance' );
		$output = ob_get_clean();

		$this->assertStringContainsString( 'conecom-webhooks-fieldset', $output );
		$this->assertStringContainsString( 'conecom-webhook-url', $output );
	}

	/**
	 * The product import screen hides the webhooks panel when the connector does not opt in.
	 *
	 * @return void
	 */
	public function test_import_screen_hides_webhooks_when_not_enabled() {
		$settings = $this->make_settings( array( 'name' => 'Test' ) );

		ob_start();
		$settings->page_get_sync( 'sync_products', 'test-instance' );
		$output = ob_get_clean();

		$this->assertStringNotContainsString( 'conecom-webhooks-fieldset', $output );
		$this->assertStringNotContainsString( 'conecom-webhook-url', $output );
	}
}

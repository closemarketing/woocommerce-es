<?php
/**
 * Class CreateProductSimpleTest
 *
 * @package Connect_Ecommerce
 */

use CLOSE\ConnectEcommerce\Helpers\PROD;

/**
 * Create Product Simple without Errors.
 *
 * @group woocommerce
 */
class CreateProductSimpleTest extends WP_UnitTestCase {

	/**
	 * Settings for testing
	 */
	protected $settings;
	
	/**
	 * API connection for testing
	 */
	protected $connapi_erp;
	
	/**
	 * Set up test environment.
	 */
	public function setUp(): void {
		parent::setUp();

		$this->settings = [
			'test_mode' => true,
		];
		
		// Mock API connection
		$this->connapi_erp = null;
	}

	/**
	 * Create Product Simple without Errors.
	 */
	public function test_create_product_simple_without_errors() {
		$item_path = UNIT_TESTS_DATA_PLUGIN_DIR . 'Products/simple.json';
		$item      = file_get_contents( $item_path );
		$item      = json_decode( $item, true )[0];
		
		$result_sync = PROD::sync_product_item( $this->settings, $item, $this->connapi_erp );
		
		// Verificar resultado
		$this->assertNotNull($result_sync, 'El resultado de sincronización no debería ser nulo');
	}
}

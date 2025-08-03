<?php
/**
 * Class CreateProductSimpleTest
 *
 * @package Connect_Ecommerce
 */

use CLOSE\ConnectEcommerce\Helpers\PROD;

/**
 * Sample test case.
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
		// Initialize test settings
		$this->settings = [
			// Add your test settings here
			'test_mode' => true,
		];
		
		// Mock API connection
		$this->connapi_erp = null; // Replace with mock if needed
	}

	/**
	 * A single example test.
	 */
	public function test_sample() {
		$item = UNIT_TESTS_DATA_PLUGIN_DIR . '/Products/simple.json';
		$generate_ai = false;
		$product_id = 0;
		
		// Verificar que el archivo existe
		$this->assertTrue(file_exists($item), "El archivo de prueba $item no existe");
		
		// Si el archivo no existe, no continuamos
		if (!file_exists($item)) {
			return;
		}
		
		$result_sync = PROD::sync_product_item( $this->settings, $item, $this->connapi_erp, $generate_ai, $product_id );
		
		// Verificar resultado
		$this->assertNotNull($result_sync, 'El resultado de sincronización no debería ser nulo');
	}
}

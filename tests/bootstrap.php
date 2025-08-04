<?php
/**
 * PHPUnit bootstrap file.
 *
 * @package Connect_Ecommerce
 */


define( 'TESTS_PLUGIN_DIR', dirname( __DIR__ ) );
define( 'UNIT_TESTS_DATA_PLUGIN_DIR', TESTS_PLUGIN_DIR . '/tests/Data/' );

$_tests_dir = getenv( 'WP_TESTS_DIR' );

if ( ! $_tests_dir ) {
	$_tests_dir = rtrim( sys_get_temp_dir(), '/\\' ) . '/wordpress-tests-lib';
}

// Forward custom PHPUnit Polyfills configuration to PHPUnit bootstrap file.
$_phpunit_polyfills_path = getenv( 'WP_TESTS_PHPUNIT_POLYFILLS_PATH' );
if ( false !== $_phpunit_polyfills_path ) {
	define( 'WP_TESTS_PHPUNIT_POLYFILLS_PATH', $_phpunit_polyfills_path );
}

if ( ! file_exists( "{$_tests_dir}/includes/functions.php" ) ) {
	echo "Could not find {$_tests_dir}/includes/functions.php, have you run bin/install-wp-tests.sh ?" . PHP_EOL; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	exit( 1 );
}

// Give access to tests_add_filter() function.
require_once "{$_tests_dir}/includes/functions.php";

/**
 * Manually load the plugin being tested and its dependencies.
 */
function _manually_load_plugin() {
	global $wpdb;
	
	// Load WooCommerce first
	$woocommerce_path = '';
	
	// In GitHub Actions
	if ( getenv( 'GITHUB_WORKSPACE' ) ) {
		$woocommerce_path = dirname( getenv( 'GITHUB_WORKSPACE' ) ) . '/woocommerce/woocommerce.php';
	} 
	// In local environment
	else {
		$woocommerce_path = '../woocommerce/woocommerce.php';
	}
	
	// Check if the file exists
	if ( !file_exists( $woocommerce_path ) ) {
		echo "WooCommerce not found at {$woocommerce_path}. Please verify the installation." . PHP_EOL;
		exit( 1 );
	}
	
	require_once $woocommerce_path;
	
	// Ensure WooCommerce tables are created correctly
	if ( isset( $GLOBALS['woocommerce'] ) && is_object( $GLOBALS['woocommerce'] ) ) {
		// Create WooCommerce tables
		$GLOBALS['woocommerce']->install();
		
		// If we're on PHP 8.0 or earlier, run some additional actions
		// to avoid compatibility issues
		if ( version_compare( PHP_VERSION, '8.1', '<' ) ) {
			if ( ! get_option( 'woocommerce_db_version' ) ) {
				add_option( 'woocommerce_db_version', WC()->version );
			}
			
			// Enable logging for debugging
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				error_log( 'WooCommerce version: ' . WC()->version );
				error_log( 'PHP version: ' . PHP_VERSION );
			}
		}
	}

	// Load our plugin after WooCommerce
	require dirname( dirname( __FILE__ ) ) . '/connect-ecommerce.php';
}

tests_add_filter( 'muplugins_loaded', '_manually_load_plugin' );

// Start up the WP testing environment.
require "{$_tests_dir}/includes/bootstrap.php";

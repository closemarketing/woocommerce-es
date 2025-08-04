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
		// Enable logging for debugging
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			error_log( 'WooCommerce version: ' . WC()->version );
			error_log( 'PHP version: ' . PHP_VERSION );
		}
		
		// Try to install WooCommerce tables safely
		try {
			// Method 1: Try direct install() method if available
			if ( method_exists( $GLOBALS['woocommerce'], 'install' ) ) {
				$GLOBALS['woocommerce']->install();
			} 
			// Method 2: Try WC_Install class
			else if ( file_exists( dirname( $woocommerce_path ) . '/includes/class-wc-install.php' ) ) {
				require_once dirname( $woocommerce_path ) . '/includes/class-wc-install.php';
				if ( class_exists( 'WC_Install' ) ) {
					WC_Install::install();
					
					// Run specific schema installation functions if they exist
					if ( method_exists( 'WC_Install', 'create_tables' ) ) {
						WC_Install::create_tables();
					}
				}
			}
			
			// Set database version option if needed
			if ( ! get_option( 'woocommerce_db_version' ) && function_exists( 'WC' ) ) {
				add_option( 'woocommerce_db_version', WC()->version );
			}
			
			// Additional compatibility for specific errors with webhook tables
			global $wpdb;
			$webhook_table = $wpdb->prefix . 'wc_webhooks';
			if ( $wpdb->get_var( "SHOW TABLES LIKE '$webhook_table'" ) != $webhook_table ) {
				// If webhook table doesn't exist, create a minimal version to avoid errors
				$wpdb->query(
					"CREATE TABLE IF NOT EXISTS {$wpdb->prefix}wc_webhooks (
					  webhook_id bigint(20) NOT NULL AUTO_INCREMENT,
					  status varchar(200) NOT NULL,
					  PRIMARY KEY (webhook_id)
					) {$wpdb->get_charset_collate()};"
				);
			}
			
		} catch ( Exception $e ) {
			// Log any errors but don't break the test suite
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				error_log( 'Error installing WooCommerce tables: ' . $e->getMessage() );
			}
		}
	}

	// Load our plugin after WooCommerce
	require dirname( dirname( __FILE__ ) ) . '/connect-ecommerce.php';
}

tests_add_filter( 'muplugins_loaded', '_manually_load_plugin' );

// Start up the WP testing environment.
require "{$_tests_dir}/includes/bootstrap.php";

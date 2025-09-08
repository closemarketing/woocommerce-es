<?php
/**
 * PHPStan Bootstrap File
 * 
 * This file defines constants and functions that PHPStan needs to understand
 * but are not available during static analysis.
 */

// Define plugin constants that are used throughout the codebase
if (!defined('CONECOM_PLUGIN_URL')) {
    define('CONECOM_PLUGIN_URL', 'http://localhost/wp-content/plugins/connect-ecommerce/');
}

if (!defined('CONECOM_VERSION')) {
    define('CONECOM_VERSION', '1.0.0');
}

if (!defined('CONECOM_FILE')) {
    define('CONECOM_FILE', __FILE__);
}

// Define WordPress constants that might be missing
if (!defined('DOING_AJAX')) {
    define('DOING_AJAX', false);
}

if (!defined('WP_DEBUG')) {
    define('WP_DEBUG', false);
}

if (!defined('ABSPATH')) {
    define('ABSPATH', '/path/to/wordpress/');
}

// Mock WordPress functions that PHPStan can't find
if (!function_exists('wp_doing_ajax')) {
    function wp_doing_ajax() {
        return defined('DOING_AJAX') && DOING_AJAX;
    }
}

if (!function_exists('conecom_get_options')) {
    function conecom_get_options() {
        return [];
    }
}

// Mock WooCommerce functions
if (!function_exists('wc_get_logger')) {
    function wc_get_logger() {
        return new class {
            public function info($message) {}
            public function error($message) {}
            public function warning($message) {}
            public function debug($message) {}
        };
    }
}

if (!function_exists('wc_get_product')) {
    function wc_get_product($id = null) {
        return null;
    }
}

if (!function_exists('wc_get_order')) {
    function wc_get_order($id = null) {
        return null;
    }
}

if (!function_exists('wc_get_orders')) {
    function wc_get_orders($args = []) {
        return [];
    }
}

if (!function_exists('wc_get_page_screen_id')) {
    function wc_get_page_screen_id($page) {
        return 'woocommerce_page_' . $page;
    }
}

if (!function_exists('wc_get_endpoint_url')) {
    function wc_get_endpoint_url($endpoint, $value = '', $permalink = '') {
        return '';
    }
}

if (!function_exists('wc_get_page_id')) {
    function wc_get_page_id($page) {
        return 0;
    }
}

if (!function_exists('wc_get_page_permalink')) {
    function wc_get_page_permalink($page) {
        return '';
    }
}

if (!function_exists('wc_sanitize_taxonomy_name')) {
    function wc_sanitize_taxonomy_name($taxonomy) {
        return sanitize_title($taxonomy);
    }
}

if (!function_exists('wc_get_attribute_taxonomies')) {
    function wc_get_attribute_taxonomies() {
        return [];
    }
}

if (!function_exists('wc_attribute_taxonomy_id_by_name')) {
    function wc_attribute_taxonomy_id_by_name($name) {
        return 0;
    }
}

if (!function_exists('wc_attribute_taxonomy_name')) {
    function wc_attribute_taxonomy_name($name) {
        return 'pa_' . $name;
    }
}

if (!function_exists('wc_create_attribute')) {
    function wc_create_attribute($args) {
        return 0;
    }
}

if (!function_exists('get_woocommerce_currency')) {
    function get_woocommerce_currency() {
        return 'USD';
    }
}

if (!function_exists('is_account_page')) {
    function is_account_page() {
        return false;
    }
}

// Mock WordPress functions
if (!function_exists('sanitize_title')) {
    function sanitize_title($title) {
        return strtolower(preg_replace('/[^a-zA-Z0-9\-_]/', '-', $title));
    }
}

// Mock WP_CLI class
if (!class_exists('WP_CLI')) {
    class WP_CLI {
        public static function line($message) {
            echo $message . "\n";
        }
    }
}

// Mock WooCommerce classes
if (!class_exists('WC_Logger')) {
    class WC_Logger {
        public function info($message) {}
        public function error($message) {}
        public function warning($message) {}
        public function debug($message) {}
    }
}

if (!class_exists('WC_Product')) {
    class WC_Product {
        public function __construct($product = 0) {}
    }
}

if (!class_exists('WC_Product_Variable')) {
    class WC_Product_Variable extends WC_Product {
        public function __construct($product = 0) {
            parent::__construct($product);
        }
    }
}

if (!class_exists('WC_Product_Variation')) {
    class WC_Product_Variation extends WC_Product {
        public function __construct($product = 0) {
            parent::__construct($product);
        }
    }
}

if (!class_exists('WC_Order')) {
    class WC_Order {
        public function __construct($order = 0) {}
    }
}

if (!class_exists('WC_Tax')) {
    class WC_Tax {
        public function __construct() {}
    }
}

if (!class_exists('WC_Coupon')) {
    class WC_Coupon {
        public function __construct($code = '') {}
    }
}

if (!class_exists('WC_Product_Attribute')) {
    class WC_Product_Attribute {
        public function __construct() {}
    }
}

// Mock Action Scheduler function
if (!function_exists('as_schedule_recurring_action')) {
    function as_schedule_recurring_action($timestamp, $interval_in_seconds, $hook, $args = [], $group = '') {
        return true;
    }
}

<?php

// Define WordPress constants needed by the plugin
define('WP_CONTENT_DIR', __DIR__ . '/tmp');
define('MINUTE_IN_SECONDS', 60);
define('ANAR_DEBUG', false);
define('ANAR_DB_NAME', 'anar_test_table');

// Create temporary directory for logs if it doesn't exist
if (!file_exists(__DIR__ . '/tmp')) {
    mkdir(__DIR__ . '/tmp', 0777, true);
}

// Mock WordPress functions
if (!function_exists('get_transient')) {
    function get_transient($key) {
        global $wp_transients;
        return $wp_transients[$key] ?? false;
    }
    
    function set_transient($key, $value, $expiration = 0) {
        global $wp_transients;
        $wp_transients[$key] = $value;
        return true;
    }
    
    function delete_transient($key) {
        global $wp_transients;
        unset($wp_transients[$key]);
        return true;
    }
}

if (!function_exists('wp_verify_nonce')) {
    function wp_verify_nonce($nonce, $action = -1) {
        return true;
    }
}

if (!function_exists('current_user_can')) {
    function current_user_can($capability) {
        return true;
    }
}

if (!function_exists('add_action')) {
    function add_action($hook, $callback, $priority = 10, $accepted_args = 1) {
        return true;
    }
}

if (!function_exists('get_option')) {
    function get_option($option, $default = false) {
        return $default;
    }
}

if (!function_exists('update_option')) {
    function update_option($option, $value, $autoload = null) {
        return true;
    }
}

if (!function_exists('delete_option')) {
    function delete_option($option) {
        return true;
    }
}

if (!function_exists('awca_log')) {
    function awca_log($message) {
        // Do nothing in tests
    }
}

// Mock wpdb class
if (!class_exists('wpdb')) {
    class wpdb {
        public $prefix = 'wp_';
        
        public function prepare($query, ...$args) {
            return $query;
        }
        
        public function get_results($query, $output = OBJECT) {
            return [];
        }
        
        public function get_row($query, $output = OBJECT, $y = 0) {
            return null;
        }
    }
}

// Initialize global $wpdb
global $wpdb;
$wpdb = new wpdb();
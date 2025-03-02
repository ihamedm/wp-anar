<?php
namespace Anar\Core;

use Anar\ProductData;
use Anar\SyncTools;

class Reports{
    private static $instance;

    public static function get_instance(){
        if( null === self::$instance ){
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function __construct(){
        add_action( 'wp_ajax_anar_get_system_reports', [ $this, 'get_system_reports'] );
    }

    public function get_system_reports() {
        // WordPress Information
        $wp_info = sprintf(
            "WordPress Information:\nVersion: %s\nLanguage: %s\nCharset: %s\nDebug mode: %s\nHome URL: %s\nSite URL: %s\nWordPress Path: %s\nWordPress Content Path: %s\n\n",
            get_bloginfo('version'),
            get_bloginfo('language'),
            get_bloginfo('charset'),
            (defined('WP_DEBUG') && WP_DEBUG) ? 'Enabled' : 'Disabled',
            home_url(),
            site_url(),
            ABSPATH,
            WP_CONTENT_DIR
        );

        // Theme Information
        $theme = wp_get_theme();
        $theme_info = sprintf(
            "Theme Information:\nName: %s\nVersion: %s\nAuthor: %s\nChild Theme: %s\nTheme Directory: %s\n\n",
            $theme->get('Name'),
            $theme->get('Version'),
            $theme->get('Author'),
            is_child_theme() ? 'Yes' : 'No',
            $theme->get_stylesheet_directory()
        );

        // Plugins Information
        $plugins = get_plugins();
        $plugins_info = "Plugins Information:\n";
        foreach ($plugins as $plugin_file => $plugin_data) {
            $plugins_info .= sprintf(
                "%s (v%s) by %s - %s\n",
                $plugin_data['Name'],
                $plugin_data['Version'],
                $plugin_data['Author'],
                is_plugin_active($plugin_file) ? 'Active' : 'Inactive'
            );
        }

        // Server Environment
        $server_info = sprintf(
            "\nServer Environment:\nPHP Version: %s\nServer Software: %s\nMySQL Version: %s\nPHP Time Limit: %s\nPHP Input Vars: %s\nPHP Memory Limit: %s\nPHP Max Upload Size: %s\nFile Upload Permission: %s\nHTTPS: %s\n",
            phpversion(),
            $_SERVER['SERVER_SOFTWARE'],
            $GLOBALS['wpdb']->db_version(),
            ini_get('max_execution_time'),
            ini_get('max_input_vars'),
            ini_get('memory_limit'),
            ini_get('upload_max_filesize'),
            is_writable(__FILE__) ? 'Writable' : 'Not Writable',
            is_ssl() ? 'Yes' : 'No'
        );

        wp_send_json_success(
            [
                'reports' => $wp_info . $theme_info . $plugins_info . $server_info . $this->get_some_anar_data(),
                'toast' => 'گزارش سیستم دریافت شد'
            ]
        );

    }

    public function get_some_anar_data(){
        $sync_tools = SyncTools::get_instance();
        $product_data = new ProductData();

        $anar_info = sprintf(
            "\nAnar Data:\nAnar products count: %s\nNot synced last hour ago: %s",
            $product_data->count_anar_products()   ,
            $sync_tools->found_not_synced_products(1),
        );

        return $anar_info;
    }
}
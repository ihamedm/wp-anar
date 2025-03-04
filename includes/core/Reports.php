<?php
namespace Anar\Core;

use Anar\OrderData;
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
            "WordPress Information:\nVersion: %s\nLanguage: %s\nTimeZone: %s\nCharset: %s\nDebug mode: %s\nHome URL: %s\nSite URL: %s\nWordPress Path: %s\nWordPress Content Path: %s\n\n",
            get_bloginfo('version'),
            get_bloginfo('language'),
            wp_timezone_string(),
            get_bloginfo('charset'),
            (defined('WP_DEBUG') && WP_DEBUG) ? 'Enabled' : 'Disabled',
            home_url(),
            site_url(),
            ABSPATH,
            WP_CONTENT_DIR
        );

        // Woocommerce Information
        $wc_info = sprintf(
            "Woocommerce Information:\nHPOS: %s\nAnar Orders: %s\nAnar Register Orders: %s\n\n",
           awca_is_hpos_enable() ? 'Yes' : 'No',
                    OrderData::count_anar_orders(),
                    OrderData::count_anar_orders_submited()
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
                'reports' => $wp_info . $wc_info . $theme_info . $plugins_info . $server_info .
                    $this->get_some_anar_data()
                    .SystemStatus::get_cron_health_report()
                    .SystemStatus::get_db_health_report()
                    .Logger::get_logs_status_report()
                ,
                'toast' => 'گزارش سیستم دریافت شد'
            ]
        );

    }

    public function get_some_anar_data(){
        $sync_tools = SyncTools::get_instance();
        $product_data = new ProductData();
        $sync = \Anar\Sync::get_instance();
        $last_sync_time = mysql2date('j F Y' . ' - ' . 'H:i', $sync->getLastSyncTime());
        $sync->fullSync = true;
        $last_full_sync_time = mysql2date('j F Y' . ' - ' . 'H:i', $sync->getLastSyncTime());

        $anar_info = sprintf(
            "\nAnar Data:\nAnar products count: %s\nNot synced last hour ago: %s\nLast fullSync: %s\nLast partial sync: %s\n",
            $product_data->count_anar_products()   ,
            $sync_tools->found_not_synced_products(1),
            $last_full_sync_time,
            $last_sync_time,
        );

        return $anar_info;
    }
}
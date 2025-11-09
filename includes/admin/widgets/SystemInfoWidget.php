<?php

namespace Anar\Admin\Widgets;

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

/**
 * System Information Report Widget
 */
class SystemInfoWidget extends AbstractReportWidget
{
    protected function init()
    {
        $this->widget_id = 'anar-system-info-widget';
        $this->title = 'اطلاعات سیستم';
        $this->description = 'نمایش اطلاعات کلی سیستم وردپرس و انار';
        $this->icon = '<span class="dashicons dashicons-info"></span>';
        $this->ajax_action = 'anar_get_system_info';
        $this->button_text = 'دریافت اطلاعات سیستم';
        $this->button_class = 'button-primary';

        // Register AJAX handler
        add_action('wp_ajax_' . $this->ajax_action, [$this, 'handle_ajax']);
    }

    protected function get_report_data()
    {
        global $wpdb;
        
        // Get WordPress info
        $wp_info = [
            'version' => get_bloginfo('version'),
            'multisite' => is_multisite() ? 'بله' : 'خیر',
            'language' => get_locale(),
            'timezone' => wp_timezone_string(),
            'memory_limit' => ini_get('memory_limit'),
            'max_execution_time' => ini_get('max_execution_time')
        ];

        // Get WooCommerce info
        $wc_info = [];
        if (class_exists('WooCommerce')) {
            $wc_info = [
                'version' => WC()->version,
                'currency' => get_woocommerce_currency(),
                'products_count' => wp_count_posts('product')->publish,
                'orders_count' => wp_count_posts('shop_order')->publish
            ];
        }

        // Get server info
        $server_info = [
            'php_version' => PHP_VERSION,
            'mysql_version' => $wpdb->db_version(),
            'server_software' => $_SERVER['SERVER_SOFTWARE'] ?? 'نامشخص',
            'upload_max_filesize' => ini_get('upload_max_filesize'),
            'post_max_size' => ini_get('post_max_size')
        ];

        // Get Anar info
        $anar_info = [
            'plugin_version' => ANAR_PLUGIN_VERSION,
            'anar_products' => $this->get_anar_products_count(),
            'last_sync' => $this->get_last_sync_time()
        ];

        return [
            'wp_info' => $wp_info,
            'wc_info' => $wc_info,
            'server_info' => $server_info,
            'anar_info' => $anar_info,
            'timestamp' => current_time('Y-m-d H:i:s')
        ];
    }

    private function get_anar_products_count()
    {
        global $wpdb;
        
        $count = $wpdb->get_var("
            SELECT COUNT(DISTINCT p.ID)
            FROM {$wpdb->posts} p
            INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
            WHERE p.post_type = 'product'
              AND p.post_status = 'publish'
              AND pm.meta_key = '_anar_sku'
              AND pm.meta_value != ''
        ");
        
        return intval($count);
    }

    private function get_last_sync_time()
    {
        $last_sync = anar_get_last_regular_sync_time();
        
        if ($last_sync) {
            return mysql2date('j F Y - H:i', $last_sync);
        }
        
        return 'هیچ‌گاه';
    }
}

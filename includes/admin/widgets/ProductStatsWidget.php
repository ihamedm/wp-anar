<?php

namespace Anar\Admin\Widgets;

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

/**
 * Product Statistics Report Widget
 */
class ProductStatsWidget extends AbstractReportWidget
{
    protected function init()
    {
        $this->widget_id = 'anar-product-stats-widget';
        $this->title = 'آمار محصولات';
        $this->description = 'نمایش آمار کلی محصولات انار و وضعیت همگام‌سازی';
        $this->icon = '<span class="dashicons dashicons-products"></span>';
        $this->ajax_action = 'anar_get_product_stats';
        $this->button_text = 'دریافت آمار محصولات';
        $this->button_class = 'button-secondary';

        // Register AJAX handler
        add_action('wp_ajax_' . $this->ajax_action, [$this, 'handle_ajax']);
    }

    protected function get_report_data()
    {
        global $wpdb;

        $stats = [
            'total_products' => $this->get_total_products(),
            'anar_products' => $this->get_anar_products_stats(),
            'sync_status' => $this->get_sync_status(),
            'product_status' => $this->get_product_status_stats(),
            'recent_activity' => $this->get_recent_activity()
        ];

        return $stats;
    }

    private function get_total_products()
    {
        global $wpdb;

        $total = wp_count_posts('product');
        
        return [
            'published' => intval($total->publish),
            'draft' => intval($total->draft),
            'pending' => intval($total->pending),
            'private' => intval($total->private),
            'trash' => intval($total->trash)
        ];
    }

    private function get_anar_products_stats()
    {
        global $wpdb;

        // Total Anar products
        $total_anar = $wpdb->get_var("
            SELECT COUNT(DISTINCT p.ID)
            FROM {$wpdb->posts} p
            INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
            WHERE p.post_type = 'product'
              AND pm.meta_key = '_anar_sku'
              AND pm.meta_value != ''
        ");

        // Products with prices
        $with_prices = $wpdb->get_var("
            SELECT COUNT(DISTINCT p.ID)
            FROM {$wpdb->posts} p
            INNER JOIN {$wpdb->postmeta} sku ON p.ID = sku.post_id AND sku.meta_key = '_anar_sku'
            INNER JOIN {$wpdb->postmeta} prices ON p.ID = prices.post_id AND prices.meta_key = '_anar_prices'
            WHERE p.post_type = 'product'
              AND sku.meta_value != ''
        ");

        // Products with zero profit
        $zero_profit = $wpdb->get_var("
            SELECT COUNT(DISTINCT p.ID)
            FROM {$wpdb->posts} p
            INNER JOIN {$wpdb->postmeta} sku ON p.ID = sku.post_id AND sku.meta_key = '_anar_sku'
            INNER JOIN {$wpdb->postmeta} prices ON p.ID = prices.post_id AND prices.meta_key = '_anar_prices'
            WHERE p.post_type = 'product'
              AND sku.meta_value != ''
              AND prices.meta_value LIKE '%s:14:\"resellerProfit\";i:0;%'
        ");

        // Deprecated products
        $deprecated = $wpdb->get_var("
            SELECT COUNT(DISTINCT p.ID)
            FROM {$wpdb->posts} p
            INNER JOIN {$wpdb->postmeta} deprecated ON p.ID = deprecated.post_id
            WHERE p.post_type = 'product'
              AND deprecated.meta_key = '_anar_deprecated'
        ");

        return [
            'total' => intval($total_anar),
            'with_prices' => intval($with_prices),
            'zero_profit' => intval($zero_profit),
            'deprecated' => intval($deprecated)
        ];
    }

    private function get_sync_status()
    {
        global $wpdb;

        // Recently synced (last hour)
        $recently_synced = $wpdb->get_var("
            SELECT COUNT(DISTINCT p.ID)
            FROM {$wpdb->posts} p
            INNER JOIN {$wpdb->postmeta} sku ON p.ID = sku.post_id AND sku.meta_key = '_anar_sku'
            INNER JOIN {$wpdb->postmeta} sync ON p.ID = sync.post_id AND sync.meta_key = '_anar_last_sync_time'
            WHERE p.post_type = 'product'
              AND sku.meta_value != ''
              AND sync.meta_value > DATE_SUB(NOW(), INTERVAL 1 HOUR)
        ");

        // Not synced recently (more than 1 hour)
        $not_synced_recently = $wpdb->get_var("
            SELECT COUNT(DISTINCT p.ID)
            FROM {$wpdb->posts} p
            INNER JOIN {$wpdb->postmeta} sku ON p.ID = sku.post_id AND sku.meta_key = '_anar_sku'
            LEFT JOIN {$wpdb->postmeta} sync ON p.ID = sync.post_id AND sync.meta_key = '_anar_last_sync_time'
            WHERE p.post_type = 'product'
              AND sku.meta_value != ''
              AND (sync.meta_value IS NULL OR sync.meta_value < DATE_SUB(NOW(), INTERVAL 1 HOUR))
        ");

        // Never synced
        $never_synced = $wpdb->get_var("
            SELECT COUNT(DISTINCT p.ID)
            FROM {$wpdb->posts} p
            INNER JOIN {$wpdb->postmeta} sku ON p.ID = sku.post_id AND sku.meta_key = '_anar_sku'
            LEFT JOIN {$wpdb->postmeta} sync ON p.ID = sync.post_id AND sync.meta_key = '_anar_last_sync_time'
            WHERE p.post_type = 'product'
              AND sku.meta_value != ''
              AND sync.meta_value IS NULL
        ");

        return [
            'recently_synced' => intval($recently_synced),
            'not_synced_recently' => intval($not_synced_recently),
            'never_synced' => intval($never_synced)
        ];
    }

    private function get_product_status_stats()
    {
        global $wpdb;

        $status_stats = $wpdb->get_results("
            SELECT 
                p.post_status,
                COUNT(*) as count
            FROM {$wpdb->posts} p
            INNER JOIN {$wpdb->postmeta} sku ON p.ID = sku.post_id AND sku.meta_key = '_anar_sku'
            WHERE p.post_type = 'product'
              AND sku.meta_value != ''
            GROUP BY p.post_status
            ORDER BY count DESC
        ", ARRAY_A);

        $formatted_stats = [];
        foreach ($status_stats as $stat) {
            $formatted_stats[$stat['post_status']] = intval($stat['count']);
        }

        return $formatted_stats;
    }

    private function get_recent_activity()
    {
        global $wpdb;

        // Products created today
        $created_today = $wpdb->get_var("
            SELECT COUNT(*)
            FROM {$wpdb->posts}
            WHERE post_type = 'product'
              AND DATE(post_date) = CURDATE()
        ");

        // Products updated today
        $updated_today = $wpdb->get_var("
            SELECT COUNT(*)
            FROM {$wpdb->posts}
            WHERE post_type = 'product'
              AND DATE(post_modified) = CURDATE()
              AND post_date != post_modified
        ");

        // Last sync time
        $last_sync = $wpdb->get_var("
            SELECT MAX(meta_value)
            FROM {$wpdb->postmeta}
            WHERE meta_key = '_anar_last_sync_time'
        ");

        return [
            'created_today' => intval($created_today),
            'updated_today' => intval($updated_today),
            'last_sync' => $last_sync ? mysql2date('j F Y - H:i', $last_sync) : 'هیچ‌گاه'
        ];
    }
}

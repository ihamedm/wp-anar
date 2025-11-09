<?php

namespace Anar\Admin\Widgets;

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

/**
 * Database Health Report Widget
 */
class DatabaseHealthWidget extends AbstractReportWidget
{
    protected function init()
    {
        $this->widget_id = 'anar-database-health-widget';
        $this->title = 'سلامت پایگاه داده';
        $this->description = 'بررسی وضعیت جداول و ایندکس‌های پایگاه داده';
        $this->icon = '<span class="dashicons dashicons-database"></span>';
        $this->ajax_action = 'anar_get_database_health';
        $this->button_text = 'بررسی سلامت پایگاه داده';
        $this->button_class = 'button-secondary';

        // Register AJAX handler
        add_action('wp_ajax_' . $this->ajax_action, [$this, 'handle_ajax']);
    }

    protected function get_report_data()
    {
        global $wpdb;

        $health_data = [
            'tables' => $this->check_tables_health(),
            'indexes' => $this->check_indexes_health(),
            'performance' => $this->check_performance_metrics(),
            'recommendations' => $this->get_recommendations()
        ];

        return $health_data;
    }

    private function check_tables_health()
    {
        global $wpdb;

        $tables = [
            'posts' => $wpdb->posts,
            'postmeta' => $wpdb->postmeta,
            'options' => $wpdb->options,
            'users' => $wpdb->users,
            'usermeta' => $wpdb->usermeta
        ];

        $table_status = [];

        foreach ($tables as $name => $table) {
            $result = $wpdb->get_row("SHOW TABLE STATUS LIKE '$table'");
            
            if ($result) {
                $table_status[$name] = [
                    'name' => $name,
                    'rows' => intval($result->Rows),
                    'data_length' => $this->format_bytes($result->Data_length),
                    'index_length' => $this->format_bytes($result->Index_length),
                    'engine' => $result->Engine,
                    'collation' => $result->Collation,
                    'status' => $result->Comment === 'OK' ? 'good' : 'warning'
                ];
            }
        }

        return $table_status;
    }

    private function check_indexes_health()
    {
        global $wpdb;

        $indexes = [
            'postmeta_post_id' => "SHOW INDEX FROM {$wpdb->postmeta} WHERE Key_name = 'post_id'",
            'postmeta_meta_key' => "SHOW INDEX FROM {$wpdb->postmeta} WHERE Key_name = 'meta_key'",
            'posts_type_status' => "SHOW INDEX FROM {$wpdb->posts} WHERE Key_name = 'type_status_date'"
        ];

        $index_status = [];

        foreach ($indexes as $name => $query) {
            $result = $wpdb->get_results($query);
            $index_status[$name] = [
                'name' => $name,
                'exists' => !empty($result),
                'status' => !empty($result) ? 'good' : 'warning'
            ];
        }

        return $index_status;
    }

    private function check_performance_metrics()
    {
        global $wpdb;

        $metrics = [];

        // Check slow query log
        $slow_queries = $wpdb->get_var("SHOW VARIABLES LIKE 'slow_query_log'");
        $metrics['slow_query_log'] = [
            'enabled' => $slow_queries === 'ON',
            'status' => $slow_queries === 'ON' ? 'good' : 'warning'
        ];

        // Check query cache
        $query_cache = $wpdb->get_var("SHOW VARIABLES LIKE 'query_cache_size'");
        $metrics['query_cache'] = [
            'size' => $query_cache,
            'status' => intval($query_cache) > 0 ? 'good' : 'warning'
        ];

        // Check innodb buffer pool
        $innodb_buffer = $wpdb->get_var("SHOW VARIABLES LIKE 'innodb_buffer_pool_size'");
        $metrics['innodb_buffer'] = [
            'size' => $this->format_bytes($innodb_buffer),
            'status' => intval($innodb_buffer) > 134217728 ? 'good' : 'warning' // > 128MB
        ];

        return $metrics;
    }

    private function get_recommendations()
    {
        $recommendations = [];

        // Check if we need to add indexes
        global $wpdb;
        
        $postmeta_indexes = $wpdb->get_results("SHOW INDEX FROM {$wpdb->postmeta} WHERE Key_name = 'post_id'");
        if (empty($postmeta_indexes)) {
            $recommendations[] = 'ایندکس post_id برای جدول postmeta اضافه نشده است';
        }

        $meta_key_indexes = $wpdb->get_results("SHOW INDEX FROM {$wpdb->postmeta} WHERE Key_name = 'meta_key'");
        if (empty($meta_key_indexes)) {
            $recommendations[] = 'ایندکس meta_key برای جدول postmeta اضافه نشده است';
        }

        return $recommendations;
    }

    private function format_bytes($bytes)
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);
        $bytes /= pow(1024, $pow);
        return round($bytes, 2) . ' ' . $units[$pow];
    }
}

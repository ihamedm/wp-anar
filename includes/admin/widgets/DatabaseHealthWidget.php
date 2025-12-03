<?php

namespace Anar\Admin\Widgets;

use Anar\Admin\Tools\DatabaseTools;

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

/**
 * Database Health Report Widget
 * Checks database health including indexes, table engines, and performance metrics
 */
class DatabaseHealthWidget extends AbstractReportWidget
{
    protected function init()
    {
        $this->widget_id = 'anar-database-health-widget';
        $this->title = 'سلامت پایگاه داده';
        $this->description = 'بررسی وضعیت جداول، ایندکس‌ها و عملکرد پایگاه داده';
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
            'database_info' => $this->get_database_info(),
            'tables' => $this->check_tables_health(),
            'indexes' => $this->check_indexes_health(),
            'innodb_status' => $this->check_innodb_status(),
            'performance' => $this->check_performance_metrics(),
            'recommendations' => $this->get_recommendations(),
            'timestamp' => current_time('Y-m-d H:i:s')
        ];

        return $health_data;
    }

    /**
     * Get basic database information
     */
    private function get_database_info()
    {
        global $wpdb;

        $version = $wpdb->get_var('SELECT VERSION()');
        $database_name = $wpdb->get_var('SELECT DATABASE()');
        
        // Get database size
        $db_size = $wpdb->get_var($wpdb->prepare("
            SELECT SUM(data_length + index_length) AS size
            FROM information_schema.TABLES
            WHERE table_schema = %s
        ", $database_name));

        return [
            'version' => $version,
            'database_name' => $database_name,
            'database_size' => $this->format_bytes($db_size),
            'charset' => $wpdb->get_charset_collate() ?: 'utf8mb4',
        ];
    }

    /**
     * Check health of important WordPress tables
     */
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
            $result = $wpdb->get_row($wpdb->prepare("SHOW TABLE STATUS LIKE %s", $table));
            
            if ($result) {
                $is_innodb = strtolower($result->Engine) === 'innodb';
                $total_size = $result->Data_length + $result->Index_length;
                
                $table_status[$name] = [
                    'name' => $name,
                    'table_name' => $table,
                    'rows' => intval($result->Rows),
                    'data_length' => $this->format_bytes($result->Data_length),
                    'index_length' => $this->format_bytes($result->Index_length),
                    'total_size' => $this->format_bytes($total_size),
                    'engine' => $result->Engine,
                    'is_innodb' => $is_innodb,
                    'collation' => $result->Collation,
                    'status' => $result->Comment === 'OK' ? 'good' : 'warning',
                    'can_create_index' => $is_innodb // Only InnoDB supports indexes properly
                ];
            }
        }

        return $table_status;
    }

    /**
     * Check health of sync indexes using DatabaseTools
     */
    private function check_indexes_health()
    {
        // Use DatabaseTools to check index status
        $index_status = DatabaseTools::check_indexes_status();
        
        if ($index_status === false) {
            return [
                'error' => true,
                'message' => 'خطا در بررسی وضعیت ایندکس‌ها'
            ];
        }

        // Format the response for display
        $formatted_status = [
            'all_exist' => $index_status['all_exist'],
            'total_required' => $index_status['total_required'],
            'existing_count' => $index_status['existing_count'],
            'missing_count' => $index_status['missing_count'],
            'missing_indexes' => $index_status['missing_indexes'],
            'indexes' => []
        ];

        // Format individual index status
        foreach ($index_status['status'] as $index_name => $status) {
            $formatted_status['indexes'][$index_name] = [
                'name' => $index_name,
                'exists' => $status === 'exists',
                'status' => $status === 'exists' ? 'good' : 'error',
                'display_name' => $this->get_index_display_name($index_name)
            ];
        }

        return $formatted_status;
    }

    /**
     * Get display name for index
     */
    private function get_index_display_name($index_name)
    {
        $names = [
            'idx_anar_sku' => 'ایندکس SKU محصول',
            'idx_anar_sku_backup' => 'ایندکس SKU پشتیبان',
            'idx_anar_last_sync_time' => 'ایندکس زمان آخرین همگام‌سازی',
            'idx_posts_product_sync' => 'ایندکس همگام‌سازی محصولات'
        ];

        return $names[$index_name] ?? $index_name;
    }

    /**
     * Check InnoDB status for tables that need indexes
     */
    private function check_innodb_status()
    {
        global $wpdb;

        $critical_tables = [
            'posts' => $wpdb->posts,
            'postmeta' => $wpdb->postmeta
        ];

        $innodb_status = [
            'all_innodb' => true,
            'tables' => [],
            'can_create_indexes' => true
        ];

        foreach ($critical_tables as $name => $table) {
            $result = $wpdb->get_row($wpdb->prepare("SHOW TABLE STATUS LIKE %s", $table));
            
            if ($result) {
                $is_innodb = strtolower($result->Engine) === 'innodb';
                $innodb_status['tables'][$name] = [
                    'name' => $name,
                    'table_name' => $table,
                    'engine' => $result->Engine,
                    'is_innodb' => $is_innodb,
                    'status' => $is_innodb ? 'good' : 'error'
                ];

                if (!$is_innodb) {
                    $innodb_status['all_innodb'] = false;
                    $innodb_status['can_create_indexes'] = false;
                }
            }
        }

        return $innodb_status;
    }

    /**
     * Check database performance metrics
     */
    private function check_performance_metrics()
    {
        global $wpdb;

        $metrics = [];

        // Check MySQL version
        $version = $wpdb->get_var('SELECT VERSION()');
        $metrics['mysql_version'] = [
            'value' => $version,
            'status' => 'info'
        ];

        // Check slow query log
        $slow_query_log = $wpdb->get_var("SHOW VARIABLES LIKE 'slow_query_log'");
        $metrics['slow_query_log'] = [
            'enabled' => $slow_query_log === 'ON',
            'status' => $slow_query_log === 'ON' ? 'good' : 'warning'
        ];

        // Check query cache (may not exist in MySQL 8+)
        $query_cache_size = $wpdb->get_var("SHOW VARIABLES LIKE 'query_cache_size'");
        if ($query_cache_size !== null) {
            $metrics['query_cache'] = [
                'size' => $this->format_bytes($query_cache_size),
                'enabled' => intval($query_cache_size) > 0,
                'status' => intval($query_cache_size) > 0 ? 'good' : 'warning'
            ];
        }

        // Check InnoDB buffer pool size
        $innodb_buffer = $wpdb->get_var("SHOW VARIABLES LIKE 'innodb_buffer_pool_size'");
        if ($innodb_buffer) {
            $innodb_buffer_bytes = intval($innodb_buffer);
            $metrics['innodb_buffer_pool'] = [
                'size' => $this->format_bytes($innodb_buffer_bytes),
                'size_bytes' => $innodb_buffer_bytes,
                'status' => $innodb_buffer_bytes > 134217728 ? 'good' : 'warning' // > 128MB
            ];
        }

        // Check max connections
        $max_connections = $wpdb->get_var("SHOW VARIABLES LIKE 'max_connections'");
        if ($max_connections) {
            $metrics['max_connections'] = [
                'value' => intval($max_connections),
                'status' => intval($max_connections) >= 100 ? 'good' : 'warning'
            ];
        }

        // Get current connections
        $current_connections = $wpdb->get_var("SHOW STATUS LIKE 'Threads_connected'");
        if ($current_connections !== null) {
            $metrics['current_connections'] = [
                'value' => intval($current_connections),
                'status' => 'info'
            ];
        }

        return $metrics;
    }

    /**
     * Get recommendations based on health checks
     */
    private function get_recommendations()
    {
        $recommendations = [];

        // Check InnoDB status
        $innodb_status = $this->check_innodb_status();
        if (!$innodb_status['all_innodb']) {
            foreach ($innodb_status['tables'] as $table) {
                if (!$table['is_innodb']) {
                    $recommendations[] = [
                        'type' => 'error',
                        'message' => "جدول {$table['name']} از موتور {$table['engine']} استفاده می‌کند. برای ایجاد ایندکس، باید از InnoDB استفاده شود."
                    ];
                }
            }
        }

        // Check index status
        $index_status = DatabaseTools::check_indexes_status();
        if ($index_status !== false && !$index_status['all_exist']) {
            $missing_count = $index_status['missing_count'];
            $recommendations[] = [
                'type' => 'warning',
                'message' => "{$missing_count} ایندکس مورد نیاز وجود ندارد. برای بهبود عملکرد همگام‌سازی، ایندکس‌ها را ایجاد کنید."
            ];
        }

        // Check InnoDB buffer pool
        global $wpdb;
        $innodb_buffer = $wpdb->get_var("SHOW VARIABLES LIKE 'innodb_buffer_pool_size'");
        if ($innodb_buffer && intval($innodb_buffer) < 134217728) {
            $recommendations[] = [
                'type' => 'info',
                'message' => 'اندازه InnoDB Buffer Pool کمتر از 128MB است. برای عملکرد بهتر، افزایش آن را در نظر بگیرید.'
            ];
        }

        return $recommendations;
    }

    /**
     * Format bytes to human-readable format
     */
    private function format_bytes($bytes)
    {
        if ($bytes === null || $bytes === '') {
            return '0 B';
        }

        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $bytes = max(intval($bytes), 0);
        
        if ($bytes === 0) {
            return '0 B';
        }

        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);
        $bytes /= pow(1024, $pow);
        
        return round($bytes, 2) . ' ' . $units[$pow];
    }
}

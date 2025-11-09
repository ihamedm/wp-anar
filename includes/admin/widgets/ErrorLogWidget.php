<?php

namespace Anar\Admin\Widgets;

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

/**
 * Error Log Report Widget
 */
class ErrorLogWidget extends AbstractReportWidget
{
    protected function init()
    {
        $this->widget_id = 'anar-error-log-widget';
        $this->title = 'گزارش خطاها';
        $this->description = 'نمایش آخرین خطاهای سیستم و لاگ‌های انار';
        $this->icon = '<span class="dashicons dashicons-warning"></span>';
        $this->ajax_action = 'anar_get_error_logs';
        $this->button_text = 'دریافت گزارش خطاها';
        $this->button_class = 'button-secondary';

        // Register AJAX handler
        add_action('wp_ajax_' . $this->ajax_action, [$this, 'handle_ajax']);
    }

    protected function get_report_data()
    {
        $logs = [
            'anar_logs' => $this->get_anar_logs(),
            'wp_debug_log' => $this->get_wp_debug_log(),
            'php_errors' => $this->get_php_errors(),
            'summary' => $this->get_error_summary()
        ];

        return $logs;
    }

    private function get_anar_logs()
    {
        // Get Anar plugin logs from database
        global $wpdb;
        
        // Check if the logs table exists
        $table_name = $wpdb->prefix . 'anar_logs';
        $table_exists = $wpdb->get_var("SHOW TABLES LIKE '$table_name'") == $table_name;
        
        if (!$table_exists) {
            return [];
        }
        
        $logs = $wpdb->get_results("
            SELECT *
            FROM {$wpdb->prefix}anar_logs
            WHERE level IN ('error', 'critical')
            ORDER BY created_at DESC
            LIMIT 50
        ", ARRAY_A);

        if (!$logs) {
            return [];
        }

        return array_map(function($log) {
            return [
                'id' => $log['id'],
                'level' => $log['level'],
                'message' => $log['message'],
                'context' => $log['context'],
                'created_at' => $log['created_at']
            ];
        }, $logs);
    }

    private function get_wp_debug_log()
    {
        $debug_log_file = WP_CONTENT_DIR . '/debug.log';
        
        if (!file_exists($debug_log_file)) {
            return [];
        }

        $lines = file($debug_log_file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        $recent_lines = array_slice($lines, -50); // Last 50 lines
        
        $parsed_logs = [];
        foreach ($recent_lines as $line) {
            if (preg_match('/^\[(.*?)\].*?(ERROR|WARNING|NOTICE|FATAL).*?:(.*)$/', $line, $matches)) {
                $parsed_logs[] = [
                    'timestamp' => $matches[1],
                    'level' => strtolower($matches[2]),
                    'message' => trim($matches[3])
                ];
            }
        }

        return $parsed_logs;
    }

    private function get_php_errors()
    {
        $errors = [];
        
        // Check if error reporting is enabled
        if (error_reporting() === 0) {
            $errors[] = [
                'type' => 'warning',
                'message' => 'گزارش‌دهی خطاهای PHP غیرفعال است'
            ];
        }

        // Check common PHP issues
        if (ini_get('display_errors') == 1) {
            $errors[] = [
                'type' => 'warning',
                'message' => 'نمایش خطاهای PHP در محیط تولید فعال است'
            ];
        }

        if (ini_get('log_errors') == 0) {
            $errors[] = [
                'type' => 'warning',
                'message' => 'ثبت خطاهای PHP در فایل لاگ غیرفعال است'
            ];
        }

        return $errors;
    }

    private function get_error_summary()
    {
        global $wpdb;

        // Check if the logs table exists
        $table_name = $wpdb->prefix . 'anar_logs';
        $table_exists = $wpdb->get_var("SHOW TABLES LIKE '$table_name'") == $table_name;
        
        $summary = [
            'last_24h' => [],
            'total_errors' => 0,
            'most_common_error' => null
        ];

        if (!$table_exists) {
            return $summary;
        }

        // Count errors by level in last 24 hours
        $error_counts = $wpdb->get_results("
            SELECT 
                level,
                COUNT(*) as count
            FROM {$wpdb->prefix}anar_logs
            WHERE created_at > DATE_SUB(NOW(), INTERVAL 24 HOUR)
              AND level IN ('error', 'critical', 'warning')
            GROUP BY level
            ORDER BY count DESC
        ", ARRAY_A);

        if ($error_counts) {
            foreach ($error_counts as $error) {
                $summary['last_24h'][$error['level']] = intval($error['count']);
                $summary['total_errors'] += intval($error['count']);
            }
        }

        // Find most common error message
        $most_common = $wpdb->get_row("
            SELECT 
                message,
                COUNT(*) as count
            FROM {$wpdb->prefix}anar_logs
            WHERE created_at > DATE_SUB(NOW(), INTERVAL 7 DAYS)
              AND level IN ('error', 'critical')
            GROUP BY message
            ORDER BY count DESC
            LIMIT 1
        ", ARRAY_A);

        if ($most_common) {
            $summary['most_common_error'] = [
                'message' => $most_common['message'],
                'count' => intval($most_common['count'])
            ];
        }

        return $summary;
    }
}

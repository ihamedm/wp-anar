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
        $this->description = 'لاگ‌های انار';
        $this->icon = '<span class="dashicons dashicons-warning"></span>';
        $this->ajax_action = 'anar_get_error_logs';
        $this->button_text = 'دریافت گزارش خطاها';
        $this->button_class = 'button-secondary';

        // Register AJAX handlers
        add_action('wp_ajax_' . $this->ajax_action, [$this, 'handle_ajax']);
        add_action('wp_ajax_anar_get_log_file_content', [$this, 'get_log_file_content']);
        add_action('wp_ajax_anar_download_log_file', [$this, 'download_log_file']);
    }

    protected function get_report_data()
    {
        $logs = [
            'log_files' => $this->get_all_log_files(),
            'anar_logs' => $this->get_anar_logs(),
            'wp_debug_log' => $this->get_wp_debug_log(),
            'php_errors' => $this->get_php_errors(),
            'summary' => $this->get_error_summary()
        ];

        return $logs;
    }

    /**
     * Get all log files from the log directory
     */
    private function get_all_log_files()
    {
        $log_dir = WP_CONTENT_DIR . '/wp-anar-logs';
        $log_files = [];

        if (!file_exists($log_dir) || !is_dir($log_dir)) {
            return $log_files;
        }

        // Get all .log files
        $files = glob($log_dir . '/*.log');

        if (!$files) {
            return $log_files;
        }

        // Sort by modification time (newest first)
        usort($files, function($a, $b) {
            return filemtime($b) - filemtime($a);
        });

        foreach ($files as $file) {
            $filename = basename($file);
            $file_size = filesize($file);
            $modified_time = filemtime($file);
            
            // Create a safe identifier for the file (base64 encoded filename)
            $file_id = base64_encode($filename);
            
            $log_files[] = [
                'id' => $file_id,
                'filename' => $filename,
                'size' => size_format($file_size, 2),
                'size_bytes' => $file_size,
                'modified' => wp_date('Y-m-d H:i:s', $modified_time),
                'modified_timestamp' => $modified_time,
                'download_url' => admin_url('admin-ajax.php?action=anar_download_log_file&file=' . urlencode($file_id) . '&nonce=' . wp_create_nonce('anar_log_file_' . $file_id)),
                'preview_url' => '#', // Will be handled by JavaScript
            ];
        }

        return $log_files;
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

    /**
     * Get log file content for preview
     */
    public function get_log_file_content()
    {
        $this->verify_permissions();

        if (!isset($_POST['file_id'])) {
            wp_send_json_error(['message' => 'File ID not provided']);
            return;
        }

        $file_id = sanitize_text_field($_POST['file_id']);
        $filename = base64_decode($file_id);

        if (!$filename || !preg_match('/^[a-zA-Z0-9._-]+\.log$/', $filename)) {
            wp_send_json_error(['message' => 'Invalid file ID']);
            return;
        }

        $log_dir = WP_CONTENT_DIR . '/wp-anar-logs';
        $file_path = $log_dir . '/' . $filename;

        // Security check: ensure file is within log directory
        $real_log_dir = realpath($log_dir);
        $real_file_path = realpath($file_path);

        if (!$real_file_path || !$real_log_dir || strpos($real_file_path, $real_log_dir) !== 0) {
            wp_send_json_error(['message' => 'Invalid file path']);
            return;
        }

        if (!file_exists($file_path)) {
            wp_send_json_error(['message' => 'File not found']);
            return;
        }

        // Read file content (limit to last 10MB to prevent memory issues)
        $max_size = 10 * 1024 * 1024; // 10MB
        $file_size = filesize($file_path);

        if ($file_size > $max_size) {
            // Read only the last 10MB
            $handle = fopen($file_path, 'r');
            fseek($handle, -$max_size, SEEK_END);
            $content = fread($handle, $max_size);
            fclose($handle);
            $truncated = true;
        } else {
            $content = file_get_contents($file_path);
            $truncated = false;
        }

        // Remove UTF-8 BOM if present
        if (substr($content, 0, 3) === "\xEF\xBB\xBF") {
            $content = substr($content, 3);
        }

        wp_send_json_success([
            'filename' => $filename,
            'content' => $content,
            'size' => $file_size,
            'size_formatted' => size_format($file_size, 2),
            'truncated' => $truncated,
            'lines' => substr_count($content, "\n") + 1
        ]);
    }

    /**
     * Download log file
     */
    public function download_log_file()
    {
        // Verify nonce
        if (!isset($_GET['nonce']) || !isset($_GET['file'])) {
            wp_die('Invalid request');
        }

        $file_id = sanitize_text_field($_GET['file']);
        $nonce = sanitize_text_field($_GET['nonce']);

        if (!wp_verify_nonce($nonce, 'anar_log_file_' . $file_id)) {
            wp_die('Security check failed');
        }

        // Check user capabilities
        if (!current_user_can('manage_options')) {
            wp_die('Insufficient permissions');
        }

        $filename = base64_decode($file_id);

        if (!$filename || !preg_match('/^[a-zA-Z0-9._-]+\.log$/', $filename)) {
            wp_die('Invalid file ID');
        }

        $log_dir = WP_CONTENT_DIR . '/wp-anar-logs';
        $file_path = $log_dir . '/' . $filename;

        // Security check: ensure file is within log directory
        $real_log_dir = realpath($log_dir);
        $real_file_path = realpath($file_path);

        if (!$real_file_path || !$real_log_dir || strpos($real_file_path, $real_log_dir) !== 0) {
            wp_die('Invalid file path');
        }

        if (!file_exists($file_path)) {
            wp_die('File not found');
        }

        // Set headers for download
        header('Content-Type: text/plain; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Content-Length: ' . filesize($file_path));
        header('Cache-Control: must-revalidate');
        header('Pragma: public');

        // Output file
        readfile($file_path);
        exit;
    }
}

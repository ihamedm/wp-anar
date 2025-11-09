<?php

namespace Anar\Core;

use Anar\OrderReports;
use Anar\ProductData;

class SystemStatus{

    private static $instance;

    public static function get_instance(){
        if( null == self::$instance ){
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function __construct()
    {
        add_action( 'wp_ajax_anar_get_system_reports', [ $this, 'get_system_reports'] );
    }

    public function get_system_reports() {
        // Create structured data
        $table_data = array_merge(
            $this->get_wordpress_info(),
            $this->get_woocommerce_info(),
            $this->get_theme_info(),
            $this->get_server_info(),
            $this->get_anar_data(),
            $this->verify_db_table_health(),
            $this->verify_cron_health(),
            $this->get_logs_info()
        );

        // Generate text report from table data
        $text_report = $this->generate_text_report($table_data);

        wp_send_json_success([
            'text_report' => $text_report,
            'table_data' => $table_data,
            'toast' => 'گزارش سیستم دریافت شد'
        ]);
    }

    private function generate_text_report($data) {
        $report = '';
        $current_group = '';

        foreach ($data as $item) {
            if ($item['group'] !== $current_group) {
                $current_group = $item['group'];
                $report .= "\n=== {$current_group} ===\n";
            }

            $status_icon = $item['status'] === 'good' ? '✓' : ($item['status'] === 'warning' ? '⚠' : '✗');
            $report .= sprintf("%s %s: %s\n", $status_icon, $item['label'], $item['value']);
        }

        return $report;
    }

    private function verify_db_table_health() {
        global $wpdb;
        $table_name = $wpdb->prefix . ANAR_DB_NAME;

        $results = [];
        try {
            $table_exists = $wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $table_name)) === $table_name;

            $results['db_table_exists'] = [
                'label' => 'Database Table',
                'value' => $table_exists ? 'Exists' : 'Missing',
                'status' => $table_exists ? 'good' : 'critical',
                'group' => 'Database Health'
            ];

            if ($table_exists) {
                $results['db_table_structure'] = [
                    'label' => 'Table Structure',
                    'value' => 'Valid',
                    'status' => 'good',
                    'group' => 'Database Health'
                ];
            }
        } catch (\Exception $e) {
            $results['db_error'] = [
                'label' => 'Database Error',
                'value' => $e->getMessage(),
                'status' => 'critical',
                'group' => 'Database Health'
            ];
        }

        return $results;
    }

    private function verify_cron_health() {
        return [
            'cron_running' => [
                'label' => 'Cron Status',
                'value' => '',
                'status' => '',
                'group' => 'Cron Health'
            ],
            'cron_last_run' => [
                'label' => 'Last Run',
                'value' => get_option('awca_cron_last_run', 'Never'),
                'status' => 'good',
                'group' => 'Cron Health'
            ],
            'cron_next_schedule' => [
                'label' => 'Next Schedule',
                'value' => wp_next_scheduled('awca_import_products_cron')
                    ? wp_date('Y-m-d H:i:s', wp_next_scheduled('awca_import_products_cron'))
                    : 'Not Scheduled',
                'status' => wp_next_scheduled('awca_import_products_cron') ? 'good' : 'warning',
                'group' => 'Cron Health'
            ]
        ];
    }

    private function get_anar_data() {
        $product_data = new ProductData();

        return [
            'anar_versions' => [
                'label' => 'Anar Versions',
                'value' => ANAR_PLUGIN_VERSION,
                'status' => 'good',
                'group' => 'Anar Information'
            ],
            'anar_products' => [
                'label' => 'Anar Products Count',
                'value' => $product_data->count_anar_products(),
                'status' => 'good',
                'group' => 'Anar Information'
            ],
            'not_synced_products' => [
                'label' => 'Not Synced (Last Hour)',
                'value' => 'deprecated',
                'status' => 'good',
                'group' => 'Anar Information'
            ],
            'last_partial_sync' => [
                'label' => 'Last Partial Sync',
                'value' => mysql2date('j F Y - H:i', anar_get_last_regular_sync_time()),
                'status' => 'good',
                'group' => 'Anar Information'
            ]
        ];
    }

    private function get_wordpress_info() {
        return [
            'wp_version' => [
                'label' => 'WordPress Version',
                'value' => get_bloginfo('version'),
                'status' => 'good',
                'group' => 'WordPress Information'
            ],
            'wp_language' => [
                'label' => 'Language',
                'value' => get_bloginfo('language'),
                'status' => 'good',
                'group' => 'WordPress Information'
            ],
            'wp_timezone' => [
                'label' => 'TimeZone',
                'value' => wp_timezone_string(),
                'status' => 'good',
                'group' => 'WordPress Information'
            ],
            'wp_charset' => [
                'label' => 'Charset',
                'value' => get_bloginfo('charset'),
                'status' => 'good',
                'group' => 'WordPress Information'
            ],
            'wp_debug' => [
                'label' => 'Debug Mode',
                'value' => (defined('WP_DEBUG') && WP_DEBUG) ? 'Enabled' : 'Disabled',
                'status' => 'good',
                'group' => 'WordPress Information'
            ],
            'home_url' => [
                'label' => 'Home URL',
                'value' => home_url(),
                'status' => 'good',
                'group' => 'WordPress Information',
                'is_link' => true
            ],
            'site_url' => [
                'label' => 'Site URL',
                'value' => site_url(),
                'status' => 'good',
                'group' => 'WordPress Information',
                'is_link' => true
            ],
            'wp_path' => [
                'label' => 'WordPress Path',
                'value' => ABSPATH,
                'status' => 'good',
                'group' => 'WordPress Information'
            ],
            'wp_content_path' => [
                'label' => 'WordPress Content Path',
                'value' => WP_CONTENT_DIR,
                'status' => 'good',
                'group' => 'WordPress Information'
            ]
        ];
    }

    private function get_woocommerce_info() {
        return [
            'wc_hpos' => [
                'label' => 'HPOS',
                'value' => awca_is_hpos_enable() ? 'Yes' : 'No',
                'status' => 'good',
                'group' => 'WooCommerce Information'
            ],
            'wc_anar_orders' => [
                'label' => 'Anar Orders',
                'value' => OrderReports::count_anar_orders(),
                'status' => 'good',
                'group' => 'WooCommerce Information'
            ],
            'wc_anar_register_orders' => [
                'label' => 'Anar Register Orders',
                'value' => OrderReports::count_anar_orders_submited(),
                'status' => 'good',
                'group' => 'WooCommerce Information'
            ]
        ];
    }

    private function get_theme_info() {
        $theme = wp_get_theme();
        return [
            'theme_name' => [
                'label' => 'Theme Name',
                'value' => $theme->get('Name'),
                'status' => 'good',
                'group' => 'Theme Information'
            ],
            'theme_version' => [
                'label' => 'Theme Version',
                'value' => $theme->get('Version'),
                'status' => 'good',
                'group' => 'Theme Information'
            ],
            'theme_author' => [
                'label' => 'Theme Author',
                'value' => $theme->get('Author'),
                'status' => 'good',
                'group' => 'Theme Information'
            ],
            'theme_child' => [
                'label' => 'Child Theme',
                'value' => is_child_theme() ? 'Yes' : 'No',
                'status' => 'good',
                'group' => 'Theme Information'
            ],
            'theme_directory' => [
                'label' => 'Theme Directory',
                'value' => $theme->get_stylesheet_directory(),
                'status' => 'good',
                'group' => 'Theme Information'
            ]
        ];
    }

    private function get_server_info() {
        return [
            'php_version' => [
                'label' => 'PHP Version',
                'value' => phpversion(),
                'status' => 'good',
                'group' => 'Server Environment'
            ],
            'server_software' => [
                'label' => 'Server Software',
                'value' => $_SERVER['SERVER_SOFTWARE'],
                'status' => 'good',
                'group' => 'Server Environment'
            ],
            'mysql_version' => [
                'label' => 'MySQL Version',
                'value' => $GLOBALS['wpdb']->db_version(),
                'status' => 'good',
                'group' => 'Server Environment'
            ],
            'php_time_limit' => [
                'label' => 'PHP Time Limit',
                'value' => ini_get('max_execution_time'),
                'status' => 'good',
                'group' => 'Server Environment'
            ],
            'php_input_vars' => [
                'label' => 'PHP Input Vars',
                'value' => ini_get('max_input_vars'),
                'status' => 'good',
                'group' => 'Server Environment'
            ],
            'php_memory_limit' => [
                'label' => 'PHP Memory Limit',
                'value' => ini_get('memory_limit'),
                'status' => 'good',
                'group' => 'Server Environment'
            ],
            'php_upload_size' => [
                'label' => 'PHP Max Upload Size',
                'value' => ini_get('upload_max_filesize'),
                'status' => 'good',
                'group' => 'Server Environment'
            ],
            'file_upload_permission' => [
                'label' => 'File Upload Permission',
                'value' => is_writable(__FILE__) ? 'Writable' : 'Not Writable',
                'status' => is_writable(__FILE__) ? 'good' : 'warning',
                'group' => 'Server Environment'
            ],
            'https_status' => [
                'label' => 'HTTPS',
                'value' => is_ssl() ? 'Yes' : 'No',
                'status' => 'good',
                'group' => 'Server Environment'
            ]
        ];
    }

    private function get_logs_info() {
        $logs_status = Logger::get_logs_status();
        $logs_data = [];

        foreach ($logs_status as $key => $status) {
            $logs_data['log_' . $key] = [
                'label' => $status['label'],
                'value' => $status['message'],
                'status' => $status['status'],
                'group' => 'Log Files Status'
            ];

            // Add detailed file information if available
            if (isset($status['files'])) {
                foreach ($status['files'] as $index => $file) {
                    $logs_data['log_' . $key . '_file_' . $index] = [
                        'label' => '─ ' . $file['name'],
                        'value' => sprintf('%s (Modified: %s)', $file['size'], $file['modified']),
                        'status' => $file['writable'] ? 'good' : 'warning',
                        'group' => 'Log Files Status',
                        'is_link' => true,
                        'url' => $file['url']
                    ];
                }
            }

            // Add details if available
            if (isset($status['details'])) {
                $logs_data['log_' . $key . '_details'] = [
                    'label' => '└─ Status',
                    'value' => $status['details'],
                    'status' => $status['status'],
                    'group' => 'Log Files Status'
                ];
            }
        }

        return $logs_data;
    }

}
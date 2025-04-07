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
        // Create structured data
        $table_data = array_merge(
            SystemStatus::get_wordpress_info(),
            SystemStatus::get_woocommerce_info(),
            SystemStatus::get_theme_info(),
            SystemStatus::get_server_info(),
            SystemStatus::get_anar_data(),
            SystemStatus::verify_db_table_health(),
            SystemStatus::verify_cron_health(),
            SystemStatus::get_logs_info()
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

    
}
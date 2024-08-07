<?php
namespace Anar;

class AWCA_CronJobs {

    public function __construct() {
        $this->schedule_events();
        $this->assign_jobs();
    }

    public function schedule_events() {
        // Create custom interval for every minute
        add_filter('cron_schedules', [$this, 'add_anar_cron_interval']);

        // Schedule the create_products_job to run every minute
        if (!wp_next_scheduled('awca_create_products_cron')) {
            wp_schedule_event(time(), 'every_minute', 'awca_create_products_cron');
        }

        // Schedule the sync_products_job to run every two minutes (or any other interval you prefer)
        if (!wp_next_scheduled('awca_sync_products_cron')) {
            wp_schedule_event(time(), 'every_two_min', 'awca_sync_products_cron');
        }
    }

    public function assign_jobs() {
        add_action('awca_sync_products_cron', [$this, 'sync_all_products_job']);
        add_action('awca_create_products_cron', [$this, 'create_products_job']);
    }

    public function add_anar_cron_interval($schedules) {
        $schedules['every_minute'] = [
            'interval' => 60, // 60 seconds
            'display' => 'Anar - Every Minute'
        ];

        $schedules['every_two_min'] = [
            'interval' => 120, // 120 seconds
            'display' => 'Anar - Every Two Minutes'
        ];

        return $schedules;
    }

    public function create_products_job() {
        awca_log('Cron: create_products_job called');
        awca_process_products_cron_function();
    }

    public function sync_all_products_job() {
        awca_sync_all_products();
    }

    public function deactivate() {
        awca_log('deactivate');
        wp_clear_scheduled_hook('awca_sync_products_cron');
        wp_clear_scheduled_hook('awca_create_products_cron');
    }
}
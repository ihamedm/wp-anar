<?php
namespace Anar\Core;

use Anar\CronJob_Process_Products;
use Anar\Notifications;
use Anar\Payments;
use Anar\Sync;

class CronJobs {

    private static $instance = null;

    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }


    private function __construct() {
        // Only run schedule_events once
        static $initialized = false;
        if (!$initialized) {
            $this->schedule_events();
            $this->assign_jobs();
            $initialized = true;
        }
    }

    public function schedule_events() {
        // create custom interval that not exist by default on cron_schedules
        add_filter('cron_schedules', [$this, 'add_custom_cron_interval']);


        if (!wp_next_scheduled('awca_create_products')
            && !CronJob_Process_Products::is_create_products_cron_locked()
        ) {
            wp_schedule_event(time(), 'every_one_min', 'awca_create_products');
        }


        if (!wp_next_scheduled('awca_sync_products_cron')) {
            wp_schedule_event(time(), 'every_two_min', 'awca_sync_products_cron');
        }


        if (!wp_next_scheduled('awca_sync_products_full_cron')) {
            wp_schedule_event(time(), 'hourly', 'awca_sync_products_full_cron');
        }


        // Schedule the unread notifications count event
        if (!wp_next_scheduled('awca_fetch_updated_data_from_anar_cron')) {
            wp_schedule_event(time(), 'hourly', 'awca_fetch_updated_data_from_anar_cron');
        }

        // Schedule the log cleanup job to run daily
        if (!wp_next_scheduled('awca_daily_log_cleanup_cron')) {
            wp_schedule_event(time(), 'daily', 'awca_daily_log_cleanup_cron');
        }


    }

    public function assign_jobs() {

        add_action('awca_create_products', [$this, 'create_products_job']);

        add_action('awca_sync_products_cron', [$this, 'sync_updated_products_job']);

        add_action('awca_sync_products_full_cron', [$this, 'sync_all_products_job']);

        // Assign the action for counting unread notifications
        add_action('awca_fetch_updated_data_from_anar_cron', [$this, 'fetch_updated_data_from_anar_job']);

        // Assign the daily log cleanup job
        add_action('awca_daily_log_cleanup_cron', [$this, 'log_cleanup_job']);

    }

    public function add_custom_cron_interval($schedules){

        $schedules['every_one_min'] = array(
            'interval'  => 60,
            'display'   => 'هر یک دقیقه'
        );

        $schedules['every_two_min'] = array(
            'interval'  => 120,
            'display'   => 'هر دو دقیقه'
        );

        $schedules['every_three_min'] = array(
            'interval'  => 180,
            'display'   => 'هر سه دقیقه'
        );


        return $schedules;
    }


    public function create_products_job(){
        $cron_product_generator = CronJob_Process_Products::get_instance();
        $cron_product_generator->process_the_row();
    }


    public function sync_updated_products_job() {

        $sync = Sync::get_instance();
        $sync->syncProducts();

    }

    public function sync_all_products_job() {

        $sync = Sync::get_instance();
        $sync->fullSync = true;
        $sync->syncProducts();
        $sync->get_api_total_products_number();

    }


    public function fetch_updated_data_from_anar_job() {
        (new Notifications)->count_unread_notifications();
        //(new Payments())->count_unpaid_orders_count();

    }



    public function log_cleanup_job() {
        // Create an instance of the Logger class
        $logger = new Logger();

        // Call the cleanup method
        $logger->cleanup_logs($logger);
    }


    public function reschedule_events() {
        awca_log('reschedule cron jobs');
        $this->deactivate();
        $this->schedule_events();
    }



    public function deactivate() {
        wp_clear_scheduled_hook('awca_sync_products_cron');
        wp_clear_scheduled_hook('awca_fetch_updated_data_from_anar_cron');
        wp_clear_scheduled_hook('awca_create_products');
    }


}

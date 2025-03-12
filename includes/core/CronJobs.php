<?php
namespace Anar\Core;

use Anar\CronJob_Process_Products;
use Anar\Notifications;
use Anar\Payments;
use Anar\ProductData;
use Anar\Sync;
use Anar\SyncOutdated;
use Anar\SyncTools;

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


        if (!wp_next_scheduled('awca_full_sync_products_cron')) {
            wp_schedule_event(time(), 'every_two_min', 'awca_full_sync_products_cron');
        }


        // Schedule the unread notifications count event
        if (!wp_next_scheduled('awca_fetch_updated_data_from_anar_cron')) {
            wp_schedule_event(time(), 'every_one_min', 'awca_fetch_updated_data_from_anar_cron');
        }

        // Schedule the log cleanup job to run daily
        if (!wp_next_scheduled('anar_daily_jobs')) {
            wp_schedule_event(time(), 'daily', 'anar_daily_jobs');
        }


    }

    public function assign_jobs() {

        add_action('awca_create_products', [$this, 'create_products_job']);

        add_action('awca_sync_products_cron', [$this, 'sync_updated_products_job']);

        add_action('awca_full_sync_products_cron', [$this, 'sync_all_products_job']);

        // Assign the action for counting unread notifications
        add_action('awca_fetch_updated_data_from_anar_cron', [$this, 'fetch_updated_data_from_anar_job']);

        // Assign the daily log cleanup job
        add_action('anar_daily_jobs', [$this, 'daily_jobs']);

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

        // First check if there's a stuck process
        $cron_product_generator->check_for_stuck_processes();

        // Then proceed with regular processing
        $cron_product_generator->process_the_row();
    }


    public function sync_updated_products_job() {

        $sync = new Sync();
        $sync->syncProducts();

    }

    public function sync_all_products_job() {

        $sync = new Sync();
        $sync->fullSync = true;
        $sync->syncProducts();

        $sync_tools = SyncTools::get_instance();
        $sync_tools->get_api_total_products_number();

    }


    public function fetch_updated_data_from_anar_job() {
        // (new Notifications)->count_unread_notifications();
        (new ProductData())->count_anar_products();
        //(new Payments())->count_unpaid_orders_count();

    }



    public function daily_jobs() {
        $logger = new Logger();
        $logger->cleanup_logs();
        

        $sync_outdated = SyncOutdated::get_instance();
        $sync_outdated->process_outdated_products_job();
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

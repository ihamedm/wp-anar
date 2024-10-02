<?php
namespace Anar\Core;

use Anar\Background_Process_Images;
use Anar\Background_Process_Products;
use Anar\Notifications;
use Anar\Payments;
use Anar\Sync;

class CronJobs {

    public function __construct() {
        $this->schedule_events();
        $this->assign_jobs();
    }

    public function schedule_events() {
        // create custom interval that not exist by default on cron_schedules
        add_filter('cron_schedules', [$this, 'add_custom_cron_interval']);


        if (!wp_next_scheduled('awca_sync_products_cron')) {
            wp_schedule_event(time(), 'every_two_min', 'awca_sync_products_cron');
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

        add_action('awca_sync_products_cron', [$this, 'sync_all_products_job']);

        // Assign the action for counting unread notifications
        add_action('awca_fetch_updated_data_from_anar_cron', [$this, 'fetch_updated_data_from_anar_job']);

        // Assign the daily log cleanup job
        add_action('awca_daily_log_cleanup_cron', [$this, 'log_cleanup_job']);

    }

    public function add_custom_cron_interval($schedules){

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


    public function sync_all_products_job() {

        if($this->allow_run_cron()){
            $sync = new Sync();
            $sync->syncAllProducts();
        }


    }


    public function fetch_updated_data_from_anar_job() {
        (new Notifications)->count_unread_notifications();
        (new Payments())->count_unpaid_orders_count();
    }

    public function log_cleanup_job() {
        // Create an instance of the Logger class
        $logger = new Logger();

        // Call the cleanup method
        $logger->cleanup_logs($logger);
    }

    public function allow_run_cron(){
        $img_processing = Background_Process_Images::get_instance();
        $product_processing = Background_Process_Products::get_instance();

        if(
            $img_processing->is_processing() ||
            $img_processing->is_queued() ||
            $product_processing->is_processing() ||
            $product_processing->is_queued()
        ){
            awca_log('sync cron job : wait until background Process ended.');
            return false;
        }else{
            return true;
        }
    }


    public function reschedule_events() {
        $this->deactivate();
        $this->schedule_events();
    }



    public function deactivate() {
        awca_log('deactivate');
        wp_clear_scheduled_hook('awca_sync_products_cron');
        wp_clear_scheduled_hook('awca_fetch_updated_data_from_anar_cron');
    }


}

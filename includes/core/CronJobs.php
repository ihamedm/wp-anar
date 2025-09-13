<?php
namespace Anar\Core;

use Anar\Import;
use Anar\ImportSlow;
use Anar\Notifications;
use Anar\ProductData;
use Anar\Sync;
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

        // Get the appropriate import class based on the option
        $import_class = $this->get_import_class();

        if (!wp_next_scheduled('anar_import_products')
            && !$import_class::is_create_products_cron_locked()
        ) {
            wp_schedule_event(time(), 'every_one_min', 'anar_import_products');
        }

        // Schedule the unread notifications count event
        if (!wp_next_scheduled('anar_fetch_updates')) {
            wp_schedule_event(time(), 'hourly', 'anar_fetch_updates');
        }

        // Schedule all daily jobs in a single event for better performance
        if (!wp_next_scheduled('anar_daily_maintenance')) {
            wp_schedule_event(time(), 'daily', 'anar_daily_maintenance');
        }
    }

    public function assign_jobs() {
        add_action('anar_import_products', [$this, 'create_products_job']);
        add_action('anar_fetch_updates', [$this, 'fetch_updated_data_from_anar_job']);
        add_action('anar_daily_maintenance', [$this, 'run_daily_maintenance']);
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

        $schedules['every_five_min'] = array(
            'interval'  => 300,
            'display'   => 'هر ۵ دقیقه'
        );

        return $schedules;
    }

    /**
     * Get the appropriate import class based on the slow import option
     * 
     * @return string The class name to use (Import or ImportSlow)
     */
    private function get_import_class() {
        return get_option('anar_conf_feat__slow_import', 'no') == 'yes' ? 'Anar\ImportSlow' : 'Anar\Import';
    }

    public function create_products_job(){
        $import_class = $this->get_import_class();
        $cron_product_generator = $import_class::get_instance();

        // First check if there's a stuck process
        $cron_product_generator->check_for_stuck_processes();

        // Then proceed with regular processing
        $cron_product_generator->process_the_row();
    }

    public function fetch_updated_data_from_anar_job() {
        (new ProductData())->count_anar_products(true);
        (new Notifications())->count_unread_notifications();
    }

    /**
     * Daily maintenance job that runs all daily tasks
     * Consolidates multiple daily jobs into a single event for better performance
     */
    public function run_daily_maintenance() {
        awca_log('Starting daily maintenance tasks');
        
        try {
            // 1. Send usage data
            awca_log('Daily maintenance: Sending usage data');
            $usage_data = UsageData::instance();
            $usage_data->send();
            
            // 2. Clean up logs
            awca_log('Daily maintenance: Cleaning up logs');
            $logger = new Logger();
            $logger->cleanup_logs();
            
            // 3. Clean up action scheduler
            awca_log('Daily maintenance: Cleaning up action scheduler');
            $cleaner = new Cleaner();
            $cleaner->cleanup_action_scheduler();
            
            // 4. Validate token
            awca_log('Daily maintenance: Validating token');
            $is_valid = Activation::validate_token();
            
            if ($is_valid) {
                awca_log('Daily maintenance: Token validation successful');
            } else {
                awca_log('Daily maintenance: Token validation failed');
            }
            
            awca_log('Daily maintenance tasks completed successfully');
            
        } catch (\Exception $e) {
            awca_log('Daily maintenance error: ' . $e->getMessage());
        }
    }

    public function reschedule_events() {
        awca_log('reschedule cron jobs');
        $this->clear_schedule();
        $this->schedule_events();
    }

    public function clear_schedule() {
        wp_clear_scheduled_hook('anar_sync_outdated_products');
        wp_clear_scheduled_hook('anar_sync_products');
        wp_clear_scheduled_hook('anar_full_sync_products');
        wp_clear_scheduled_hook('anar_import_products');
        wp_clear_scheduled_hook('anar_daily_maintenance');
        wp_clear_scheduled_hook('anar_fetch_updates');


        // deprecated
        wp_clear_scheduled_hook('awca_fetch_updated_data_from_anar_cron');
        wp_clear_scheduled_hook('awca_daily_log_cleanup_cron');
        wp_clear_scheduled_hook('anar_daily_jobs');
        wp_clear_scheduled_hook('awca_create_products');
        wp_clear_scheduled_hook('anar_cleanup_logs');
        wp_clear_scheduled_hook('anar_cleanup_action_scheduler');
        wp_clear_scheduled_hook('awca_sync_products_cron');
        wp_clear_scheduled_hook('awca_full_sync_products_cron');
        wp_clear_scheduled_hook('anar_sync_outdated');

    }
}

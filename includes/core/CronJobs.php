<?php
namespace Anar\Core;

use Anar\Import;
use Anar\ImportSlow;
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

        if (!wp_next_scheduled('awca_create_products')
            && !$import_class::is_create_products_cron_locked()
        ) {
            wp_schedule_event(time(), 'every_one_min', 'awca_create_products');
        }

        // Schedule the unread notifications count event
        if (!wp_next_scheduled('awca_fetch_updated_data_from_anar_cron')) {
            wp_schedule_event(time(), 'hourly', 'awca_fetch_updated_data_from_anar_cron');
        }

        // Schedule the log cleanup job to run daily
        if (!wp_next_scheduled('anar_daily_jobs')) {
            wp_schedule_event(time(), 'daily', 'anar_daily_jobs');
        }

        if (!wp_next_scheduled('anar_cleanup_logs')) {
            wp_schedule_event(time(), 'daily', 'anar_cleanup_logs');
        }

        if (!wp_next_scheduled('anar_cleanup_action_scheduler')) {
            wp_schedule_event(time(), 'daily', 'anar_cleanup_action_scheduler');
        }
    }

    public function assign_jobs() {
        add_action('awca_create_products', [$this, 'create_products_job']);
        add_action('awca_fetch_updated_data_from_anar_cron', [$this, 'fetch_updated_data_from_anar_job']);
        add_action('anar_daily_jobs', [$this, 'anar_daily_jobs']);
        add_action('anar_cleanup_logs', [$this, 'cleanup_logs']);
        add_action('anar_cleanup_action_scheduler', [$this, 'cleanup_action_scheduler']);
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
    }

    public function cleanup_logs(){
        $logger = new Logger();
        $logger->cleanup_logs();
    }

    public function cleanup_action_scheduler(){
        $cleaner = new Cleaner();
        $cleaner->cleanup_action_scheduler();
    }

    public function anar_daily_jobs(){
        $usage_data = UsageData::instance();
        $usage_data->send();
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
        wp_clear_scheduled_hook('awca_full_sync_products_cron');
        wp_clear_scheduled_hook('anar_sync_outdated');
    }
}

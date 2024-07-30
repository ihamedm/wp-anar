<?php
namespace Anar;

class AWCA_CronJobs {

    public function __construct() {
        $this->schedule_events();
        $this->assign_jobs();
    }

    public function schedule_events() {
        // create custom interval that not exist by default on cron_schedules
        add_filter('cron_schedules', [$this, 'add_custom_cron_interval']);


//        if (!wp_next_scheduled('awca_every_one_min_event')) {
//            wp_schedule_event(time(), 'every_one_min', 'awca_every_one_min_event');
//        }
//
//        if (!wp_next_scheduled('awca_add_every_two_min')) {
//            wp_schedule_event(time(), 'every_two_min', 'awca_add_every_two_min');
//        }

        if (!wp_next_scheduled('awca_sync_products_cron')) {
            wp_schedule_event(time(), 'every_five_min', 'awca_sync_products_cron');
        }


    }

    public function assign_jobs() {
//        add_action('awca_every_one_min_event', [$this, 'dl_and_set_product_image_job']);
//        add_action('awca_every_one_min_event', [$this, 'call_categories_api_save_on_db']);

//        add_action('awca_add_every_two_min', [$this, 'sync_all_products_job']);

        add_action('awca_sync_products_cron', [$this, 'sync_all_products_job']);

    }

    public function add_custom_cron_interval($schedules){
//        $schedules['every_one_min'] = array(
//            'interval' => 60,
//            'display' => 'هر ۶۰ ثانیه'
//        );
//
//        $schedules['every_two_min'] = array(
//            'interval'  => 120,
//            'display'   => 'هر ۲ دقیقه'
//        );

        $schedules['every_five_min'] = array(
            'interval'  => 120,
            'display'   => 'هر دو دقیقه'
        );


        return $schedules;
    }


    public function call_products_api_save_on_db(){
        $products_api = 'https://api.anar360.com/api/360/products';
        awca_fetch_and_store_api_response('product', $products_api);
    }


    public function call_categories_api_save_on_db(){
        $products_api = 'https://api.anar360.com/api/360/categories';
        awca_fetch_and_store_api_response('categories', $products_api);
    }


    public function sync_all_products_job() {
        awca_sync_all_products();
    }

    public function deactivate() {
        awca_log('deactivate');

//        wp_clear_scheduled_hook('awca_every_one_min_event');
//        wp_clear_scheduled_hook('awca_add_every_two_min');
        wp_clear_scheduled_hook('awca_sync_products_cron');
    }

}

<?php

namespace Anar\Init;

use Anar\core\CronJobs;
use Anar\core\SyncOutdated;

/**
 * Fired during plugin deactivation
 *
 * @link       https://https://anar360.com/
 * @since      1.0.0
 *
 * @package    Anar_Woocomerce_Api
 * @subpackage Anar_Woocomerce_Api/includes
 */

/**
 * Fired during plugin deactivation.
 *
 * This class defines all code necessary to run during the plugin's deactivation.
 */
class Uninstall
{

	/**
	 * Short Description. (use period)
	 *
	 * Long Description.
	 *
	 * @since    1.0.0
	 */
	public function run_uninstall()
	{
        self::reset_options();
        self::clear_scheduled();
	}

    public static function reset_options(){
        update_option('awca_create_product_cron_lock', 'lock');

        self::truncate_table();
        self::remove_options();
        self::clear_scheduled();

        // Unschedule outdated sync cron
        $sync_outdated = \Anar\SyncOutdated::get_instance();
        $sync_outdated->unscheduled_cron();
    }


	/**
	 * Remove Plugins Options.
	 *
	 * @since    1.0.0
	 */
	public static function remove_options()
	{
        delete_option('awca_db_version');
        delete_option('awca_cron_version');
        delete_option('_awca_unpaid_orders');
        delete_option('anar_unread_notifications');
        delete_option('awca_last_sync_time');
        delete_option('awca_total_products');
        delete_option('awca_proceed_products');
        delete_option('awca_product_save_lock');
        delete_option('anar_active_full_sync_jobID');
        delete_option('awca_cron_create_products_start_time');
        delete_option('awca_proceed_products');
        delete_option('anar_last_sync_outdated_time');

        delete_transient('awca_create_product_row_on_progress');
        delete_transient('awca_sync_all_products_lock');
        delete_transient('awca_create_product_row_start_time');
        delete_transient('awca_create_product_heartbeat');
        delete_transient('awca_sync_outdated_lock');
    }


    public static function remove_map_data(){
        // make a backup
        update_option('awca_categoryMap_backup', get_option('categoryMap'));
        update_option('awca_attributeMap_backup', get_option('attributeMap'));

        delete_option('categoryMap');
        delete_option('attributeMap');

    }



    public static function truncate_table(){
        Db::truncate_table();
        StatusTools::remove_indexes();
    }



    public static function clear_scheduled(){
        $cron_jobs = CronJobs::get_instance();
        $cron_jobs->clear_schedule();
    }
}

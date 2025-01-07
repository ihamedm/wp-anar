<?php

namespace Anar\Init;

use Anar\core\CronJobs;

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
		self::awca_remove_plugin_options();

        $uninstaller = new self();
        $uninstaller->clear_scheduled();
	}


	/**
	 * Remove Plugins Options.
	 *
	 * @since    1.0.0
	 */
	private static function awca_remove_plugin_options()
	{
        delete_option('_awca_activation_key');
        delete_option('awca_db_version');
        delete_option('awca_cron_version');
        delete_option('_awca_unpaid_orders');
        delete_option('awca_unread_notifications');
        delete_option('awca_last_sync_time');
        delete_option('awca_total_products');
        delete_option('categoryMap');
        delete_option('attributeMap');

        delete_transient('awca_sync_all_products_lock');

	}


    public function clear_scheduled(){
        $cron_jobs = CronJobs::get_instance();
        $cron_jobs->deactivate();
    }
}

<?php
namespace Anar\Init;

/**
 * Fired during plugin activation
 *
 * @link       https://https://anar360.com/
 * @since      1.0.0
 *
 * @package    Anar_Woocomerce_Api
 * @subpackage Anar_Woocomerce_Api/includes
 */

/**
 * Fired during plugin activation.
 *
 * This class defines all code necessary to run during the plugin's activation.
 *
 * @since      1.0.0
 * @package    Anar_Woocomerce_Api
 * @subpackage Anar_Woocomerce_Api/includes
 * @author     انار 360 <mrsh13610@gmail.com>
 */
class Install
{

    /**
	 * Short Description. (use period)
	 *
	 * Long Description.
	 *
	 * @since    1.0.0
	 */
    public function run_install()
    {

        add_action('plugins_loaded', array(__CLASS__, 'awca_update_plugin_version'));

        $this->set_options();
    }


	/**
	 * Routine Updates.
	 *
	 * @since    2.0.0
	 */
	public static function awca_update_plugin_version()
	{
		$current_version = get_option('anar_woocommerce_api_version');
		if ($current_version !== ANAR_PLUGIN_VERSION) {
			update_option('anar_woocommerce_api_version', ANAR_PLUGIN_VERSION);
		}
	}

    public function set_options(){
        if(!get_option('awca_create_product_cron_lock')){
            update_option('awca_create_product_cron_lock', 'lock');
        }
    }


}

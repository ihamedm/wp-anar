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
        self::check_wc_activation();
        add_action('plugins_loaded', array(__CLASS__, 'awca_update_plugin_version'));
    }

	/**
	 * Check if WooCommerce is activated.
	 *
	 * @since    1.0.0
	 */
	private static function check_wc_activation()
	{
		if (!class_exists('WooCommerce')) {
			deactivate_plugins(plugin_basename(__FILE__));
			wp_die('پلاگین فوق نیاز به فعال بودن ووکامرس دارد لطفا ابتدا ووکامرس را نصب و فعال کنید. <br><a href="' . admin_url('plugins.php') . '">برگشت به صفحه افزونه</a>');
		}
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


}

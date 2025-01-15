<?php
/**
 * Plugin Name:      	 انار 360
 * Plugin URI:       	 https://anar360.com/wordpress-plugin
 * Plugin Signature:  	AWCA
 * Description:      	 پلاگین سازگار با ووکامرس برای دریافت محصولات انار 360 در وبسایت کاربران
 * Version:          	0.1.7
 * Author:            	تیم توسعه 360
 * Author URI:        	https://anar360.com/
 * Text Domain:       	awca
 * Tested up to: 		6.7.1
 * WC tested up to: 	9.5.1
 * Stable tag: 			0.1.6
 * Requires PHP: 		7.4
 *
 * Copyright:            (c) 2024 Anar360 Dev. Group, All rights reserved.
 * License:            GPLv2 or later
 * License URI:        https://www.gnu.org/licenses/gpl-2.0.html
 * This program is free software; you can redistribute it and/or modify
 *  it under the terms of the GNU General Public License as published by
 *  the Free Software Foundation; either version 2 of the License, or
 *  (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 *  but WITHOUT ANY WARRANTY; without even the implied warranty of
 *  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 *  GNU General Public License for more details.
 */

namespace Anar;


// If this file is called directly, abort.

use Anar\Init\Install;
use Anar\Init\Uninstall;

if (!defined('ABSPATH')) {
	exit;
}

class Wp_Anar
{

    public static $plugin_text_domain;

	/**
	 * Minimum PHP version required
	 *
	 * @var string
	 */
	private $min_php_version = '7.4.0';

	/**
	 * List Of Class
	 * @var array
	 */
	public static $providers = array();


	/**
	 * URL to this plugin's directory.
	 *
	 * @type string
	 * @status Core
	 */
	public static $plugin_url;

	/**
	 * Path to this plugin's directory.
	 *
	 * @type string
	 * @status Core
	 */
	public static $plugin_path;

	/**
	 * Path to this plugin's directory.
	 *
	 * @type string
	 * @status Core
	 */
	public static $plugin_version;

	/**
	 * Plugin instance.
	 *
	 * @see get_instance()
	 * @status Core
	 */
	protected static $_instance = null;

	/**
	 * Access this plugin’s working instance
	 *
	 * @wp-hook plugins_loaded
	 * @return  object of this class
	 * @since   2012.09.13
	 */
	public static function instance()
	{
		null === self::$_instance and self::$_instance = new self;
		return self::$_instance;
	}

	/**
	 * Wp_Anar constructor.
	 */
	public function __construct(){
        static $constructed = false;
        if ($constructed) {
            return;
        }
        $constructed = true;

        $this->define_constants();
        $this->includes();
        $this->check_needs();
        $this->hooks();
        $this->instances();


    }


    private function check_needs()
    {
        /**
         * Check Require Php Version
         */
        if (version_compare(PHP_VERSION, $this->min_php_version, '<=')) {
            add_action('admin_notices', array($this, 'php_version_notice'));
            return;
        }

        /**
         * @todo check woocommerce installed and versions, move from Install class to here.
         */

        $this->check_and_update_db();
        $this->check_and_update_cron();
        $this->plugin_update_check();
        $this->wc_hpos_compatibility();
    }


	public function define_constants()
	{
		/*
         * Get Plugin Data
         */
		if (!function_exists('get_plugin_data')) {
			require_once(ABSPATH . 'wp-admin/includes/plugin.php');
		}
		$plugin_data = get_plugin_data(__FILE__, true, false);

		self::$plugin_version = $plugin_data['Version'];

        self::$plugin_text_domain = $plugin_data['TextDomain'];

		self::$plugin_url = plugins_url('', __FILE__);

		self::$plugin_path = plugin_dir_path(__FILE__);


        /**
         * Define needed constants to use in plugin
         */
        define('ANAR_PLUGIN_TEXTDOMAIN', self::$plugin_text_domain);
        define('ANAR_PLUGIN_VERSION', self::$plugin_version);
        define('ANAR_PLUGIN_PATH', self::$plugin_path);
        define('ANAR_PLUGIN_URL', self::$plugin_url);
        define('ANAR_DB_NAME', 'anar');
        define('ANAR_DB_VERSION', '1.7');
        define('ANAR_CRON_VERSION', '1.2');

        define('ANAR_IS_ENABLE_CREATE_ORDER', false);
        define('ANAR_IS_ENABLE_PAYMENTS_PAGE', false);


        /**
         * @todo
         * rename constants
         * remove unneeded
         * use new values
         */
        define('ANAR_WC_API_PLUGIN_DIR', plugin_dir_path(__FILE__));
        define('ANAR_WC_API_PLUGIN_URL', plugin_dir_url(__FILE__));

	}


    public function hooks(){
        /**
         * plugin activation stuff.
         */
        $installer = new Install();
        register_activation_hook(__FILE__, [$installer, 'run_install']);

        /**
         * plugin deactivation stuff.
         */
        $uninstaller = new Uninstall();
        register_deactivation_hook(__FILE__, [$uninstaller, 'run_uninstall']);



    }


    public function includes(){
        /**
         * Composer autoloader
         */
        require_once dirname(__FILE__) . '/vendor/autoload.php';

        /**
         * Plugin update checker library
         * Source : https://github.com/YahnisElsts/plugin-update-checker
         */
        require_once dirname(__FILE__) . '/includes/lib/puc/plugin-update-checker.php';


        /**
         * Some require functions
         * @todo move to classes
         */
        require_once dirname(__FILE__) . '/includes/core/helper-functions.php';
    }


    public function instances()
    {
        new Core\Activation();
        new Core\Assets();
        Core\CronJobs::get_instance();

        new Admin\Menus();

        new Wizard\Wizard();
        new Wizard\Category();
        new Wizard\Attributes();
        Wizard\ProductManager::get_instance();

        new Woocommerce();
        new Sync();

        //Background_Process_Products::get_instance();
//        Background_Process_Images::get_instance();



        new Notifications();
        Checkout::get_instance();
        new Cart();

        if( ANAR_IS_ENABLE_PAYMENTS_PAGE )
            Payments::get_instance();

        Orders_List::get_instance();
        Order::get_instance();

    }



    /**
     * Show notice about PHP version
     *
     * @return void
     */
    public function php_version_notice()
    {
        if (!current_user_can('manage_options')) {
            return;
        }
        $error = sprintf('نسخه PHP شما %s می‌باشد.', PHP_VERSION);
        $error .= sprintf('حداقل نسخه PHP مورد نیاز پلاگین انار %s می باشد', $this->min_php_version);?>
        <div class="error">
            <p><?php printf($error); ?></p>
        </div>
        <?php
    }



    /**
     * create/update plugin tables
     * @return void
     */
    public function check_and_update_db() {
        $installed_version = get_option('awca_db_version');

        global $wpdb;
        $table_name = $wpdb->prefix . ANAR_DB_NAME;

        if ($installed_version !== ANAR_DB_VERSION || $wpdb->get_var("SHOW TABLES LIKE '$table_name'") != $table_name) {
            Init\Db::make_tables();
        }
    }



    /**
     * reinitialize cron schedules if we have changes on cron-job functions
     * @return void
     */
    public function check_and_update_cron() {
        $installed_cron_version = get_option('awca_cron_version');

        if ($installed_cron_version !== ANAR_CRON_VERSION) {
            $cron_jobs = Core\CronJobs::get_instance();
            $cron_jobs->reschedule_events();

            update_option('awca_cron_version', ANAR_CRON_VERSION);
        }
    }



    /**
     * Plugin update checker
     */
    public function plugin_update_check()
    {
        $update_checker = \YahnisElsts\PluginUpdateChecker\v5\PucFactory::buildUpdateChecker(
            'https://github.com/ihamedm/wp-anar',
            __FILE__,
            'wp-anar'
        );
        //Set the branch that contains the stable release.
        $update_checker->setBranch('main');
    }


    /**
     * add compatibility with High-Performance Order Storage
     */
    public function wc_hpos_compatibility(){
        add_action("before_woocommerce_init", function(){
            if (class_exists(\Automattic\WooCommerce\Utilities\FeaturesUtil::class)) {
                \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility('custom_order_tables', __FILE__, true);
            }
        });
    }


}


/**
 * Begins execution of the plugin.
 *
 *  The core plugin class that is used to define internationalization,
 *  admin-specific hooks, and public-facing site hooks.
 *
 * Since everything within the plugin is registered via hooks,
 * then kicking off the plugin from this point in the file does
 * not affect the page life cycle.
 *
 */
function run_wp_anar(){
    return Wp_Anar::instance();
}

// Hook into plugins_loaded instead of running directly
add_action('plugins_loaded', 'Anar\run_wp_anar', 10);
?>

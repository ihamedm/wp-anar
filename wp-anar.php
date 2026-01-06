<?php
/**
 * Plugin Name:      	 انار 360
 * Plugin URI:       	 https://wp.anar360.com/
 * Plugin Signature:  	AWCA
 * Description:      	به کمک پلاگین انار۳۶۰ میتوانید کلیه محصولات انار خود را در وب سایت خود ایمپورت کنید، قیمت و موجودی به صورت لحظه ایی با انار سینک می شود و سفارش های دریافتی ووکامرس با یک کلیک در انار ثبت می شوند.
 * Version:          	0.7.3
 * Author:            	تیم توسعه انار 360
 * Author URI:        	https://anar360.com/
 * Text Domain:       	awca
 * Tested up to: 		6.8.3
 * WC tested up to: 	10.3.4
 * Stable tag: 			0.6.1
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

use Anar\Init\Db;
use Anar\Init\Install;
use Anar\Init\Reset;
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
	 * Base name this plugin.
	 *
	 * @type string
	 * @status Core
	 */
	public static $plugin_base_name;

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


    private $update_checker;

	/**
	 * Access this plugin's working instance
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

        add_action('admin_notices', array($this, 'show_new_version_notice'));

        add_action('admin_init', [$this, 'check_woocommerce_activate']);
        $this->check_and_update_db();
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

        self::$plugin_base_name = plugin_basename(__FILE__);



        /**
         * Define needed constants to use in plugin
         */
        define('ANAR_PLUGIN_TEXTDOMAIN', self::$plugin_text_domain);
        define('ANAR_PLUGIN_VERSION', self::$plugin_version);
        define('ANAR_PLUGIN_PATH', self::$plugin_path);
        define('ANAR_PLUGIN_URL', self::$plugin_url);
        define('ANAR_PLUGIN_BASENAME', self::$plugin_base_name);
        define('ANAR_DB_NAME', 'anar');
        define('ANAR_DB_PRODUCTS_NAME', 'anar_products');
        define('ANAR_DB_VERSION', '1.12');
        define('ANAR_CRON_VERSION', '1.14');


        define('ANAR_DEBUG', get_option('anar_log_level', 'info') == 'debug');
        define('ANAR_SUPPORT_MODE', get_option('anar_support_mode', 'disable') == 'enable' ?? false);
        define('ANAR_IS_ENABLE_OPTIONAL_SYNC_PRICE', get_option('anar_conf_feat__optional_price_sync', 'no') !== 'no');
        define('ANAR_IS_ENABLE_NOTIF_PAGE', true);


        /**
         * @todo
         * rename constants
         * remove unneeded
         * use new values
         */
        define('ANAR_WC_API_PLUGIN_DIR', plugin_dir_path(__FILE__));
        define('ANAR_WC_API_PLUGIN_URL', plugin_dir_url(__FILE__));

        define('OPT_KEY__COUNT_ANAR_PRODUCT_ON_DB', 'anar_count_anar_products_on_db');

        define('OPT_KEY__REPORT_ANAR_ORDERS', 'anar_report_orders');
        define('OPT_KEY__REPORT_ANAR_ORDERS_SUBMITTED', 'anar_report_orders_submitted');

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


        require_once dirname(__FILE__) . '/includes/anar-core-functions.php';
    }


    public function instances()
    {

        // Initial
        Init\Checks::get_instance();
        Init\Update::get_instance();
        Core\Assets::get_instance();
        Core\Activation::get_instance();
        Admin\Menus::get_instance();
        Core\CronJobs::get_instance();


        // Import
        new Wizard\Wizard();
        new Wizard\Category();
        new Wizard\Attributes();
        Wizard\ProductManager::get_instance();
//        Import\BackgroundImporter::get_instance();
//        new Import\AjaxHandlers();

        // Product
        new Product\Edit();
        new Product\Lists();
        new Product\Front();

        // Tools & Features
        Gallery::get_instance();
        Reset::get_instance();
        Admin\Tools::get_instance();

        // Pages
        Notifications::get_instance();

        // Sync
        Sync\RegularSync::get_instance();
        Sync\OutdatedSync::get_instance();
        Sync\RealTimeSync::get_instance();
        Sync\ForceSync::get_instance();
        Sync\FixProducts::get_instance();

        // Order
        Order::get_instance();
        OrderManager::get_instance();
        OrderList::get_instance();
        OrderFront::get_instance();

        // Checkout
        Checkout::get_instance();
        if(anar_shipping_enabled()){
            new CheckoutDropShipping();
        }


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

    public function check_woocommerce_activate(){
        if (!class_exists('WooCommerce')) {
            // Deactivate the plugin
            deactivate_plugins(plugin_basename(__FILE__));

            // Add admin notice
            add_action('admin_notices', [$this, 'woocommerce_dependency_notice']);

            // If user is activating this plugin, redirect to plugins page
            if (isset($_GET['activate'])) {
                unset($_GET['activate']);
                wp_redirect(admin_url('plugins.php?deactivate=true'));
                exit;
            }
        }
    }



    /**
     * create/update plugin tables
     * @return void
     */
    public function check_and_update_db() {
        $installed_version = get_option('awca_db_version');

        global $wpdb;
        $table_name = $wpdb->prefix . ANAR_DB_NAME;

        if ($installed_version !== ANAR_DB_VERSION || $wpdb->get_var("SHOW TABLES LIKE '$table_name'") !== $table_name) {
            Db::make_tables();
            Admin\Tools\DatabaseTools::force_recreate_indexes();
        }
    }



    /**
     * Plugin update checker
     */
    public function plugin_update_check()
    {
        $this->update_checker = \YahnisElsts\PluginUpdateChecker\v5\PucFactory::buildUpdateChecker(
            'https://github.com/ihamedm/wp-anar',
            __FILE__,
            'wp-anar'
        );
        //Set the branch that contains the stable release.
        $this->update_checker->setBranch('main');
    }

    public function show_new_version_notice()
    {
        $state = $this->update_checker->getUpdateState();
        $update = $state->getUpdate();

        // Check if an update is available
        if ($update !== null) {
            $current_version = $this->update_checker->getInstalledVersion();
            $new_version = $update->version;

            // Only show notification if new version is greater than installed version
            if (version_compare($new_version, $current_version, '>')) {
                // Display update notification
                echo '<div class="notice notice-warning is-dismissible">';
                echo '<p><strong>هشدار بروزرسانی به نسخه جدید پلاگین انار:</strong> نسخه ' . esc_html($new_version) . ' پلاگین انار منتشر شد. لطفا هر چه سریعتر بروزرسانی کنید. ';
                echo 'نسخه فعلی شما ' . esc_html($current_version) . ' می باشد. ';
                echo '<a href="' . esc_url(admin_url('plugins.php')) . '">برو به صفحه افزونه ها</a></p>';
                echo '</div>';
            }
        }
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


    public function woocommerce_dependency_notice() {
        $class = 'notice notice-error';
        $message = 'پلاگین انار نیاز به فعال بودن ووکامرس دارد. لطفا ابتدا ووکامرس را نصب و فعال کنید.';

        printf('<div class="%1$s"><p>%2$s</p><p></p></div>',
            esc_attr($class),
            esc_html($message),
        );
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
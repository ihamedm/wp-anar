<?php
/**
 * Plugin Name:      	 انار 360
 * Plugin URI:       	 https://anar360.com/wordpress-plugin
 * Plugin Signature:  	AWCA
 * Description:      	 پلاگین سازگار با ووکامرس برای دریافت محصولات انار 360 در وبسایت کاربران
 * Version:          	2.9.2
 * Author:            	تیم توسعه 360
 * Author URI:        	https://anar360.com/
 * Copyright: 			(c) 2024 Anar360 Dev. Group, All rights reserved.
 * License: 			GPLv2 or later
 * License URI: 		https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       	awca
 * Domain Path:       	/languages
 * Tested up to: 		6.6.1
 * WC tested up to: 	9.1.0
 * Stable tag: 			2.9.2
 * Requires at least: 	5.0
 * Requires PHP: 		7.0
 * WC requires at least: 5.0
 */

// If this file is called directly, abort.
if (!defined('WPINC')) {
	die;
}





// Define plugin constants.
define('ANAR_WC_API_PLUGIN_NAME', 'Anar WooCommerce API');
define('ANAR_WOOCOMERCE_API_VERSION', '2.9.2');
define('ANAR_WC_API_PLUGIN_FILE', __FILE__);
define('ANAR_WC_API_PLUGIN_DIR', plugin_dir_path(ANAR_WC_API_PLUGIN_FILE));
define('ANAR_WC_API_PLUGIN_URL', plugin_dir_url(ANAR_WC_API_PLUGIN_FILE));
define('ANAR_WC_API_ADMIN', ANAR_WC_API_PLUGIN_DIR . 'admin/');
define('ANAR_WC_API_FRONT', ANAR_WC_API_PLUGIN_DIR . 'public/');
define('ANAR_WC_API_TEXT_DOMAIN', 'anar-woocommerce-api');
define('ANAR_WC_API_LANG_DIR', trailingslashit(ANAR_WC_API_PLUGIN_DIR) . 'languages');
define('AWCA_DB_VERSION', '1.5');

require_once ANAR_WC_API_PLUGIN_DIR . '/vendor/autoload.php';
// Sentry\init(['dsn' => 'https://b53916540d815e2220677e5e696d4bf4@o4507180208553984.ingest.us.sentry.io/4507180214714368']);


include_once ANAR_WC_API_PLUGIN_DIR . '/functions.php';


/**
 * The code that runs during plugin activation.
 * This action is documented in includes/class-anar-woocomerce-api-activator.php
 */
function activate_anar_woocomerce_api()
{
	require_once ANAR_WC_API_PLUGIN_DIR . 'includes/class-anar-woocomerce-api-activator.php';
	new Anar_Woocomerce_Api_Activator();
}

/**
 * The code that runs during plugin deactivation.
 * This action is documented in includes/class-anar-woocomerce-api-deactivator.php
 */
function deactivate_anar_woocomerce_api()
{
	require_once ANAR_WC_API_PLUGIN_DIR . 'includes/class-anar-woocomerce-api-deactivator.php';
	new Anar_Woocomerce_Api_Deactivator();
}

register_activation_hook(__FILE__, 'activate_anar_woocomerce_api');
register_deactivation_hook(__FILE__, 'deactivate_anar_woocomerce_api');

/**
 * The core plugin class that is used to define internationalization,
 * admin-specific hooks, and public-facing site hooks.
 */
require ANAR_WC_API_PLUGIN_DIR . 'includes/class-anar-woocomerce-api.php';

/**
 * Begins execution of the plugin.
 *
 * Since everything within the plugin is registered via hooks,
 * then kicking off the plugin from this point in the file does
 * not affect the page life cycle.
 *
 * @since    1.0.0
 */
function run_anar_woocomerce_api()
{
	$plugin = new Anar_Woocomerce_Api();
	$plugin->run();
}
run_anar_woocomerce_api();


?>

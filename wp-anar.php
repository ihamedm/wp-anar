<?php
/**
 * Plugin Name:      	 انار 360
 * Plugin URI:       	 https://anar360.com/wordpress-plugin
 * Plugin Signature:  	AWCA
 * Description:      	 پلاگین سازگار با ووکامرس برای دریافت محصولات انار 360 در وبسایت کاربران
 * Version:          	2.9.2.6
 * Author:            	تیم توسعه 360
 * Author URI:        	https://anar360.com/
 * Copyright: 			(c) 2024 Anar360 Dev. Group, All rights reserved.
 * License: 			GPLv2 or later
 * License URI: 		https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       	awca
 * Domain Path:       	/languages
 * Tested up to: 		6.6.1
 * WC tested up to: 	9.1.4
 * Stable tag: 			2.9.2
 * Requires at least: 	5.0
 * Requires PHP: 		7.0
 * WC requires at least: 9.1.4
 */

namespace Anar;

// If this file is called directly, abort.
if (!defined('WPINC')) {
	die;
}


// Retrieve plugin metadata
$plugin_file = plugin_dir_path(__FILE__) . basename(__FILE__); // Adjust the file name if different
$plugin_data = get_file_data($plugin_file, array('Version' => 'Version'));

// Define plugin version
define('ANAR_WOOCOMERCE_API_VERSION', !empty($plugin_data['Version']) ? $plugin_data['Version'] : 'unknown');

// Define other plugin constants
define('ANAR_WC_API_PLUGIN_NAME', 'Anar WooCommerce API');
define('ANAR_WC_API_PLUGIN_FILE', __FILE__);
define('ANAR_WC_API_PLUGIN_DIR', plugin_dir_path(ANAR_WC_API_PLUGIN_FILE));
define('ANAR_WC_API_PLUGIN_URL', plugin_dir_url(ANAR_WC_API_PLUGIN_FILE));
define('ANAR_WC_API_ADMIN', ANAR_WC_API_PLUGIN_DIR . 'admin/');
define('ANAR_WC_API_FRONT', ANAR_WC_API_PLUGIN_DIR . 'public/');
define('ANAR_WC_API_TEXT_DOMAIN', 'anar-woocommerce-api');
define('ANAR_WC_API_LANG_DIR', trailingslashit(ANAR_WC_API_PLUGIN_DIR) . 'languages');
define('AWCA_DB_VERSION', '1.7');



require_once ANAR_WC_API_PLUGIN_DIR . '/vendor/autoload.php';


include_once ANAR_WC_API_PLUGIN_DIR . '/functions.php';


/**
 * The code that runs during plugin activation.
 * This action is documented in includes/class-anar-woocomerce-api-activator.php
 */
function activate_anar_woocomerce_api()
{
	new AWCA_Activator();
}

/**
 * The code that runs during plugin deactivation.
 * This action is documented in includes/class-anar-woocomerce-api-deactivator.php
 */
function deactivate_anar_woocomerce_api()
{
	new AWCA_Deactivator();
}

register_activation_hook(__FILE__, 'Anar\activate_anar_woocomerce_api');
register_deactivation_hook(__FILE__, 'Anar\deactivate_anar_woocomerce_api');


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
 * @since    1.0.0
 */
function run_anar_woocomerce_api()
{
	$plugin = new AWCA_Core();
	$plugin->run();
}
run_anar_woocomerce_api();


/**
 * add compatibility with High-Performance Order Storage
 */
add_action("before_woocommerce_init", function(){
	if (class_exists(\Automattic\WooCommerce\Utilities\FeaturesUtil::class)) {
		\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility('custom_order_tables', __FILE__, true);
	}
});



use YahnisElsts\PluginUpdateChecker\v5\PucFactory;
$awca_update_checker = PucFactory::buildUpdateChecker(
	'https://github.com/ihamedm/wp-anar',
	__FILE__,
	'wp-anar'
);
//Set the branch that contains the stable release.
$awca_update_checker->setBranch('main');


?>

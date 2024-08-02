<?php
 namespace Anar;

use Anar\Admin\AWCA_Menus;

/**
 * The file that defines the core plugin class
 *
 * A class definition that includes attributes and functions used across both the
 * public-facing side of the site and the admin area.
 *
 * @link       https://https://anar360.com/
 * @since      1.0.0
 *
 * @package    Anar_Woocomerce_Api
 * @subpackage Anar_Woocomerce_Api/includes
 */

/**
 * The core plugin class.
 *
 * This is used to define internationalization, admin-specific hooks, and
 * public-facing site hooks.
 *
 * Also maintains the unique identifier of this plugin as well as the current
 * version of the plugin.
 *
 * @since      1.0.0
 * @package    Anar_Woocomerce_Api
 * @subpackage Anar_Woocomerce_Api/includes
 * @author     انار 360 <mrsh13610@gmail.com>
 */
class AWCA_Core {

	/**
	 * The loader that's responsible for maintaining and registering all hooks that power
	 * the plugin.
	 *
	 * @since    1.0.0
	 * @access   protected
	 * @var      AWCA_Loader    $loader    Maintains and registers all hooks for the plugin.
	 */
	protected $loader;

	/**
	 * The unique identifier of this plugin.
	 *
	 * @since    1.0.0
	 * @access   protected
	 * @var      string    $plugin_name    The string used to uniquely identify this plugin.
	 */
	protected $plugin_name;

	/**
	 * The current version of the plugin.
	 *
	 * @since    1.0.0
	 * @access   protected
	 * @var      string    $version    The current version of the plugin.
	 */
	protected $version;


	/**
	 * Define the core functionality of the plugin.
	 *
	 * Set the plugin name and the plugin version that can be used throughout the plugin.
	 * Load the dependencies, define the locale, and set the hooks for the admin area and
	 * the public-facing side of the site.
	 *
	 * @since    1.0.0
	 */
	public function __construct() {
		if ( defined( 'ANAR_WOOCOMERCE_API_VERSION' ) ) {
			$this->version = ANAR_WOOCOMERCE_API_VERSION;
		} else {
			$this->version = '2.0.0';
		}

        if(!get_option('awca_db_version')){
            update_option('awca_db_version', '0');
        }

		$this->plugin_name = 'anar-woocomerce-api';

		$this->load_dependencies();

	}

	/**
	 * Load the required dependencies for this plugin.
	 *
	 * Include the following files that make up the plugin:
	 *
	 * - Anar_Woocomerce_Api_Loader. Orchestrates the hooks of the plugin.
	 * - Anar_Woocomerce_Api_i18n. Defines internationalization functionality.
	 * - Anar_Woocomerce_Api_Admin. Defines all hooks for the admin area.
	 * - Anar_Woocomerce_Api_Public. Defines all hooks for the public side of the site.
	 *
	 * Create an instance of the loader which will be used to register the hooks
	 * with WordPress.
	 *
	 * @since    1.0.0
	 * @access   private
	 */
	private function load_dependencies() {

        $this->awca_check_and_update_db();
		/**
		 * The class responsible for orchestrating the actions and filters of the
		 * core plugin.
		 */
        $this->loader = new AWCA_Loader();
        new AWCA_i18n();


        new AWCA_Assets($this->plugin_name, $this->get_version());
        new AWCA_Product();
        new AWCA_CronJobs();
        new AWCA_Woocommerce();
//        new AWCA_Updater();
        new AWCA_Menus();
//        new AWCA_Notifications();
//        new AWCA_Payments();
//        new AWCA_Checkout();
//        new AWCA_Cart();
//        new AWCA_Order();

	}


    private function create_products_response_table_if_not_exist() {
        global $wpdb;

        // Log the current and new database versions
        $installed_version = get_option('awca_db_version');
        awca_log('Current DB Version: ' . $installed_version);
        awca_log('New DB Version: ' . AWCA_DB_VERSION);

        $table_name = $wpdb->prefix . 'awca_large_api_responses';
        $charset_collate = $wpdb->get_charset_collate();

        // Remove the unique constraint if it exists
        $wpdb->query("ALTER TABLE $table_name DROP INDEX unique_key");

        $sql = "CREATE TABLE $table_name (
        id mediumint(9) NOT NULL AUTO_INCREMENT,
        response longtext NOT NULL,
        `key` varchar(255) NOT NULL,
        processed tinyint(1) NOT NULL DEFAULT 0,
        page int(11) DEFAULT NULL,
        created_at datetime DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY  (id)
    ) $charset_collate;";

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);

        // Check if the table was created/updated successfully
        $table_exists = $wpdb->get_var("SHOW TABLES LIKE '$table_name'") == $table_name;
        if ($table_exists) {
            awca_log('Table ' . $table_name . ' created/updated successfully.');
            // Update the database version option
            update_option('awca_db_version', AWCA_DB_VERSION);
        } else {
            awca_log('Failed to create/update table ' . $table_name . '.');
        }
    }




    private function awca_check_and_update_db() {
        $installed_version = get_option('awca_db_version');

        if ($installed_version !== AWCA_DB_VERSION) {
            $this->create_products_response_table_if_not_exist();
        }
    }



	/**
	 * Run the loader to execute all of the hooks with WordPress.
	 *
	 * @since    1.0.0
	 */
	public function run() {
		$this->loader->run();
	}

	/**
	 * The name of the plugin used to uniquely identify it within the context of
	 * WordPress and to define internationalization functionality.
	 *
	 * @since     1.0.0
	 * @return    string    The name of the plugin.
	 */
	public function get_plugin_name() {
		return $this->plugin_name;
	}

	/**
	 * The reference to the class that orchestrates the hooks with the plugin.
	 *
	 * @return    AWCA_Loader    Orchestrates the hooks of the plugin.
	 *@since     1.0.0
	 */
	public function get_loader() {
		return $this->loader;
	}

	/**
	 * Retrieve the version number of the plugin.
	 *
	 * @since     1.0.0
	 * @return    string    The version number of the plugin.
	 */
	public function get_version() {
		return $this->version;
	}

}

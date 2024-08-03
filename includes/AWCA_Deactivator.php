<?php

namespace Anar;

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
 *
 * @since      1.0.0
 * @package    Anar_Woocomerce_Api
 * @subpackage Anar_Woocomerce_Api/includes
 * @author     انار 360 <mrsh13610@gmail.com>
 */
class AWCA_Deactivator
{

    public function __construct(){
        $this->deactivate();
    }

	/**
	 * Short Description. (use period)
	 *
	 * Long Description.
	 *
	 * @since    1.0.0
	 */
	private function deactivate()
	{
		self::awca_remove_plugin_options();
        $this->clear_scheduled();
	}
	/**
	 * Remove Plugins Options.
	 *
	 * @since    1.0.0
	 */
	private static function awca_remove_plugin_options()
	{
		$options = get_option('_awca_activation_key') ? true : false;
		if ($options) {
			delete_option('_awca_activation_key');
			return true;
		}
		// self::awca_delete_anar_attribute_on_deactivation();
	}


	private static function awca_delete_anar_attribute_on_deactivation()
	{
		if (class_exists('WooCommerce')) {
			global $wpdb;

			// Define attribute details
			$attribute_name = 'anar';

			// Get the attribute ID
			$attribute_id = $wpdb->get_var(
				$wpdb->prepare(
					"SELECT attribute_id FROM {$wpdb->prefix}woocommerce_attribute_taxonomies WHERE attribute_name = %s;",
					$attribute_name
				)
			);

			if ($attribute_id) {
				// Delete the attribute
				$wpdb->delete(
					"{$wpdb->prefix}woocommerce_attribute_taxonomies",
					array('attribute_id' => $attribute_id),
					array('%d')
				);

				// Remove attribute terms
				$taxonomy = wc_attribute_taxonomy_name($attribute_name);
				if (taxonomy_exists($taxonomy)) {
					$terms = get_terms(array('taxonomy' => $taxonomy, 'hide_empty' => false));
					foreach ($terms as $term) {
						wp_delete_term($term->term_id, $taxonomy);
					}
				}

				// Clear the transient cache
				delete_transient('wc_attribute_taxonomies');
			}
		}
	}


    private function clear_scheduled(){
        $cron_jobs = new AWCA_CronJobs();
        $cron_jobs->deactivate();
    }
}

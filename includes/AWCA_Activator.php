<?php
namespace Anar;

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
class AWCA_Activator
{

    public function __construct()
    {
        $this->activate();
    }

    /**
	 * Short Description. (use period)
	 *
	 * Long Description.
	 *
	 * @since    1.0.0
	 */
	private function activate()
	{
		self::awca_check_wc_activation();
		// self::awca_create_wc_default_attribute();
		add_action('plugins_loaded', array(__CLASS__, 'awca_update_plugin_version'));
	}

	/**
	 * Check if WooCommerce is activated.
	 *
	 * @since    1.0.0
	 */
	private static function awca_check_wc_activation()
	{
		if (!class_exists('WooCommerce')) {
			deactivate_plugins(plugin_basename(__FILE__));
			wp_die('متاسفانه، پلاگین فوق نیاز به فعال بودن ووکامرس دارد لطفا ابتدا ووکامرس را نصب و فعال کنید. <br><a href="' . admin_url('plugins.php') . '">برگشت به صفحه افزونه</a>');
		}
	}
	/**
	 * Create default WooCommerce attributes.
	 *
	 * @since 2.0.0
	 */


	private static function awca_create_wc_default_attribute()
	{
		if (class_exists('WooCommerce')) {
			$existing_attributes = wc_get_attribute_taxonomies();

			if ($existing_attributes) {
				// Define attribute details
				$attribute_name = 'anar';
				$attribute_label = 'Anar';
				$attribute_type = 'select'; // You can change the type if needed
				$attribute_orderby = 'menu_order'; // Sorting order for the attribute
				$attribute_public = true; // Make attribute visible on the frontend

				// Check if the attribute already exists
				global $wpdb;

				$attribute_exists = $wpdb->get_var(
					$wpdb->prepare(
						"SELECT attribute_id FROM {$wpdb->prefix}woocommerce_attribute_taxonomies WHERE attribute_name = %s;",
						$attribute_name
					)
				);

				// If the attribute doesn't exist, create it
				if (!$attribute_exists) {
					$wpdb->insert(
						"{$wpdb->prefix}woocommerce_attribute_taxonomies",
						array(
							'attribute_name'    => $attribute_name,
							'attribute_label'   => $attribute_label,
							'attribute_type'    => $attribute_type,
							'attribute_orderby' => $attribute_orderby,
							'attribute_public'  => $attribute_public,
						),
						array(
							'%s',
							'%s',
							'%s',
							'%s',
							'%d',
						)
					);

					// Register the attribute taxonomy
					delete_transient('wc_attribute_taxonomies');
					wc_create_attribute([
						'name'         => $attribute_label,
						'slug'         => $attribute_name,
						'type'         => $attribute_type,
						'order_by'     => $attribute_orderby,
						'has_archives' => $attribute_public,
					]);
				}

				// Hook into WooCommerce to prevent deletion of the "anar" attribute
				add_filter('woocommerce_attribute_delete_prevent', function ($prevent, $attribute_id) use ($attribute_name) {
					global $wpdb;

					$attribute = $wpdb->get_row(
						$wpdb->prepare(
							"SELECT attribute_name FROM {$wpdb->prefix}woocommerce_attribute_taxonomies WHERE attribute_id = %d",
							$attribute_id
						)
					);

					if ($attribute && $attribute->attribute_name === $attribute_name) {
						$prevent = true; // Prevent deletion
					}

					return $prevent;
				}, 10, 2);
			}
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
		if ($current_version !== ANAR_WOOCOMERCE_API_VERSION) {
			update_option('anar_woocommerce_api_version', ANAR_WOOCOMERCE_API_VERSION);
		}
	}

}

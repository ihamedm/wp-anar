<?php
namespace Anar\Admin;

class Product_Status_Changer {

    protected static $instance;

    public static function get_instance() {
        if (null === static::$instance) {
            static::$instance = new static();
        }
        return static::$instance;
    }

    protected function __construct() {
        // Hook into WooCommerce to add custom action
        add_action('admin_footer-edit.php', array($this, 'add_publish_action'));
        add_action('load-edit.php', array($this, 'handle_publish_action'));
    }

    // Add custom action to the bulk actions dropdown
    public function add_publish_action() {
        global $post_type;

        if ($post_type == 'product') {
            ?>
            <script type="text/javascript">
                jQuery(document).ready(function() {
                    // Add "Publish Products" option to the bulk actions dropdown
                    jQuery('<option>').val('publish_products').text('انتشار محصولات پیش نویس').appendTo('select[name="action"], select[name="action2"]');
                });
            </script>
            <?php
        }
    }

    // Handle the custom action when it's triggered
    public function handle_publish_action() {
        global $typenow;

        if ($typenow == 'product') {
            // Get the action
            $wp_list_table = _get_list_table('WP_Posts_List_Table');
            $action = $wp_list_table->current_action();

            if ($action == 'publish_products') {
                // Get the selected product IDs
                $product_ids = $_GET['post'] ? array_map('intval', $_GET['post']) : array();

                if (!empty($product_ids)) {
                    foreach ($product_ids as $product_id) {
                        // Publish the product
                        $post_data = array(
                            'ID' => $product_id,
                            'post_status' => 'publish'
                        );
                        wp_update_post($post_data);
                    }
                }

                // Redirect to the product list page
                wp_redirect(add_query_arg('bulk_published_products', count($product_ids), remove_query_arg(array('action', 'action2', 'post'), wp_get_referer())));
                exit;
            }
        }
    }
}
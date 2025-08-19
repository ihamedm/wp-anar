<?php

namespace Anar;

use Anar\Core\Logger;

class SyncTools{

    private $baseApiUrl;
    private $logger;

    private $sync;

    private static $instance;

    public static function get_instance(){
        if( null === self::$instance ){
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function __construct() {
        $this->baseApiUrl = 'https://api.anar360.com/wp/products';
        $this->logger = new Logger();
        $this->sync = Sync::get_instance();

        add_action('wp_ajax_anar_find_not_synced_products', array($this, 'find_not_synced_products_ajax_callback'));

        add_action('pre_get_posts', [$this, 'filter_not_synced_products']);

        // show total products changed notice
        // this is accrued when user add/remove some product from anar panel
        //add_action('admin_notices', [$this, 'show_total_products_changed_notice']);
    }


    private function log($message){
        $this->logger->log($message, 'sync');
    }



    public function show_total_products_changed_notice() {
        $awca_api_total_products = get_option('awca_api_total_products', 0);
        $awca_last_sync_total_products = get_option(OPT_KEY__COUNT_ANAR_PRODUCT_ON_DB, 0);

        if(
            $awca_api_total_products == 0 || $awca_last_sync_total_products == 0
            || !is_numeric($awca_api_total_products) || !is_numeric($awca_last_sync_total_products)
            || awca_is_import_products_running()) {
            return;
        }

        // Calculate the absolute difference between the two values
        $difference = abs($awca_api_total_products - $awca_last_sync_total_products);

        // If difference is more than 10 products, show the notice
        if ($difference >= 10) {
            // Get the direction of change (increase or decrease)
            $change_direction = ($awca_api_total_products > $awca_last_sync_total_products)
                ? 'افزایش'
                : 'کاهش';

            // Create the notice message
            $notice = sprintf(
                '<div class="notice notice-warning is-dismissible">
                <p>
                    <strong>تغییر در تعداد محصولات انار:</strong> 
                    تعداد %d محصول %s یافته است. 
                    (تعداد فعلی: %d، تعداد قبلی: %d)
                    
                    <a href="%s">همگام‌سازی محصولات</a>
                </p>
            </div>',
                $difference,
                $change_direction,
                $awca_api_total_products,
                $awca_last_sync_total_products,
                admin_url('admin.php?page=wp-anar')
            );

            // Echo the notice
            echo wp_kses_post($notice);
        }
    }

    public function found_not_synced_products($hours_ago)
    {
        $time_ago = current_time('mysql', false);
        $time_ago = date('Y-m-d H:i:s', strtotime($time_ago . " -{$hours_ago} hours"));
        
        $this->log("Checking for products not synced since: " . $time_ago);
        
        $args = array(
            'post_type' => 'product',
            'post_status' => ['publish', 'draft'],
            'posts_per_page' => -1,
            'meta_query' => array(
                'relation' => 'AND',
                array(
                    'key' => '_anar_products',
                    'compare' => 'EXISTS'
                ),
                array(
                    'relation' => 'OR',
                    array(
                        'key' => '_anar_last_sync_time',
                        'compare' => 'NOT EXISTS'
                    ),
                    array(
                        'key' => '_anar_last_sync_time',
                        'value' => $time_ago,
                        'compare' => '<',
                        'type' => 'DATETIME'
                    )
                )
            )
        );

        $products = new \WP_Query($args);

        return $products->found_posts;
    }

    public function find_not_synced_products_ajax_callback() {

        // Verify nonce
        if (!isset($_POST['security_nonce']) || !wp_verify_nonce($_POST['security_nonce'], 'awca_not_synced_products_nonce')) {
            wp_send_json_error(array(
                'message' => 'فرم نامعتبر است.'
            ));
        }

        // Check user capabilities
        if (!current_user_can('manage_woocommerce')) {
            wp_send_json_error(array(
                'message' => 'شما مجوز این کار را ندارید!'
            ));
        }


        $hours_ago = $_POST['hours_ago'] ?? 1;

        $found_posts = $this->found_not_synced_products($hours_ago);
        $message = $found_posts > 0 ? "$found_posts محصول پیدا شد" : 'همه محصولات آپدیت هستند.';
        wp_send_json_success([
            'found_posts' => $found_posts,
            'toast' => $message,
            'message' => sprintf('<span>%s</span>%s',
                $message,
                $found_posts > 0 ? sprintf('<a href="%s" target="_blank">%s</a>'
                    , admin_url('edit.php?post_type=product&sync=late&hours_ago='.$hours_ago), get_anar_icon('external', 14)) : '',
            )
        ]);

    }



    public function filter_not_synced_products($query){
        if (!is_admin() || !$query->is_main_query()) {
            return;
        }

        $hours_ago = $_GET['hours_ago'] ?? 1;
        // Use WordPress current_time instead of PHP date function
        $time_ago = current_time('mysql', false);
        $time_ago = date('Y-m-d H:i:s', strtotime($time_ago . " -{$hours_ago} hours"));

        if (isset($_GET['sync']) && $_GET['sync'] === 'late') {
            $query->set('post_status', array('publish'));
            $query->set('meta_query',
                [
                    'relation' => 'AND',
                    array(
                        'key' => '_anar_products',
                        'compare' => 'EXISTS'
                    ),
                    array(
                        'key' => '_anar_deprecated',
                        'compare' => 'NOT EXISTS'
                    ),
                    array(
                        'relation' => 'OR',
                        array(
                            'key' => '_anar_last_sync_time',
                            'compare' => 'NOT EXISTS'
                        ),
                        array(
                            'key' => '_anar_last_sync_time',
                            'value' => $time_ago,
                            'compare' => '<',
                            'type' => 'DATETIME'
                        )
                    )
                ]
            );
        }
    }

}
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
        $this->sync = new Sync();

        add_action('wp_ajax_anar_find_not_synced_products', array($this, 'find_not_synced_products_ajax_callback'));

        add_action('pre_get_posts', [$this, 'filter_not_synced_products']);

        // show total products changed notice
        // this is accrued when user add/remove some product from anar panel
        //add_action('admin_notices', [$this, 'show_total_products_changed_notice']);
    }

    private function callAnarApi($apiUrl) {
        return ApiDataHandler::tryGetAnarApiResponse($apiUrl);
    }

    private function log($message){
        $this->logger->log($message, 'sync');
    }


    /**
     * periodically call products api, check total
     * and compare with saved total_products on last time get products
     *
     * @return void
     */
    public function get_api_total_products_number() {
        $apiUrl = add_query_arg(
            array('page' => 1, 'limit' => 1),
            $this->baseApiUrl
        );
        $awcaProducts = $this->callAnarApi($apiUrl);

        if (is_wp_error($awcaProducts)) {
            return;
        }

        update_option('awca_api_total_products', $awcaProducts->total);
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
        $time_ago = date('Y-m-d H:i:s', strtotime("-{$hours_ago} hour"));
        $args = array(
            'post_type' => 'product',
            'post_status' => 'publish',
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

        wp_send_json_success([
            'message' => sprintf("%s محصول پیدا شد", $found_posts),
            'markup_message' => sprintf('<p>%s محصول پیدا شد</p><p><a href="%s" target="_blank">مشاهده محصولات آپدیت نشده</a></p>',
                $found_posts,
                admin_url('edit.php?post_type=product&sync=late&hours_ago='.$hours_ago),
            )
        ]);

    }


    public function process_not_synced_products_batch($limit=5, $hours_ago=1) {

        $time_ago = date('Y-m-d H:i:s', strtotime("-{$hours_ago} hour"));

        $args = array(
            'post_type' => 'product',
            'posts_per_page' => $limit,
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

        $q = new \WP_Query($args);
        $this->log(sprintf('process %s product of %s found products that not synced in %s hours ago, start to sync [ Cronjob check ]',
            $limit, $q->found_posts, $hours_ago));

        if($q->have_posts()) : while($q->have_posts()) : $q->the_post();
            $product_wc_id = get_the_ID();

            $sku = get_post_meta($product_wc_id, '_anar_sku', true);
            $this->log($sku);
            if(!$sku)
                return;

            $api_url = 'https://api.anar360.com/wp/product/' . $sku;
            $product_api_data = $this->callAnarApi($api_url);

            $this->log(print_r($product_api_data, true));


            if (is_wp_error($product_api_data)) {
                $this->log('Failed to fetch products from API: ' . $product_api_data->get_error_message());
                break;
            }

            if(empty($product_api_data->variants)){
                $this->log('Product #'.$product_wc_id.' has empty variants from API');
                break;
            }

            if (count($product_api_data->variants) == 1) {
                $variant = $product_api_data->variants[0];
                $wc_product = wc_get_product($product_wc_id);

                $this->sync->updateProductStockAndPrice($wc_product,$product_api_data , $variant);
                $this->sync->updateProductMetadata($product_wc_id, $variant);

                $log_product = '#'.$product_wc_id;
            } else {
                foreach ($product_api_data->variants as $variant) {
                    $wc_variation_id = ProductData::get_product_variation_by_anar_sku($sku);
                    if($wc_variation_id && !is_wp_error($wc_variation_id)) {
                        $wc_product = wc_get_product($wc_variation_id);

                        $this->sync->updateProductStockAndPrice($wc_product, $product_api_data, $variant );
                        $this->sync->updateProductMetadata($product_wc_id, $variant);
                    }
                }
                $log_product = 'v#'.$product_wc_id;
            }

            $this->log(sprintf('%s' , $log_product));


        endwhile;endif; wp_reset_postdata();


        wp_send_json_success(
            sprintf('<p>%s محصول از %s محصول پردازش شد.</p>',
                $limit,
                $q->found_posts),
        );
    }


    public function filter_not_synced_products($query){
        if (!is_admin() || !$query->is_main_query()) {
            return;
        }

        $hours_ago = $_GET['hours_ago'] ?? 1;
        $time_ago = date('Y-m-d H:i:s', strtotime("-{$hours_ago} hour"));

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
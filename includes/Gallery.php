<?php

namespace Anar;

use Anar\Core\ImageDownloader;
use Anar\Core\Logger;

class Gallery{

    private static $instance = null;

    private $image_downloader;

    private $logger;

    public static function get_instance(){
        if( null === self::$instance ){
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function __construct() {
        $this->image_downloader = ImageDownloader::get_instance();
        $this->logger = new Logger();

        add_action('anar_edit_product_meta_box', [$this, 'add_dl_image_buttons_product_meta_box']);
        add_action('wp_ajax_awca_dl_the_product_images_ajax', [$this, 'download_the_product_gallery_and_thumbnail_images_ajax']);

        add_action('wp_ajax_anar_estimate_products_gallery_ajax', array($this, 'estimate_products_gallery_ajax'));
        add_action('wp_ajax_anar_dl_products_gallery_ajax', array($this, 'download_products_gallery_ajax'));


    }

    private function log($message, $level = 'info'){
        $this->logger->log($message, 'general', $level);
    }

    public function add_dl_image_buttons_product_meta_box(){
        global $post;
        $image_url = get_post_meta($post->ID, '_product_image_url', true);
        $gallery_image_urls = get_post_meta($post->ID, '_anar_gallery_images', true);
        
        // Check if product already has gallery images set
        $product = wc_get_product($post->ID);
        $existing_gallery_ids = $product ? $product->get_gallery_image_ids() : array();
        $has_gallery_images = !empty($existing_gallery_ids);
        
        // Check if product has thumbnail set
        $has_thumbnail = $product ? $product->get_image_id() : false;
        

        // Show thumbnail download button if no thumbnail and image URL exists
        if (!$has_thumbnail) {
        ?>
        <a href="#" class="anar-ajax-action awca-btn awca-alt-btn awca-outline-btn" 
           id="anar-dl-product-thumbnail-form"
           style="margin-bottom: 10px; display: inline-block; text-decoration: none;"
           data-action="awca_dl_the_product_images_ajax"
           data-product_id="<?php echo $post->ID; ?>"
           data-reload="success"
           data-reload_timeout="1500"
           data-type="thumbnail">
            دریافت تصویر شاخص محصول از انار
            <svg class="spinner-loading" width="24px" height="24px" viewBox="0 0 66 66" xmlns="http://www.w3.org/2000/svg">
                <circle class="path" fill="none" stroke-width="6" stroke-linecap="round" cx="33" cy="33" r="30"></circle>
            </svg>
        </a>
        <?php
        }
        
        // Show gallery download button if no gallery images and gallery URLs exist
        if (!$has_gallery_images && !empty($gallery_image_urls)) {
        ?>
        <a href="#" class="anar-ajax-action awca-btn awca-alt-btn awca-outline-btn" 
           id="anar-dl-product-gallery-form"
           style="display: inline-block; text-decoration: none;"
           data-action="awca_dl_the_product_images_ajax"
           data-product_id="<?php echo $post->ID;?>"
           data-reload="success"
           data-reload_timeout="2000"
           data-type="gallery"
           data-gallery_image_limit="20">
            دریافت تصاویر گالری محصول از انار
            <svg class="spinner-loading" width="24px" height="24px" viewBox="0 0 66 66"
                 xmlns="http://www.w3.org/2000/svg">
                <circle class="path" fill="none" stroke-width="6" stroke-linecap="round" cx="33" cy="33"
                        r="30"></circle>
            </svg>
        </a>
        <?php
            }
    }

    public function download_the_product_gallery_and_thumbnail_images_ajax(){

        if ( !isset( $_POST['product_id'] ) ) {
            wp_send_json_error( array( 'message' => 'product_id required') );
        }

        if ( !isset( $_POST['type'] ) ) {
            wp_send_json_error( array( 'message' => 'type required') );
        }

        $product_id = intval( $_POST['product_id'] );
        $type = $_POST['type'];
        $image_downloader = \Anar\Core\ImageDownloader::get_instance();

        if ($type === 'thumbnail') {
            // Handle thumbnail download
            $image_url = get_post_meta($product_id, '_product_image_url', true);
            
            // If no image URL in meta, fetch fresh data from API
            if (empty($image_url)) {
                $fresh_data = ProductData::fetch_anar_product_by_sku($product_id);
                
                if (!$fresh_data['success']) {
                    wp_send_json_error( array( 'message' => 'خطا در دریافت اطلاعات محصول: ' . $fresh_data['message']) );
                }
                
                // Check if mainImage exists in fresh data
                if (isset($fresh_data['data']->mainImage) && !empty($fresh_data['data']->mainImage)) {
                    $image_url = $fresh_data['data']->mainImage;                    
                } else {
                    wp_send_json_error( array( 'message' => 'تصویر شاخص در اطلاعات محصول یافت نشد.') );
                }
            }

            $res_thumbnail = $image_downloader->set_product_thumbnail($product_id, $image_url);

            if(is_wp_error($res_thumbnail)){
                wp_send_json_error( array( 'message' => $res_thumbnail->get_error_message() ) );
            }

            wp_send_json_success(array('message' => 'تصویر شاخص با موفقیت دانلود و به محصول افزوده شد.'));

        } elseif ($type === 'gallery') {
            // Handle gallery download
            $gallery_image_limit = $_POST['gallery_image_limit'] ? (int) $_POST['gallery_image_limit'] : 5;
            $gallery_image_urls = get_post_meta($product_id, '_anar_gallery_images', true);

            if (empty($gallery_image_urls)) {
                wp_send_json_error( array( 'message' => 'تصاویر گالری برای این محصول یافت نشد.') );
            }

            $res_gallery = $image_downloader->set_product_gallery($product_id, $gallery_image_urls, $gallery_image_limit);

            if(is_wp_error($res_gallery)){
                wp_send_json_error( array( 'message' => $res_gallery->get_error_message() ) );
            }

            wp_send_json_success(array('message' => 'تصاویر گالری با موفقیت دانلود و به محصول افزوده شد.'));

        } else {
            wp_send_json_error( array( 'message' => 'نوع نامعتبر. فقط thumbnail یا gallery مجاز است.') );
        }
    }

    public function estimate_products_gallery_ajax(){
        $max_images = $_POST['max_images'] ?? 5;
        $count_gallery_images = 0;

        $args = array(
            'post_type'      => 'product',
            'post_status'    => array('publish', 'draft'),
            'posts_per_page' => -1,
            'meta_query'     => array(
                array(
                    'key'     => '_anar_gallery_processed',
                    'compare' => 'NOT EXISTS',
                ),
            ),
            'fields'         => 'ids',
        );

        $query_products = new \WP_Query($args);
        foreach($query_products->posts as $product){
            $gallery_images = get_post_meta( $product, '_anar_gallery_images', true );
            if(is_array($gallery_images)){
                $count_gallery_images += count($gallery_images) > $max_images ? $max_images : count($gallery_images);
            }
        }

        $host_needed = $count_gallery_images * 0.7;
        $host_needed_h = $host_needed > 1000 ? round($host_needed/1000, 1) . ' گیگابایت' : $host_needed . ' مگابایت';

        if($query_products->found_posts == 0){
            wp_send_json_success([
                'message' => '<p>گالری همه محصولات دانلود شده است</p>'
                ]);
        }

        wp_send_json_success([
            'message' => sprintf(
                '
                <p>حداقل به <strong>%s</strong> فضای خالی هاست برای دانلود تصاویر نیاز دارید</p>
                <p>تعداد %s تصویر از %s محصول باید دانلود شود.</p>
            ', $host_needed_h ,$count_gallery_images, $query_products->found_posts),
        ]);
    }


    public function download_products_gallery_ajax() {
        global $wpdb;
        // Verify nonce
        if (!isset($_POST['security_nonce']) || !wp_verify_nonce($_POST['security_nonce'], 'anar_dl_products_gallery_ajax_nonce')) {
            $this->log('Security nonce verification failed in download_products_gallery_ajax', 'error');
            wp_send_json_error(array(
                'message' => 'فرم نامعتبر است.'
            ));
        }

        // Check user capabilities
        if (!current_user_can('manage_woocommerce')) {
            $this->log('User lacks manage_woocommerce capability in download_products_gallery_ajax', 'error');
            wp_send_json_error(array(
                'message' => 'شما مجوز این کار را ندارید!'
            ));
        }

        $page = isset($_POST['page']) ? intval($_POST['page']) : 1;
        $limit = isset($_POST['limit']) ? intval($_POST['limit']) : 1;
        $max_images = isset($_POST['max_images']) ? intval($_POST['max_images']) : 5;
        $has_more = true;
        $total_processed = 0;
        $total_downloaded = 0;
        $found_products = 0;
        $this_loop_products = 0;
        $queue_transient_key = 'anar_gallery_queue';
        $queue_ttl = 60 * 60; // 1 hour
        $product_ids = [];

        try {
            // On first page, build the queue and store in transient
            if ($page === 1) {
                // Build the queue of product IDs to process
                $query = $wpdb->prepare("
                    SELECT p.ID FROM {$wpdb->posts} p
                    INNER JOIN {$wpdb->postmeta} pm1 ON p.ID = pm1.post_id AND pm1.meta_key = '_anar_gallery_images'
                    LEFT JOIN {$wpdb->postmeta} pm2 ON p.ID = pm2.post_id AND pm2.meta_key = '_anar_gallery_processed'
                    WHERE p.post_type = 'product'
                      AND p.post_status IN ('publish', 'draft')
                      AND pm2.post_id IS NULL
                ");
                $all_ids = $wpdb->get_col($query);
                if ($wpdb->last_error) {
                    throw new \Exception('Database error: ' . $wpdb->last_error);
                }
                set_transient($queue_transient_key, $all_ids, $queue_ttl);
            }

            // Get the queue from transient
            $queue = get_transient($queue_transient_key);
            if (!is_array($queue) || empty($queue)) {
                $has_more = false;
                $this->log('No more products found for gallery download (queue empty)', 'info');
                delete_transient($queue_transient_key);
                wp_send_json_success([
                    'success' => true,
                    'has_more' => false,
                    'total_processed' => $total_processed,
                    'total_downloaded' => $total_downloaded,
                    'loop_products' => $this_loop_products,
                    'found_products' => 0,
                    'message' => 'گالری همه محصولات دانلود شده است',
                ]);
            }

            // Pop the next $limit IDs from the queue
            $product_ids = array_splice($queue, 0, $limit);
            $found_products = count($queue) + count($product_ids); // total left + this batch
            $this_loop_products = count($product_ids);
            $has_more = !empty($queue);

            // Update the queue in the transient
            set_transient($queue_transient_key, $queue, $queue_ttl);

            foreach ($product_ids as $product_id) {
                try {
                    $gallery_image_urls = get_post_meta($product_id, '_anar_gallery_images', true);
                    if (is_array($gallery_image_urls)) {
                        if (count($gallery_image_urls) > $max_images) {
                            $gallery_image_urls = array_slice($gallery_image_urls, 0, $max_images);
                        }
                        $result = $this->image_downloader->set_product_gallery($product_id, $gallery_image_urls, $max_images);
                        if (!is_wp_error($result)) {
                            $total_downloaded += count($gallery_image_urls);
                        } else {
                            $this->log(sprintf(
                                'Error processing gallery for product #%d: %s',
                                $product_id,
                                $result->get_error_message()
                            ), 'error');
                        }
                    }
                    // Mark as processed regardless of outcome to avoid reprocessing
                    update_post_meta($product_id, '_anar_gallery_processed', current_time('mysql'));
                    $total_processed++;
                } catch (\Exception $e) {
                    $this->log('Exception processing product #' . $product_id . ': ' . $e->getMessage(), 'error');
                }
            }

        } catch (\Exception $exception) {
            $this->log('Exception in download_products_gallery_ajax: ' . $exception->getMessage(), 'error');
            $response = [
                'success' => false,
                'has_more' => false,
                'total_processed' => $total_processed,
                'total_downloaded' => $total_downloaded,
                'loop_products' => $this_loop_products,
                'found_products' => $found_products,
                'message' => $exception->getMessage()
            ];
            wp_send_json($response);
        } finally {
            $this->log(sprintf('Completed page %d. Found Products %s, total Processed %d , Loop Products %s, Has More? %s, $_POST: %s',
                $page,
                $found_products,
                $total_processed,
                $this_loop_products,
                print_r($has_more, true),
                print_r($_POST, true)
            ), 'debug');
            $response = [
                'success' => true,
                'has_more' => $has_more,
                'total_processed' => $total_processed,
                'total_downloaded' => $total_downloaded,
                'loop_products' => $this_loop_products,
                'found_products' => $found_products,
            ];
            wp_send_json($response);
        }
    }
}
<?php

namespace Anar;

use Anar\Core\Image_Downloader;
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
        $this->image_downloader = new Image_Downloader();
        $this->logger = new Logger();

        add_action('wp_ajax_anar_estimate_products_gallery_ajax', array($this, 'estimate_products_gallery_ajax'));
        add_action('wp_ajax_anar_dl_products_gallery_ajax', array($this, 'download_products_gallery_ajax'));
    }

    private function log($message, $level = 'info'){
        $this->logger->log($message, 'general', $level);
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
        // Verify nonce
        if (!isset($_POST['security_nonce']) || !wp_verify_nonce($_POST['security_nonce'], 'anar_dl_products_gallery_ajax_nonce')) {
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

        $page = isset($_POST['page']) ? intval($_POST['page']) : 1;
        $limit = isset($_POST['limit']) ? intval($_POST['limit']) : 1;
        $max_images = isset($_POST['max_images']) ? intval($_POST['max_images']) : 5;
        $has_more = true;
        $total_processed = 0;
        $total_downloaded = 0;
        $found_products = 0;
        $this_loop_products = 0;

        // Query arguments to get draft products
        $query_args = array(
            'post_type'      => 'product',
            'post_status'    => array('publish', 'draft'),
            'posts_per_page' => $limit,
            'meta_query'     => array(
                array(
                    'key'     => '_anar_gallery_processed',
                    'compare' => 'NOT EXISTS',
                ),
            ),
            'fields'         => 'ids',
        );


        try {
            $products_query = new \WP_Query($query_args);

            $found_products = $products_query->found_posts;
            $this_loop_products = count($products_query->posts);

            if ($products_query->have_posts()) :
                while ($products_query->have_posts()) :
                    $products_query->the_post();
                    $product_id = get_the_ID();
                    $gallery_image_urls = get_post_meta($product_id, '_anar_gallery_images', true);

                    // If gallery images exist, set the product gallery
                    if (is_array($gallery_image_urls)) {

                        // Only use the first 5 image URLs if there are more than 5
                        if (count($gallery_image_urls) > $max_images) {
                            $gallery_image_urls = array_slice($gallery_image_urls, 0, $max_images);
                        }

                        $result = $this->image_downloader->set_product_gallery($product_id, $gallery_image_urls, $max_images); // Pass product_id here
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

                    $total_downloaded += count($gallery_image_urls);

                    $total_processed++;
                    // Clean up product object to free memory
                endwhile;
            else:
                $has_more = false;
                return;
            endif;

            // Reset post data
            wp_reset_postdata();

            // Clear some memory
            if (function_exists('gc_collect_cycles')) {
                gc_collect_cycles();
            }

        }catch (\Exception $exception){
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

            self::log(sprintf('Completed page %d. Found Products %s, total Published %d , Loop Products %s, Has More? %s, $_POST: %s',
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
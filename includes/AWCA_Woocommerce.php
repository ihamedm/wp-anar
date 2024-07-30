<?php

namespace Anar;

class AWCA_Woocommerce{

    public function __construct(){

        add_action( 'add_meta_boxes', [$this, 'awca_add_custom_meta_box'] );

    }

     // Add button under product thumbnail in product edit page
    public function awca_custom_meta_box_html($post) {
        $image_url = get_post_meta($post->ID, '_product_image_url', true);
        $gallery_image_urls = get_post_meta($post->ID, '_anar_gallery_images', true);
        ?>
        <div id="awca-custom-meta-box-container">

            <button id="awca-dl-the-product-images" class="awca-primary-btn" data-product-id="<?php echo $post->ID;?>"
            <?php echo !$image_url && !$gallery_image_urls ? ' disabled' : '';?>
            >
                دریافت تصاویر گالری محصول از انار
                <svg class="spinner-loading" width="24px" height="24px" viewBox="0 0 66 66" xmlns="http://www.w3.org/2000/svg">
                    <circle class="path" fill="none" stroke-width="6" stroke-linecap="round" cx="33" cy="33" r="30"></circle>
                </svg>
            </button>

            <?php wp_nonce_field( 'awca_nonce', 'awca_nonce_field' ); ?>
        </div>
        <?php
    }

    public function awca_add_custom_meta_box() {
        add_meta_box(
            'awca_custom_meta_box',            // Unique ID
            'انار',                  // Box title
            [$this, 'awca_custom_meta_box_html'],       // Content callback
            'product',                         // Post type
            'side',                            // Context (side, advanced, etc.)
            'high'                          // Priority (default, high, low, etc.)
        );
    }


    public static function dl_and_set_product_image_job() {
        $paged = 1; // Start from the first page
        $posts_per_page = 20; // Number of products per page
        $total_processed = 0; // Counter to keep track of processed products
        do {
            // Setup WP_Query arguments
            $args = array(
                'post_type'      => 'product',
                'post_status'    => array('publish', 'draft'), // Include both published and draft posts
                'meta_query'     => array(
                    array(
                        'key'     => '_product_image_url',
                        'value'   => '',
                        'compare' => '!=', // Ensure meta_value is not empty
                    ),
                ),
                'posts_per_page' => $posts_per_page, // Limit to 10 posts
//                'paged'          => $paged, // Set the current page
            );

            // Perform the query
            $query = new \WP_Query($args);

            $found_posts = $query->found_posts;

            awca_log('WP_Query Results: ' . $query->found_posts . ' on loop ' . $paged);

            if ($query->have_posts()) {
                while ($query->have_posts()) {
                    $query->the_post();
                    $post_id = get_the_ID();
                    $image_url = get_post_meta($post_id, '_product_image_url', true);
                    if (!empty($image_url)) {
                        awca_set_product_image_from_url($post_id, $image_url);
                    }
                    $total_processed++;
                }
                wp_reset_postdata();
            } else {
                awca_log('No more products found with non-empty _product_image_url on page ' . $paged);
                break; // Exit the loop if no more products are found
            }

            $paged++; // Increment the page number

            $progress_message = sprintf(' دانلود تصاویر - %s تصویر شاخص دانلود شد', $total_processed);
            set_transient('awca_product_creation_progress', $progress_message, 3 * MINUTE_IN_SECONDS);

        } while ($found_posts > 0);

        awca_log('Total products processed: ' . $total_processed);
    }



    public static function dl_and_set_product_gallery_job() {
        $paged = 1; // Start from the first page
        $posts_per_page = 4; // Number of products per page
        $total_processed = 0; // Counter to keep track of processed products

        do {
            // Setup WP_Query arguments
            $args = array(
                'post_type'      => 'product',
                'post_status'    => array('publish', 'draft'), // Include both published and draft posts
                'meta_query'     => array(
                    array(
                        'key'     => '_anar_gallery_images',
                        'value'   => '',
                        'compare' => '!=', // Ensure meta_value is not empty
                    ),
                ),
                'posts_per_page' => $posts_per_page, // Limit to 4 posts
                'paged'          => $paged, // Set the current page
            );

            // Perform the query
            $query = new \WP_Query($args);

            awca_log('WP_Query Results: ' . $query->found_posts . ' on page ' . $paged);

            if ($query->have_posts()) {
                while ($query->have_posts()) {
                    $query->the_post();
                    $post_id = get_the_ID();
                    awca_log($post_id . ' -- ' . get_the_title($post_id));
                    $gallery_image_urls = get_post_meta($post_id, '_anar_gallery_images', true);
                    if (!empty($gallery_image_urls)) {
                        awcs_set_product_gallery_from_array_urls($post_id, $gallery_image_urls);
                    }
                    $total_processed++;
                }
                wp_reset_postdata();
            } else {
                awca_log('No more products found with non-empty _anar_gallery_images on page ' . $paged);
                break; // Exit the loop if no more products are found
            }

            $paged++; // Increment the page number

        } while ($query->max_num_pages >= $paged);

        awca_log('Total products processed: ' . $total_processed);
    }



}
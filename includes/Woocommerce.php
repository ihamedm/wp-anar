<?php

namespace Anar;

/**
 * This class responsible to do some customization of
 * - product edit page
 * - product list page
 * - replace default image placeholder in admin & front when product still doesn't have uploaded thumbnail
 */
class Woocommerce{

    public function __construct(){
        // add meta box for anar actions
        add_action( 'add_meta_boxes', [$this, 'anar_product_edit_page_meta_box'] );
        add_action('wp_ajax_awca_dl_the_product_images_ajax', [$this, 'download_the_product_gallery_and_thumbnail_images_ajax']);
        add_action('wp_ajax_nopriv_awca_dl_the_product_images_ajax', [$this, 'download_the_product_gallery_and_thumbnail_images_ajax']);


        // add a column to show Anar label on product list page
        add_filter('manage_product_posts_columns', [$this, 'anar_product_list_column']);
        add_action('manage_product_posts_custom_column', [$this, 'anar_product_list_label'], 10, 2);

        // add anar products filter
        add_filter('views_edit-product', [$this, 'anar_products_filter_link']);
        add_action('pre_get_posts', [$this, 'filter_anar_products']);
        add_filter('post_row_actions', [$this, 'add_view_on_anar_action_link'], 10, 2);

        add_action('pre_get_posts', [$this, 'filter_anar_deprecated_products']);


    }

    public function anar_product_edit_page_meta_box() {
        // Add meta box only for products with _anar_products=true meta field
        $post_id = get_the_ID();
        $anar_products = get_post_meta($post_id, '_anar_products', true);

        if($anar_products){
            add_meta_box(
                'awca_custom_meta_box',            // Unique ID
                'انار',                  // Box title
                [$this, 'anar_product_edit_page_meta_box_html'],       // Content callback
                'product',                         // Post type
                'side',                            // Context (side, advanced, etc.)
                'high'                          // Priority (default, high, low, etc.)
            );
        }


    }

    public function anar_product_edit_page_meta_box_html($post) {
        $image_url = get_post_meta($post->ID, '_product_image_url', true);
        $gallery_image_urls = get_post_meta($post->ID, '_anar_gallery_images', true);
        $last_sync_time = get_post_meta($post->ID, '_anar_last_sync_time', true);
        $anar_prices = get_post_meta($post->ID, '_anar_prices', true);
        ?>
        <div id="awca-custom-meta-box-container">
            <?php if($last_sync_time) {
                printf('<div class="awca-product-last-sync-time"><span>آخرین همگام سازی با انار</span> <strong>%s</strong>%s</div>',
                    mysql2date('j F Y' . ' ساعت ' . 'H:i', $last_sync_time),
                    ANAR_IS_ENABLE_OPTIONAL_SYNC_PRICE == 'yes' ? 'همگام سازی قیمت غیر فعال است.' : ''
                );
            }?>

            <?php if($anar_prices) {
                printf('<div class="awca-product-anar-prices">
                        <span>قیمت فروش در انار <strong>%s</strong></span>
                        <span>قیمت همکار <strong>%s</strong></span>
                        </div>',
                    awca_get_formatted_price($anar_prices['price']) ?? '-',
                    awca_get_formatted_price($anar_prices['priceForResell']) ?? '-',
                );
            }?>

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

    public function download_the_product_gallery_and_thumbnail_images_ajax(){

        if ( !isset( $_POST['product_id'] ) ) {
            wp_send_json_error( array( 'message' => 'product_id required') );
        }

        $gallery_image_limit = $_POST['gallery_image_limit'] ?? 5;
        $product_id = intval( $_POST['product_id'] );
        $image_downloader = new \Anar\Core\Image_Downloader();

        // set product thumbnail
        $image_url = get_post_meta($product_id, '_product_image_url', true);
        if (!empty($image_url)) {
            $res = $image_downloader->set_product_thumbnail($product_id, $image_url);

            if(is_wp_error($res)){
                wp_send_json_error( array( 'message' => $res->get_error_message() ) );
            }

        }


        // set product gallery
        $gallery_image_urls = get_post_meta($product_id, '_anar_gallery_images', true);

        // prevent to pass more images as gallery
        if (count($gallery_image_urls) > $gallery_image_limit) {
            $gallery_image_urls = array_slice($gallery_image_urls, 0, $gallery_image_limit);
        }

        if (!empty($gallery_image_urls)) {
            $res_gallery = $image_downloader->set_product_gallery($product_id, $gallery_image_urls);

            if(is_wp_error($res_gallery)){
                wp_send_json_error( array( 'message' => $res_gallery->get_error_message() ) );
            }
        }

        wp_send_json_success(array('message' => 'تصاویر با موفقیت دانلود و به محصول افزوده شد.'));
    }

    public function anar_product_list_column($columns){
        $new_columns = array();
        foreach ($columns as $key => $value) {
            $new_columns[$key] = $value;
            if ($key === 'date') {
                $new_columns['product_label'] = 'انار';
            }
        }
        return $new_columns;
    }

    public function anar_product_list_label($column, $post_id)
    {
        if ($column === 'product_label') {
            $anar_products = get_post_meta($post_id, '_anar_sku', true);
            if (!empty($anar_products)) {
                $anar_prices = get_post_meta($post_id, '_anar_prices', true);

                $anar_url = "https://anar360.com/earning-income/product/{$anar_products}";
                echo '<a class="anar-fruit" href="'.$anar_url.'" target="_blank" title="مشاهده محصول در سایت انار۳۶۰"><img src="'.ANAR_WC_API_PLUGIN_URL.'assets/images/anar-fruit.svg"></a>';

                if($anar_prices && ANAR_IS_ENABLE_OPTIONAL_SYNC_PRICE == 'yes') {
                    echo '<br>';
                    echo 'قیمت انار';
                    echo '<br>';
                    echo awca_get_formatted_price($anar_prices['price']) ?? '-';
                }
            }
        }
    }


    public function anar_products_filter_link($views) {
        global $wpdb;

        $count = $wpdb->get_var($wpdb->prepare("
    SELECT COUNT(DISTINCT p.ID)
    FROM {$wpdb->posts} AS p
    INNER JOIN {$wpdb->postmeta} AS pm ON p.ID = pm.post_id
    WHERE p.post_type = 'product'
    AND p.post_status IN ('publish', 'draft', 'pending', 'private')
    AND pm.meta_key = %s
", '_anar_sku'));


        update_option('awca_count_anar_products_on_db', $count);
        // Check if current filter is active
        $current = isset($_GET['is_anar_product']) ? ' class="current"' : '';

        // Add the custom filter link
        $views['is_anar_product'] = sprintf(
            '<a href="%s"%s>انار <span class="count">(%d)</span></a>',
            admin_url('edit.php?post_type=product&is_anar_product=1'),
            $current,
            $count
        );

        return $views;
    }

    public function filter_anar_products($query) {

        if (!is_admin() || !$query->is_main_query()) {
            return;
        }

        if (isset($_GET['is_anar_product']) && $_GET['is_anar_product'] === '1') {
            $query->set('meta_query', array(
                array(
                    'key' => '_anar_sku',
                    'compare' => 'EXISTS'
                ),
            ));
        }

    }

    // Add filter to modify products query to show deprecated products
    public function filter_anar_deprecated_products($query) {
        if (!is_admin()) {
            return;
        }

        if (!$query->is_main_query() ||
            !isset($_GET['post_type']) ||
            $_GET['post_type'] !== 'product' ||
            !isset($_GET['anar_deprecated'])) {
            return;
        }

        // Add meta query to show only deprecated products
        $query->set('meta_query', array(
            array(
                'key' => '_anar_deprecated',
                'compare' => 'EXISTS'
            )
        ));
    }

    public function add_view_on_anar_action_link($actions, $post) {
        if ($post->post_type === 'product') {
            $anar_products = get_post_meta($post->ID, '_anar_sku', true);
            if (!empty($anar_products)) {
                $anar_url = "https://anar360.com/earning-income/product/{$anar_products}";
                $actions['view_on_shop'] = sprintf(
                    '<a href="%s" target="_blank" aria-label="%s">%s</a>',
                    esc_url($anar_url),
                    'مشاهده این محصول در سایت انار۳۶۰',
                    'مشاهده در انار'
                );
            }
        }

        return $actions;
    }


}
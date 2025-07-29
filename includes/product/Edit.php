<?php

namespace Anar\Product;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}
class Edit{

    public function __construct() {
        add_action( 'add_meta_boxes', [$this, 'anar_product_edit_page_meta_box'] );
        add_action('wp_ajax_awca_dl_the_product_images_ajax', [$this, 'download_the_product_gallery_and_thumbnail_images_ajax']);
        add_action('wp_ajax_nopriv_awca_dl_the_product_images_ajax', [$this, 'download_the_product_gallery_and_thumbnail_images_ajax']);

//        new PriceSync();
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
        $anar_shipments = get_post_meta($post->ID, '_anar_prices', true);
        $anar_sku = get_post_meta($post->ID, '_anar_sku', true);

        $anar_shop_url = get_option('_anar_shop_url', 'https://anar360.com/earning-income');
        $anar_url = $anar_shop_url ."/product/{$anar_sku}";

        $wc_product = wc_get_product($post->ID);

        ?>
        <div id="awca-custom-meta-box-container">

            <?php if($last_sync_time) {
                printf('<div class="awca-product-last-sync-time"><span>آخرین همگام سازی با انار</span> <strong>%s</strong>%s<br>%s</div>',
                    mysql2date('j F Y' . ' ساعت ' . 'H:i', $last_sync_time),
                    awca_time_ago($last_sync_time),
                    ANAR_IS_ENABLE_OPTIONAL_SYNC_PRICE == 'yes' ? 'همگام سازی قیمت غیر فعال است.' : ''
                );

            }?>

            <div class="awca-product-anar-prices">
                <?php if($anar_prices && $wc_product->get_type() == 'simple') {
                    printf('
                        <span>قیمت لیبل <strong>%s</strong></span>
                        <span>قیمت همکار <strong>%s</strong></span>
                        <span>قیمت فروش شما <strong>%s</strong></span>
                        <span style="color: #079d66">سود شما <strong>%s</strong></span>
                        ',
                        anar_get_formatted_price($anar_prices['labelPrice']) ?? '-',
                        anar_get_formatted_price($anar_prices['priceForResell']) ?? '-',
                        anar_get_formatted_price($anar_prices['price']) ?? '-',
                        anar_get_formatted_price($anar_prices['resellerProfit']) ?? '-',
                    );
                }

                printf('<span><a style="display: flex; gap:8px" href="%s" target="_blank"><span class="anar-fruit"><img src="' .ANAR_WC_API_PLUGIN_URL.'assets/images/anar-fruit.svg"></span> مشاهده محصول در سایت انار</a></strong></span>',
                    esc_url($anar_url)
                )

                ?>
            </div>

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
}
<?php

namespace Anar\Product;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}
class Edit{

    public function __construct() {
        add_action( 'add_meta_boxes', [$this, 'anar_product_edit_page_meta_box'] );
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


            <?php do_action( 'anar_edit_product_meta_box' ); ?>

            <?php wp_nonce_field( 'awca_nonce', 'awca_nonce_field' ); ?>

        </div>
        <?php
    }
}
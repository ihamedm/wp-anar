<?php

namespace Anar\Product;

class PriceSync {
    public function __construct() {
        add_action('admin_enqueue_scripts', [$this, 'enqueue_scripts']);
        add_action('wp_ajax_toggle_anar_price_sync', [$this, 'toggle_anar_price_sync_ajax']);
    }

    public function enqueue_scripts($hook) {
        global $post;
        
        // Only on product edit page
        if ($hook !== 'post.php' || get_post_type() !== 'product') {
            return;
        }

        // Only for Anar products
        $anar_products = get_post_meta($post->ID, '_anar_products', true);
        if (!$anar_products) {
            return;
        }

        // Get price data
        $price_sync_status = get_post_meta($post->ID, '_anar_price_sync_status', true);
        $anar_prices = get_post_meta($post->ID, '_anar_prices', true);
        
        // Get variation price data if product is variable
        $product = wc_get_product($post->ID);
        $variations_data = [];
        
        if ($product && $product->is_type('variable')) {
            $variations = $product->get_children();
            foreach ($variations as $variation_id) {
                $variation_prices = get_post_meta($variation_id, '_anar_prices', true);
                if ($variation_prices) {
                    $variations_data[$variation_id] = [
                        'minPrice' => isset($variation_prices['minPriceForResell']) ? $variation_prices['minPriceForResell'] : 0,
                        'maxPrice' => isset($variation_prices['maxPriceForResell']) ? $variation_prices['maxPriceForResell'] : 0,
                        'syncStatus' => get_post_meta($variation_id, '_anar_price_sync_status', true)
                    ];
                }
            }
        }

        // Enqueue styles
        wp_enqueue_style(
            'anar-price-sync',
            ANAR_WC_API_PLUGIN_URL . 'assets/css/edit-product.css',
            [],
            ANAR_PLUGIN_VERSION
        );

        // Enqueue script
        wp_enqueue_script(
            'anar-price-sync',
            ANAR_WC_API_PLUGIN_URL . 'assets/js/edit-product.js',
            ['jquery'],
            ANAR_PLUGIN_VERSION,
            true
        );

        // Localize script
        // Update localized data to include variations
        wp_localize_script('anar-price-sync', 'anarPriceData', [
            'productId' => $post->ID,
            'syncStatus' => $price_sync_status,
            'minPrice' => isset($anar_prices['minPriceForResell']) ? $anar_prices['minPriceForResell'] : 0,
            'maxPrice' => isset($anar_prices['maxPriceForResell']) ? $anar_prices['maxPriceForResell'] : 0,
            'nonce' => wp_create_nonce('anar_price_sync_nonce'),
            'isVariable' => $product && $product->is_type('variable'),
            'variations' => $variations_data
        ]);
    }

    public function toggle_anar_price_sync_ajax() {
        // Verify nonce
        if (!check_ajax_referer('anar_price_sync_nonce', 'nonce', false)) {
            wp_send_json_error('Invalid nonce');
        }

        $product_id = intval($_POST['product_id']);
        $variation_id = isset($_POST['variation_id']) ? intval($_POST['variation_id']) : 0;
        $status = $_POST['status'];

        // Update the meta for the main product or variation
        if ($variation_id) {
            update_post_meta($variation_id, '_anar_price_sync_status', $status);
        } else {
            update_post_meta($product_id, '_anar_price_sync_status', $status);
        }

        wp_send_json_success();
    }
}
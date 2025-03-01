<?php

namespace Anar;

class Multi_Seller {
    public function __construct() {
        // Add seller field to products
        add_action('woocommerce_product_options_inventory_product_data', array($this, 'add_seller_field'));
        add_action('woocommerce_process_product_meta', array($this, 'save_seller_field'));

        // Cart and checkout notices
        add_action('woocommerce_before_cart', array($this, 'display_multiple_seller_notice'));
        add_action('woocommerce_before_checkout_form', array($this, 'display_multiple_seller_notice'));

        // Modify shipping calculations
        add_filter('woocommerce_package_rates', array($this, 'modify_shipping_rates'), 10, 2);
        add_filter('woocommerce_cart_shipping_packages', array($this, 'split_shipping_packages'));
    }

    // Add seller field to product admin
    public function add_seller_field() {
        woocommerce_wp_text_input(array(
            'id' => '_product_seller',
            'label' => __('Seller ID', 'woocommerce'),
            'desc_tip' => true,
            'description' => __('Enter the seller ID for this product.', 'woocommerce')
        ));
    }

    // Save seller field
    public function save_seller_field($post_id) {
        $seller_id = isset($_POST['_product_seller']) ? sanitize_text_field($_POST['_product_seller']) : '';
        update_post_meta($post_id, '_product_seller', $seller_id);
    }

    // Get unique sellers from cart
    private function get_cart_sellers() {
        $sellers = array();

        foreach (WC()->cart->get_cart() as $cart_item) {
            $product_id = $cart_item['product_id'];
            $seller_id = get_post_meta($product_id, '_product_seller', true);

            if (!empty($seller_id) && !in_array($seller_id, $sellers)) {
                $sellers[] = $seller_id;
            }
        }

        return $sellers;
    }

    // Display notice about multiple packages
    public function display_multiple_seller_notice() {
        $sellers = $this->get_cart_sellers();

        if (count($sellers) > 1) {
            wc_add_notice(
                sprintf(
                    __('Your order will be shipped in %d separate packages as items are from different sellers.', 'woocommerce'),
                    count($sellers)
                ),
                'notice'
            );
        }
    }

    // Split cart items into packages by seller
    public function split_shipping_packages($packages) {
        $new_packages = array();

        foreach (WC()->cart->get_cart() as $item_key => $item) {
            $product_id = $item['product_id'];
            $seller_id = get_post_meta($product_id, '_product_seller', true);

            if (!isset($new_packages[$seller_id])) {
                $new_packages[$seller_id] = array(
                    'contents' => array(),
                    'contents_cost' => 0,
                    'applied_coupons' => WC()->cart->applied_coupons,
                    'seller_id' => $seller_id,
                    'destination' => $packages[0]['destination']
                );
            }

            $new_packages[$seller_id]['contents'][$item_key] = $item;
            $new_packages[$seller_id]['contents_cost'] += $item['line_total'];
        }

        return array_values($new_packages);
    }

    // Modify shipping rates based on seller
    public function modify_shipping_rates($rates, $package) {
        if (empty($package['seller_id'])) {
            return $rates;
        }

        // Here you can implement your logic to modify shipping rates based on seller
        // For example, filter shipping methods based on seller_id
        foreach ($rates as $rate_id => $rate) {
            // Example: if shipping method doesn't belong to this seller, unset it
            if (!$this->is_shipping_method_for_seller($rate_id, $package['seller_id'])) {
                unset($rates[$rate_id]);
            }
        }

        return $rates;
    }

    // Helper function to check if shipping method belongs to seller
    private function is_shipping_method_for_seller($rate_id, $seller_id) {
        // Implement your logic here to determine if a shipping method
        // belongs to a specific seller
        // This is just an example implementation
        $seller_shipping_methods = array(
            'seller1' => array('flat_rate:1', 'free_shipping:3'),
            'seller2' => array('flat_rate:2', 'local_pickup:4')
        );

        return isset($seller_shipping_methods[$seller_id]) &&
            in_array($rate_id, $seller_shipping_methods[$seller_id]);
    }
}

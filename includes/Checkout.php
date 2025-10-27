<?php
namespace Anar;

use Anar\Core\Logger;

defined( 'ABSPATH' ) || exit;

/**
 * Checkout Core Class
 * 
 * Base checkout functionality for WooCommerce integration with Anar platform.
 * Handles product type detection, order meta management, multi-package shipping,
 * and provides reusable methods for shipping calculations.
 * 
 * Key Responsibilities:
 * - Detect Anar products vs standard products in cart
 * - Manage order meta data for Anar orders
 * - Calculate shipping options from Anar API
 * - Handle multi-package alerts and fees
 * - Apply shipping rate multipliers for multiple packages
 * - Provide AJAX endpoints for admin shipping calculations
 * 
 * This class serves as the parent for CheckoutDropShipping which adds
 * Anar-specific shipping method display and dropshipping functionality.
 * 
 * @package Anar
 * @since 1.0.0
 */
class Checkout {

    /**
     * Meta key to mark orders containing Anar products
     * @var string
     */
    private const ANAR_ORDER_META = '_is_anar_order';
    
    /**
     * Meta key to identify Anar products
     * @var string
     */
    private const ANAR_PRODUCT_META = '_anar_products';
    
    /**
     * Logger instance for debugging
     * @var Logger
     */
    private $logger;

    /**
     * Singleton instance
     * @var Checkout
     */
    protected static $instance;

    /**
     * Get singleton instance
     * 
     * @return Checkout
     */
    public static function get_instance() {
        if ( ! isset( self::$instance ) ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Constructor - Register hooks and filters
     * 
     * Registers WordPress/WooCommerce hooks for:
     * - Cart product type detection
     * - Order meta management
     * - Multi-package alerts and fees
     * - Shipping rate multipliers
     * - AJAX endpoints for admin
     * 
     * @since 1.0.0
     */
    public function __construct() {
        add_action( 'woocommerce_before_calculate_totals', [$this, 'check_for_cart_products_types']);
        add_action( 'woocommerce_checkout_create_order', [$this, 'update_order_meta'], 20, 1 );
        
        // Handle orders created via admin or programmatically
        add_action('woocommerce_new_order', [$this, 'handle_new_order'], 10, 1);

        // AJAX endpoints for admin modal
        add_action('wp_ajax_awca_get_shipping_options_ajax', [$this, 'get_shipping_options_ajax']);

        // Show multiple package alert when Anar shipping is disabled
        if (!anar_shipping_enabled() && get_option('anar_show_multi_package_alert', 'yes') === 'yes') {
            // Show on checkout page (before shipping rates in order review table)
            add_action('woocommerce_review_order_before_shipping', [$this, 'display_multiple_packages_alert']);
            
            // Show on cart page (before shipping calculator in cart totals)
            add_action('woocommerce_cart_totals_before_shipping', [$this, 'display_multiple_packages_alert']);
        }

        // Add multiple package fee when enabled
        if (!anar_shipping_enabled() && get_option('anar_enable_multi_package_fee', 'no') === 'yes') {
             add_action('woocommerce_cart_calculate_fees', [$this, 'add_multiple_package_fee']);
        }

        // Multiply shipping rates for multiple packages when enabled
        if (!anar_shipping_enabled() && get_option('anar_enable_shipping_multiplier', 'no') === 'yes') {
            add_filter('woocommerce_package_rates', [$this, 'multiply_shipping_for_multiple_packages'], 100, 2);
        }
    }

    /**
     * Log a message to the checkout log file
     * 
     * @param string $message Message to log
     * @param string $level Log level (info, warning, error, debug)
     * @return void
     * @since 1.0.0
     */
    public function log($message, $level = 'info') {
        $this->logger = new Logger();
        $this->logger->log($message, 'checkout', $level);
    }

    /**
     * Safely get a product by ID with validation
     * 
     * Validates that the product exists and is a valid WC_Product instance
     * 
     * @param int $product_id Product ID to retrieve
     * @return \WC_Product|null Product object or null if invalid
     * @since 1.0.0
     */
    public function get_product_safely($product_id) {
        $product = wc_get_product($product_id);
        return $product instanceof \WC_Product ? $product : null;
    }

    /**
     * Safely get cart items with validation
     * 
     * Returns empty array if cart is not initialized
     * 
     * @return array Cart items array
     * @since 1.0.0
     */
    public function get_cart_items_safely() {
        return WC()->cart ? WC()->cart->get_cart() : [];
    }

    /**
     * Detect and classify product types in cart
     * 
     * Scans all cart items to determine:
     * - If cart contains Anar products (dropshipping products)
     * - If cart contains standard WooCommerce products
     * - If any product can ship to stock
     * 
     * Results are stored in WooCommerce session for use throughout checkout process.
     * This method runs before cart totals are calculated to ensure shipping methods
     * and fees can be determined based on product types.
     * 
     * Hooked to: woocommerce_before_calculate_totals
     * 
     * @return void Stores results in WC()->session
     * @since 1.0.0
     */
    public function check_for_cart_products_types() {
        $cart_items = $this->get_cart_items_safely();
        $has_standard_product = false;
        $has_anar_product = false;
        $order_can_ship_to_stock = false;

        foreach ($cart_items as $item => $values) {
            $_product = $this->get_product_safely($values['data']->get_id());
            if (!$_product) continue;

            $product_parent_id = $_product->get_parent_id();

            // Check for Anar products
            $anar_meta = $product_parent_id == 0
                ? get_post_meta($_product->get_id(), self::ANAR_PRODUCT_META, true)
                : get_post_meta($product_parent_id, self::ANAR_PRODUCT_META, true);

            if ($anar_meta) {
                $has_anar_product = true; // Found an Anar product
            } else {
                $has_standard_product = true; // Found a standard product
            }

            $order_can_ship_to_stock = $product_parent_id == 0
                ? get_post_meta($_product->get_id(), '_can_ship_to_stock', true)
                : get_post_meta($product_parent_id, '_can_ship_to_stock', true);
        }

        // Store the results in session
        WC()->session->set('has_standard_product', $has_standard_product);
        WC()->session->set('has_anar_product', $has_anar_product);
        WC()->session->set('anar_can_ship_stock', $order_can_ship_to_stock);
    }

    /**
     * Update order meta data during frontend checkout
     * 
     * Reads product type flags from WooCommerce session (set by check_for_cart_products_types)
     * and adds appropriate meta data to the order. This method is optimized for frontend
     * checkout as it uses pre-calculated session data instead of scanning order items.
     * 
     * Sets the following order meta:
     * - _is_anar_order: Marks order as containing Anar products
     * - anar_can_ship_stock: Marks order as eligible for ship-to-stock
     * 
     * Hooked to: woocommerce_checkout_create_order (priority 20)
     * 
     * @param \WC_Order $order Order object being created
     * @return void
     * @since 1.0.0
     * @see check_for_cart_products_types() For session data setup
     * @see handle_new_order() For admin/programmatic order handling
     */
    public function update_order_meta($order){
        $has_anar_product = WC()->session->get('has_anar_product', false);
        $order_can_ship_to_stock = WC()->session->get('anar_can_ship_stock', false);

        if($has_anar_product){
            update_post_meta($order->get_id(), self::ANAR_ORDER_META, 'anar');
            $order->update_meta_data(self::ANAR_ORDER_META, 'anar');
        }

        if ($order_can_ship_to_stock) {
            update_post_meta($order->get_id(), 'anar_can_ship_stock', 'anar');
            $order->update_meta_data('anar_can_ship_stock', 'anar');
        }

        $order->save();
    }

    /**
     * Check order items for Anar products and ship-to-stock capability
     * 
     * Scans all order items (including variations) to detect:
     * - Anar products (via _anar_products meta)
     * - Ship-to-stock capability (via _can_ship_to_stock meta)
     * 
     * This is a reusable method that doesn't modify the order itself.
     * 
     * @param \WC_Order $order Order object to check
     * @return array{has_anar_product: bool, can_ship_to_stock: bool} Detection results
     * @since 1.0.0
     * @access public
     */
    public function detect_anar_products_in_order($order) {
        $has_anar_product = false;
        $order_can_ship_to_stock = false;
        
        foreach ($order->get_items() as $item) {
            $product_id = $item->get_product_id();
            $variation_id = $item->get_variation_id();
            
            // For variations, check parent; for simple products, check the product itself
            if ($variation_id > 0) {
                // It's a variation - check both variation and parent
                $check_ids = [$variation_id, $product_id];
            } else {
                // It's a simple product
                $check_ids = [$product_id];
            }
            
            // Check each potential ID for Anar meta
            foreach ($check_ids as $check_id) {
                // Check for Anar product meta on the product
                $anar_meta = get_post_meta($check_id, self::ANAR_PRODUCT_META, true);
                
                if ($anar_meta) {
                    $has_anar_product = true;
                }
                
                // Check ship to stock capability
                $can_ship = get_post_meta($check_id, '_can_ship_to_stock', true);
                
                if ($can_ship) {
                    $order_can_ship_to_stock = true;
                }
                
                // Early exit if both found
                if ($has_anar_product && $order_can_ship_to_stock) {
                    break 2; // Break out of both loops
                }
            }
        }
        
        return [
            'has_anar_product' => $has_anar_product,
            'can_ship_to_stock' => $order_can_ship_to_stock
        ];
    }

    /**
     * Update order metadata with Anar product information
     * 
     * Sets the necessary meta fields for Anar orders:
     * - _is_anar_order: Marks order as containing Anar products
     * - anar_can_ship_stock: Marks order as eligible for ship-to-stock
     * 
     * Uses both HPOS and legacy post meta for compatibility.
     * 
     * @param int $order_id Order ID
     * @param bool $has_anar_product Whether order contains Anar products
     * @param bool $can_ship_to_stock Whether order can ship to stock
     * @return void
     * @since 1.0.0
     * @access public
     */
    public function update_anar_order_meta($order_id, $has_anar_product, $can_ship_to_stock) {
        $order = wc_get_order($order_id);
        
        if (!$order) {
            return;
        }
        
        // Update order meta if Anar products found
        if ($has_anar_product) {
            $order->update_meta_data(self::ANAR_ORDER_META, 'anar');
            update_post_meta($order_id, self::ANAR_ORDER_META, 'anar'); // Also update via post meta for compatibility
        }
        
        if ($can_ship_to_stock) {
            $order->update_meta_data('anar_can_ship_stock', 'anar');
            update_post_meta($order_id, 'anar_can_ship_stock', 'anar'); // Also update via post meta for compatibility
        }
        
        $order->save();
    }

    /**
     * Handle orders created via admin dashboard or programmatically
     * This method checks order items for Anar products and ship-to-stock capability
     * Skips if already processed by update_order_meta() during frontend checkout
     * 
     * @param int $order_id Order ID
     * @return void
     */
    public function handle_new_order($order_id) {
        // Get the order object
        $order = wc_get_order($order_id);
        
        if (!$order) {
            return; // Invalid order
        }
        
        // GUARD: Skip if already processed during frontend checkout
        // update_order_meta() sets _is_anar_order, so if it exists, we're done
        if ($order->get_meta(self::ANAR_ORDER_META)) {
            return; // Already processed during checkout
        }
        
        // Detect Anar products in the order
        $detection = $this->detect_anar_products_in_order($order);
        
        // Update order meta based on detection results
        $this->update_anar_order_meta(
            $order_id,
            $detection['has_anar_product'],
            $detection['can_ship_to_stock']
        );
    }

    /**
     * AJAX handler: Get available shipping options for admin order modal
     * 
     * Calculates available Anar shipping options for an existing order when
     * admin opens the shipping modal. Used in admin order management interface.
     * 
     * Required POST parameters:
     * - order_id: The WooCommerce order ID
     * 
     * Response format:
     * - success: true/false
     * - data.html: HTML markup for shipping options
     * 
     * Hooked to: wp_ajax_awca_get_shipping_options_ajax
     * 
     * @return void Sends JSON response via wp_send_json_*
     * @since 1.0.0
     * @access public
     */
    public function get_shipping_options_ajax() {
        // Validate order_id
        if (!isset($_POST['order_id']) || !$_POST['order_id']) {
            wp_send_json_error(array('message' => 'order_id required'));
        }

        $order_id = $_POST['order_id'];
        $order = wc_get_order($order_id);

        if (!$order) {
            wp_send_json_error(array('message' => 'Order not found'));
        }

        // Get customer address
        $billing_city = $order->get_billing_city();
        $billing_state = $order->get_billing_state();
        $billing_country = $order->get_billing_country();

        // Get state name
        $countries = new \WC_Countries();
        $states = $countries->get_states($billing_country);
        $billing_state_name = $states[$billing_state] ?? $billing_state;

        // Get city name from PWS plugin if available
        $billing_city_name = $billing_city;
        if (function_exists('PWS') && method_exists(PWS(), 'get_city')) {
            $billing_city_name = PWS()->get_city($billing_city);
        }

        // Process order items and get shipping options
        $shipping_data = $this->get_available_shipping_options_for_order($order, $billing_city_name, $billing_state_name);

        // Generate HTML markup for shipping options
        $shipping_html = $this->generate_shipping_options_html($shipping_data['packages']);

        // Generate dynamic heading with state and city
        $shipping_heading = $this->generate_shipping_heading($billing_state_name, $billing_city_name);

        wp_send_json_success(array(
            'shipping_html' => $shipping_html,
            'shipping_heading' => $shipping_heading,
            'customer_address' => $billing_city_name . '، ' . $billing_state_name
        ));
    }

    /**
     * Get available shipping options for order items (Admin use)
     * 
     * Helper method that extracts products from an existing WooCommerce order
     * and fetches available shipping options for them. Used by admin AJAX handler.
     * 
     * Handles product variations properly by getting parent product ID.
     *
     * @param \WC_Order $order The WooCommerce order
     * @param string $customer_city Customer city name
     * @param string $customer_state Customer state name
     * @return array Available shipping options data structure
     * @since 1.0.0
     * @access private
     */
    private function get_available_shipping_options_for_order($order, $customer_city, $customer_state) {
        $products = [];
        foreach ($order->get_items() as $item) {
            $product = $item->get_product();
            if (!$product) continue;
            
            // Get product ID (handle variations)
            $product_id = $product->is_type('variation') ? $product->get_parent_id() : $product->get_id();
            $products[] = ['product_id' => $product_id, 'quantity' => $item->get_quantity()];
        }
        
        return $this->get_available_shipping_options($products, $customer_city, $customer_state);
    }

    /**
     * Get available shipping options for products (Core method)
     * 
     * Universal method that fetches and processes Anar shipping options for a list of products.
     * Works with both cart items and order items. Groups products by their shipmentsReferenceId
     * (warehouse/origin) and returns available delivery methods for each group.
     * 
     * Algorithm:
     * 1. Loop through products and get their Anar shipment data
     * 2. Determine if shipping is intra-province or inter-province
     * 3. Filter active shipments and delivery methods
     * 4. Group products by same warehouse (shipmentsReferenceId)
     * 5. Return grouped data with delivery options
     * 
     * Return structure:
     * - packages: Array of shipment groups, each containing:
     *   - names: Product IDs in this group
     *   - delivery: Available delivery methods with prices and estimated times
     * - shipment_data: Raw shipment data for order creation API
     *
     * @param array $products Array of products with ['product_id' => id, 'quantity' => qty]
     * @param string $customer_city Customer city name
     * @param string $customer_state Customer state name
     * @param bool $use_verbose_names Whether to use human-readable delivery type names (admin) or API codes (frontend)
     * @return array ['packages' => array, 'shipment_data' => array]
     * @since 1.0.0
     * @access protected
     */
    protected function get_available_shipping_options($products, $customer_city, $customer_state, $use_verbose_names = true) {
        $ship = [];
        $shipment_data = [];

        foreach ($products as $product_data) {
            $product_id = $product_data['product_id'];

            // Get Anar shipment data
            $anar_shipment_data = ProductData::get_anar_product_shipments($product_id);
            if (!$anar_shipment_data) continue;

            // Determine region
            $shipment_types_to_display = $this->determine_shipment_region(
                $customer_city,
                $customer_state,
                $anar_shipment_data['shipmentsReferenceCity'] ?? '',
                $anar_shipment_data['shipmentsReferenceState'] ?? ''
            );

            // Process shipments
            $shipmentsReferenceId = $anar_shipment_data['shipmentsReferenceId'] ?? '';

            foreach ($anar_shipment_data['shipments'] as $shipment) {
                if ($shipment->type === 'allCities' || in_array($shipment->type, $shipment_types_to_display)) {
                    if ($shipment->active) {
                        foreach ($shipment->delivery as $delivery) {
                            if ($delivery->active) {
                                // Store products with same shipmentsReferenceId together on $ship
                                if (isset($ship[$shipmentsReferenceId])) {
                                    $ship[$shipmentsReferenceId]['names'][] = $product_id;
                                } else {
                                    $ship[$shipmentsReferenceId] = [
                                        'delivery' => [],
                                        'names' => [],
                                    ];
                                    $ship[$shipmentsReferenceId]['names'][] = $product_id;
                                }

                                // Add unique delivery to the list
                                $delivery_key = $delivery->_id;
                                if (!isset($ship[$shipmentsReferenceId]['delivery'][$delivery_key])) {
                                    $delivery_name = $use_verbose_names 
                                        ? ProductData::verbose_shipment_name($delivery->deliveryType)
                                        : $delivery->deliveryType;
                                        
                                    $ship[$shipmentsReferenceId]['delivery'][$delivery_key] = [
                                        'name' => $delivery_name,
                                        'estimatedTime' => $delivery->estimatedTime,
                                        'price' => $delivery->price,
                                        'freeConditions' => $delivery->freeConditions ?? [],
                                    ];

                                    // Save relevant shipment data
                                    $item_shipment_data = [
                                        'shipmentId' => $shipment->_id,
                                        'deliveryId' => $delivery->_id,
                                        'shipmentsReferenceId' => $shipmentsReferenceId,
                                    ];
                                    $shipment_data[] = $item_shipment_data;
                                }
                            }
                        }
                    }
                }
            }
        }

        return [
            'packages' => $ship,
            'shipment_data' => $shipment_data
        ];
    }

    /**
     * Determine shipment region based on customer and shop locations
     * 
     * Compares customer address with product warehouse/shop location
     * to determine applicable shipping types (intra-city, intra-province, or inter-province).
     * 
     * Logic:
     * - Same city & state = 'insideShopCity' (intra-city)
     * - Same state only = 'insideShopState' (intra-province)  
     * - Different state = 'otherStates' (inter-province)
     *
     * @param string $customer_city Customer city name
     * @param string $customer_state Customer state/province name
     * @param string $shop_city Warehouse/shop city name from product data
     * @param string $shop_state Warehouse/shop state name from product data
     * @return array Array of shipment type codes to filter against
     * @since 1.0.0
     * @access protected
     */
    protected function determine_shipment_region($customer_city, $customer_state, $shop_city, $shop_state) {
        if ($customer_state === $shop_state && $customer_city === $shop_city) {
            return ['insideShopCity'];
        } elseif ($customer_state === $shop_state) {
            return ['insideShopState'];
        } else {
            return ['otherStates'];
        }
    }

    /**
     * Generate HTML markup for shipping options in admin modal
     * 
     * Creates styled HTML interface for admin to select shipping methods for each package.
     * Displays packages with product thumbnails, delivery options as radio buttons,
     * and calculates free shipping conditions.
     * 
     * Features:
     * - Multi-package alert if more than one shipment
     * - Product thumbnails and names per package
     * - Radio buttons for delivery method selection
     * - Free shipping badge when conditions met
     * - Total shipping fee calculator
     *
     * @param array $packages Array of packages grouped by shipmentsReferenceId
     * @return string HTML markup for admin shipping options interface
     * @since 1.0.0
     * @access private
     */
    private function generate_shipping_options_html($packages) {
        if (empty($packages)) {
            return '<div class="no-options">هیچ روش ارسالی برای این سفارش موجود نیست</div>';
        }

        $html = '';
        $pack_index = 0;

        // Show notice if multiple packages
        if (count($packages) > 1) {
            $html .= '<div class="anar-shipments-user-notice-row" style="margin-bottom: 15px; padding: 10px; background: #f0f8ff; border-radius: 4px; border-left: 4px solid #0073aa;"><p style="margin: 0; color: #0073aa;">کالاهای انتخابی شما از چند انبار مختلف برای خریدار ارسال می شوند.</p></div>';
        }

        foreach ($packages as $key => $package) {
            $pack_index++;
            $product_uniques = array_unique($package['names']);

            // Package header with icon, product count, and product images
            $product_list_markup = sprintf('<div class="package-title" style="display: flex; align-items: center; margin-bottom: 10px; padding: 4px; background: #f9f9f9; border-radius: 4px;">
                    <div class="icon" style="margin-left: 10px;">
                        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon" style="color: #666;"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><path d="M7 17m-2 0a2 2 0 1 0 4 0a2 2 0 1 0 -4 0" /><path d="M17 17m-2 0a2 2 0 1 0 4 0a2 2 0 1 0 -4 0" /><path d="M5 17h-2v-4m-1 -8h11v12m-4 0h6m4 0h2v-6h-8m0 -5h5l3 5" /><path d="M3 9l4 0" /></svg>
                    </div>
                    <div class="text" style="flex: 1;display: flex;justify-content: space-between;align-items: center;">
                        <div style="font-weight: bold; color: #333;">مرسوله %d <span class="chip" style="background: #e5e5e5;color: #717171;padding: 2px 8px;border-radius: 12px;font-size: 12px;margin-right: 5px;font-weight: normal;">%s کالا</span></div>
                        <ul class="package-items" style="display: flex; flex-wrap: wrap; gap: 5px; margin: 0; padding: 0; list-style: none;">',
                $pack_index,
                count($product_uniques)
            );

            // Collect package images
            foreach ($product_uniques as $item) {
                $product_title = get_the_title($item);

                if ($item) {
                    $product = wc_get_product($item);

                    // Check if the product is a variation
                    if ($product && $product->is_type('variation')) {
                        $parent_id = $product->get_parent_id();
                        $thumbnail_url = get_the_post_thumbnail_url($parent_id);
                    } else {
                        $thumbnail_url = get_the_post_thumbnail_url($item);
                    }

                    $product_list_markup .= sprintf('<li style="margin: 0;"><a class="awca-tooltip-on" href="%s" title="%s" style="display: block;"><img src="%s" alt="%s" style="width: 32px; height: 32px; object-fit: cover; border-radius: 4px; border: 1px solid #ddd;"></a></li>',
                        get_permalink($item),
                        $product_title,
                        $thumbnail_url,
                        $product_title,
                    );
                } else {
                    $product_list_markup .= '<li style="margin: 0; padding: 4px 8px; background: #f5f5f5; border-radius: 4px; font-size: 11px;">' . esc_html(get_the_title($item)) . '</li>';
                }
            }
            $product_list_markup .= '</ul></div></div>';

            $html .= '<div class="anar-shipments-package-row" style="margin-bottom: 8px;border: 1px dashed #ddd;border-radius: 8px;overflow: hidden;">';
            $html .= '<div class="anar-shipments-package-content" style="padding: 8px;">';
            $html .= '<div class="anar-package-items-list">';
            $html .= $product_list_markup;
            $html .= '</div>';
            $html .= '<div class="anar-delivery-options-area" style="margin-top: 15px;">';

            // Generate delivery options
            $names = [];
            $radio_data = [];
            foreach ($package['delivery'] as $delivery_key => $delivery) {
                $package_total = $this->calculate_package_total($product_uniques);
                $is_free_shipping = false;
                $original_price = $delivery['price'];
                $original_estimate_time = $delivery['estimatedTime'];

                // Check free condition
                if (isset($delivery['freeConditions']) && isset($delivery['freeConditions']['purchasesPrice'])) {
                    if ($package_total >= $delivery['freeConditions']['purchasesPrice']) {
                        $is_free_shipping = true;
                        $delivery['price'] = 0;
                        $delivery['estimatedTime'] = 'ارسال رایگان';
                    }
                }

                $estimate_time_str = $delivery['estimatedTime'] ? ' (' . $delivery['estimatedTime'] . ')' : '';
                $names[$delivery_key] = $delivery['name'] . $estimate_time_str . ' : ' . $delivery['price'] . ' ' . get_woocommerce_currency_symbol();
                $radio_data[$delivery_key] = [
                    'label' => $delivery['name'],
                    'estimated_time' => $delivery['estimatedTime'] ?? '',
                    'price' => $delivery['price'],
                ];
            }

            // Generate radio buttons
            $html .= $this->generate_delivery_option_field($key, $radio_data, '');

            $html .= '</div>'; // Close anar-delivery-options-area
            $html .= '</div>'; // Close anar-shipments-package-content
            $html .= '</div>'; // Close anar-shipments-package-row
        }

        // Add total fee display
        $html .= '<div class="anar-shipping-total" style="margin-top: 8px; padding: 8px; background: #f8f9fa; border-radius: 8px; border: 1px solid #e9ecef;">';
        $html .= '<div style="display: flex; justify-content: space-between; align-items: center;">';
        $html .= '<span style="color: #333; font-size: 12px; font-weight: 600">مجموع هزینه ارسال:</span>';
        $html .= '<span id="anar-total-shipping-fee" style="color: #0073aa; font-size: 12px;font-weight: 600">0 ' . get_woocommerce_currency_symbol() . '</span>';
        $html .= '</div>';
        $html .= '</div>';

        return $html;
    }

    /**
     * Calculate package total for free shipping conditions
     * 
     * Sums up product prices for a package to check against free shipping thresholds.
     * Base implementation uses product base price. Child classes (CheckoutDropShipping)
     * can override to include cart quantities and actual cart item prices.
     * 
     * @param array $product_ids Array of product IDs in the package
     * @return float Package total amount
     * @since 1.0.0
     * @access protected
     */
    protected function calculate_package_total($product_ids) {
        $total = 0;
        foreach ($product_ids as $product_id) {
            $product = wc_get_product($product_id);
            if ($product) {
                $total += $product->get_price();
            }
        }
        return $total;
    }

    /**
     * Generate delivery option radio buttons for a package
     * 
     * Creates HTML markup for delivery method selection (radio buttons) for a shipment package.
     * Each option shows method name, price, and estimated delivery time.
     * Can be overridden in child classes for different styles (admin vs frontend).
     * 
     * Features:
     * - Auto-selects first option if chosen doesn't exist
     * - Displays translated delivery method names
     * - Shows price in WooCommerce currency
     * - Includes estimated delivery time
     * - JS-ready with data attributes for dynamic updates
     *
     * @param string $input_key Unique identifier for this package's input group
     * @param array $radio_data Delivery options array with 'label', 'price', 'estimated_time'
     * @param string $chosen Pre-selected delivery option key
     * @return string HTML markup for radio button group
     * @since 1.0.0
     * @access protected
     */
    protected function generate_delivery_option_field($input_key, $radio_data, $chosen) {
        $output = '<div class="form-row form-row-wide update_totals_on_change">';

        // Check if the chosen value exists in the radio data
        if (!array_key_exists($chosen, $radio_data)) {
            reset($radio_data);
            $chosen = key($radio_data);
        }

        foreach ($radio_data as $key => $data) {
            // Generate a unique ID for the radio button
            $id = 'anar_delivery_option_' . $input_key . '_' . sanitize_title($key);

            // Determine if this option is chosen
            $checked = ($chosen === $key) ? 'checked' : '';
            $div_selected = ($chosen === $key) ? ' selected' : '';

            // Create the radio button and label
            $output .= '<div class="anar-delivery-option' . $div_selected . '" style="display: flex; align-items:center;justify-content: start; gap:4px; cursor: pointer; margin-bottom:8px">';
            $output .= sprintf(
                '<input type="radio" class="input-radio" data-input-group="%s" value="%s" name="shipping_option_%s" id="%s" %s style="margin-left: 8px;">',
                esc_attr($input_key),
                esc_attr($key),
                esc_attr($input_key),
                esc_attr($id),
                esc_attr($checked)
            );

            // Customize the label with additional spans
            $output .= sprintf(
                '<label for="%s" class="radio" style="cursor: pointer;display: flex;justify-content: start;align-items: center;gap: 8px;">
                        <span class="label" style="font-weight: bold;">%s</span>
                        <span class="price" style="color: #0073aa;background: #0073aa24;padding: 2px 6px;border-radius: 5px;" data-raw-price="%s">%s</span>
                        <span class="estimated-time" style="color: #666; font-size: 12px;">%s</span>
                        </label>',
                esc_attr($id),
                esc_html(anar_translator($data['label'])),
                esc_attr(awca_convert_price_to_woocommerce_currency($data['price'])),
                esc_html(anar_get_formatted_price($data['price'])),
                esc_html($data['estimated_time'])
            );

            $output .= '</div>'; // Close anar-delivery-option div
        }

        $output .= '</div>'; // Close form-row div
        return $output;
    }

    /**
     * Generate dynamic shipping heading with customer location
     * 
     * Creates a formatted heading displaying customer's state and city
     * for the shipping options section in admin modal.
     *
     * @param string $state_name Customer's state/province name
     * @param string $city_name Customer's city name
     * @return string HTML markup with styled location information
     * @since 1.0.0
     * @access private
     */
    private function generate_shipping_heading($state_name, $city_name) {
        $heading = sprintf(
            'روش های ارسال تامین کننده برای استان <span style="color: #0073aa; font-weight: bold;">%s</span> شهرستان <span style="color: #0073aa; font-weight: bold;">%s</span>',
            esc_html($state_name),
            esc_html($city_name)
        );

        return $heading;
    }

    /**
     * Analyze cart and group Anar products by warehouse/origin
     * 
     * Scans current cart to determine how many separate shipment packages are needed.
     * Products with the same shipmentsReferenceId are grouped together as they
     * ship from the same warehouse.
     * 
     * Used by:
     * - Multi-package alert display
     * - Multi-package fee calculation
     * - Shipping rate multiplier logic
     * - Frontend checkout shipping display
     * 
     * @return array {
     *     @type int $package_count Number of separate packages/warehouses
     *     @type array $packages Grouped packages data by shipmentsReferenceId
     *     @type bool $has_anar_products Whether cart contains any Anar products
     * }
     * @since 1.0.0
     * @access public
     */
    public function get_cart_packages_info() {
        $result = [
            'package_count' => 0,
            'packages' => [],
            'has_anar_products' => false,
        ];

        // Early return if no cart
        if (!WC()->cart || empty(WC()->cart->get_cart())) {
            return $result;
        }

        $cart_items = WC()->cart->get_cart();
        $packages_by_reference = [];

        foreach ($cart_items as $cart_item_key => $cart_item) {
            $product = wc_get_product($cart_item['data']->get_id());
            if (!$product) continue;

            $product_parent_id = $product->get_parent_id();
            
            // Check if it's an Anar product
            $anar_meta = $product_parent_id == 0
                ? get_post_meta($product->get_id(), self::ANAR_PRODUCT_META, true)
                : get_post_meta($product_parent_id, self::ANAR_PRODUCT_META, true);

            if (!$anar_meta) continue;

            $result['has_anar_products'] = true;

            // Get product ID (handle variations)
            $product_id = $product->is_type('simple') 
                ? $product->get_id() 
                : $product->get_parent_id();

            // Get shipment data
            $shipment_data = ProductData::get_anar_product_shipments($product_id);
            $reference_id = $shipment_data['shipmentsReferenceId'];
            if (!$shipment_data || empty($reference_id) || !is_scalar($reference_id)) {
                continue;
            }

            
            // Group products by shipmentsReferenceId
            if (!isset($packages_by_reference[$reference_id])) {
                $packages_by_reference[$reference_id] = [
                    'reference_id' => $reference_id,
                    'products' => [],
                    'warehouse_city' => $shipment_data['shipmentsReferenceCity'] ?? '',
                    'warehouse_state' => $shipment_data['shipmentsReferenceState'] ?? '',
                ];
            }

            $packages_by_reference[$reference_id]['products'][] = [
                'product_id' => $product_id,
                'product_name' => $product->get_name(),
                'quantity' => $cart_item['quantity'],
            ];
        }

        $result['packages'] = array_values($packages_by_reference);
        $result['package_count'] = count($packages_by_reference);

        return $result;
    }

    /**
     * Generate product list markup for package display
     * 
     * Creates HTML markup showing package header with icon, product count,
     * and thumbnails of products in the package. Used to visually group
     * products that ship from the same warehouse.
     * 
     * Features:
     * - Package number badge
     * - Product count chip
     * - Product thumbnails with tooltips
     * - Handles product variations properly
     * 
     * @param array $product_ids Array of product IDs in this package
     * @param int $pack_index Package number for display (1-based)
     * @return string HTML markup for product list section
     * @since 1.0.0
     * @access protected
     */
    protected function generate_product_list_markup($product_ids, $pack_index) {
        $product_uniques = array_unique($product_ids);
        $product_count = count($product_uniques);
        
        $markup = sprintf('<div class="package-title">
                <div class="icon">
                    <svg  xmlns="http://www.w3.org/2000/svg"  width="24"  height="24"  viewBox="0 0 24 24"  fill="none"  stroke="currentColor"  stroke-width="2"  stroke-linecap="round"  stroke-linejoin="round"  class="icon"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><path d="M7 17m-2 0a2 2 0 1 0 4 0a2 2 0 1 0 -4 0" /><path d="M17 17m-2 0a2 2 0 1 0 4 0a2 2 0 1 0 -4 0" /><path d="M5 17h-2v-4m-1 -8h11v12m-4 0h6m4 0h2v-6h-8m0 -5h5l3 5" /><path d="M3 9l4 0" /></svg>
                </div>
                <div class="text">
                    <div>مرسوله %d <span class="chip">%s کالا</span></div>
                </div>
            </div>',
            $pack_index,
            $product_count
        );

        // collect package images
        $markup .= '<ul class="package-items">';
        foreach ($product_uniques as $item) {
            $product_title = get_the_title($item);

            if ($item) {
                $product = wc_get_product($item);

                // Check if the product is a variation
                if ($product && $product->is_type('variation')) {
                    $parent_id = $product->get_parent_id();
                    $thumbnail_url = get_the_post_thumbnail_url($parent_id);
                } else {
                    $thumbnail_url = get_the_post_thumbnail_url($item);
                }

                $markup .= sprintf('<li><a class="awca-tooltip-on" href="%s" title="%s"><img src="%s" alt="%s"></a></li>',
                    get_permalink($item),
                    $product_title,
                    $thumbnail_url,
                    $product_title,
                );
            }
        }
        $markup .= '</ul>';
        
        return $markup;
    }

    /**
     * Display alert for multiple packages on cart and checkout pages
     * 
     * Shows an informative message when cart contains products from multiple warehouses.
     * Alert appears before shipping rates to inform customers about multiple shipments.
     * Only active when Anar shipping is disabled and alert option is enabled.
     * 
     * Alert text supports sprintf formatting with %d for package count.
     * 
     * Display locations:
     * - Checkout page (before shipping rates in order review)
     * - Cart page (before shipping calculator in totals)
     * 
     * Hooked to:
     * - woocommerce_review_order_before_shipping (checkout)
     * - woocommerce_cart_totals_before_shipping (cart)
     * 
     * Controlled by options:
     * - anar_show_multi_package_alert: Enable/disable alert
     * - anar_multi_package_alert_text: Customizable alert message
     * 
     * @return void Outputs HTML directly
     * @since 1.0.0
     * @access public
     */
    public function display_multiple_packages_alert() {
        $packages_info = $this->get_cart_packages_info();

        // Show alert if more than one package
        if ($packages_info['package_count'] > 1) {
            // Get custom alert text from options
            $alert_text = get_option(
                'anar_multi_package_alert_text', 
                'کالاهای انتخابی شما از چند انبار مختلف ارسال می شوند.'
            );
            
            // Replace %d with actual package count if present
            $alert_text = sprintf($alert_text, $packages_info['package_count']);
            
            echo '<tr class="anar-shipments-user-notice-row"><td colspan="2"><p>' . esc_html($alert_text) . '</p></td></tr>';
        }
    }

    /**
     * Add extra fee for multiple packages
     * Charges extra for each additional warehouse/origin beyond the first
     * Supports free condition based on cart total
     * 
     * Controlled by options:
     * - anar_enable_multi_package_fee: Enable/disable feature
     * - anar_multi_package_fee: Fee amount per extra package
     * - anar_multi_package_fee_free_condition: Minimum cart total for free shipping
     */
    public function add_multiple_package_fee() {
        if (!WC()->cart) return;

        $packages_info = $this->get_cart_packages_info();
        
        // Only add fee if more than 1 package
        if ($packages_info['package_count'] <= 1) {
            return;
        }

        // Check free condition: if cart total is above threshold, skip fee
        $free_condition = get_option('anar_multi_package_fee_free_condition', 0);
        if ($free_condition > 0) {
            $cart_total = WC()->cart->get_subtotal();
            
            if ($cart_total >= $free_condition) {
                // Cart total meets free condition, don't add fee
                return;
            }
        }

        // Calculate extra packages (first package is "free", additional ones cost extra)
        $extra_packages = $packages_info['package_count'] - 1;
        
        // Get fee amount from options or use default
        $fee_per_extra_package = get_option('anar_multi_package_fee', 50000);
        
        // Calculate total extra fee
        $total_extra_fee = $extra_packages * $fee_per_extra_package;
        
        // Add the fee to cart
        WC()->cart->add_fee(
            sprintf('هزینه ارسال از %d انبار', $extra_packages),
            $total_extra_fee
        );
    }

    /**
     * Multiply shipping rate for multiple packages
     * This applies a multiplier per extra package to the selected shipping method cost
     * Formula: final_rate = rate × (1 + (extra_packages × multiplier))
     * 
     * Example: 3 packages with multiplier 1.5
     * - Extra packages = 2
     * - If base rate is 30,000: 30,000 × (1 + 2 × 1.5) = 30,000 × 4 = 120,000
     * 
     * Controlled by options:
     * - anar_enable_shipping_multiplier: Enable/disable feature
     * - anar_shipping_multiplier: Multiplier per extra package (default: 1)
     * - anar_multi_package_fee_free_condition: Minimum cart total for free
     * 
     * @param array $rates Available shipping rates
     * @param array $package Shipping package
     * @return array Modified rates
     */
    public function multiply_shipping_for_multiple_packages($rates, $package) {
        $packages_info = $this->get_cart_packages_info();
        
        // Only modify if more than 1 package
        if ($packages_info['package_count'] <= 1) {
            return $rates;
        }

        // Check free condition: if cart total is above threshold, skip multiplier
        $free_condition = get_option('anar_multi_package_fee_free_condition', 0);
        if ($free_condition > 0) {
            $cart_total = WC()->cart->get_subtotal();
            
            if ($cart_total >= $free_condition) {
                // Cart total meets free condition, don't multiply
                return $rates;
            }
        }

        // Calculate extra packages (first package is included in base rate)
        $extra_packages = $packages_info['package_count'] - 1;
        
        // Get multiplier per extra package from options
        $multiplier_per_package = floatval(get_option('anar_shipping_multiplier', 1));
        
        // Calculate the total multiplier: 1 + (extra_packages × multiplier_per_package)
        // This means the base rate is always charged, plus additional cost per extra package
        $total_multiplier = 1 + ($extra_packages * $multiplier_per_package);
        
        // Apply the multiplier to each shipping rate
        foreach ($rates as $rate_key => $rate) {
            $rates[$rate_key]->cost = $rate->cost * $total_multiplier;
            
            // Also update taxes if they exist
            if (isset($rate->taxes) && is_array($rate->taxes)) {
                foreach ($rate->taxes as $tax_key => $tax) {
                    $rates[$rate_key]->taxes[$tax_key] = $tax * $total_multiplier;
                }
            }
        }

        return $rates;
    }

}
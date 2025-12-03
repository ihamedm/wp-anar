<?php
namespace Anar;

use mysql_xdevapi\Exception;

/**
 * CheckoutDropShipping Class
 * 
 * Handles Anar dropshipping checkout functionality for WooCommerce.
 * Extends base Checkout class to add Anar-specific shipping method display,
 * delivery option selection, validation, and order creation integration.
 * 
 * Key Responsibilities:
 * - Display Anar shipping methods on frontend checkout
 * - Handle customer delivery method selection per package
 * - Validate delivery selections before order placement
 * - Calculate and display total shipping fees
 * - Save selected shipping data to order meta for API
 * - Manage address change detection and selection clearing
 * - Handle free shipping for non-standard products
 * - Integrate with Anar order creation API
 * 
 * This class is the main interface between WooCommerce checkout and Anar
 * dropshipping platform, managing the complete shipping selection flow.
 * 
 * @package Anar
 * @since 1.0.0
 * @extends Checkout
 */
class CheckoutDropShipping extends Checkout {

    /**
     * Meta key for storing Anar shipping data on orders
     * @var string
     */
    private const ANAR_SHIPPING_META = '_anar_shipping_data';
    
    /**
     * Meta key to identify Anar products
     * @var string
     */
    private const ANAR_PRODUCT_META = '_anar_products';

    /**
     * Singleton instance
     * @var CheckoutDropShipping
     */
    protected static $instance;

    /**
     * Flag to track if Anar shipping has been displayed (prevents duplicates)
     * @var bool
     */
    private static $shipping_displayed = false;

    /**
     * Get singleton instance
     * 
     * @return CheckoutDropShipping
     */
    public static function get_instance() {
        if ( ! isset( self::$instance ) ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Constructor - Register hooks and filters for dropshipping checkout
     * 
     * Registers WordPress/WooCommerce hooks for:
     * - Frontend shipping method display
     * - Delivery selection processing
     * - Checkout validation
     * - Order meta management
     * - Free shipping for non-standard products
     * - Address change detection
     * 
     * @since 1.0.0
     */
    public function __construct() {
        parent::__construct();

        // Checkout Customization
        // Primary hook: fires when shipping methods exist
        add_action( 'woocommerce_review_order_before_shipping', [$this, 'display_anar_products_shipping'] , 99);
        // Fallback hook: always fires even when no shipping methods are configured
        add_action( 'woocommerce_review_order_before_order_total', [$this, 'display_anar_products_shipping_fallback'] , 5);
        add_filter( 'woocommerce_shipping_package_name', [$this, 'prefix_shipping_package_name_other_products'] );
        add_action( 'woocommerce_cart_calculate_fees', [$this, 'calculate_total_shipping_fee'] );

        // Remove Anar products without shipping data before checkout
        add_action('woocommerce_check_cart_items', [ $this, 'remove_anar_products_without_shipping' ], 5);

        add_filter( 'woocommerce_package_rates', [$this, 'enable_free_shiping_when_no_standard_products'], 100, 2 );
        add_action( 'wp_head', [$this, 'visually_hide_shipment_rates_no_standard_products']);
        add_filter( 'woocommerce_no_shipping_available_html', function($html){
            return '';
        } ) ;

        // process and Create order
        add_action( 'woocommerce_checkout_update_order_review', [$this, 'save_checkout_delivery_choice_on_session_better'] );
        add_action( 'woocommerce_checkout_update_order_review', [$this, 'chosen_shipping_methods_when_no_standard_products'] );
        add_action( 'woocommerce_checkout_process', [$this, 'checkout_validations']);
        add_action( 'woocommerce_checkout_create_order', [$this, 'save_anar_data_on_order'], 20, 1 );

        // Add action to handle autofill events
        add_action('woocommerce_checkout_update_order_review', [$this, 'handle_autofill_update'], 5);
    }

    /**
     * Display Anar shipping methods on frontend checkout
     * 
     * Main method that renders Anar dropshipping delivery options on checkout page.
     * Shows grouped packages with their available delivery methods, validates
     * customer address, handles free shipping conditions, and manages delivery selections.
     * 
     * Process flow:
     * 1. Get customer billing address (city, state)
     * 2. Check and clear selections if address changed
     * 3. Collect Anar products from cart
     * 4. Remove products without shipping data
     * 5. Fetch available shipping options from API
     * 6. Group products by warehouse (shipmentsReferenceId)
     * 7. Calculate free shipping conditions per package
     * 8. Display packages with delivery option radio buttons
     * 9. Show multi-package alert if needed
     * 
     * Features:
     * - PWS plugin integration for Persian city names
     * - Address change detection
     * - Free shipping calculation per package
     * - Multi-package notifications
     * - Product variation handling
     * - Session-based delivery selection persistence
     * 
     * Hooked to: woocommerce_review_order_before_shipping (priority 99)
     * 
     * @return void Outputs HTML directly
     * @throws Exception If address data is invalid or API fails
     * @since 1.0.0
     * @access public
     */
    public function display_anar_products_shipping() {
        // Reset flag at start of each display attempt (will be set if display happens)
        // This ensures fallback can display if primary hook doesn't fire
        $was_already_displayed = self::$shipping_displayed;
        
        try {
            // Check if the billing city and state are filled
            $billing_country = WC()->customer->get_billing_country();
            $billing_state = WC()->customer->get_billing_state();
            $billing_state_name = WC()->countries->states[$billing_country][$billing_state] ?? '';
            $billing_city = WC()->customer->get_billing_city();

            // Try to get city name from PWS plugin
            $billing_city_name = $billing_city; // Default to original value
            if ( function_exists( 'PWS' ) && method_exists( PWS(), 'get_city' ) ) {
                $billing_city_name = PWS()->get_city( $billing_city );
            }
            
            // Check if address has changed and validate delivery selections
            $this->check_and_clear_delivery_selections_on_address_change($billing_city, $billing_state);

            // Check if city or state is empty
            if (empty($billing_city) || empty($billing_state_name)) {
                $billing_state_name = 'نامعلوم';
                $billing_city = 'نامعلوم';
            }

            // Retrieve the has_standard_product and has_anar_product values from the session
            $has_standard_product = WC()->session->get('has_standard_product');
            $has_anar_product = WC()->session->get('has_anar_product');

            // Prepare cart products for processing
            global $woocommerce;
            $cart_items = $woocommerce->cart->get_cart();
            $products = [];

            foreach ($cart_items as $cart_item_key => $values) {
                $_product = wc_get_product($values['data']->get_id());
                $product_parent_id = $_product->get_parent_id();

                // Retrieve the meta values for both the product and its parent
                $anar_meta = $product_parent_id == 0
                    ? get_post_meta($_product->get_id(), self::ANAR_PRODUCT_META, true)
                    : get_post_meta($product_parent_id, self::ANAR_PRODUCT_META, true);

                // If it's an Anar product, add to products array
                if ($anar_meta) {
                    if($_product->is_type('simple')){
                        $product_id = $_product->get_id();
                    }else{
                        $product_id = $_product->get_parent_id();
                    }
                    
                    // Check if product has shipping data before adding
                    $anar_shipment_data = ProductData::get_anar_product_shipments($product_id);
                    if ($anar_shipment_data && count($anar_shipment_data) > 0) {
                        $products[] = [
                            'product_id' => $product_id,
                            'quantity' => $values['quantity']
                        ];
                    } else {
                        // Remove products without shipping data
                        WC()->cart->remove_cart_item($cart_item_key);
                        $product_title = $_product->get_name();
                        wc_add_notice(sprintf(__('محصول "%s" به دلیل عدم وجود روش ارسال از سبد خرید شما حذف شد.', 'wp-anar'), $product_title), 'error');
                    }
                }
            }

            // Use parent method to get available shipping options
            $shipping_result = $this->get_available_shipping_options($products, $billing_city_name, $billing_state_name, false);
            $ship = $shipping_result['packages'];
            $shipment_data = $shipping_result['shipment_data'];

            // Store shipment data in session for later use [save on order meta, need for call anar order create API]
            if(!empty($shipment_data)){
                WC()->session->set('anar_shipment_data', $shipment_data);
            }

            // Display Anar shipping methods if Anar products are present
            if ($has_anar_product && !empty($ship)) {
                $this->render_anar_shipping_display($ship);
                // Mark as displayed to prevent duplicate display
                self::$shipping_displayed = true;
            } elseif ($has_anar_product && empty($ship)) {
                // If we have Anar products but no shipping data, mark as attempted
                // This prevents fallback from trying again unnecessarily
                if (!$was_already_displayed) {
                    // Only log if this is the first attempt (not fallback)
                    $this->log('Anar products found but no shipping data available', 'warning');
                }
            }
        } catch (Exception $e) {
            awca_log($e->getMessage());
        }

    }

    /**
     * Fallback method to display Anar shipping when primary hook doesn't fire
     * 
     * This method ensures Anar shipping methods are displayed even when no
     * WooCommerce shipping methods are configured. It checks if shipping was
     * already displayed via the primary hook and only displays if not shown yet.
     * 
     * Hooked to: woocommerce_review_order_before_order_total (priority 5)
     * 
     * @return void Outputs HTML directly if not already displayed
     * @since 1.0.0
     * @access public
     */
    public function display_anar_products_shipping_fallback() {
        // If already displayed via primary hook, skip
        if (self::$shipping_displayed) {
            return;
        }

        // Check if we have Anar products in cart
        $has_anar_product = WC()->session->get('has_anar_product');
        if (!$has_anar_product) {
            return;
        }

        // Call the main display method
        $this->display_anar_products_shipping();
    }

    /**
     * Render Anar shipping display HTML
     * 
     * Core method that generates and outputs the HTML for Anar shipping
     * options. Extracted from display_anar_products_shipping() for reuse.
     * 
     * @param array $ship Grouped shipping packages by shipmentsReferenceId
     * @return void Outputs HTML directly
     * @since 1.0.0
     * @access private
     */
    private function render_anar_shipping_display($ship) {
        $pack_index = 0;
        
        // Use parent method to get package info for multi-origin alert
        $packages_info = $this->get_cart_packages_info();
        
        // Show notice if multiple packages
        if($packages_info['package_count'] > 1){
            echo '<tr class="anar-shipments-user-notice-row"><td colspan="2"><p>کالاهای انتخابی شما از چند انبار مختلف ارسال می شوند.</p></td></tr>';
        }
        
        foreach ($ship as $key => $v) {
            $pack_index++;
            $product_uniques = array_unique($v['names']);

            // Use parent method to generate product list markup
            $product_list_markup = $this->generate_product_list_markup($product_uniques, $pack_index);

            // Process delivery options with free shipping conditions
            $radio_data = [];
            foreach ($v['delivery'] as $delivery_key => $delivery) {
                $package_total = $this->calculate_package_total($product_uniques);
                
                // Check free condition
                if (isset($delivery['freeConditions']) && isset($delivery['freeConditions']['purchasesPrice'])){
                    if($package_total >= $delivery['freeConditions']['purchasesPrice']) {
                        $delivery['price'] = 0;
                        $delivery['estimatedTime'] = 'ارسال رایگان';
                    }
                }

                $radio_data[$delivery_key] = [
                    'label' => anar_translator($delivery['name']),
                    'estimated_time' => $delivery['estimatedTime'] ?? '',
                    'price' => anar_get_formatted_price($delivery['price']),
                ];
            }

            // Get the chosen delivery option from the session or checkout value
            $chosen = WC()->session->get('anar_delivery_option_' . $key);
            $chosen = empty($chosen) ? WC()->checkout->get_value('anar_delivery_option_' . $key) : $chosen;

            // If no option is chosen, set the first one as default
            if (empty($chosen) && !empty($radio_data)) {
                $chosen = key($radio_data);
            }

            ?>
            <tr class="anar-shipments-package-row">
                <td colspan="2">
                    <div class="anar-shipments-package-content <?php echo count($product_uniques) > 2 ? 'vertical-view' : '';?>">
                        <div class="anar-package-items-list">
                            <?php
                            echo $product_list_markup;
                            ?>
                        </div>
                        <div class="anar-delivery-options-area">
                            <?php $this->generate_delivery_option_field($key, $radio_data, $chosen );?>
                        </div>
                    </div>
                </td>
            </tr>
            <?php
        }
    }

    /**
     * Calculate package total with cart quantities (Override)
     * 
     * Overrides parent method to include cart item quantities for accurate
     * free shipping condition calculations in frontend checkout. Unlike parent
     * which uses base product price, this multiplies by quantity from cart.
     * 
     * Essential for correct free shipping threshold checks when customers
     * order multiple quantities of the same product.
     * 
     * @param array $product_ids Array of product IDs in the package
     * @return float Total package value including quantities
     * @since 1.0.0
     * @access protected
     * @see Checkout::calculate_package_total() Parent implementation
     */
    protected function calculate_package_total($product_ids) {
        $total = 0;
        foreach ($product_ids as $product_id) {
            $product = wc_get_product($product_id);
            if ($product) {
                $cart_item = $this->find_cart_item_by_product_id($product_id);
                if ($cart_item) {
                    $total += $product->get_price() * $cart_item['quantity'];
                }
            }
        }
        return $total;
    }

    /**
     * Find cart item by product ID
     * 
     * Helper method to locate a cart item given a product ID. Handles both
     * simple products and variations by checking both product_id and variation_id.
     * 
     * @param int $product_id Product or variation ID to find
     * @return array|null Cart item array or null if not found
     * @since 1.0.0
     * @access private
     */
    private function find_cart_item_by_product_id($product_id) {
        foreach (WC()->cart->get_cart() as $cart_item) {
            if ($cart_item['product_id'] == $product_id || $cart_item['variation_id'] == $product_id) {
                return $cart_item;
            }
        }
        return null;
    }

    /**
     * Calculate total Anar shipping fee for checkout
     * 
     * Calculates and adds total shipping cost as a WooCommerce fee to cart.
     * Processes all selected delivery options for each package and sums their costs.
     * Validates selections against current address and handles standard/Anar product mixing.
     * 
     * Process flow:
     * 1. Validate cart and session exist
     * 2. Check for address changes and clear invalid selections
     * 3. Loop through cart items grouping by shipmentsReferenceId
     * 4. Get selected delivery option for each package
     * 5. Fallback to first available option if none selected
     * 6. Calculate free shipping conditions
     * 7. Sum all package shipping costs
     * 8. Add as WooCommerce fee to cart
     * 
     * Features:
     * - Address change detection
     * - Automatic selection fallback
     * - Free shipping condition handling
     * - Duplicate package prevention
     * - Standard product integration
     * 
     * Hooked to: woocommerce_cart_calculate_fees
     * 
     * @return void Adds fee to WC()->cart
     * @since 1.0.0
     * @access public
     */
    public function calculate_total_shipping_fee() {
        // Early validation
        if (!WC()->session || empty(WC()->cart->get_cart())) {
            return;
        }

        // Get current address data for validation
        $current_city = WC()->customer->get_billing_city();
        $current_state = WC()->customer->get_billing_state();
        
        // Check if address has changed and validate delivery selections
        $this->check_and_clear_delivery_selections_on_address_change($current_city, $current_state);

        // Process cart items and calculate total shipping fee
        $total_shipping_fee = 0;
        $processed_references = [];
        $has_standard_product = WC()->session->get('has_standard_product');
        
        foreach (WC()->cart->get_cart() as $cart_item) {
            $shipments_data = ProductData::get_anar_product_shipments($cart_item['product_id']);

            if (!$shipments_data) {
                continue;
            }

            $shipmentsReferenceId = $shipments_data['shipmentsReferenceId'];
            
            // Skip if already processed
            if (in_array($shipmentsReferenceId, $processed_references)) {
                continue;
            }

            // Get selected option with fallback
            $selected_option = $this->get_selected_shipping_option($shipmentsReferenceId);

            if ($selected_option && isset($shipments_data['delivery'][$selected_option])) {
                $delivery_price = floatval($shipments_data['delivery'][$selected_option]['price']);
                $converted_price = awca_convert_price_to_woocommerce_currency($delivery_price);
                $total_shipping_fee += $converted_price;
                
                // Mark this shipment reference as processed
                $processed_references[] = $shipmentsReferenceId;
            }
        }

        // Add the shipping fee if total is greater than 0
        if ($total_shipping_fee > 0) {
            $label = $has_standard_product
                ? 'مجموع حمل نقل سایر محصولات'
                : 'مجموع حمل نقل';

            WC()->cart->add_fee($label, $total_shipping_fee);
        } else {
            $this->log('No shipping fee to add', 'error');
        }
    }


    /**
     * Detect address changes and validate delivery selections
     * 
     * Compares current billing address with previously stored address in session.
     * If address has changed, validates all delivery selections to ensure they're
     * still valid for the new location. Invalid selections are cleared.
     * 
     * This prevents customers from keeping delivery options that aren't available
     * for their new address (e.g., switching from Tehran to Shiraz).
     * 
     * @param string $current_city Current billing city
     * @param string $current_state Current billing state code
     * @return void Updates session and clears invalid selections
     * @since 1.0.0
     * @access private
     */
    private function check_and_clear_delivery_selections_on_address_change($current_city, $current_state) {
        // Get stored address from session
        $stored_city = WC()->session->get('anar_last_city');
        $stored_state = WC()->session->get('anar_last_state');
        
        // If address has changed, validate delivery selections
        if ($stored_city !== $current_city || $stored_state !== $current_state) {
            // Validate each delivery selection against current address
            $this->validate_delivery_selections_for_current_address($current_city, $current_state);
            
            // Update stored address
            WC()->session->set('anar_last_city', $current_city);
            WC()->session->set('anar_last_state', $current_state);
        }
    }

    /**
     * Validate all delivery selections against current address
     * 
     * Loops through all cart items with Anar products and checks if their
     * selected delivery options are valid for the current customer address.
     * Clears session data for any invalid selections.
     * 
     * Called when address changes are detected to ensure delivery method
     * availability matches the new location.
     * 
     * @param string $current_city Current billing city code
     * @param string $current_state Current billing state code
     * @return void Clears invalid delivery selections from session
     * @since 1.0.0
     * @access private
     */
    private function validate_delivery_selections_for_current_address($current_city, $current_state) {
        $cart_items = WC()->cart->get_cart();
        $current_state_name = WC()->countries->states[WC()->customer->get_billing_country()][$current_state] ?? '';
        
        // Try to get city name from PWS plugin
        $current_city_name = $current_city; // Default to original value
        if ( function_exists( 'PWS' ) && method_exists( PWS(), 'get_city' ) ) {
            $current_city_name = PWS()->get_city( $current_city );
        }
        
        foreach ($cart_items as $cart_item_key => $cart_item) {
            $product_id = $cart_item['product_id'];
            $shipments_data = ProductData::get_anar_product_shipments($product_id);
            
            if ($shipments_data) {
                $shipmentsReferenceId = $shipments_data['shipmentsReferenceId'];
                $selected_option = WC()->session->get('anar_delivery_option_' . $shipmentsReferenceId);
                
                if ($selected_option) {
                    // Check if the selected option is valid for current address
                    $is_valid = $this->is_delivery_option_valid_for_address($shipments_data, $selected_option, $current_city_name, $current_state_name);
                    
                    if (!$is_valid) {
                        WC()->session->set('anar_delivery_option_' . $shipmentsReferenceId, null);
                    }
                }
            }
        }
    }

    /**
     * Check if delivery option is available for given address
     * 
     * Validates a specific delivery option against customer's address by checking
     * shipment type availability (intra-city, intra-province, or inter-province).
     * 
     * Logic:
     * - Same city & state = 'insideShopCity' required
     * - Same state only = 'insideShopState' required
     * - Different state = 'otherStates' required
     * - 'allCities' type is always valid
     * 
     * @param array $shipments_data Complete shipment data for the product
     * @param string $selected_option Delivery option ID to validate
     * @param string $current_city Customer's city name (translated)
     * @param string $current_state_name Customer's state name (translated)
     * @return bool True if delivery option is valid for address, false otherwise
     * @since 1.0.0
     * @access private
     */
    private function is_delivery_option_valid_for_address($shipments_data, $selected_option, $current_city, $current_state_name) {
        // Get the shipment data for the selected option
        foreach ($shipments_data['shipments'] as $shipment) {
            foreach ($shipment->delivery as $delivery) {
                if ($delivery->_id === $selected_option) {
                    // Check if this delivery option is available for current address
                    $shipment_types_to_display = [];
                    
                    if (
                        $current_state_name === $shipments_data['shipmentsReferenceState'] &&
                        $current_city === $shipments_data['shipmentsReferenceCity']
                    ) {
                        $shipment_types_to_display = ['insideShopCity'];
                    } elseif ($current_state_name === $shipments_data['shipmentsReferenceState']) {
                        $shipment_types_to_display = ['insideShopState'];
                    } else {
                        $shipment_types_to_display = ['otherStates'];
                    }
                    
                    $is_valid = $shipment->type === 'allCities' || in_array($shipment->type, $shipment_types_to_display);
                    return $is_valid;
                }
            }
        }
        
        return false;
    }

    /**
     * Get selected shipping option with intelligent fallback
     * 
     * Retrieves customer's selected delivery option for a package using
     * multi-source fallback strategy to ensure a valid selection always exists.
     * 
     * Fallback order:
     * 1. Check WooCommerce session for saved selection
     * 2. Check POST data from checkout form
     * 3. Auto-select first available option for current address
     * 
     * Updates session when selection is found in POST or auto-selected.
     * 
     * @param string $shipmentsReferenceId Warehouse/package identifier
     * @return string|null Delivery option ID or null if none available
     * @since 1.0.0
     * @access private
     */
    private function get_selected_shipping_option($shipmentsReferenceId) {
        // First try session
        $selected_option = WC()->session->get('anar_delivery_option_' . $shipmentsReferenceId);

        // Then try POST data
        if (empty($selected_option) && isset($_POST['anar_delivery_option_' . $shipmentsReferenceId])) {
            $selected_option = sanitize_text_field($_POST['anar_delivery_option_' . $shipmentsReferenceId]);
            // Update session
            WC()->session->set('anar_delivery_option_' . $shipmentsReferenceId, $selected_option);
        }

        // If still no option selected, try to get the first available option
        if (empty($selected_option)) {
            $selected_option = $this->get_first_available_delivery_option($shipmentsReferenceId);
            if ($selected_option) {
                WC()->session->set('anar_delivery_option_' . $shipmentsReferenceId, $selected_option);
            }
        }

        return $selected_option;
    }

    /**
     * Get first available delivery option for package
     * 
     * Automatically selects the first valid delivery option for a package
     * based on customer's current address. Used as last resort fallback
     * when no delivery method has been selected.
     * 
     * Process:
     * 1. Find cart item matching shipmentsReferenceId
     * 2. Get product's shipment data
     * 3. Determine applicable shipment types for address
     * 4. Find first active shipment with active delivery
     * 5. Return first delivery option ID
     * 
     * @param string $shipmentsReferenceId Warehouse/package identifier
     * @return string|null First available delivery option ID or null if none found
     * @since 1.0.0
     * @access private
     */
    private function get_first_available_delivery_option($shipmentsReferenceId) {
        $cart_items = WC()->cart->get_cart();
        $current_city = WC()->customer->get_billing_city();
        $current_state = WC()->customer->get_billing_state();
        $current_state_name = WC()->countries->states[WC()->customer->get_billing_country()][$current_state] ?? '';
        
        // Try to get city name from PWS plugin
        $current_city_name = $current_city; // Default to original value
        if ( function_exists( 'PWS' ) && method_exists( PWS(), 'get_city' ) ) {
            $current_city_name = PWS()->get_city( $current_city );
        }
        
        foreach ($cart_items as $cart_item_key => $cart_item) {
            $product_id = $cart_item['product_id'];
            $shipments_data = ProductData::get_anar_product_shipments($product_id);
            
            if ($shipments_data && $shipments_data['shipmentsReferenceId'] === $shipmentsReferenceId) {
                // Get available shipment types for current address
                $shipment_types_to_display = [];
                
                if (
                    $current_state_name === $shipments_data['shipmentsReferenceState'] &&
                    $current_city_name === $shipments_data['shipmentsReferenceCity']
                ) {
                    $shipment_types_to_display = ['insideShopCity'];
                } elseif ($current_state_name === $shipments_data['shipmentsReferenceState']) {
                    $shipment_types_to_display = ['insideShopState'];
                } else {
                    $shipment_types_to_display = ['otherStates'];
                }
                
                // Find first available delivery option
                foreach ($shipments_data['shipments'] as $shipment) {
                    if ($shipment->type === 'allCities' || in_array($shipment->type, $shipment_types_to_display)) {
                        if ($shipment->active && !empty($shipment->delivery)) {
                            foreach ($shipment->delivery as $delivery) {
                                if ($delivery->active) {
                                    return $delivery->_id;
                                }
                            }
                        }
                    }
                }
            }
        }
        
        return null;
    }

    /**
     * Customize shipping package name for standard products
     * 
     * Modifies the WooCommerce shipping package display name to differentiate
     * between Anar products and standard WooCommerce products. Shows product
     * names for standard products or hides shipping section entirely if only
     * Anar products exist.
     * 
     * Behavior:
     * - If only Anar products: Returns empty string (shipping hidden via CSS)
     * - If mixed cart: Returns "حمل و نقل (product names)"
     * 
     * Hooked to: woocommerce_shipping_package_name
     * 
     * @param string $name Original shipping package name
     * @return string Modified package name or empty string
     * @since 1.0.0
     * @access public
     */
    public function prefix_shipping_package_name_other_products( $name ) {

        $res = Cart::get_anar_products_in_cart();
        $has_standard_product = WC()->session->get('has_standard_product');

        $anarProducts = $res[0];
        $otherProducts = $res[1];

        $tags = implode(', ', $otherProducts);



        // If no standard products, add custom free shipping
        if (!$has_standard_product) {
            // Clear existing rates
            return '';
        }else{
            return "حمل و نقل ($tags)";
        }

    }

    /**
     * Save customer's delivery selections to session
     * 
     * Parses checkout form data to extract delivery option selections and saves
     * them to WooCommerce session. Validates selections against current address
     * before saving to prevent invalid choices.
     * 
     * Only processes fields starting with 'anar_delivery_option_' prefix.
     * Invalid selections are not saved - system will auto-select valid option.
     * Forces cart totals recalculation after updating selections.
     * 
     * Hooked to: woocommerce_checkout_update_order_review
     * 
     * @param string $posted_data URL-encoded form data from checkout
     * @return void Updates session and recalculates cart totals
     * @since 1.0.0
     * @access public
     */
    public function save_checkout_delivery_choice_on_session_better($posted_data ) {
        parse_str($posted_data, $output);
        foreach ($output as $key => $value) {
            if (strpos($key, 'anar_delivery_option_') === 0) {
                $shipmentsReferenceId = str_replace('anar_delivery_option_', '', $key);
                
                // Validate the delivery option against current address before saving
                $is_valid = $this->validate_delivery_option_for_current_address($shipmentsReferenceId, $value);
                
                if ($is_valid) {
                    WC()->session->set($key, $value);
                }
                // Don't save invalid options - let the system auto-select the correct one
            }
        }

        // Force recalculation after updating session
        WC()->cart->calculate_totals();
    }

    /**
     * Validate specific delivery option against current address
     * 
     * Checks if a particular delivery option ID is valid for the customer's
     * current billing address. Used when processing form submissions to
     * ensure selected option is available for the location.
     * 
     * Finds the product with matching shipmentsReferenceId and delegates
     * validation to is_delivery_option_valid_for_address().
     * 
     * @param string $shipmentsReferenceId Warehouse/package identifier
     * @param string $delivery_option_id Delivery option ID to validate
     * @return bool True if valid for current address, false otherwise
     * @since 1.0.0
     * @access private
     */
    private function validate_delivery_option_for_current_address($shipmentsReferenceId, $delivery_option_id) {
        $cart_items = WC()->cart->get_cart();
        $current_city = WC()->customer->get_billing_city();
        $current_state = WC()->customer->get_billing_state();
        $current_state_name = WC()->countries->states[WC()->customer->get_billing_country()][$current_state] ?? '';
        
        // Try to get city name from PWS plugin
        $current_city_name = $current_city; // Default to original value
        if ( function_exists( 'PWS' ) && method_exists( PWS(), 'get_city' ) ) {
            $current_city_name = PWS()->get_city( $current_city );
        }
        
        foreach ($cart_items as $cart_item_key => $cart_item) {
            $product_id = $cart_item['product_id'];
            $shipments_data = ProductData::get_anar_product_shipments($product_id);
            
            if ($shipments_data && $shipments_data['shipmentsReferenceId'] === $shipmentsReferenceId) {
                $is_valid = $this->is_delivery_option_valid_for_address($shipments_data, $delivery_option_id, $current_city_name, $current_state_name);
                return $is_valid;
            }
        }
        
        return false;
    }

    /**
     * Set free shipping when cart has only Anar products
     * 
     * Automatically sets 'free_shipping' as the chosen shipping method when
     * cart contains only Anar products (no standard WooCommerce products).
     * This prevents WooCommerce from requiring a shipping method selection
     * for Anar-only orders since shipping is handled separately.
     * 
     * Works with enable_free_shiping_when_no_standard_products() to provide
     * a seamless checkout experience for dropshipping orders.
     * 
     * Hooked to: woocommerce_checkout_update_order_review
     * 
     * @return void Updates chosen_shipping_methods in session
     * @since 1.0.0
     * @access public
     */
    public function chosen_shipping_methods_when_no_standard_products()
    {
        $has_standard_product = WC()->session->get('has_standard_product');
        if (!$has_standard_product) {
            WC()->session->set('chosen_shipping_methods', ['free_shipping']);
        }
    }

    /**
     * Validate checkout requirements for Anar-only orders
     * 
     * Validates that free shipping method is properly selected for orders
     * containing only Anar products. Prevents checkout from proceeding if
     * shipping method is missing or incorrect.
     * 
     * Shows error message instructing customer to return to cart if validation
     * fails. This ensures WooCommerce's shipping requirement is satisfied for
     * Anar dropshipping orders.
     * 
     * Hooked to: woocommerce_checkout_process
     * 
     * @return void Adds WooCommerce notice if validation fails
     * @since 1.0.0
     * @access public
     */
    public function checkout_validations(){
        $chosen_shipping_methods = WC()->session->get('chosen_shipping_methods');
        $has_standard_product = WC()->session->get('has_standard_product');
        if(!$has_standard_product){
            if (empty($chosen_shipping_methods) || !in_array('free_shipping', $chosen_shipping_methods)) {
                wc_add_notice( 'هیچ روش حمل و نقلی انتخاب نشده است. لطفا به سبد خرید بروید و مجدد دکمه تسویه حساب را کلیک کنید.', 'error' );
            }
        }
    }

    /**
     * Replace shipping rates with free shipping for Anar-only orders
     * 
     * Filters WooCommerce shipping rates to provide free shipping method when
     * cart contains only Anar products. Clears all standard shipping rates and
     * adds a free shipping rate with label "حمل و نقل" (Shipping).
     * 
     * This allows WooCommerce checkout to function normally while Anar shipping
     * is handled separately through the Anar delivery options interface.
     * 
     * Works in conjunction with:
     * - chosen_shipping_methods_when_no_standard_products()
     * - visually_hide_shipment_rates_no_standard_products()
     * 
     * Hooked to: woocommerce_package_rates (priority 100)
     * 
     * @param array $rates Available shipping rates
     * @param array $package Shipping package data
     * @return array Modified rates (free shipping only if no standard products)
     * @since 1.0.0
     * @access public
     */
    public function enable_free_shiping_when_no_standard_products( $rates, $package ) {
        $has_standard_product = WC()->session->get('has_standard_product');

        // If no standard products, add an instance of Free Shipping method
        if (!$has_standard_product) {
            // Clear existing rates
            $rates = [];

            // Add a free shipping method
            $free_shipping_rate = new \WC_Shipping_Rate(
                'free_shipping',
                'حمل و نقل',
                0,
                [],
                'free_shipping',
                ''
            );

            $rates['free_shipping'] = $free_shipping_rate;

            return $rates;
        }

        return $rates;
    }

    /**
     * Hide WooCommerce shipping section for Anar-only orders
     * 
     * Injects CSS to hide the standard WooCommerce shipping totals row on checkout
     * when cart contains only Anar products. This prevents showing the free shipping
     * rate that was added by enable_free_shiping_when_no_standard_products().
     * 
     * Results in cleaner checkout UI where only Anar delivery options are visible.
     * The free shipping method still exists in backend for WooCommerce requirement,
     * but is invisible to customers.
     * 
     * Only active on checkout page.
     * 
     * Hooked to: wp_head
     * 
     * @return void Outputs CSS style tag if conditions met
     * @since 1.0.0
     * @access public
     */
    public function visually_hide_shipment_rates_no_standard_products() {
        if(is_checkout()) {
            $has_standard_product = WC()->session->get('has_standard_product');

            if (!$has_standard_product) {
                echo '<style>.woocommerce-shipping-totals.shipping {display: none;}</style>';
            }
        }
    }

    /**
     * Save Anar shipping data to order meta for API integration
     * 
     * Critical method that saves selected delivery options to order meta for later
     * use in Anar order creation API call. Retrieves shipment data and customer
     * selections from session and formats them for order storage.
     * 
     * Process flow:
     * 1. Get shipment data from session (set during display_anar_products_shipping)
     * 2. Get customer's selected delivery option per package
     * 3. Match selections to shipment/delivery IDs
     * 4. Format data for API: shipmentId, deliveryId, shipmentsReferenceId
     * 5. Fallback to first option if no selection found
     * 6. Save to order meta with key _anar_shipping_data
     * 
     * Saved data structure is used by OrderManager for Anar API order creation.
     * 
     * Hooked to: woocommerce_checkout_create_order (priority 20)
     * 
     * @param \WC_Order $order Order object being created
     * @return void Updates order meta
     * @throws \Exception Logs error if data processing fails
     * @since 1.0.0
     * @access public
     */
    public function save_anar_data_on_order($order) {
        try {
            // Retrieve the shipment data from the session
            $shipment_data = WC()->session->get('anar_shipment_data', []);

            if (empty($shipment_data)) {
                return;
            }

            // Initialize an array to hold the processed shipping data
            $shipping_data = [];

            // Loop through the shipment data stored in the session
            foreach ($shipment_data as $shipment) {
                // Get the chosen delivery option from the session
                $chosen = WC()->session->get('anar_delivery_option_' . $shipment['shipmentsReferenceId']);

                if (!$chosen) {
                    continue;
                }

                // Proceed only if a delivery option is chosen
                if ($chosen === $shipment['deliveryId']) {
                    // Save relevant shipment data
                    $shipping_data[] = [
                        'shipmentId' => $shipment['shipmentId'],
                        'deliveryId' => $shipment['deliveryId'],
                        'shipmentsReferenceId' => $shipment['shipmentsReferenceId'],
                    ];
                }
            }

            // Fallback: If shipping_data is empty, use the first available option
            if (empty($shipping_data) && !empty($shipment_data)) {
                $first_shipment = $shipment_data[0];
                $shipping_data[] = [
                    'shipmentId' => $first_shipment['shipmentId'],
                    'deliveryId' => $first_shipment['deliveryId'],
                    'shipmentsReferenceId' => $first_shipment['shipmentsReferenceId'],
                ];
            }

            // Save the shipping data in the order meta if there are any valid entries
            if (!empty($shipping_data)) {
                update_post_meta($order->get_id(), self::ANAR_SHIPPING_META, $shipping_data);
                $order->update_meta_data(self::ANAR_SHIPPING_META, $shipping_data);
            } else {
                $this->log('No shipping data to save after fallback for order #' . $order->get_id(), 'warning');
            }

        } catch (\Exception $e) {
            $this->log('Error saving Anar order meta: ' . $e->getMessage() . ' for order #' . $order->get_id(), 'error');
            if (current_user_can('manage_options')) {
                wc_add_notice('Error processing Anar shipping data: ' . $e->getMessage(), 'error');
            }
        }
    }


    /**
     * Generate delivery option radio buttons for frontend checkout (Override)
     * 
     * Overrides parent method to echo output directly instead of returning HTML string.
     * Uses frontend-specific styling and JavaScript hooks for real-time fee calculation.
     * Parent returns HTML for admin modal, this echoes for frontend checkout.
     * 
     * Differences from parent:
     * - Echoes output directly instead of returning string
     * - Uses 'anar_delivery_option_' prefix for field names
     * - Includes JS hooks for dynamic total calculation
     * - Frontend-optimized styling and layout
     * - Integrated with WooCommerce checkout update mechanism
     * 
     * @param string $input_key Unique identifier for package (shipmentsReferenceId)
     * @param array $radio_data Delivery options array with 'label', 'price', 'estimated_time'
     * @param string $chosen Pre-selected delivery option key
     * @param string $context Context identifier (defaults to 'frontend', not used)
     * @return void Echoes HTML directly to output buffer
     * @since 1.0.0
     * @access public
     * @see Checkout::generate_delivery_option_field() Parent implementation
     */
    public function generate_delivery_option_field($input_key, $radio_data, $chosen, $context = 'frontend') {
        $output = '<div class="form-row form-row-wide update_totals_on_change">';

        // Check if the chosen value exists in the radio data
        if (!array_key_exists($chosen, $radio_data)) {
            reset($radio_data);
            $chosen = key($radio_data);  // Set to the first key in the array if not found
        }

        foreach ($radio_data as $key => $data) {
            // Generate a unique ID for the radio button
            $id = 'anar_delivery_option_' . $input_key . '_' . sanitize_title($key);

            // Determine if this option is chosen
            $checked = ($chosen === $key) ? 'checked' : '';
            $div_selected = ($chosen === $key) ? ' selected' : '';

            // Create the radio button and label
            $output .= '<div class="anar-delivery-option '.$div_selected.'">';
            $output .= sprintf(
                '<input type="radio" class="input-radio" data-input-group="%s" value="%s" name="anar_delivery_option_%s" id="%s" %s>',
                esc_attr($input_key),
                esc_attr($key),
                esc_attr($input_key),
                esc_attr($id),
                esc_attr($checked)
            );

            // Customize the label with additional spans
            $output .= sprintf(
                '<label for="%s" class="radio"><span class="label">%s</span><span class="estimated-time">%s</span><span class="price">%s</span></label>',
                esc_attr($id),
                esc_html($data['label']),
                esc_html($data['estimated_time']),
                esc_html($data['price']),
            );

            $output .= '</div>'; // Close anar-delivery-option div
        }

        $output .= '</div>'; // Close form-row div
        
        // Echo output directly for frontend (used in display_anar_products_shipping)
        echo $output;
    }


    /**
     * Handle autofill events on checkout
     * 
     * Forces cart totals recalculation when checkout fields are autofilled
     * (e.g., browser autofill, password managers, address completion).
     * Ensures shipping fees and Anar delivery options are recalculated
     * when address changes via autofill.
     * 
     * Called early in the order review update process (priority 5) to ensure
     * calculations happen before other checkout update handlers.
     * 
     * Hooked to: woocommerce_checkout_update_order_review (priority 5)
     * 
     * @return void Forces cart recalculation
     * @since 1.0.0
     * @access public
     */
    public function handle_autofill_update() {
        // Force recalculation of totals
        WC()->cart->calculate_totals();
    }

    /**
     * Remove Anar products without shipping data from cart
     * 
     * Validates all Anar products in cart to ensure they have valid shipping data.
     * Products missing shipment data are automatically removed with error notice.
     * This prevents checkout errors and ensures only shippable products proceed.
     * 
     * Validation checks:
     * - Product has _anar_products meta (is Anar product)
     * - Product has shipment data from ProductData::get_anar_product_shipments()
     * - Shipments array is not empty
     * 
     * Forces cart recalculation after any removals to update totals.
     * Shows Persian error message for each removed product.
     * 
     * Hooked to: woocommerce_check_cart_items (priority 5)
     * 
     * @return void Removes invalid items and shows notices
     * @since 1.0.0
     * @access public
     */
    public function remove_anar_products_without_shipping() {
        $cart = WC()->cart;
        if (!$cart) return;
        $removed = false;
        foreach ($cart->get_cart() as $cart_item_key => $cart_item) {
            $product_id = $cart_item['product_id'];
            $product = wc_get_product($product_id);
            if (!$product) continue;
            $parent_id = $product->get_parent_id();
            $anar_meta = $parent_id == 0
                ? get_post_meta($product_id, self::ANAR_PRODUCT_META, true)
                : get_post_meta($parent_id, self::ANAR_PRODUCT_META, true);
            if ($anar_meta) {
                $shipping_data = \Anar\ProductData::get_anar_product_shipments($product_id);
                if (empty($shipping_data) || !isset($shipping_data['shipments']) || count($shipping_data['shipments']) === 0) {
                    $cart->remove_cart_item($cart_item_key);
                    $removed = true;
                    $product_title = $product->get_name();
                    wc_add_notice(sprintf(__('محصول "%s" به دلیل عدم وجود روش ارسال از سبد خرید شما حذف شد.', 'wp-anar'), $product_title), 'error');
                }
            }
        }
        if ($removed) {
            // Force cart totals recalculation
            $cart->calculate_totals();
        }
    }


}

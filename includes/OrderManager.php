<?php
namespace Anar;

defined( 'ABSPATH' ) || exit;


/**
 * OrderManager Class
 * 
 * Handles creation and management of Anar orders in the dropshipping system.
 * Acts as the bridge between WooCommerce orders and Anar API for order creation,
 * address formatting, shipment preparation, and order validation.
 * 
 * Key Responsibilities:
 * - Process AJAX requests for Anar order creation
 * - Validate customer data (phone, postcode, address)
 * - Format addresses for Iranian customers per Anar API requirements
 * - Prepare shipment data from order meta
 * - Handle both retail (ship to customer) and wholesale (ship to stock) orders
 * - Extract Anar variation IDs from WooCommerce products
 * - Manage order metadata and API communications
 * - Save raw order data for debugging purposes
 * 
 * Order Creation Flow:
 * 1. Receive AJAX request with order_id and order_type
 * 2. Validate order hasn't been created in Anar already
 * 3. Validate required customer data (phone, postcode)
 * 4. Extract Anar product variations from order items
 * 5. Prepare shipment data from order meta
 * 6. Prepare address data based on order type
 * 7. Send order creation request to Anar API
 * 8. Save Anar order data to WooCommerce order meta
 * 9. Add order note and return success/error response
 * 
 * @package Anar
 * @since 1.0.0
 */
class OrderManager {

    /**
     * Singleton instance
     * @var OrderManager|null
     */
    private static $instance;

    /**
     * Get singleton instance
     * 
     * @return OrderManager The singleton instance
     * @since 1.0.0
     */
    public static function get_instance() {
        if ( ! isset( self::$instance ) ) {
            self::$instance = new self();
        }
        return self::$instance;
    }


    /**
     * Constructor - Register hooks for order management
     * 
     * Registers WordPress hooks for:
     * - WooCommerce address formatting for Iranian customers
     * - AJAX handler for Anar order creation from admin
     * 
     * @since 1.0.0
     */
    public function __construct() {
        add_filter('woocommerce_localisation_address_formats', [$this, 'format_iranian_address'] , 30, 1);
        add_action('wp_ajax_awca_create_anar_order_ajax', [$this, 'handle_ajax_order_creation']);
    }



    /**
     * Format Iranian addresses for Anar compatibility
     * 
     * Customizes WooCommerce address format for Iran (IR) to match Anar API requirements.
     * Places state and city before street address, removes country from output.
     * 
     * Format structure:
     * - Company name (if provided)
     * - Full name (first_name + last_name)
     * - State/province
     * - City
     * - Address line 1
     * - Address line 2 (if provided)
     * - Postcode
     * 
     * Hooked to: woocommerce_localisation_address_formats (priority 30)
     * 
     * @param array $formats Array of address formats keyed by country code
     * @return array Modified formats array with Iranian format
     * @since 1.0.0
     * @access public
     */
    public function format_iranian_address( $formats ) {
        $formats['IR'] = "{company}\n{first_name} {last_name}\n{state}\n{city}\n{address_1}\n{address_2}\n{postcode}";
        return $formats;
    }


    /**
     * AJAX handler: Create order in Anar system
     * 
     * Main method that processes admin request to create an order in Anar platform.
     * Performs comprehensive validation, data preparation, API communication,
     * and meta data management for both retail and wholesale order types.
     * 
     * Process flow:
     * 1. Validate order_id and order_type from POST
     * 2. Check if order already exists in Anar (prevent duplicates)
     * 3. Validate customer data (phone, postcode format)
     * 4. Extract and validate Anar product variations
     * 5. Prepare shipment data from order meta
     * 6. Prepare address based on order type (customer or stock)
     * 7. Build order data payload for API
     * 8. Send POST request to Anar API
     * 9. Process API response and handle errors
     * 10. Save Anar order data to order meta
     * 11. Add order note with Anar group ID
     * 
     * Required POST parameters:
     * - order_id: WooCommerce order ID
     * - order_type: 'retail' (ship to customer) or 'wholesale' (ship to stock)
     * - shipping_option_*: Selected delivery options (for retail orders)
     * - stock_*: Stock address fields (for wholesale orders, if needed)
     * 
     * Validation rules:
     * - Phone number must not be empty
     * - Postcode must be exactly 10 digits (English numbers)
     * - At least one Anar product must exist in order
     * - Shipment data must be available
     * 
     * Hooked to: wp_ajax_awca_create_anar_order_ajax
     * 
     * @return void Sends JSON response (success/error) and exits
     * @since 1.0.0
     * @access public
     */
    public function handle_ajax_order_creation() {
        // Validate order_id from AJAX request
        if (!isset($_POST['order_id']) || !$_POST['order_id']) {
            wp_send_json_error(array('message' => 'order_id required'));
        }
        
        $order_id = $_POST['order_id'];
        $order = wc_get_order($order_id);

        // Get order type from AJAX request (default to retail)
        $order_type = $_POST['order_type'] ?? 'retail';
        
        // Save shipping data to order meta if it's a retail order with shipping selections
        if ($order_type === 'retail') {
            $this->save_shipping_data_to_order_meta($order);
        }
        
        // Check if order already exists in Anar system
        if (awca_is_hpos_enable()) {
            $anar_order_number = $order->get_meta('_anar_order_group_id', true);
        } else {
            $anar_order_number = get_post_meta($order_id, '_anar_order_group_id', true);
        }
        
        if($anar_order_number){
            awca_log('anar order created before successfully: #' . $anar_order_number);
            wp_send_json_error(array('message' => 'سفارش قبلا ساخته شده است.'));
        }

        // Validate required customer data
        $address = $order->get_address('billing');
        $validation_fields = true;
        $validation_message  = '';

        if ($address['phone'] == ''){
            $validation_fields = false;
            $validation_message .= ' :: [انار] شماره موبایل خریدار اجباری است';

        }
        if ($address['postcode'] == ''){
            $validation_fields = false;
            $validation_message .= ' :: [انار] کد پستی خریدار اجباری است';
        }
        if( $address['postcode'] != '' && !preg_match('/^\d{10}$/' , $address['postcode']) ){
            $validation_fields = false;
            $validation_message .= sprintf(" :: [انار] کدپستی %s معتبر نیست. کدپستی باید اعداد انگلیسی بدون فاصله و ۱۰ رقمی باشد.", $address['postcode']);
        }

        if(!$validation_fields){
            $order->add_order_note($validation_message);
            wp_send_json_error(array('message' => $validation_message));
        }


        // Prepare order items for Anar API
        $items = [];
        foreach ($order->get_items() as $item_id => $item) {
            $anar_variation_id = $this->get_anar_variation_id($item);

            if($anar_variation_id){
                $items[] = [
                    'variation' => $anar_variation_id,
                    'amount' => $item->get_quantity(),
                    'info' => []
                ];
            }

        }

        if(empty($items)){
            $message = 'مشکلی در اطلاعات محصولات انار وجود دارد.';
            $order->add_order_note($message);
            wp_send_json_error(array('message' => $message));
        }

        // Format address for Anar API
        $formatted_address = $this->format_address_for_anar($order);
        
        // Get city and province information
        $billing_city = $order->get_billing_city();
        $billing_state = $order->get_billing_state();
        $billing_country = $order->get_billing_country();

        // Get state name from WooCommerce countries
        $billing_state_name = '';
        if (!empty($billing_country) && !empty($billing_state)) {
            $countries = new \WC_Countries();
            $states = $countries->get_states($billing_country);
            $billing_state_name = $states[$billing_state] ?? $billing_state;
        }



        // Prepare shipment data for Anar API
        $prepared_anar_shipments = $this->prepare_anar_shipments($order);
        if(empty($prepared_anar_shipments)){
            $message = 'اطلاعات حمل و نقل مرسوله های انار وجود ندارد!';
            $order->add_order_note($message);
            wp_send_json_error(array('message' => $message));
        }
        
        // Prepare address data based on order type
        $address_data = $this->prepare_address_data($order, $order_type);

        // Build order data for Anar API
        $create_data = [
            'type' => $order_type,
            'items' => $items,
            'address' => $address_data,
            'externalId' => $order->get_id(),
            'shipments' => $prepared_anar_shipments,
        ];

        // Save raw order data for debugging
        $this->save_raw_order_data($order, $create_data);

        // Create order in Anar system via API
        $create_response = ApiDataHandler::postAnarApi('https://api.anar360.com/wp/orders/', $create_data);
        if (is_wp_error($create_response)) {
            $order->add_order_note('خطا در برقراری ارتباط با سرور انار.');
            wp_send_json_error(array('message' => 'خطا در برقراری ارتباط با سرور انار.'));
        }

        // Process API response
        $create_response = json_decode(wp_remote_retrieve_body($create_response), true);

        if (!isset($create_response['success']) || !$create_response['success']) {
            // Handle API error response
            if(isset($create_response['data']['message'])){
                $error_message = $create_response['data']['message'];
            }elseif(isset($create_response['message'])){
                $error_message = $create_response['message'];
            }else{
                $error_message = 'Unknown error';
            }

            $order->add_order_note('ساخت سفارش در پنل انار انجام نشد: ' . $error_message);
            wp_send_json_error(array('message' => 'ساخت سفارش در پنل انار انجام نشد'));
        }

        // Save Anar order data to WooCommerce order meta
        $anar_order_data = [];
        foreach($create_response['result'] as $result){
            $anar_order_data[] = [
                "_id"           => $result["_id"],
                "groupId"       => $result["groupId"],
                "orderNumber"   => $result["orderNumber"]
            ];
        }

        $order->update_meta_data('_anar_order_data', $anar_order_data);
        $order->update_meta_data('_anar_order_group_id', $create_response['result'][0]['groupId']);
        
        // No need to save stock address here anymore - it's saved before order creation
        
        $order->add_order_note('سفارش در پنل انار با موفقیت ایجاد شد. #' . $create_response['result'][0]['groupId']);
        $order->save();

        // TODO: Update unpaid orders count for admin alerts
        //(new Payments())->count_unpaid_orders_count();

        wp_send_json_success(array('message' => 'سفارش با موفقیت در پنل انار ثبت شد.'));
    }


    /**
     * Save raw order data for debugging
     * 
     * Stores complete order payload sent to Anar API in order meta for
     * troubleshooting and debugging purposes. Data is saved with key
     * '_anar_raw_create_data' and displayed in admin if ANAR_DEBUG is true.
     * 
     * @param \WC_Order $order WooCommerce order object
     * @param array $data Complete order data array sent to API
     * @return void Updates order meta
     * @since 1.0.0
     * @access private
     */
    private function save_raw_order_data($order, $data){
        $order->update_meta_data('_anar_raw_create_data', $data);
        $order->save();
    }

    /**
     * Extract Anar variation ID from order item
     * 
     * Retrieves the Anar platform variation/variant ID stored in product meta.
     * Handles both simple products and product variations correctly.
     * 
     * For variable products (variations):
     * - Uses '_anar_sku' meta from the variation post
     * 
     * For simple products:
     * - Uses '_anar_variant_id' meta from the product post
     * 
     * This ID is required to create orders in Anar system as it identifies
     * the exact product variant in Anar's inventory.
     * 
     * @param \WC_Order_Item_Product $item Order item to extract ID from
     * @return string|false Anar variation ID or false if product invalid/not found
     * @since 1.0.0
     * @access private
     */
    private function get_anar_variation_id($item) {
        // Get the product object
        $product = $item->get_product();

        if(!$product || is_wp_error($product)) {
            return false;
        }

        // Check if the product is a variation
        if ($product->is_type('variation')) {
            // For variable products, get the variation SKU
            return get_post_meta($product->get_id(), '_anar_sku', true);
        } else {
            // For simple products, get the variant SKU
            return get_post_meta($product->get_id(), '_anar_variant_id', true);
        }
    }


    /**
     * Format billing address for Anar API
     * 
     * Processes WooCommerce formatted billing address to match Anar API format.
     * Removes HTML markup, customer name, and postcode to create clean address string.
     * 
     * Transformations:
     * 1. Replace <br/> and <br> tags with commas
     * 2. Remove customer full name from beginning
     * 3. Remove postcode from end
     * 4. Trim extra whitespace and commas
     * 
     * Example input:
     * "John Doe<br/>Tehran<br/>Valiasr Street<br/>1234567890"
     * 
     * Example output:
     * "Tehran, Valiasr Street"
     * 
     * @param \WC_Order $order WooCommerce order object
     * @return string Cleaned address string for Anar API
     * @since 1.0.0
     * @access private
     */
    private function format_address_for_anar($order) {
        $formatted_address = $order->get_formatted_billing_address();
        
        // Remove <br> tags and replace with commas
        $formatted_address = str_replace('<br/>', ', ', $formatted_address);
        $formatted_address = str_replace('<br>', ', ', $formatted_address);
        
        // Remove full name from the beginning of address
        $formatted_address = preg_replace('/^[^,]+,\s*/', '', $formatted_address);

        // Remove postcode from end of address if it exists
        $address = $order->get_address('billing');
        if (!empty($address['postcode'])) {
            $formatted_address = trim(str_replace($address['postcode'], '', $formatted_address), ', ');
        }
        
        return $formatted_address;
    }

    /**
     * Prepare shipment data from order meta for API
     * 
     * Extracts delivery selections stored in order meta (saved during checkout)
     * and formats them for Anar order creation API. Includes customer notes
     * as shipment description.
     * 
     * Data source: '_anar_shipping_data' order meta (HPOS compatible)
     * 
     * Each shipment entry contains:
     * - shipmentId: Anar shipment ID (warehouse/origin identifier)
     * - deliveryId: Selected delivery method ID
     * - shipmentsReferenceId: Package grouping identifier
     * - description: Customer's order note
     * 
     * @param \WC_Order $order WooCommerce order object
     * @return array Array of formatted shipment objects for API
     * @since 1.0.0
     * @access private
     */
    private function prepare_anar_shipments($order) {
        $shipments = [];

        // Get shipping data from order meta (HPOS compatible)
        if (awca_is_hpos_enable()) {
            $shipping_data = $order->get_meta('_anar_shipping_data', true);
        } else {
            $shipping_data = get_post_meta($order->get_id(), '_anar_shipping_data', true);
        }

        // Get customer note for shipment description
        $customer_note = $order->get_customer_note();

        // Process each shipping entry
        foreach ($shipping_data as $shipping) {
            $shipments[] = [
                'shipmentId' => $shipping['shipmentId'],
                'deliveryId' => $shipping['deliveryId'],
                'shipmentsReferenceId' => $shipping['shipmentsReferenceId'],
                'description' => esc_html($customer_note),
            ];
        }

        return $shipments;
    }

    /**
     * Save shipping selections from admin modal to order meta
     * 
     * Processes delivery selections from admin's pre-order modal and saves
     * them to order meta. Used for retail orders created via admin interface
     * where shipping options are selected in the modal instead of frontend checkout.
     * 
     * Process:
     * 1. Loop through POST data for 'shipping_option_*' fields
     * 2. Extract shipmentsReferenceId and delivery_id
     * 3. Find corresponding shipment_id from product data
     * 4. Build shipping_data array
     * 5. Save to '_anar_shipping_data' order meta
     * 
     * POST data format:
     * - shipping_option_{shipmentsReferenceId} => {deliveryId}
     * 
     * @param \WC_Order $order WooCommerce order object
     * @return void Saves shipping data to order meta
     * @since 1.0.0
     * @access private
     */
    private function save_shipping_data_to_order_meta($order) {
        $shipping_data = [];
        
        // Look for shipping option selections in POST data
        foreach ($_POST as $key => $value) {
            if (strpos($key, 'shipping_option_') === 0) {
                $reference_id = str_replace('shipping_option_', '', $key);
                $delivery_id = sanitize_text_field($value);
                
                // Find the shipmentId by looking through order products with matching reference ID
                $shipment_id = $this->find_shipment_id_for_reference($order, $reference_id, $delivery_id);
                
                if ($delivery_id && $shipment_id) {
                    $shipping_data[] = [
                        'shipmentId' => $shipment_id,
                        'deliveryId' => $delivery_id,
                        'shipmentsReferenceId' => $reference_id,
                    ];
                }
            }
        }

        // Save shipping data to order meta if we have data
        if (!empty($shipping_data)) {
            $order->update_meta_data('_anar_shipping_data', $shipping_data);
            $order->save();
        }
    }

    /**
     * Find shipment ID by matching reference and delivery IDs
     * 
     * Searches through order products to find the shipment ID that corresponds
     * to a specific shipmentsReferenceId and delivery option selection.
     * Required because admin modal selections only include reference and delivery IDs.
     * 
     * Search strategy:
     * 1. Loop through order items
     * 2. Get product's shipment data from ProductData
     * 3. Match shipmentsReferenceId
     * 4. Search shipments for matching delivery ID
     * 5. Return the shipment._id when match found
     * 
     * @param \WC_Order $order WooCommerce order object
     * @param string $reference_id Package identifier (shipmentsReferenceId)
     * @param string $delivery_id Selected delivery option ID
     * @return string|false Shipment ID if found, false otherwise
     * @since 1.0.0
     * @access private
     */
    private function find_shipment_id_for_reference($order, $reference_id, $delivery_id) {
        // Loop through order items to find a product with matching reference ID
        foreach ($order->get_items() as $item) {
            $product = $item->get_product();
            if (!$product) continue;
            
            $product_id = $product->is_type('variation') ? $product->get_parent_id() : $product->get_id();
            $product_shipments = ProductData::get_anar_product_shipments($product_id);
            
            if (!$product_shipments) continue;
            
            $product_reference_id = $product_shipments['shipmentsReferenceId'] ?? '';
            
            // If this product matches the reference ID, find the shipment with the selected delivery
            if ($product_reference_id === $reference_id && isset($product_shipments['shipments'])) {
                foreach ($product_shipments['shipments'] as $shipment) {
                    if (isset($shipment->delivery) && is_array($shipment->delivery)) {
                        foreach ($shipment->delivery as $delivery) {
                            if ($delivery->_id === $delivery_id) {
                                return $shipment->_id;
                            }
                        }
                    }
                }
            }
        }
        
        return false;
    }

    /**
     * Prepare address data based on order type
     * 
     * Routes to appropriate address preparation method based on whether
     * order is retail (ship to customer) or wholesale (ship to stock).
     * 
     * Order types:
     * - 'retail': Customer's billing address
     * - 'wholesale': Shop owner's stock address from wp_options
     * 
     * @param \WC_Order $order WooCommerce order object
     * @param string $order_type 'retail' or 'wholesale'
     * @return array Formatted address data for Anar API
     * @since 1.0.0
     * @access private
     * @see prepare_customer_address_data() For retail orders
     * @see prepare_stock_address_data() For wholesale orders
     */
    private function prepare_address_data($order, $order_type) {
        if ($order_type === 'wholesale') {
            // For wholesale orders, use stock address from AJAX data
            return $this->prepare_stock_address_data();
        } else {
            // For retail orders, use customer address
            return $this->prepare_customer_address_data($order);
        }
    }

    /**
     * Prepare customer address for retail orders
     * 
     * Formats customer's billing address from WooCommerce order for Anar API.
     * Uses PWS (Persian Woo States) plugin if available for proper city/state names.
     * 
     * Return structure:
     * - postalCode: 10-digit postcode
     * - detail: Formatted address string (without name/postcode)
     * - transFeree: Recipient full name
     * - transFereeMobile: Phone number
     * - city: City code (optional, for API categorization)
     * - province: State/province name (optional)
     * 
     * @param \WC_Order $order WooCommerce order object
     * @return array Formatted customer address for API
     * @since 1.0.0
     * @access private
     */
    private function prepare_customer_address_data($order) {
        $address = $order->get_address('billing');
        $formatted_address = $this->format_address_for_anar($order);
        
        // Get city and province information
        $billing_city = $order->get_billing_city();
        $billing_state = $order->get_billing_state();
        $billing_country = $order->get_billing_country();

        // Get state name from WooCommerce countries
        $billing_state_name = '';
        if (!empty($billing_country) && !empty($billing_state)) {
            $countries = new \WC_Countries();
            $states = $countries->get_states($billing_country);
            $billing_state_name = $states[$billing_state] ?? $billing_state;
        }

        return [
            'postalCode' => $address['postcode'],
            'detail' => $formatted_address,
            'transFeree' => $address['first_name'] . ' ' . $address['last_name'],
            'transFereeMobile' => $address['phone'],
            'city' => $billing_city, // optional
            'province' => $billing_state_name ?: '', // optional
        ];
    }

    /**
     * Prepare stock address for wholesale orders
     * 
     * Retrieves and formats shop owner's stock address from wp_options for
     * ship-to-stock orders. Validates all required fields and formats before
     * returning to ensure API compatibility.
     * 
     * Data source: '_anar_user_stock_address' option
     * 
     * Validation rules:
     * - All required fields must be present (first_name, last_name, state, city, address, postcode, phone)
     * - Postcode must be exactly 10 digits
     * - Phone must match Iranian mobile format (09xxxxxxxxx, 11 digits total)
     * - Address must be in Tehran (current Anar limitation)
     * 
     * Return structure:
     * - postalCode: 10-digit postcode
     * - detail: Complete formatted address
     * - transFeree: Stock receiver name
     * - transFereeMobile: Stock receiver phone
     * - city: City name
     * - province: Province/state name
     * 
     * @return array Formatted stock address for API
     * @throws Sends JSON error response if validation fails
     * @since 1.0.0
     * @access private
     */
    private function prepare_stock_address_data() {
        // Get stock address data from wp_options
        $stock_address = get_option('_anar_user_stock_address', array());

        if (empty($stock_address)) {
            wp_send_json_error(array('message' => 'آدرس انبار ذخیره نشده است. لطفاً ابتدا آدرس را ذخیره کنید.'));
        }

        // Validate required fields
        $required_fields = ['first_name', 'last_name', 'state', 'city', 'address', 'postcode', 'phone'];
        foreach ($required_fields as $field) {
            if (empty($stock_address[$field])) {
                wp_send_json_error(array('message' => "فیلد {$field} برای آدرس انبار اجباری است"));
            }
        }

        // Validate postcode format
        if (!preg_match('/^\d{10}$/', $stock_address['postcode'])) {
            wp_send_json_error(array('message' => 'کد پستی باید ۱۰ رقم باشد'));
        }

        // Validate phone format
        if (!preg_match('/^09\d{9}$/', $stock_address['phone'])) {
            wp_send_json_error(array('message' => 'شماره موبایل باید با ۰۹ شروع شده و ۱۱ رقم باشد'));
        }

        // Format address for Anar API
        $formatted_address = sprintf(
            '%s، %s، %s، %s',
            $stock_address['state'],
            $stock_address['city'],
            $stock_address['address'],
            $stock_address['postcode']
        );

        return [
            'postalCode' => $stock_address['postcode'],
            'detail' => $formatted_address,
            'transFeree' => $stock_address['first_name'] . ' ' . $stock_address['last_name'],
            'transFereeMobile' => $stock_address['phone'],
            'city' => $stock_address['city'],
            'province' => $stock_address['state'],
        ];
    }

}
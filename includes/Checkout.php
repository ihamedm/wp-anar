<?php
namespace Anar;

use Anar\Core\Logger;

class Checkout {

    private const ANAR_SHIPPING_META = '_anar_shipping_data';
    private const ANAR_ORDER_META = '_is_anar_order';
    private const ANAR_PRODUCT_META = '_anar_products';

    private $logger;

    protected static $instance;

    public static function get_instance() {
        if ( ! isset( self::$instance ) ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function __construct() {
        $this->logger = new Logger();

        // Checkout Customization
        add_action( 'woocommerce_review_order_before_shipping', [$this, 'display_anar_products_shipping'] , 99);
        add_filter( 'woocommerce_shipping_package_name', [$this, 'prefix_shipping_package_name_other_products'] );

        add_action( 'woocommerce_cart_calculate_fees', [$this, 'calculate_total_shipping_fee'] );
        add_action( 'woocommerce_before_calculate_totals', [$this, 'check_for_cart_products_types']);

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
        // Ensure shipping calculation runs after session update
        add_action('woocommerce_after_checkout_form', [$this, 'add_autofill_handler']);
    }

    private function log($message, $level = 'info') {
        $this->logger->log($message, 'checkout', $level);
    }

    public function display_anar_products_shipping() {

        try {
            // Check if the billing city and state are filled
            $billing_country = WC()->customer->get_billing_country();
            $billing_state = WC()->customer->get_billing_state();
            $billing_state_name = WC()->countries->states[$billing_country][$billing_state] ?? '';
            $billing_city = WC()->customer->get_billing_city();


            // Check if city or state is empty
            if (empty($billing_city) || empty($billing_state_name)) {
                $billing_state_name = 'تهران';
                $billing_city = 'تهران';
            }


            global $woocommerce;
            $cart_items = $woocommerce->cart->get_cart();
            $ship = [];

            // Retrieve the has_standard_product and has_anar_product values from the session
            $has_standard_product = WC()->session->get('has_standard_product');
            $has_anar_product = WC()->session->get('has_anar_product');

            foreach ($cart_items as $item => $values) {
                $_product = wc_get_product($values['data']->get_id());
                $product_parent_id = $_product->get_parent_id();

                // Retrieve the meta values for both the product and its parent
                $anar_meta = $product_parent_id == 0
                    ? get_post_meta($_product->get_id(), self::ANAR_PRODUCT_META, true)
                    : get_post_meta($product_parent_id, self::ANAR_PRODUCT_META, true);

                // If it's an Anar product, process its shipping data
                if ($anar_meta) {
                    // Anar product

                    if($_product->is_type('simple')){
                        $anar_shipment_data = ProductData::get_anar_product_shipments($_product->get_id());
                    }else{
                        $anar_shipment_data = ProductData::get_anar_product_shipments($_product->get_parent_id());
                    }

                    if ($anar_shipment_data && count($anar_shipment_data) > 0) {
                        $shipment_types_to_display = [];

                        // Check if the customer is in the same city and state as the dropshipper
                        if (
                            $billing_state_name === $anar_shipment_data['shipmentsReferenceState'] &&
                            $billing_city === $anar_shipment_data['shipmentsReferenceCity']
                        ) {
                            $shipment_types_to_display = ['insideShopCity'];
                        } elseif ($billing_state_name === $anar_shipment_data['shipmentsReferenceState']) {
                            $shipment_types_to_display = ['insideShopState'];
                        } else {
                            $shipment_types_to_display = ['otherStates'];
                        }

                        // Convert shipmentsReferenceId to string if it's an array
                        $shipmentsReferenceId = is_array($anar_shipment_data['shipmentsReferenceId'])
                            ? implode('-', $anar_shipment_data['shipmentsReferenceId'])
                            : $anar_shipment_data['shipmentsReferenceId'];

                        // Filter the shipments based on the types to display
                        foreach ($anar_shipment_data['shipments'] as $shipment) {
                            $shipment_deliveries = [];

                            // only show shipments that we have based on customer location
                            if ($shipment->type == 'allCities' && $shipment->active) {
                                $shipment_deliveries = $shipment->delivery;
                            }elseif (in_array($shipment->type, $shipment_types_to_display) && $shipment->active){
                                $shipment_deliveries = $shipment->delivery;
                            }

                            // prepare shipments to generate radio fields later
                            foreach ($shipment_deliveries as $delivery) {
                                if ($delivery->active) {

                                    // store products with same shipmentsReferenceId together on $ship
                                    if (isset($ship[$shipmentsReferenceId])) {
                                        $ship[$shipmentsReferenceId]['names'][] = $_product->get_id();
                                    } else {
                                        $ship[$shipmentsReferenceId] = [
                                            'delivery' => [],
                                            'names' => [],
                                        ];
                                        $ship[$shipmentsReferenceId]['names'][] = $_product->get_id();
                                    }

                                    // Add unique delivery to the list
                                    $delivery_key = $delivery->_id;
                                    if (!isset($ship[$shipmentsReferenceId]['delivery'][$delivery_key])) {
                                        $ship[$shipmentsReferenceId]['delivery'][$delivery_key] = [
                                            'name' => $delivery->deliveryType,
                                            'estimatedTime' => $delivery->estimatedTime,
                                            'price' => $delivery->price,
                                            'freeConditions' => $delivery->freeConditions ?? [],
                                        ];

                                        // Save relevant shipment data to the session
                                        $shipment_data[] = [
                                            'shipmentId' => $shipment->_id, // Correctly save the shipment ID
                                            'deliveryId' => $delivery->_id, // Use the delivery ID from the active delivery
                                            'shipmentsReferenceId' => $shipmentsReferenceId,
                                        ];

                                    }

                                }
                            }

                        }

                    }
                }
            }

            // Store shipment data in session for later use [save on order meta, need for call anar order create API]
            if(isset($shipment_data))
                WC()->session->set('anar_shipment_data', $shipment_data);

            // Display Anar shipping methods if Anar products are present
            if ($has_anar_product) {
                $pack_index = 0;
                $anar_shipments_alert = '';
                if(count($ship) > 1){
                    echo '<tr class="anar-shipments-user-notice"><td><p>کالاهای انتخابی شما از چند انبار مختلف ارسال می شوند.</p></td></tr>';
                }
                foreach ($ship as $key => $v) {
                    $pack_index++;
                    $product_uniques = array_unique($v['names']);

                    $product_list_markup = sprintf('<div class="package-title">
                            <div class="icon">
                                <svg  xmlns="http://www.w3.org/2000/svg"  width="24"  height="24"  viewBox="0 0 24 24"  fill="none"  stroke="currentColor"  stroke-width="2"  stroke-linecap="round"  stroke-linejoin="round"  class="icon"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><path d="M7 17m-2 0a2 2 0 1 0 4 0a2 2 0 1 0 -4 0" /><path d="M17 17m-2 0a2 2 0 1 0 4 0a2 2 0 1 0 -4 0" /><path d="M5 17h-2v-4m-1 -8h11v12m-4 0h6m4 0h2v-6h-8m0 -5h5l3 5" /><path d="M3 9l4 0" /></svg>
                            </div>
                            <div class="text">
                                <div>مرسوله %d <span class="chip">%s کالا</span></div>
                            </div>
                        </div>',
                        $pack_index,
                        count($product_uniques)
                    );

                    // collect package images
                    $product_list_markup .= '<ul class="package-items">';
                    foreach ($product_uniques as $item) {
                        $product_title = get_the_title($item);

                        if ($item) {
                            $product = wc_get_product($item);

                            // Check if the product is a variation
                            if ($product && $product->is_type('variation')) {
                                $parent_id = $product->get_parent_id(); // Get the parent product ID
                                $thumbnail_url = get_the_post_thumbnail_url($parent_id); // Get the parent product thumbnail
                            } else {
                                $thumbnail_url = get_the_post_thumbnail_url($item); // Get the thumbnail for the current product
                            }

                            $product_list_markup .= sprintf('<li><a class="awca-tooltip-on" href="%s" title="%s"><img src="%s" alt="%s"></a></li>',
                                get_permalink($item),
                                $product_title,
                                $thumbnail_url,
                                $product_title,
                            );
                        } else {
                            // If the product ID is not valid, just use the title without a link
                            $product_list_markup .= esc_html(get_the_title($item)) . ' , ';
                        }
                    }
                    $product_list_markup .= '</ul>';



                    $names = [];
                    $radio_data = [];
                    foreach ($v['delivery'] as $delivery_key => $delivery) {
                        $package_total = $this->calculate_package_total($product_uniques);
                        $is_free_shipping = false;
                        $original_price = $delivery['price'];
                        $original_estimate_time = $delivery['estimatedTime'];
//                        awca_log(print_r($delivery, true));
                        // Check free condition
                        if (isset($delivery['freeCondition']) && isset($delivery['freeCondition']['purchasesPrice'])){
                            if($package_total >= $delivery['freeCondition']['purchasesPrice']) {
                                $is_free_shipping = true;
                                $delivery['price'] = 0;
                                $delivery['estimatedTime'] = 'ارسال رایگان';

                            }
//                            awca_log("package_total:" . $package_total . " - " . $delivery['freeCondition']['purchasesPrice']);
                        }

                        $estimate_time_str = $delivery['estimatedTime'] ? ' (' . $delivery['estimatedTime'] . ')' : '';
                        $names[$delivery_key] = awca_translator($delivery['name']) . $estimate_time_str .' : ' . $delivery['price'] . ' ' . get_woocommerce_currency_symbol() ;
                        $radio_data[$delivery_key] = [
                            'label' => awca_translator($delivery['name']),
                            'estimated_time' => $delivery['estimatedTime'] ?? '',
                            'price' => awca_get_formatted_price($delivery['price']),
                        ];
                    }

                    // Get the chosen delivery option from the session or checkout value
                    $chosen = WC()->session->get('anar_delivery_option_' . $key);
                    $chosen = empty($chosen) ? WC()->checkout->get_value('anar_delivery_option_' . $key) : $chosen;

                    // If no option is chosen, set the first one as default
                    if (empty($chosen) && !empty($names)) {
                        $chosen = key($names); // Get the first key of the $names array
                    }

                    ?>
                    <tr class="anar-shipments-checkout">
                        <th>
                            <div class="anar-package-items-list">
                                <?php
                                echo $product_list_markup;
                                ?>
                            </div>
                        </th>
                        <td>
                            <?php $this->generate_delivery_option_field($key, $radio_data, $chosen );?>
                        </td>
                    </tr>
                    <?php
                }
            }else{
//                @todo show message 'fill form please'
            }
        } finally {
            //$this->calculate_total_shipping_fee();
        }

    }

    private function calculate_package_total($product_ids) {
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

    private function find_cart_item_by_product_id($product_id) {
        foreach (WC()->cart->get_cart() as $cart_item) {
            if ($cart_item['product_id'] == $product_id || $cart_item['variation_id'] == $product_id) {
                return $cart_item;
            }
        }
        return null;
    }


    public function calculate_total_shipping_fee() {

        // 1. First, ensure we have valid session data
        if (!WC()->session) {
            return;
        }
        $cart_items = WC()->cart->get_cart();
        $total_shipping_fee = 0;
        $processed_references = []; // Array to keep track of processed shipment references
        $has_standard_product = WC()->session->get('has_standard_product');

        // 2. Add validation for cart items
        if (empty($cart_items)) {
            return;
        }

        foreach ($cart_items as $cart_item_key => $cart_item) {
            $product_id = $cart_item['product_id'];
            $shipments_data = ProductData::get_anar_product_shipments($product_id);

            if ($shipments_data) {
                $shipmentsReferenceId = $shipments_data['shipmentsReferenceId'];
                // 3. Get selected option with fallback
                $selected_option = $this->get_selected_shipping_option($shipmentsReferenceId);
                //$selected_option = WC()->session->get('anar_delivery_option_' . $shipmentsReferenceId);

                // Check if this shipment reference has already been processed
                if ($selected_option && !in_array($shipmentsReferenceId, $processed_references)) {
                    // If a shipping option is selected, add its price to the total
                    if (isset($shipments_data['delivery'][$selected_option])) {
                        $total_shipping_fee += awca_convert_price_to_woocommerce_currency(floatval($shipments_data['delivery'][$selected_option]['price']));
                    }
                    // Mark this shipment reference as processed
                    $processed_references[] = $shipmentsReferenceId;
                }
            }
        }

        // 4. Add the shipping fee with proper validation
        if ($total_shipping_fee > 0) {
            $label = $has_standard_product
                ? 'مجموع حمل نقل سایر محصولات'
                : 'مجموع حمل نقل';

            // Remove existing shipping fees before adding new one
            //$this->remove_existing_shipping_fees();

            WC()->cart->add_fee($label, $total_shipping_fee);
        } else {
            if(ANAR_DEBUG)
                awca_log('No shipping fee to add');
        }
    }



    /**
     * New method to get selected shipping option with fallback
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

        return $selected_option;
    }


    public function check_for_cart_products_types() {
        $cart_items = $this->get_cart_items_safely();
        $has_standard_product = false;
        $has_anar_product = false;

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
        }

        // Store the results in session
        WC()->session->set('has_standard_product', $has_standard_product);
        WC()->session->set('has_anar_product', $has_anar_product);
    }


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



    public function save_checkout_delivery_choice_on_session_better($posted_data ) {
        parse_str($posted_data, $output);
        foreach ($output as $key => $value) {
            if (strpos($key, 'anar_delivery_option_') === 0) {
                WC()->session->set($key, $value);
            }
        }

        // Force recalculation after updating session
        WC()->cart->calculate_totals();
    }



    public function chosen_shipping_methods_when_no_standard_products()
    {
        $has_standard_product = WC()->session->get('has_standard_product');
        if (!$has_standard_product) {
            WC()->session->set('chosen_shipping_methods', ['free_shipping']);
        }
    }


    public function checkout_validations(){
        $chosen_shipping_methods = WC()->session->get('chosen_shipping_methods');
        $has_standard_product = WC()->session->get('has_standard_product');
        if(!$has_standard_product){
            if (empty($chosen_shipping_methods) || !in_array('free_shipping', $chosen_shipping_methods)) {
                wc_add_notice( __( 'Please select a shipping method.', 'woocommerce' ), 'error' );
            }
        }
    }


    public function enable_free_shiping_when_no_standard_products( $rates, $package ) {
        $has_standard_product = WC()->session->get('has_standard_product');

        // If no standard products, add an instance of Free Shipping method
        if (!$has_standard_product) {
            // Clear existing rates
            $rates = [];

            // Add a free shipping method
            $free_shipping_rate = new \WC_Shipping_Rate(
                'free_shipping',    // ID
                '',  // Label
                0,    // Cost
                [],
                'free_shipping',    // Method ID
                ''    // Instance ID
            );

            $rates['free_shipping'] = $free_shipping_rate;

            return $rates;
        }

        return $rates;
    }


    public function visually_hide_shipment_rates_no_standard_products() {
        if(is_checkout()) {
            $has_standard_product = WC()->session->get('has_standard_product');

            if (!$has_standard_product) {
                echo '<style>.woocommerce-shipping-totals.shipping {display: none;}</style>';
            }
        }
    }



    public function save_anar_data_on_order($order) {
        try {
            $this->log('-------------------- new order ------------------------- ', 'debug');
            $this->log('Starting save_anar_data_on_order on session for order #' . $order->get_id(), 'debug');

            // Retrieve the shipment data from the session
            $shipment_data = WC()->session->get('anar_shipment_data', []);

            if (empty($shipment_data)) {
                $this->log('No shipment data found in session for order #' . $order->get_id(), 'debug');
                return;
            }

            $this->log('Processing shipment data: ' . print_r($shipment_data, true), 'debug');

            // Initialize an array to hold the processed shipping data
            $shipping_data = [];

            // Loop through the shipment data stored in the session
            foreach ($shipment_data as $shipment) {
                // Get the chosen delivery option from the session
                $chosen = WC()->session->get('anar_delivery_option_' . $shipment['shipmentsReferenceId']);
                $this->log('Checking chosen delivery option for reference ' . $shipment['shipmentsReferenceId'] . ': ' . ($chosen ?: 'not set'), 'debug');

                if (!$chosen) {
                    $this->log('No delivery option chosen for shipment reference: ' . $shipment['shipmentsReferenceId'], 'debug');
                    continue;
                }

                // Proceed only if a delivery option is chosen
                if ($chosen === $shipment['deliveryId']) {
                    $this->log('Match found for delivery option: ' . $chosen, 'debug');
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
                $this->log('No shipping data collected, using fallback to first option', 'debug');
                $first_shipment = $shipment_data[0];
                $shipping_data[] = [
                    'shipmentId' => $first_shipment['shipmentId'],
                    'deliveryId' => $first_shipment['deliveryId'],
                    'shipmentsReferenceId' => $first_shipment['shipmentsReferenceId'],
                ];
                $this->log('Fallback shipping data: ' . print_r($shipping_data, true), 'debug');
            }

            // Save the shipping data in the order meta if there are any valid entries
            if (!empty($shipping_data)) {
                $this->log('Saving shipping data to order meta: ' . print_r($shipping_data, true), 'debug');

                update_post_meta($order->get_id(), self::ANAR_SHIPPING_META, $shipping_data);
                $order->update_meta_data(self::ANAR_SHIPPING_META, $shipping_data);

                update_post_meta($order->get_id(), self::ANAR_ORDER_META, 'anar');
                $order->update_meta_data(self::ANAR_ORDER_META, 'anar');

                $order->save();

                $this->log('Successfully saved Anar order meta data for order #' . $order->get_id(), 'debug');
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
     * This method used instead of default woocommerce input field generator method woocommerce_form_field()
     * because we need to customize the markup of generated field
     *
     * @param $input_key
     * @param $radio_data
     * @param $chosen
     * @return string
     */
    public function generate_delivery_option_field($input_key, $radio_data, $chosen) {
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
        echo $output;
    }


    /**
     * Add JavaScript to handle autofill events
     */
    public function add_autofill_handler() {
        ?>
        <script type="text/javascript">
            jQuery(function($) {
                // Handle Chrome autofill
                $('form.checkout').on('change', 'input, select', function() {
                    // Small delay to ensure autofill is complete
                    setTimeout(function() {
                        $('body').trigger('update_checkout');
                    }, 2000);
                });
            });
        </script>
        <?php
    }

    /**
     * Handle autofill updates
     */
    public function handle_autofill_update() {
        // Force recalculation of totals
        WC()->cart->calculate_totals();
    }

    private function get_session_data($key, $default = null) {
        return WC()->session ? WC()->session->get($key, $default) : $default;
    }

    private function set_session_data($key, $value) {
        if (WC()->session) {
            WC()->session->set($key, $value);
        }
    }

    private function get_product_safely($product_id) {
        $product = wc_get_product($product_id);
        return $product instanceof \WC_Product ? $product : null;
    }

    private function get_cart_items_safely() {
        return WC()->cart ? WC()->cart->get_cart() : [];
    }

}

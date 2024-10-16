<?php
namespace Anar;

class Checkout {

    public function __construct() {

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


    }


    public function display_anar_products_shipping() {
        // Check if the billing city and state are filled
        $billing_country = WC()->customer->get_billing_country();
        $billing_state = WC()->customer->get_billing_state();
        $billing_state_name = WC()->countries->states[$billing_country][$billing_state] ?? '';
        $billing_city = WC()->customer->get_billing_city();


        // Check if city or state is empty
        if (empty($billing_city) || empty($billing_state_name)) {
            return;
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
                ? get_post_meta($_product->get_id(), '_anar_products', true)
                : get_post_meta($product_parent_id, '_anar_products', true);

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
                        if (in_array($shipment->type, $shipment_types_to_display)) {
                            foreach ($shipment->delivery as $delivery) {
                                if ($delivery->active) { // Check if the delivery method is active

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
        }

        // Store shipment data in session for later use [save on order meta, need for call anar order create API]
        if(isset($shipment_data))
            WC()->session->set('anar_shipment_data', $shipment_data);

//        awca_log('$shipment_data' . print_r($shipment_data, true));
//        awca_log('$ship' . print_r($ship, true));


        // Display Anar shipping methods if Anar products are present
        if ($has_anar_product) {
            $pack_index = 0;
            foreach ($ship as $key => $v) {
                $pack_index++;
                $product_uniques = array_unique($v['names']);

                $product_list_markup = sprintf('<div class="package-title">
                            <div class="icon">
                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-6">
                                  <path stroke-linecap="round" stroke-linejoin="round" d="M8.25 18.75a1.5 1.5 0 0 1-3 0m3 0a1.5 1.5 0 0 0-3 0m3 0h6m-9 0H3.375a1.125 1.125 0 0 1-1.125-1.125V14.25m17.25 4.5a1.5 1.5 0 0 1-3 0m3 0a1.5 1.5 0 0 0-3 0m3 0h1.125c.621 0 1.129-.504 1.09-1.124a17.902 17.902 0 0 0-3.213-9.193 2.056 2.056 0 0 0-1.58-.86H14.25M16.5 18.75h-2.25m0-11.177v-.958c0-.568-.422-1.048-.987-1.106a48.554 48.554 0 0 0-10.026 0 1.106 1.106 0 0 0-.987 1.106v7.635m12-6.677v6.677m0 4.5v-4.5m0 0h-12" />
                                </svg>
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
                    $estimate_time_str = $delivery['estimatedTime'] ? ' (' . $delivery['estimatedTime'] . ')' : '';
                    $names[$delivery_key] = awca_translator($delivery['name']) . $estimate_time_str .' : ' . $delivery['price'] . ' ' . get_woocommerce_currency_symbol() ;
                    $radio_data[$delivery_key] = [
                        'label' => awca_translator($delivery['name']),
                        'estimated_time' => $delivery['estimatedTime'] ?? '',
                        'price' => awca_convert_price_to_woocommerce_currency($delivery['price']) . ' ' . get_woocommerce_currency_symbol(),
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
                        <?php
//                        woocommerce_form_field('anar_delivery_option_' . $key, array(
//                            'type' => 'radio',
//                            'required' => true,
//                            'class' => array('form-row-wide', 'update_totals_on_change'),
//                            'options' => $names,
//                        ), $chosen);

                        ?>

                        <?php $this->generate_delivery_option_field($key, $radio_data, $chosen );?>
                    </td>
                </tr>
                <?php
            }
        }

    }


    public function calculate_total_shipping_fee() {
        $cart_items = WC()->cart->get_cart();
        $total_shipping_fee = 0;
        $processed_references = []; // Array to keep track of processed shipment references
        $has_standard_product = WC()->session->get('has_standard_product');

        foreach ($cart_items as $cart_item_key => $cart_item) {
            $product_id = $cart_item['product_id'];
            $shipments_data = ProductData::get_anar_product_shipments($product_id);

            if ($shipments_data) {
                $shipmentsReferenceId = $shipments_data['shipmentsReferenceId'];
                $selected_option = WC()->session->get('anar_delivery_option_' . $shipmentsReferenceId);

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

        // Add the total shipping fee to the cart
        if ($total_shipping_fee > 0) {

            if($has_standard_product){
                $label = 'مجموع حمل نقل سایر محصولات';
            }else{
                $label = 'مجموع حمل نقل';
            }

            WC()->cart->add_fee($label, $total_shipping_fee);
        }
    }



    public function check_for_cart_products_types() {
        $cart_items = WC()->cart->get_cart();
        $has_standard_product = false;
        $has_anar_product = false;

        foreach ($cart_items as $item => $values) {
            $_product = wc_get_product($values['data']->get_id());
            $product_parent_id = $_product->get_parent_id();

            // Check for Anar products
            $anar_meta = $product_parent_id == 0
                ? get_post_meta($_product->get_id(), '_anar_products', true)
                : get_post_meta($product_parent_id, '_anar_products', true);

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


    public function save_checkout_delivery_choice_on_session( $posted_data ) {
        awca_log('save_checkout_delivery_choice_on_session');
        parse_str( $posted_data, $output );
        awca_log(print_r($output, true));
        $resShip = ProductData::get_all_products_shipments_ref();
        awca_log('$resShip: ' . print_r($resShip, true));
        foreach ($resShip as $key => $v) {
            if ( isset( $output['anar_delivery_option_'.$key] ) ){
                WC()->session->set( 'anar_delivery_option_'.$key, $output['anar_delivery_option_'.$key] );
            }
        }

        awca_log('session: ' . print_r(WC()->session->get_session(get_current_user_id()), true));
    }

    public function save_checkout_delivery_choice_on_session_better($posted_data ) {
        parse_str( $posted_data, $output );
        foreach ($output as $key => $value) {
            // Check if the key starts with "anar_delivery_option_"
            if (strpos($key, 'anar_delivery_option_') === 0) {
                // Set the key-value pair in the session
                WC()->session->set($key, $value);
            }
        }
    }



    public function chosen_shipping_methods_when_no_standard_products()
    {
        $has_standard_product = WC()->session->get('has_standard_product');
        if (!$has_standard_product) {
            WC()->session->set('chosen_shipping_methods', ['free_shipping']);
        }
    }

    public function checkout_fields_validations() {
        // Loop through the session stored delivery options to validate each one
        $has_anar_product = WC()->session->get('has_anar_product');

        if ($has_anar_product) {
            // Check if any Anar delivery option is selected
            $cart_items = WC()->cart->get_cart();
            foreach ($cart_items as $item => $values) {
                $product_id = $values['product_id'];
                $shipments_ref_id = get_post_meta($product_id, '_anar_shipments_ref', true)['shipmentsReferenceId'];

                // Check if the delivery option for this reference ID is set
                $chosen = WC()->session->get('anar_delivery_option_' . $shipments_ref_id);
                if (empty($chosen)) {
                    wc_add_notice('فیلدهای حمل و نقل محصولات را بطور کامل انتخاب نکرده اید', 'error');
                    break; // Exit the loop after adding the notice
                }
            }
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
        // Retrieve the shipment data from the session
        $shipment_data = WC()->session->get('anar_shipment_data', []);

        // Initialize an array to hold the processed shipping data
        $shipping_data = [];

        // Loop through the shipment data stored in the session
        foreach ($shipment_data as $shipment) {
            // Get the chosen delivery option from the session
            $chosen = WC()->session->get('anar_delivery_option_' . $shipment['shipmentsReferenceId']);

            // Proceed only if a delivery option is chosen
            if ($chosen && $chosen === $shipment['deliveryId']) {
                // Save relevant shipment data
                $shipping_data[] = [
                    'shipmentId' => $shipment['shipmentId'], // Use the shipment ID from session data
                    'deliveryId' => $shipment['deliveryId'], // Use the delivery ID from session data
                    'shipmentsReferenceId' => $shipment['shipmentsReferenceId'], // Use the shipments reference ID
                ];
            }
        }

        // Save the shipping data in the order meta if there are any valid entries
        if (!empty($shipping_data)) {
            update_post_meta($order->get_id(), '_anar_shipping_data', $shipping_data);
            $order->update_meta_data('_anar_shipping_data', $shipping_data);

            update_post_meta($order->get_id(), '_is_anar_order', 'anar');
            $order->update_meta_data('_is_anar_order', 'anar');
        }

//        awca_log('filtered by chosen $shipping_data' . print_r($shipping_data, true)); // Log the final shipping data for debugging

        $order->save();
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

}
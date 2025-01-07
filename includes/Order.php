<?php
namespace Anar;

class Order {

    public function __construct() {
        //add_action('woocommerce_order_status_completed', [$this, 'create_anar_order'], 10, 2);
        add_action( 'add_meta_boxes', [$this, 'anar_order_meta_box'] );
        add_action('woocommerce_order_details_after_order_table', [$this, 'display_anar_order_details_front'], 10, 1);

        add_action('wp_ajax_awca_create_anar_order_ajax', [$this, 'awca_create_anar_order_ajax']);
        add_action('wp_ajax_nopriv_awca_create_anar_order_ajax', [$this, 'awca_create_anar_order_ajax']);

        add_action('wp_ajax_awca_fetch_order_details_ajax', [$this, 'awca_fetch_order_details_ajax']);
        add_action('wp_ajax_nopriv_awca_fetch_order_details_ajax', [$this, 'awca_fetch_order_details_ajax']);

        add_action('wp_ajax_awca_fetch_order_details_public_ajax', [$this, 'awca_fetch_order_details_public_ajax']);
        add_action('wp_ajax_nopriv_awca_fetch_order_details_public_ajax', [$this, 'awca_fetch_order_details_public_ajax']);

        // @todo show order shipments data [need when anar order not created from wordpress]
        //add_action( 'woocommerce_admin_order_data_after_billing_address', [$this, 'display_custom_option_in_admin'], 10, 1 );
        //add_filter('woocommerce_get_order_item_totals', [$this, 'filter_fee_and_shipment_name_in_order_details'], 10, 3);

    }

    public function is_anar_order($order) {
        if(awca_is_hpos_enable()){
            $_is_anar_order = $order->get_meta('_is_anar_order', true);
            $order_ID = $order->get_id();
        }else{
            $_is_anar_order = get_post_meta($order->ID, '_is_anar_order', true);
            $order_ID = $order->ID;
        }

        return $_is_anar_order ? $order_ID : false;
    }

    public function get_order_anar_data($order) {
        if(awca_is_hpos_enable()){
            $_anar_order_data = $order->get_meta('_anar_order_data', true);
        }else{
            $_anar_order_data = get_post_meta($order->ID, '_anar_order_data', true);
        }
        return $_anar_order_data;
    }

    public function create_anar_order($order_id) {
        $order = wc_get_order($order_id);

        if (awca_is_hpos_enable()) {
            $anar_order_number = $order->get_meta('_anar_order_group_id', true);
        } else {
            $anar_order_number = get_post_meta($order_id, '_anar_order_group_id', true);
        }
        if($anar_order_number){
            awca_log('anar order created before successfully: #' . $anar_order_number);
            return false;
        }


        // Prepare the data for the first API call
        $items = [];
        foreach ($order->get_items() as $item_id => $item) {
            $anar_variation_id = $this->get_variation_id($item);

            if($anar_variation_id){
                $items[] = [
                    'variation' => $anar_variation_id,
                    'amount' => $item->get_quantity(),
                    'info' => []
                ];
            }

        }

        $prepare_data = [
            'items' => $items
        ];

//        awca_log('prepared data: #' . print_r($prepare_data, true));

        // First API call to prepare the order
        $prepare_response = $this->call_api('https://api.anar360.com/wp/orders/prepare', $prepare_data);

        if (is_wp_error($prepare_response)) {
            $order->add_order_note('خطا در برقراری ارتباط با سرور انار.');
            return false;
        }

        // Decode & Return the response data if everything is okay
        $prepare_response = json_decode(wp_remote_retrieve_body($prepare_response), true);

        if (isset($prepare_response['statusCode']) && $prepare_response['statusCode'] !== 200) {
            // Handle error
            $order->add_order_note('سفارش از سمت انار تایید نشد. خطا: ' . $prepare_response['message']);
            awca_log(print_r($prepare_response, true));
            return false;
        } else {
            $order->add_order_note('سفارش از سمت انار تایید شد. تلاش برای ثبت سفارش جدید در پنل انار ...');
            awca_log('prepare success, total price is: ' . print_r($prepare_response['sum'], true));
        }

        // Prepare data for the second API call
        $address = $order->get_address('billing'); // Get shipping address

        $create_data = [
            'type' => 'retail', // Adjust if necessary
            'items' => $items,
            'address' => [
                'postalCode' => $address['postcode'],
                'detail' => $address['state'] .' ' . $address['city'] .' '. $address['address_1'] .' '. $address['address_2'],
                'transFeree' => $address['first_name'] . ' ' . $address['last_name'],
                'transFereeMobile' => $address['phone'],
            ],
            'shipments' => $this->prepare_shipments($order) // Prepare shipment data from response
        ];

        awca_log('prepared data: #' . print_r($create_data, true));
        // Second API call to create the order
        $create_response = $this->call_api('https://api.anar360.com/wp/orders/', $create_data);

        if (is_wp_error($create_response)) {
            $order->add_order_note('خطا در برقراری ارتباط با سرور انار.');
            return false;
        }

        // Decode the response body
        $create_response = json_decode(wp_remote_retrieve_body($create_response), true);
        awca_log('$create_response : #' . print_r($create_response, true));


        if (!isset($create_response['success']) || !$create_response['success']) {
            // Handle error
            if(isset($create_response['data']['message'])){
                $error_message = $create_response['data']['message'];
            }elseif(isset($create_response['message'])){
                $error_message = $create_response['message'];
            }else{
                $error_message = 'Unknown error';
            }

            $order->add_order_note('ساخت سفارش در پنل انار انجام نشد: ' . $error_message);
            return false;
        }

        // Save useful data in order meta
        $anar_order_data = [];
        foreach($create_response['result'] as $result){
            $anar_order_data[] = [
                "_id"           => $result["_id"],
                "groupId"       => $result["groupId"],
                "orderNumber"   => $result["orderNumber"]
            ];
        }
        $order->update_meta_data('_anar_order_data', $anar_order_data); // Save Anar order ID
        $order->update_meta_data('_anar_order_group_id', $create_response['result'][0]['groupId']); // Save Anar order number
        $order->add_order_note('سفارش در پنل انار با موفقیت ایجاد شد. #' . $create_response['result'][0]['groupId']);
        $order->save();

        // update anar unpaid orders on wpdb to show alert
        (new Payments())->count_unpaid_orders_count();

        return true;
    }


    private function call_api($url, $data) {
        return ApiDataHandler::postAnarApi($url, $data);
    }


    private function get_variation_id($item) {
        // Get the product object
        $product = $item->get_product();

        // Check if the product is a variation
        if ($product->is_type('variation')) {
            // For variable products, get the variation SKU
            return get_post_meta($product->get_id(), '_anar_sku', true);
        } else {
            // For simple products, get the variant SKU
            return get_post_meta($product->get_id(), '_anar_variant_id', true);
        }
    }


    private function prepare_shipments($order) {
        $shipments = [];

        // Check if HPOS is enabled
        if (awca_is_hpos_enable()) {
            $shipping_data = $order->get_meta('_anar_shipping_data', true);
            $customer_note = $order->get_customer_note();
        } else {
            $shipping_data = get_post_meta($order->ID, '_anar_shipping_data', true);
            $customer_note = $order->get_customer_note();
        }

        foreach ($shipping_data as $shipping) {
            // Assuming each shipping entry has the necessary keys
            $shipments[] = [
                'shipmentId' => $shipping['shipmentId'], // Use the shipmentId from saved data
                'deliveryId' => $shipping['deliveryId'], // Use the delivery ID from saved data
                'shipmentsReferenceId' => $shipping['shipmentsReferenceId'], // Reference ID for the shipment
                'description' => $customer_note, // Add the customer note as the description
            ];
        }

        return $shipments;
    }


    public function anar_order_meta_box() {

        if(isset($_GET['post'])){
            $order_id = $_GET['post'];
        }elseif(isset($_GET['id'])){
            $order_id = $_GET['id'];
        }else{
            $order_id = false;
        }

        // don't show meta box for new orders
        if(!$order_id) {
            return false;
        }

        // don't show meta box if is not anar order
        $order = wc_get_order($order_id);
        if(!$this->is_anar_order($order)){
            return false;
        }

        // check for anar order
        $screen = awca_is_hpos_enable() ? wc_get_page_screen_id( 'shop-order' ) : 'shop_order';

        add_meta_box(
            'anar_order_meta_box',            // Unique ID
            'انار',                  // Box title
            [$this, 'anar_order_meta_box_html'],       // Content callback
            $screen,                         // Post type
            'side',                            // Context (side, advanced, etc.)
            'high'                          // Priority (default, high, low, etc.)
        );
    }


    public function anar_order_meta_box_html($order) {

        $order_ID = $this->is_anar_order($order);

        if($order_ID):
            if($this->get_order_anar_data($order)):?>
                <div class="anar-text" id="anar-order-details" data-order-id="<?php echo $order_ID;?>">
                    <div class="awca-loading-message-spinner">
                        در حال دریافت اطلاعات...
                        <svg class="spinner-loading" width="24px" height="24px" viewBox="0 0 66 66" xmlns="http://www.w3.org/2000/svg">
                            <circle class="path" fill="none" stroke-width="6" stroke-linecap="round" cx="33" cy="33" r="30"></circle>
                        </svg>
                    </div>
                </div>


            <?php else:?>
                <div id="awca-custom-meta-box-container">
                    <p class="alert alert-warning">این سفارش هنوز در پنل انار شما ثبت نشده است.</p>
                    <button id="awca-create-anar-order" class="awca-primary-btn meta-box-btn" data-order-id="<?php echo $order_ID;?>">
                        ثبت این سفارش در پنل انار
                        <svg class="spinner-loading" width="24px" height="24px" viewBox="0 0 66 66" xmlns="http://www.w3.org/2000/svg">
                            <circle class="path" fill="none" stroke-width="6" stroke-linecap="round" cx="33" cy="33" r="30"></circle>
                        </svg>
                    </button>

                    <?php wp_nonce_field( 'awca_nonce', 'awca_nonce_field' ); ?>
                </div>
            <?php
            endif;endif;
    }


    public function awca_create_anar_order_ajax(){

        if ( !isset( $_POST['order_id'] ) ) {
            wp_send_json_error( array( 'message' => 'order_id required') );
        }

        if($this->create_anar_order($_POST['order_id'])){
            wp_send_json_success(array('message' => 'سفارش با موفقیت در پنل انار ثبت شد.'));
        }else{
            wp_send_json_error(array('message' => 'خطا در ثبت ثبت سفارش در پنل انار.'));
        }

    }


    public function awca_fetch_order_details_ajax() {

        if ( !isset( $_POST['order_id'] ) ) {
            wp_send_json_error( array( 'message' => 'order_id required') );
        }
        $order_id = $_POST['order_id'];
        $order = wc_get_order($_POST['order_id']);
        $total_payable = 0;
        $total_reseller_share = 0;

        $anar_order_data = $order->get_meta('_anar_order_data', true);
        if(isset($anar_order_data) && count($anar_order_data) > 0){
            $output = '';
            foreach($anar_order_data as $order_index => $order){

                $url = "https://api.anar360.com/wp/orders/" . $order['_id'];
                $response = wp_remote_get($url, [
                    'headers' => [
                        'Authorization' => awca_get_activation_key()
                    ],
                    'timeout' => 300,
                ]);

                if (is_wp_error($response)) {
                    $message = $response->get_error_message();
                    wp_send_json_error(["message" => $message]);
                }

                $response_body = json_decode(wp_remote_retrieve_body($response), true);

                if ($response_body['success']) {
                    $package = $response_body['result'];
                    $package_number = $order_index + 1 ; // Start numbering from 1
                    $total_payable += $package['price']['payable'];
                    $total_reseller_share += $package['price']['resellerShare'];
                    $all_item_titles = [];
                    // Collect item titles
                    foreach ($package['items'] as $item) {

                        $paymentStatus = $package['paymentStatus'];
                        $groupId = $package['groupId'];

                        $item_wc_id = ProductData::get_product_variation_by_anar_sku($item['product']);
                        // Check if the product ID is valid and get the product link
                        if ($item_wc_id) {
                            $product_link = get_permalink($item_wc_id); // Get the product link
                            $all_item_titles[] = sprintf('<a href="%s">%s</a>', esc_url($product_link), esc_html($item['title']));
                        } else {
                            // If the product ID is not valid, just use the title without a link
                            $all_item_titles[] = esc_html($item['title']);
                        }

                    }
                    $currency = get_woocommerce_currency_symbol(get_woocommerce_currency());

                    $output .= sprintf(
                        '
                        <div class="anar-package-data close">
                        <header>
                            <strong>%s</strong>
                            <span>%s</span>
                            <spn class="toggle-handler"><span class="close">+</span><span class="open">-</span></span>
                        </header>
                        
                        <ul>
                          
                            <li><b>شماره مرسوله: </b>%s</li>
                            <li><b>آیتم ها(%s): </b>%s</li>
                            
                            <li>
                                <ul style="background:#eee; border-radius:5px; padding:8px;">
                                    <li><b>جمع کالاها: </b>%s</li>
                                    <li><b>سهم شما: </b>%s</li>
                                    <li><b>هزینه ارسال: </b>%s</li>
                                    <li><b>قابل پرداخت: </b>%s</li>    
                                </ul>
                           
                            </li>
                            <li><b>وضعیت سفارش: </b>%s</li>
                            <li><b>وضعیت پرداخت: </b>%s</li>
                            <li><b>شیوه ارسال: </b>%s</li>
                            <li><b>زمان تقریبی ارسال: </b>%s</li>
                            <li><b>کد رهگیری: </b>%s</li>
                            <li><b>تاریخ ثبت: </b>%s</li>
                            <li><b>شناسه یکتای سفارش: </b>%s</li>
                            <li><b>شناسه انار: </b>%s</li>
                        </ul>
                        </div>
                        ',
                        sprintf('<b style="color:red">مرسوله %d</b>', $package_number),
                        $package['orderNumber'],
                        $package['orderNumber'],
                        count($all_item_titles),
                        implode(', ', $all_item_titles),
                        awca_get_formatted_price($package['price']['items']),
                        awca_get_formatted_price($package['price']['resellerShare']),
                        awca_get_formatted_price($package['price']['delivery']),
                        awca_get_formatted_price($package['price']['payable']),
                        awca_translator($package['status']),
                        awca_translator($package['paymentStatus']),
                        awca_translator($package['delivery']['deliveryType']),
                        $package['delivery']['estimatedTime'],
                        $package['trackingNumber'],
                        date_i18n('j F Y ساعت H:i', strtotime($package['createdAt'])),
                        $order_id,
                        $package['_id'],

                    );

                } else {
                    $message =  $response_body['message'] ?? 'مشکلی در دریافت اطلاعات سفارش از انار بوجود آمد.';
                    wp_send_json_error(["message" => $message]);
                }
            }// end loop orders



            // if order unpaid we need to create payment modal and button
            if(isset($groupId) && isset($paymentStatus) && $paymentStatus == 'unpaid'){

                $output .= sprintf('<p><strong>قابل پرداخت : %s</strong></p>', awca_get_formatted_price($total_payable - $total_reseller_share));

                $output .= sprintf(
                    '<div style="display: flex;flex-direction: column; ">
                                         <p class="alert alert-warning">این سفارش هنوز به انار پرداخت نشده است. انار موجودی این کالا را رزرو نمی کند</p>
                                         <a id="pay-order-btn" class="awca-primary-btn" target="_blank" href="https://anar360.com/payment/order/%s/pay?type=retail&callback=%s">پرداخت آنلاین</a>
                                    </div>',
                    $groupId,
                    rawurlencode(admin_url('post.php?post='.$order_id.'action=edit'))
                );
                $output .= '
                     <div class="modal micromodal-slide" id="order-payment-modal"  aria-hidden="true">
            <div class="modal__overlay" tabindex="-1" data-micromodal-close>
                <div class="modal__container" role="dialog" aria-modal="true" aria-labelledby="modal-1-title">
                    <header class="modal__header">
                        <strong class="modal__title">
                            تسویه حساب
                        </strong>
                        <button class="modal__close" aria-label="Close modal" data-micromodal-close></button>
                    </header>
                    <main class="modal__content" >
                        <p>
                        این سفارش در وضعیت <strong style="color:#e11c47">پرداخت نشده</strong> می باشد. با توجه به حجم سفارشات در انار لازم است هرچه سریعتر نسبت به پرداخت اقدام فرمایید تا موجودی اقلام سفارش برای شما رزرو بماند.
                        </p>
                        <p><strong>قابل پرداخت : '. awca_get_formatted_price($total_payable - $total_reseller_share) .'</strong></p>
                    </main>
                    <footer class="modal__footer">
                        <a id="pay-order-btn" class="awca-primary-btn" target="_blank" href="https://anar360.com/payment/order/'.$groupId.'/pay?type=retail&callback='.rawurlencode(admin_url('post.php?post='.$order_id.'action=edit')).'">پرداخت آنلاین</a>
                    </footer>
                </div>
            </div>
        </div>';

            }


            $message = "اطلاعات سفارش از انار دریافت شد.";
            wp_send_json_success(['message' => $message, "output" => $output, 'paymentStatus'=> $package['paymentStatus'] ?? '']);
        }else{
            wp_send_json_error(["message" => "اطلاعات سفارش انار معتبر نیست!"]);
        }

    }


    public function awca_fetch_order_details_public_ajax() {

        if ( !isset( $_POST['order_id'] ) ) {
            wp_send_json_error( array( 'message' => 'order_id required') );
        }
        $order_id = $_POST['order_id'];
        $order = wc_get_order($_POST['order_id']);

        $anar_order_data = $order->get_meta('_anar_order_data', true);
        if(isset($anar_order_data) && count($anar_order_data) > 0){
            $output = '';
            foreach($anar_order_data as $order_index => $order){

                $url = "https://api.anar360.com/wp/orders/" . $order['_id'];
                $response = wp_remote_get($url, [
                    'headers' => [
                        'Authorization' => awca_get_activation_key()
                    ],
                    'timeout' => 300,
                ]);

                if (is_wp_error($response)) {
                    $message = $response->get_error_message();
                    wp_send_json_error(["message" => $message]);
                }

                $response_body = json_decode(wp_remote_retrieve_body($response), true);

                if ($response_body['success']) {

                    $package = $response_body['result'];
                    $package_number = $order_index + 1 ; // Start numbering from 1

                    // Collect items list
                    $product_list_markup = '<ul class="package-items">';
                    foreach ($package['items'] as $item) {
//                        $item_wc_id = awca_get_product_by_anar_variant_id($item['product']);
                        $item_wc_id = ProductData::get_product_variation_by_anar_sku($item['product']);
                        // Check if the product ID is valid and get the product link
                        if ($item_wc_id) {
                            $product_list_markup .= sprintf('<li><a class="awca-tooltip-on" href="%s" title="%s"><img src="%s" alt="%s"></a></li>',
                                get_permalink($item_wc_id),
                                $item['title'],
                                get_the_post_thumbnail_url($item_wc_id),
                                $item['title'],
                            );
                        } else {
                            // If the product ID is not valid, just use the title without a link
                            $product_list_markup .= esc_html($item['title']) . ' , ';
                        }
                    }
                    $product_list_markup .= '</ul>';

                    $output .= sprintf('<div class="package-title">
                            <div class="icon">
                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-6">
                                  <path stroke-linecap="round" stroke-linejoin="round" d="M8.25 18.75a1.5 1.5 0 0 1-3 0m3 0a1.5 1.5 0 0 0-3 0m3 0h6m-9 0H3.375a1.125 1.125 0 0 1-1.125-1.125V14.25m17.25 4.5a1.5 1.5 0 0 1-3 0m3 0a1.5 1.5 0 0 0-3 0m3 0h1.125c.621 0 1.129-.504 1.09-1.124a17.902 17.902 0 0 0-3.213-9.193 2.056 2.056 0 0 0-1.58-.86H14.25M16.5 18.75h-2.25m0-11.177v-.958c0-.568-.422-1.048-.987-1.106a48.554 48.554 0 0 0-10.026 0 1.106 1.106 0 0 0-.987 1.106v7.635m12-6.677v6.677m0 4.5v-4.5m0 0h-12" />
                                </svg>
                            </div>
                            <div class="text">
                                <div>مرسوله %d <span class="chip">%s کالا</span></div>
                            </div>
                        </div>',
                        $package_number,
                        count($package['items'])
                    );
                    $output .= sprintf('<ul class="package-data-list">
                            <li><b>شماره مرسوله: </b>%s</li>
                            <li>%s</li>
                            
                            <li>
                                <ul style="background:#eee; border-radius:5px; padding:8px;list-style:none">
                                    <li><b>جمع کالاها: </b>%s</li>
                                    <li><b>هزینه ارسال: </b>%s</li>
                                    <li><b>جمع کل: </b>%s</li>    
                                </ul>
                           
                            </li>
                            
                            <li><b>وضعیت سفارش: </b>%s</li>
                            <li><b>شیوه ارسال: </b>%s</li>
                            <li><b>زمان ارسال: </b>%s</li>
                            <li><b>کد رهگیری: </b>%s</li>
                            <li><b>تاریخ ثبت: </b>%s</li>
                        </ul>',
                        $package['orderNumber'],
                        $product_list_markup,

                        awca_get_formatted_price($package['price']['items']),
                        awca_get_formatted_price($package['price']['delivery']),
                        awca_get_formatted_price($package['price']['payable']),

                        awca_translator($package['status']),
                        awca_translator($package['delivery']['deliveryType']),
                        $package['delivery']['estimatedTime'],
                        $package['trackingNumber'],
                        date_i18n('j F Y ساعت H:i', strtotime($package['createdAt'])),
                    );

                    $output .= '</ul>';

                } else {
                    $message =  $response_body['message'] ?? 'مشکلی در دریافت اطلاعات سفارش بوجود آمد.';
                    wp_send_json_error(["message" => $message]);
                }
            }

            $message = "اظلاعات سفارش از دریافت شد.";
            wp_send_json_success(['message' => $message, "output" => $output]);
        }else{
            wp_send_json_error(["message" => "اطلاعات سفارش معتبر نیست!"]);
        }

    }


    function display_anar_order_details_front($order) {
        $_anar_order_data = $order->get_meta('_anar_order_data', true);
        $_is_anar_order = $order->get_meta('_is_anar_order', true);
        if($_is_anar_order && $_anar_order_data):?>
            <section class="woocommerce-order-details">
                <h2 class="woocommerce-order-details__title">وضعیت مرسوله ها</h2>
                <div id="anar-order-details-front" class="anar-package-items-list" data-order-id="<?php echo $order->get_id();?>">
                    <div class="awca-loading-message-spinner">
                        <span>در حال دریافت اطلاعات مرسوله ها ...</span>
                        <svg class="spinner-loading" width="24px" height="24px" viewBox="0 0 66 66" xmlns="http://www.w3.org/2000/svg">
                            <circle class="path" fill="none" stroke-width="6" stroke-linecap="round" cx="33" cy="33" r="30"></circle>
                        </svg>
                    </div>
                </div>
            </section>

        <?php endif;
    }


    public function display_custom_option_in_admin( $order ) {
        $delivery_option = get_post_meta( $order->get_id(), 'delivery_option', true );
        if ( $delivery_option ) {
            echo '<p><strong>' . __('Custom Option') . ':</strong> ' . ucfirst( str_replace( '_', ' ', $delivery_option ) ) . '</p>';
        }
    }


    public function filter_fee_and_shipment_name_in_order_details($total_rows, $order, $tax_display) {
        foreach ($total_rows as $key => $row) {
            if (str_contains($key, 'fee')) {
                $row['label'] = $row['label'] . "     (" . "وضعیت ارسال : در حال بررسی"  . ")      ";
                $total_rows[$key] = $row;
            }
            if ($key === 'shipping') {
                $row['label'] = $row['label'] . "     (" . "وضعیت ارسال : در حال بررسی"  . ")      ";
                $total_rows[$key] = $row;
            }
        }
        return $total_rows;
    }
}
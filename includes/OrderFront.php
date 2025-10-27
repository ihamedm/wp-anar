<?php
namespace Anar;

defined( 'ABSPATH' ) || exit;

class OrderFront {

    private static $instance;

    public static function get_instance() {
        if ( ! isset( self::$instance ) ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function __construct() {
        add_action('woocommerce_order_details_after_order_table', [$this, 'display_anar_order_details'], 10, 1);
        add_action('wp_ajax_nopriv_awca_fetch_order_details_public_ajax', [$this, 'fetch_anar_order_details']);
        add_action('wp_ajax_awca_fetch_order_details_public_ajax', [$this, 'fetch_anar_order_details']);
    }

    public function display_anar_order_details($order) {
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

    public function fetch_anar_order_details() {

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
                        'Authorization' => anar_get_saved_token()
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
                        $item_wc_id = ProductData::get_product_variation_by_anar_variation($item['product']);

                        // Check if the product ID is valid and get the product link
                        if ($item_wc_id && !is_wp_error($item_wc_id)) {
                            $product = wc_get_product($item_wc_id);
                            if ($product && $product->is_type('variation')) {
                                // Return the parent ID
                                $product_id = $product->get_parent_id();
                            }else{
                                $product_id = $product->get_id();
                            }

                            $product_list_markup .= sprintf('<li><a class="awca-tooltip-on" href="%s" title="%s"><img src="%s" alt="%s"></a></li>',
                                get_permalink($product_id),
                                $item['title'],
                                get_the_post_thumbnail_url($product_id),
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
                            <li><b>شیوه ارسال: </b>%s</li>
                            <li><b>زمان ارسال: </b>%s</li>
                            <li><b>کد رهگیری: </b>%s</li>
                            <li><b>تاریخ ثبت: </b>%s</li>
                        </ul>',
                        $package['orderNumber'],
                        $product_list_markup,

                        anar_translator($package['delivery']['deliveryType']),
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

}
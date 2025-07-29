<?php

namespace Anar;

class Payments{

    protected static $instance = null;

    public static function get_instance(){
        if( null === self::$instance ){
            self::$instance = new self();
        }
        return self::$instance;
    }


    public function __construct()
    {
        add_action('admin_notices', [$this, 'show_unpaid_orders_notice']);
        add_action('wp_ajax_awca_fetch_payments_ajax', [$this, 'fetch_payments_ajax']);
        add_action('wp_ajax_nopriv_awca_fetch_payments_ajax', [$this, 'fetch_payments_ajax']);

    }


    public function fetch_payments_ajax(){
        $page = isset($_POST['page']) ? intval($_POST['page']) : 1;
        $limit = isset($_POST['limit']) ? intval($_POST['limit']) : 10;

        $response = ApiDataHandler::callAnarApi("https://api.anar360.com/wp/orders/payment/pendings?page=$page&limit=$limit");

        if (is_wp_error($response)) {
            $message = $response->get_error_message();
            wp_send_json_error(["message" => $message]);
        }

        $response_body = json_decode(wp_remote_retrieve_body($response), true);

        if ($response_body['success']) {

            $payments = $response_body['result']['order'];
            $payable = $response_body['result']['payable'];
            $output = '';

            update_option('_awca_unpaid_orders', $response_body['total']);

            if(count($payments) > 0 ) {
                foreach ($payments as $index => $payment) {

                    $output .= sprintf('<tr class="item">
                            <td>%s</td>
                            <td>%s</td>
                            <td>%s</td>
                            <td>%s</td>
                            <td><div style="display:flex; gap:8px"><p>%s</p> %s</div></td>
                            <td>%s</td>
                        </tr>',
                        (($page-1) * $limit)+ ($index + 1),
                        $payment['orderNumber'],
                        $payment['groupId'],
                        anar_translator($payment['status']),
                        anar_get_formatted_price($payment['payable']),
                        sprintf('<a class="awca-primary-btn inline-btn" target="_blank" href="https://anar360.com/payment/order/g-%s/pay?callback=%s&type=retail">پرداخت آنلاین</a>',
                            $payment['orderNumber'],
                            admin_url('admin.php?page=payments'),
                        ),
                        $payment['description'],
                    );
                }

                $payable_message = sprintf('مبلغ کل بدهی شما
                            <strong >%s</strong>
                            تومان است.',
                    number_format($payable,0,'', ',')
                );
                $message = $response_body['message'] ?? 'لیست بدهی ها با موفقیت دریافت شد.';
                wp_send_json_success(["message" => $message, 'output' => $output, 'payable' => $payable_message, "total" => $response_body['total']]);

            }else{
                $output .= sprintf('<tr class="item">
                            <td></td>
                            <td></td>
                            <td>%s</td>
                            <td></td>
                            <td></td>
                            <td></td>
                        </tr>',
                    'هیچ بدهی وجود ندارد',

                );
                $payable_message = 'شما هیچ بدهی ندارید.';
                $message = $response_body['message'] ?? 'هیچ بدهی وجود ندارد';
                wp_send_json_success(["message" => $message, 'output' => $output, 'payable' => $payable_message]);
            }
        } else {
            $message =  $response_body['message'] ?? 'مشکلی در دریافت بدهی ها بوجود آمد.';

            wp_send_json_error(["message" => $message]);
        }
    }


    public function show_unpaid_orders_notice() {
        $unpaid_orders_count = get_option('_awca_unpaid_orders', 0);

        if ($unpaid_orders_count > 0) {
            echo '<div class="notice notice-error">
                    <p>' . sprintf('شما %d سفارش پرداخت نشده که شامل محصولات انار می باشد دارید. 
                    <a href="%s">صفحه پرداخت انار</a>', $unpaid_orders_count , admin_url('admin.php?page=payments') ). '</p>
                </div>';
        }
    }


    public function count_unpaid_orders_count() {
        $response = ApiDataHandler::callAnarApi("https://api.anar360.com/wp/orders/payment/pendings?page=1&limit=1");

        if (!is_wp_error($response)) {

            $response_body = json_decode(wp_remote_retrieve_body($response), true);

            if ($response_body['success']) {
                update_option('_awca_unpaid_orders', $response_body['total']);
            }

        }

    }

}
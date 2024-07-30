<?php

namespace Anar;

class AWCA_Order {

    public function __construct() {
        // orders list actions
        add_filter('manage_edit-shop_order_columns', [$this, 'add_custom_columns']);
        add_filter('manage_woocommerce_page_wc-orders_columns', [$this, 'add_custom_columns']);
        add_action('manage_shop_order_posts_custom_column', [$this, 'custom_columns_content'], 10, 2);
        add_action('manage_woocommerce_page_wc-orders_custom_column', [$this, 'custom_columns_content'], 10, 2);
        add_action('admin_notices', [$this, 'show_unpaid_orders_notice']);


        // order actions
        add_action( 'woocommerce_admin_order_data_after_billing_address', [$this, 'display_custom_option_in_admin'], 10, 1 );
        add_filter('woocommerce_get_order_item_totals', [$this, 'filter_fee_and_shipment_name_in_order_details'], 10, 3);

    }

    public function add_custom_columns($columns) {
        $new_columns = [];

        foreach ($columns as $key => $column) {
            $new_columns[$key] = $column;

            if ($key === 'order_status') {
                // Add after the order status column
                $new_columns['anar_payment_status'] = 'انار';
            }
        }

        return $new_columns;
    }


    public function custom_columns_content($column, $post_id) {

        if ('anar_payment_status' === $column) {
            $this->display_anar_payment_status_column($post_id);
        }
    }


    private function display_anar_payment_status_column($order) {
        $anar_should_pay = $order->get_meta('_anar_should_pay', true);
        if ($anar_should_pay === 'true') {
            echo '<span class="anar-label anar-not-paid-label">منتظر پرداخت</span>';
        } else {
            echo '<span class="anar-label anar-paid-label">پرداخت شده</span>';
        }
    }


    public function show_unpaid_orders_notice() {
        $unpaid_orders_count = $this->get_unpaid_orders_count();

        if ($unpaid_orders_count > 0) {
            echo '<div class="notice notice-error">
                    <p>' . sprintf('شما %d سفارش پرداخت نشده که شامل محصولات انار می باشد دارید. <a href="%s">صفحه پرداخت انار</a>', $unpaid_orders_count , get_site_url() ). '</p>
                </div>';
        }
    }


    private function get_unpaid_orders_count() {
        $query = new \WC_Order_Query([
            'limit' => -1,
            'meta_key' => '_anar_should_pay',
            'meta_value' => 'true',
            'meta_compare' => '='
        ]);

        $orders = $query->get_orders();

        return count($orders);
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


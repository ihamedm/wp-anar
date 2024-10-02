<?php

namespace Anar;

class Orders_List {

    public function __construct() {
        // orders list actions
        add_filter('manage_edit-shop_order_columns', [$this, 'add_custom_columns']);
        add_filter('manage_woocommerce_page_wc-orders_columns', [$this, 'add_custom_columns']);
        add_action('manage_shop_order_posts_custom_column', [$this, 'custom_columns_content'], 10, 2);
        add_action('manage_woocommerce_page_wc-orders_custom_column', [$this, 'custom_columns_content'], 10, 2);



        // order actions
        //add_action( 'woocommerce_admin_order_data_after_billing_address', [$this, 'display_custom_option_in_admin'], 10, 1 );
        //add_filter('woocommerce_get_order_item_totals', [$this, 'filter_fee_and_shipment_name_in_order_details'], 10, 3);


    }
    


    public function add_custom_columns($columns) {
        $new_columns = [];

        foreach ($columns as $key => $column) {
            $new_columns[$key] = $column;

            if ($key === 'order_status') {
                // Add after the order status column
                $new_columns['anar_data'] = 'انار';
            }
        }

        return $new_columns;
    }


    public function custom_columns_content($column, $post_id) {

        if ('anar_data' === $column) {
            $this->display_anar_order_data_column($post_id);
        }
    }


    private function display_anar_order_data_column($order) {

        if (awca_is_hpos_enable()) {
            $is_anar_order = $order->get_meta('_is_anar_order', true);
            $anar_order_number = $order->get_meta('_anar_order_group_id', true);
        } else {
            $is_anar_order = get_post_meta($order->get_id(), '_is_anar_order', true);
            $anar_order_number = get_post_meta($order->get_id(), '_anar_order_group_id', true);
        }

        if($is_anar_order){
            if ($is_anar_order == 'anar')
                printf('<span class="anar-label anar-not-paid-label">%s</span>', 'سفارش انار');


            if($anar_order_number)
                printf('<span class="anar-label-info anar-label">#%s</span>', $anar_order_number);
        }

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
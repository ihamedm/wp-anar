<?php

namespace Anar;

class OrderList {

    protected static $instance = null;

    public static function get_instance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function __construct() {
        // orders list actions

        // add a column to show anar data
        add_filter('manage_edit-shop_order_columns', [$this, 'add_anar_order_column']);
        add_filter('manage_woocommerce_page_wc-orders_columns', [$this, 'add_anar_order_column']);

        // show anar order data
        add_action('manage_shop_order_posts_custom_column', [$this, 'anar_column_content'], 10, 2);
        add_action('manage_woocommerce_page_wc-orders_custom_column', [$this, 'anar_column_content'], 10, 2);


        // add anar-orders filter after default woocommerce order filters on order list page
        if (awca_is_hpos_enable()) {
            // For HPOS enabled stores
            add_action( 'woocommerce_order_list_table_restrict_manage_orders', [$this, 'anar_order_filter'], 25, 2 );
            add_filter('woocommerce_order_list_table_prepare_items_query_args', [$this, 'filter_anar_orders_hpos']);
        } else {
            // For traditional post-based orders
            add_filter('views_edit-shop_order', [$this, 'anar_orders_filter_link']);
            add_action('pre_get_posts', [$this, 'filter_anar_orders']);
        }

    }
    


    public function add_anar_order_column($columns) {
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


    public function anar_column_content($column, $post_id) {

        if ('anar_data' === $column) {
            $this->display_anar_order_data_column($post_id);
        }
    }


    private function display_anar_order_data_column($order) {
        $classes = 'anar-label';
        $order_id = is_object($order) ? $order->get_id() : $order;
        $icon = '';

        if (awca_is_hpos_enable()) {
            $is_anar_order = $order->get_meta('_is_anar_order', true);
            $anar_order_number = $order->get_meta('_anar_order_group_id', true);
        } else {
            $is_anar_order = get_post_meta($order, '_is_anar_order', true);
            $anar_order_number = get_post_meta($order, '_anar_order_group_id', true);
        }

        if( anar_order_can_ship_to_stock($order_id) ){
            $classes .= ' has-icon can-ship-to-stock';
            $icon = '<svg  xmlns="http://www.w3.org/2000/svg"  width="24"  height="24"  viewBox="0 0 24 24"  fill="none"  stroke="currentColor"  stroke-width="1"  stroke-linecap="round"  stroke-linejoin="round"  class="icon icon-tabler icons-tabler-outline icon-tabler-cube-send"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><path d="M16 12.5l-5 -3l5 -3l5 3v5.5l-5 3z" /><path d="M11 9.5v5.5l5 3" /><path d="M16 12.545l5 -3.03" /><path d="M7 9h-5" /><path d="M7 12h-3" /><path d="M7 15h-1" /></svg>';
        }


        if($is_anar_order){
            if ($is_anar_order == 'anar')
                printf('<span class="%s anar-not-paid-label">%s %s</span>',  $classes, $icon, 'سفارش انار');


            if($anar_order_number)
                printf('<span class="%s anar-label-info">#%s</span>', $classes, $anar_order_number);
        }

    }


    public function anar_order_filter( $post_type, $which ) {

        if( 'shop_order' !== $post_type ) {
            return;
        }

        $count = OrderReports::count_anar_orders();

        $is_anar_order = isset( $_GET[ 'is_anar_order' ] ) ? $_GET[ 'is_anar_order' ] : '';

        ?>
        <select name="is_anar_order">
            <option value="">سفارش های انار</option>
            <option value="1"<?php selected( $is_anar_order, '1' ) ?>><?php printf('بله (%s)' , $count);?></option>
            <option value="0"<?php selected( $is_anar_order, '0' ) ?>>خیر</option>
        </select>
        <?php

    }

    public function anar_orders_filter_link($views) {
        $count = OrderReports::count_anar_orders();

        // Check if current filter is active
        $current = isset($_GET['is_anar_order']) ? ' class="current"' : '';

        // Add the custom filter link
        $views['is_anar_order'] = sprintf(
            '<a href="%s"%s>سفارش انار <span class="count">(%d)</span></a>',
            admin_url('admin.php?page=wc-orders&is_anar_order=1'),
            $current,
            $count
        );

        return $views;
    }

    public function filter_anar_orders($query) {

        if (!is_admin() || !$query->is_main_query()) {
            return;
        }

        if (isset($_GET['is_anar_order']) && $_GET['is_anar_order'] === '1') {
            $query->set('meta_query', array(
                array(
                    'key' => '_is_anar_order',
                    'compare' => 'EXISTS'
                ),
            ));
        }

    }

    public function filter_anar_orders_hpos($query_args) {

        // Only modify query when our filter is active
        if (!isset($_GET['is_anar_order']) || $_GET['is_anar_order'] !== '1') {
            return $query_args;
        }

        // Add meta query
        if (!isset($query_args['meta_query'])) {
            $query_args['meta_query'] = [];
        }

        $query_args['meta_query'][] = array(
            'key' => '_is_anar_order',
            'value' => 'anar',
            'compare' => '='
        );

        return $query_args;

    }


}
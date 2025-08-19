<?php

namespace Anar\Product;

use Anar\ProductData;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Lists{

    public function __construct() {
        // add a column to show Anar label on product list page
        add_filter('manage_product_posts_columns', [$this, 'anar_product_list_column']);
        add_action('manage_product_posts_custom_column', [$this, 'anar_product_list_label'], 10, 2);

        // add anar products filter
        add_filter('views_edit-product', [$this, 'anar_products_filter_link']);
        add_action('pre_get_posts', [$this, 'filter_anar_products']);
        add_filter('post_row_actions', [$this, 'add_view_on_anar_action_link'], 10, 2);

        add_action('pre_get_posts', [$this, 'filter_anar_deprecated_products']);
    }


    public function anar_product_list_column($columns){
        $new_columns = array();
        foreach ($columns as $key => $value) {
            $new_columns[$key] = $value;
            if ($key === 'date') {
                $new_columns['product_label'] = 'انار';
            }
        }
        return $new_columns;
    }

    public function anar_product_list_label($column, $post_id)
    {
        if ($column === 'product_label') {
            $anar_shop_url = get_option('_anar_shop_url', 'https://anar360.com/earning-income');
            $anar_sku = get_post_meta($post_id, '_anar_sku', true);
            if (!empty($anar_sku)) {
                $anar_prices = get_post_meta($post_id, '_anar_prices', true);
                $anar_pending = get_post_meta($post_id, '_anar_pending', true);
                $last_sync_time = get_post_meta($post_id, '_anar_last_sync_time', true);


                $anar_url = $anar_shop_url ."/product/{$anar_sku}";
                $anar_fruit_url = ANAR_WC_API_PLUGIN_URL.'assets/images/'.($anar_pending ? 'anar-fruit-pending.svg' : 'anar-fruit.svg');

                echo '<a class="anar-fruit" href="'.$anar_url.'" target="_blank" title="مشاهده محصول در سایت انار۳۶۰"><img src="'.$anar_fruit_url.'"></a>';

                printf('<div class="anar-product-list-last-sync-time"><date>%s</date><br>%s</div>',
                    mysql2date('j F Y' . ' در ' . 'H:i', $last_sync_time),
                    awca_time_ago($last_sync_time),
                );

                if($anar_prices && ANAR_IS_ENABLE_OPTIONAL_SYNC_PRICE == 'yes') {
                    echo '<br>';
                    echo 'قیمت انار';
                    echo '<br>';
                    echo anar_get_formatted_price($anar_prices['price']) ?? '-';
                }
            }

            $anar_deprecated = get_post_meta($post_id, '_anar_deprecated', true);
            if($anar_deprecated) {
                $anar_sku_backup = get_post_meta($post_id, '_anar_sku_backup', true);
                $anar_url = $anar_shop_url . "/product/{$anar_sku_backup}";
                $anar_fruit_url = ANAR_WC_API_PLUGIN_URL.'assets/images/anar-fruit-deprecated.svg';

                echo '<a class="anar-fruit" href="'.$anar_url.'" target="_blank" title="از پنل انار حذف شده"><img src="'.$anar_fruit_url.'"></a>';
            }
        }
    }


    public function anar_products_filter_link($views) {

        $count = (new ProductData())->count_anar_products();
        // Check if current filter is active
        $current = isset($_GET['is_anar_product']) ? ' class="current"' : '';

        // Add the custom filter link
        $views['is_anar_product'] = sprintf(
            '<a href="%s"%s>انار <span class="count">(%d)</span></a>',
            admin_url('edit.php?post_type=product&is_anar_product=1'),
            $current,
            $count
        );

        return $views;
    }

    public function filter_anar_products($query) {

        if (!is_admin() || !$query->is_main_query()) {
            return;
        }

        // Apply your meta filter
        if (isset($_GET['is_anar_product']) && $_GET['is_anar_product'] === '1') {

            (new ProductData())->count_anar_products(true);

            $query->set('meta_key', '_anar_last_sync_time');
            $query->set('orderby', 'meta_value');
            $query->set('order', 'DESC');
            $query->set('meta_type', 'DATETIME');
        }

    }


    // Add filter to modify products query to show deprecated products
    public function filter_anar_deprecated_products($query) {
        if (!is_admin()) {
            return;
        }

        if (!$query->is_main_query() ||
            !isset($_GET['post_type']) ||
            $_GET['post_type'] !== 'product' ||
            !isset($_GET['anar_deprecated'])) {
            return;
        }

        // Add meta query to show only deprecated products
        $query->set('meta_query', array(
            array(
                'key' => '_anar_deprecated',
                'compare' => 'EXISTS'
            )
        ));
    }

    public function add_view_on_anar_action_link($actions, $post) {
        if ($post->post_type === 'product') {
            $anar_products = get_post_meta($post->ID, '_anar_sku', true);
            if (!empty($anar_products)) {
                $anar_url = "https://anar360.com/earning-income/product/{$anar_products}";
                $actions['view_on_shop'] = sprintf(
                    '<a href="%s" target="_blank" aria-label="%s">%s</a>',
                    esc_url($anar_url),
                    'مشاهده این محصول در سایت انار۳۶۰',
                    'مشاهده در انار'
                );
            }
        }

        return $actions;
    }


}
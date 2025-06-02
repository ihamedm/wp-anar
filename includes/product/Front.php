<?php

namespace Anar\Product;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}
class Front{

    public function __construct() {
        // add anar meta-data to front product page
        add_action('wp_head', [$this, 'add_anar_product_meta_tag'], 10);
    }

    public function add_anar_product_meta_tag() {

        global $post;

        if (is_product()) {
            $anar_sku = get_post_meta($post->ID, '_anar_sku', true);
            if ($anar_sku) {
                $last_sync_time = get_post_meta($post->ID, '_anar_last_sync_time', true);

                printf( '
                <meta name="anar_sku" content="%s" />
                <meta name="anar_last_sync" content="%s" />
                ',
                    $anar_sku,
                    $last_sync_time ? mysql2date('j-m-Y  H:i' , $last_sync_time) : '',
                );
            }
        }

        printf( '
                <meta name="anar_version" content="%s" />
                ',
            ANAR_PLUGIN_VERSION,
        );


    }
}
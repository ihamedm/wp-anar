<?php

namespace Anar;

class Cart{

    public function __construct()
    {

    }


    public static function get_anar_products_in_cart() {
        global $woocommerce;
        $items = $woocommerce->cart->get_cart();

        $anarProducts = [];
        $otherProducts = [];
        foreach($items as $item => $values) {
            $_product =  wc_get_product( $values['data']->get_id());
            $value = null;
            $product_parent_id = $_product->get_parent_id();

            if($product_parent_id == 0){
                $value = get_post_meta($_product->get_id(),'_anar_products');
            } else {
                $value = get_post_meta($product_parent_id,'_anar_products');
            }
            if ($value) {
                $anarProducts[] = $_product->get_title();
            } else {
                $otherProducts[] = $_product->get_title();
            }
        }
        return [
            $anarProducts, $otherProducts
        ];
    }
}
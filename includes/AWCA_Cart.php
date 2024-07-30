<?php

namespace Anar;

class AWCA_Cart{

    public function __construct()
    {
        add_filter( 'woocommerce_cart_ready_to_calc_shipping', [$this, 'disable_shipping_calc_on_cart'], 99 );

    }



    public function disable_shipping_calc_on_cart( $show_shipping ) {
        if( is_cart() ) {
            return false;
        }
        return $show_shipping;
    }
}
<?php

namespace Anar;

class AWCA_Product{


    public function __construct()
    {

    }

    /**
     * Anar API only send sku of shipping packages, we prepare human-readable name for each of them
     *
     * @param $slug
     * @return mixed|string
     */
    public static function verbose_shipment_name($slug) {
        if ($slug == 'bike') {
            return 'پیک موتوری';
        }

        if ($slug == 'post') {
            return 'پست';
        }

        return $slug;
    }


    /**
     * @return array
     */
    public static function get_all_products_shipments() {
        $api_url = 'https://api.anar360.com/api/360/products';
        $awca_products = awca_get_data_from_api($api_url);
        $sku_ship = [];
        foreach ($awca_products->items as $i) {
            $shipmentsReferenceId = $i->shipmentsReferenceId;
            $mainSku = $i->id;
            $deliveryArray = [];
            foreach ($i->shipments as $value) {
                if($value->type == "insideShopCity") {
                    foreach ($value->delivery as $delivery) {
                        if ($delivery->active == true || $delivery->active == "true") {
                            $deliveryArray[$delivery->_id] = [
                                "name" => self::verbose_shipment_name($delivery->deliveryType),
                                "price" => $delivery->price,
                                "estimatedTime" => $delivery->estimatedTime,
                            ];
                        }
                    }
                }
            }

            $sku_ship[$mainSku] = [
                'shipmentId' => $shipmentsReferenceId,
                'delivery' => $deliveryArray,
            ];

            foreach ($i->variants as $v) {
                $sku_ship[$v->_id] = [
                    'shipmentId' => $shipmentsReferenceId,
                    'delivery' => $deliveryArray,
                ];
            }
        }
        return $sku_ship;
    }


    /**
     * @return array
     */
    public static function get_all_products_shipments_ref() {
        $api_url = 'https://api.anar360.com/api/360/products';
        $awca_products = awca_get_data_from_api($api_url);
        $res = [];
        foreach ($awca_products->items as $i) {
            $shipmentsReferenceId = $i->shipmentsReferenceId;
            $deliveryArray = [];
            foreach ($i->shipments as $value) {
                if($value->type == "insideShopCity") {
                    foreach ($value->delivery as $delivery) {
                        if ($delivery->active == true || $delivery->active == "true") {
                            $deliveryArray[$delivery->_id] = [
                                "name" => self::verbose_shipment_name($delivery->deliveryType),
                                "price" => $delivery->price,
                                "estimatedTime" => $delivery->estimatedTime,
                            ];
                        }
                    }
                }
            }

            $res[$shipmentsReferenceId] = [
                'delivery' => $deliveryArray,
            ];
        }
        return $res;
    }


    /**
     * @return array[]
     */
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
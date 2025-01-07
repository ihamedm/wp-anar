<?php

namespace Anar;

class ProductData{


    public static function get_simple_product_by_anar_sku($anar_sku) {
        global $wpdb;
        $sql = "SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key = '_anar_sku' AND meta_value = %s";


        // Search for variations with the custom SKU
        $variation_id = $wpdb->get_var($wpdb->prepare($sql, $anar_sku));

        if ($variation_id) {
            return $variation_id;
        }

        // If not found, return false
        return false;
    }


    public static function get_product_variation_by_anar_sku($anar_sku) {
        global $wpdb;

        // Search for variations with the custom SKU
        $variation_id = $wpdb->get_var($wpdb->prepare("
        SELECT post_id 
        FROM {$wpdb->postmeta} 
        WHERE meta_key = '_anar_sku' 
        AND meta_value = %s
    ", $anar_sku));

        if ($variation_id) {
            return $variation_id;
        }

        // If not found, return false
        return false;
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
    public static function get_all_products_shipments_ref() {
        $awca_products = ApiDataHandler::tryGetAnarApiResponse('https://api.anar360.com/wp/products');
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
    public static function get_anar_product_shipments($product_id){
        $shipments_json = get_post_meta($product_id, '_anar_shipments', true);
        $shipments_ref = get_post_meta($product_id, '_anar_shipments_ref', true);

        if(!$shipments_json || $shipments_json == '' || count(json_decode($shipments_json)) == 0){
            return false;
        }


        $shipments = json_decode($shipments_json);


        $deliveryArray = [];
        foreach ($shipments as $value) {
            foreach ($value->delivery as $delivery) {
                if ($delivery->active || $delivery->active == "true") {
                    $deliveryArray[$delivery->_id] = [
                        "name" => self::verbose_shipment_name($delivery->deliveryType),
                        "price" => $delivery->price,
                        "estimatedTime" => $delivery->estimatedTime,
                        "deliveryType" => $delivery->deliveryType,
                    ];
                }
            }
        }

        $serialized_shipments = [
            'shipments' => $shipments,
            'shipmentsReferenceId' => $shipments_ref['shipmentsReferenceId'],
            'shipmentsReferenceState' => $shipments_ref['shipmentsReferenceState'],
            'shipmentsReferenceCity' => $shipments_ref['shipmentsReferenceCity'],

            'shipmentId' => $shipments_ref['shipmentsReferenceId'],
            'delivery' => $deliveryArray,
        ];


        return $serialized_shipments;
    }


    public function count_anar_products(){
        $meta_key = '_anar_products';

        $query = new \WP_Query( array(
            'post_type'      => 'product',
            'meta_key'       => $meta_key,
            'meta_compare'   => 'EXISTS',
            'fields'         => 'ids',
        ) );

        return $query->found_posts;
    }

}
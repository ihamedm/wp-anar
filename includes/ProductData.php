<?php

namespace Anar;

class ProductData{
    /**
     * Get simple product ID by Anar SKU
     *
     * @param string $anar_sku The Anar SKU to search for
     * @return \WP_Error|int Returns product ID on success, WP_Error on failure
     */
    public static function get_simple_product_by_anar_sku($anar_sku) {
        // Input validation
        if (empty($anar_sku)) {
            return new \WP_Error(
                'invalid_anar_sku',
                'Anar SKU cannot be empty',
                array('status' => 400)
            );
        }

        // Sanitize the input
        $anar_sku = sanitize_text_field($anar_sku);

        // Use WP_Query to search for products
        $args = array(
            'post_type'      => array('product', 'product_variation'),
            'post_status'    => ['publish', 'draft'],
            'posts_per_page' => 1,
            'meta_query'     => array(
                array(
                    'key'     => '_anar_sku',
                    'value'   => $anar_sku,
                    'compare' => '=',
                ),
            ),
            'fields'         => 'ids',
        );

        $query = new \WP_Query($args);

        // Check if we found any results
        if ($query->have_posts()) {
            return (int) $query->posts[0];
        }

        // If no product found, return WP_Error
        return new \WP_Error(
            'product_not_found',
            sprintf(
                'No product found with Anar SKU: %s',
                $anar_sku
            ),
            array('status' => 404)
        );
    }


    /**
     * Get product variation ID by Anar SKU
     *
     * @param string $anar_sku The Anar SKU to search for
     * @return int|\WP_Error Returns variation ID on success, WP_Error on failure
     */
    public static function get_product_variation_by_anar_sku($anar_sku) {
        global $wpdb;

        // Input validation
        if (empty($anar_sku)) {
            return new \WP_Error(
                'invalid_anar_sku',
                'Anar SKU cannot be empty',
                array('status' => 400)
            );
        }

        // Sanitize the input
        $anar_sku = sanitize_text_field($anar_sku);

        // Prepare and execute the query
        $sql = $wpdb->prepare("
            SELECT post_id 
            FROM {$wpdb->postmeta} 
            WHERE meta_key = '_anar_sku' 
            AND meta_value = %s
        ", $anar_sku);

        // Check for SQL errors before executing
        if ($sql === false) {
            return new \WP_Error(
                'sql_prepare_failed',
                'Failed to prepare SQL query',
                array('status' => 500)
            );
        }

        // Execute query
        $variation_id = $wpdb->get_var($sql);

        // Check for database errors
        if ($wpdb->last_error) {
            return new \WP_Error(
                'database_error',
                sprintf(
                    'Database error: %s',
                    $wpdb->last_error
                ),
                array('status' => 500)
            );
        }



        // If variation found, return it
        if ($variation_id) {
            // Verify the post exists and is a product variation
            $post_type = get_post_type($variation_id);
            if ($post_type !== 'product_variation') {
                return new \WP_Error(
                    'invalid_variation',
                    sprintf(
                    'Found ID %d is not a valid product variation',
                        $variation_id
                    ),
                    array('status' => 400)
                );
            }
            return (int) $variation_id;
        }

        // If no variation found, return WP_Error
        return new \WP_Error(
            404,
            'variation_not_found',
            sprintf(
                'No product variation found with Anar SKU: %s',
                $anar_sku
            )
        );
    }


    /**
     * Get product variation ID by Anar SKU
     *
     * @param string $anar_sku The Anar SKU to search for
     * @return int|\WP_Error Returns variation ID on success, WP_Error on failure
     */
    public static function check_for_existence_product($anar_sku) {
        global $wpdb;

        try {

            if (empty($anar_sku)) {
                throw new \Exception('Anar SKU cannot be empty');
            }

            // Sanitize the input
            $anar_sku = sanitize_text_field($anar_sku);

            // Prepare and execute the query
            $sql = $wpdb->prepare("
                SELECT post_id 
                FROM {$wpdb->postmeta} 
                WHERE meta_key = '_anar_sku' 
                AND meta_value = %s
            ", $anar_sku);

            // Check for SQL errors before executing
            if ($sql === false) {
                throw new \Exception('Failed to prepare SQL query');
            }

            // Execute query
            $variation_id = $wpdb->get_var($sql);

            // If variation found, return it
            if ($variation_id) {
                return (int) $variation_id;
            }

            // If no variation found
            throw new \Exception(sprintf('No product variation found with Anar SKU: %s', $anar_sku));

        }catch (\Exception $exception){
            awca_log($exception->getMessage(), 'debug');
            return false;
        }
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

        if($slug == 'bikeCOD'){
            return 'پرداخت کرایه در مقصد';
        }

        return $slug;
    }




    /**
     * @return array[]
     */
    public static function get_anar_product_shipments($product_id){
        $shipments_json = get_post_meta($product_id, '_anar_shipments', true);
        $shipments_ref = get_post_meta($product_id, '_anar_shipments_ref', true);

        if(!$shipments_json || $shipments_json == '' || count(json_decode($shipments_json)) == 0){
            return false;
//            @todo check the usage of this function, replace false with []
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


    public function count_anar_products($refresh = false) {
        $transient_key = OPT_KEY__COUNT_ANAR_PRODUCT_ON_DB;
        $cache_duration = 3600; // 1 hour in seconds

        // If not forced refresh, try to get from cache first
        if (!$refresh) {
            $cached_count = get_transient($transient_key);
            if ($cached_count !== false) {
                return (int)$cached_count;
            }
        }

        // Cache miss or forced refresh, fetch the count
        global $wpdb;

        // Direct SQL query is much faster than WP_Query for counting
        $count = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(DISTINCT p.ID) 
         FROM {$wpdb->posts} p 
         JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id 
         WHERE p.post_type = %s 
         AND p.post_status IN ('publish', 'draft') 
         AND pm.meta_key = %s",
            'product',
            '_anar_products'
        ));

        // Store in cache
        set_transient($transient_key, $count, $cache_duration);

        return (int)$count;
    }


}
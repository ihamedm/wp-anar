<?php

namespace Anar\Wizard;

use Anar\ApiDataHandler;

class Wizard{

    public function __construct(){

        add_action( 'wp_ajax_awca_fetch_products_paginate_ajax', [$this, 'fetch_products_paginate_ajax'] );
        add_action( 'wp_ajax_nopriv_awca_fetch_products_paginate_ajax', [$this, 'fetch_products_paginate_ajax'] );

    }


    /**
     * This method responsible to fetch products from Anar API with pagination to show with AJAX in Wizard step1
     * @return void
     */
    public function fetch_products_paginate_ajax() {

        // Start time
        $start_time = microtime(true);

        if ( !isset( $_GET['page'] ) || !is_numeric( $_GET['page'] ) ) {
            wp_send_json_error( array( 'message' => 'Invalid page number', 'code' => 'invalid_page_number' ) );
        }

        $page = intval( $_GET['page'] );
        $api_url = "https://api.anar360.com/wp/products?page=$page&limit=10";
        awca_log("Fetching products from page: $page");
        awca_log($api_url);

        // Fetch the products from the API
        $token = awca_get_activation_key();
        $response = ApiDataHandler::callAnarApi($api_url);

        if ( is_wp_error( $response ) ) {
            $error_message = $response->get_error_message();
            awca_log("API request failed: $error_message");
            wp_send_json_error( array( 'message' => 'API request failed', 'code' => 'api_request_failed', 'details' => $error_message ) );
        }

        $http_code = wp_remote_retrieve_response_code( $response );
        if ( $http_code != 200 ) {
            awca_log("API request returned HTTP status code: $http_code");
            wp_send_json_error( array( 'message' => 'Unexpected HTTP status code', 'code' => 'http_status_error', 'status_code' => $http_code ) );
        }

        $body = wp_remote_retrieve_body( $response );
        $products = json_decode( $body );

        // End time
        $end_time = microtime(true);

        // Calculate and log the time taken
        $time_taken = $end_time - $start_time;
        awca_log("Time taken to fetch products from API : " . $time_taken . " seconds");

        if ( json_last_error() !== JSON_ERROR_NONE ) {
            $json_error = json_last_error_msg();
            awca_log("JSON decoding failed: $json_error");
            wp_send_json_error( array( 'message' => 'JSON decoding failed', 'code' => 'json_decoding_failed', 'details' => $json_error ) );
        }

        if ( empty( $products ) ) {
            wp_send_json_error( array( 'message' => 'No products found', 'code' => 'no_products_found' ) );
        }

        wp_send_json_success( $products );
    }
}
<?php
namespace Anar;

defined( 'ABSPATH' ) || exit;

class AnarProduct{

    public static $baseApiUrl;

    public function __construct() {
        $this->baseApiUrl = 'https://api.anar360.com/wp/products';
    }

    public static function get($sku) {
        if (empty($sku)) {
            return false;
        }

        $apiUrl = self::$baseApiUrl . '/' . $sku;

        $api_response = ApiDataHandler::callAnarApi($apiUrl);

        if (is_wp_error($api_response)) {
            return false;
        }

        $response_code = wp_remote_retrieve_response_code($api_response);
        $response_body = wp_remote_retrieve_body($api_response);

        if ($response_code === 403) {
            return 'auth_failed';
        } elseif ($response_code !== 200) {
            return false;
        }

        $product_data = json_decode($response_body);

        if (json_last_error() === JSON_ERROR_NONE && isset($product_data->id) && isset($product_data->variants)) {
            return $product_data;
        } else {
            return false;
        }
    }

}
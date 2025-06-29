<?php

namespace Anar;

use Anar\Core\Logger;
use Anar\Sync;
use Anar\ApiDataHandler;
use Anar\Wizard\ProductManager;

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly
}

/**
 * Class SyncProduct
 * Handles real-time product updates.
 */
class SyncRealTime {

    private static $instance;
    private $logger;
    private $sync_instance;
    private $baseApiUrl;
    const LAST_SYNC_META_KEY = '_anar_last_sync_time';
    const COOLDOWN_PERIOD = 10; // Seconds
    const AJAX_NONCE_ACTION = 'awca_ajax_nonce';
    const PRODUCT_ID_META_NAME = 'anar-product-id';

    public static function get_instance() {
        if ( ! isset( self::$instance ) ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function __construct() {
        $this->logger = new Logger();
        $this->sync_instance = Sync::get_instance();
        $this->baseApiUrl = 'https://api.anar360.com/wp/products';

        add_action('wp_head', [$this, 'add_product_id_meta_tag']);
        add_action('wp_ajax_anar_update_product_async', [$this, 'handle_async_product_update_ajax']);
        add_action('wp_ajax_nopriv_anar_update_product_async', [$this, 'handle_async_product_update_ajax']);
        add_action('wp_ajax_anar_update_cart_products_async', [$this, 'handle_async_cart_update_ajax']);
        add_action('wp_ajax_nopriv_anar_update_cart_products_async', [$this, 'handle_async_cart_update_ajax']);
    }

    private function log($message, $level = 'info') {
        $this->logger->log($message, 'realTimeSync', $level);
    }

    public function add_product_id_meta_tag() {
        if (is_product()) {
            global $post;
            if ($post && isset($post->ID)) {
                echo '<meta name="' . esc_attr(self::PRODUCT_ID_META_NAME) . '" content="' . esc_attr($post->ID) . '" />' . "\n";
            }
        }
    }

    public function update_product_from_anar($anar_product, $product_id) {
        try {
            if ($anar_product === null) {
                throw new \InvalidArgumentException("Product data cannot be null for product ID: {$product_id}");
            }
            if (!is_object($anar_product) && !is_array($anar_product)) {
                throw new \InvalidArgumentException("Invalid product data type: " . gettype($anar_product) . " for product ID: {$product_id}");
            }

            $anar_product = is_array($anar_product) ? (object)$anar_product : $anar_product;

            if (!isset($anar_product->variants)) {
                throw new \InvalidArgumentException("Product is missing 'variants' property for product ID: {$product_id}");
            }

            $sync = $this->sync_instance;

            if(isset($anar_product->attributes) && !empty($anar_product->attributes)){
                $sync->processVariableProduct($anar_product, true);
            } else {
                $sync->processSimpleProduct($anar_product, true);
            }


            $this->log("Successfully processed update for product ID: {$product_id} with Anar ID: " . ($anar_product->id ?? 'Unknown'), 'debug');
            return true;

        } catch (\InvalidArgumentException $e) {
            $this->log("Error updating product ID {$product_id}: " . $e->getMessage(), 'error');
            return false;
        } catch (\Exception $e) {
            $this->log("Unexpected error updating product ID {$product_id}: " . $e->getMessage(), 'error');
            return false;
        }
    }

    private function fetch_anar_product_data($sku) {
        if (empty($sku)) {
            $this->log("Cannot fetch product data: SKU is empty.", 'error');
            return null;
        }

        $apiUrl = $this->baseApiUrl . '/' . $sku;
        $this->log("Fetching product data from API: {$apiUrl}", 'debug');

        $api_response = ApiDataHandler::callAnarApi($apiUrl);

        if (is_wp_error($api_response)) {
            $this->log("API WP_Error fetching SKU {$sku}: " . $api_response->get_error_message(), 'error');
            return null;
        }

        $response_code = wp_remote_retrieve_response_code($api_response);
        $response_body = wp_remote_retrieve_body($api_response);

        if ($response_code !== 200) {
            $this->log("API Error fetching SKU {$sku}: Received status code {$response_code}." , 'error');
            return null;
        }

        $product_data = json_decode($response_body);

        if (json_last_error() === JSON_ERROR_NONE && isset($product_data->id) && isset($product_data->variants)) {
            $this->log("Successfully fetched and decoded data for SKU {$sku}.", 'debug');
            return $product_data;
        } else {
            $this->log("API response for SKU {$sku} could not be decoded or is missing required fields. JSON Error: " . json_last_error_msg(), 'warning'); // Keep warning for bad data
            return null;
        }
    }

    /**
     * Processes the update logic for a single product ID.
     * Checks SKU, cooldown, fetches data, updates product, and updates sync time meta.
     *
     * @param int $product_id The WordPress Product or Variation ID.
     * @return string Status code: 'updated', 'skipped_cooldown', 'skipped_not_anar', 'fetch_failed', 'update_failed'.
     */
    private function process_product_update($product_id) {
        $anar_sku = get_post_meta($product_id, '_anar_sku', true);
        if (empty($anar_sku)) {
            $this->log("Skipping Product ID {$product_id}: Not an Anar product (no _anar_sku).", 'debug');
            return 'skipped_not_anar';
        }

        $last_sync_time = (int) get_post_meta($product_id, self::LAST_SYNC_META_KEY, true);
        $current_time = time();
        if (($current_time - $last_sync_time) < self::COOLDOWN_PERIOD) {
            $this->log("Skipping Product ID {$product_id} (SKU: {$anar_sku}): Cooldown active.", 'debug');
            return 'skipped_cooldown';
        }

        $anar_product_data = $this->fetch_anar_product_data($anar_sku);

        if ($anar_product_data) {
            $this->log("Fetched fresh data for Product ID {$product_id} (SKU: {$anar_sku}). Attempting update.", 'debug');
            $update_success = $this->update_product_from_anar($anar_product_data, $product_id);

            if ($update_success) {
                $this->log("Successfully updated Product ID {$product_id}.", 'debug');
                return 'updated';
            } else {
                $this->log("Update failed for Product ID {$product_id} after fetching data.", 'error');
                return 'update_failed';
            }
        } else {
            $this->log("Failed to fetch fresh data for Product ID {$product_id} (SKU: {$anar_sku}).", 'warning'); // Keep warning for fetch failure
            ProductManager::set_product_as_deprecated($product_id, 'realTimeSync', 'realTimeSync');
            return 'fetch_failed';
        }
    }

    /**
     * Handles the AJAX request for single product updates.
     */
    public function handle_async_product_update_ajax() {
        check_ajax_referer(self::AJAX_NONCE_ACTION, 'nonce');

        if (!isset($_POST['product_id']) || !is_numeric($_POST['product_id'])) {
            wp_send_json_error(['message' => 'Invalid or missing product ID.'], 400);
        }
        $product_id = intval($_POST['product_id']);

        $this->log("AJAX request received for single product ID: {$product_id}", 'debug');

        $product = wc_get_product($product_id);
        if (!$product) {
             wp_send_json_error(['message' => "Product with ID {$product_id} not found."], 404);
        }

        $status = $this->process_product_update($product_id);

        switch ($status) {
            case 'updated':
                // TODO: Prepare any data needed by the frontend JS for DOM updates if necessary
                wp_send_json_success(['message' => 'Product updated successfully.']);
                break;
            case 'skipped_cooldown':
            case 'skipped_not_anar':
                wp_send_json_success(['message' => 'Product checked, no update needed.', 'status' => $status]);
                break;
            case 'fetch_failed':
                wp_send_json_success(['message' => 'Failed to fetch fresh product data from API. set product as out-of-stock'], 404);
                break;
            case 'update_failed':
            default:
                wp_send_json_success(['message' => 'Failed to apply update after fetching data.'], 500);
                break;
        }

        wp_die();
    }

    /**
     * Handles the AJAX request for cart product updates.
     */
    public function handle_async_cart_update_ajax() {
        check_ajax_referer(self::AJAX_NONCE_ACTION, 'nonce');

        if (!function_exists('WC') || !WC()->cart) {
            wp_send_json_error(['message' => 'WooCommerce Cart not available.'], 500);
        }

        $cart = WC()->cart->get_cart();
        if (empty($cart)) {
            wp_send_json_success(['message' => 'Cart is empty, no products to update.', 'needs_refresh' => false]);
        }

        $this->log("AJAX request received to update cart products.", 'debug');

        $results = [
            'processed' => 0,
            'updated' => 0,
            'skipped_cooldown' => 0,
            'skipped_not_anar' => 0,
            'fetch_failed' => 0,
            'update_failed' => 0,
        ];

        foreach ($cart as $cart_item_key => $cart_item) {
            $product_id = $cart_item['product_id'];
            $actual_id_to_check = $cart_item['variation_id'] ? $cart_item['variation_id'] : $product_id;

            $results['processed']++;
            $this->log("Cart Update: Checking product ID: {$actual_id_to_check}", 'debug');

            $status = $this->process_product_update($actual_id_to_check);

            // Increment the counter corresponding to the status
            if (isset($results[$status])) {
                $results[$status]++;
            }
        }

        $results['needs_refresh'] = ($results['updated'] > 0);
        // Keep refresh disabled temporarily as requested
        $results['needs_refresh'] = false;

        $this->log("Cart product update check finished. Results: " . json_encode($results), 'debug');
        wp_send_json_success($results);
    }
}
<?php

namespace Anar;
use Anar\Core\Logger;
use Anar\Wizard\ProductManager;

class SyncOutdated {
    private static $instance;
    private $logger;
    private $baseApiUrl;

    private $startTime;

    private $jobID;

    private $max_execution_time = 240;

    public static function get_instance() {
        if (!isset(self::$instance)) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function __construct() {
        $this->baseApiUrl = 'https://api.anar360.com/wp/products';
        $this->logger = new Logger();

        add_action('wp_ajax_anar_sync_outdated_products', array($this, 'sync_outdated_products_ajax'));
        // Add this new action hook
        add_action('anar_process_outdated_products_continued', array($this, 'continue_outdated_products_processing'));
    }



    /**
     * Update product based on Anar API response
     *
     * @param object $anar_product Anar product data
     */
    private function update_product_from_anar($anar_product) {
        try {
            // Early validation
            if ($anar_product === null) {
                throw new \InvalidArgumentException("Product data cannot be null");
            }

            if (!is_object($anar_product) && !is_array($anar_product)) {
                throw new \InvalidArgumentException("Invalid product data type: " . gettype($anar_product));
            }

            // Convert to object if it's an array
            $anar_product = is_array($anar_product) ? (object)$anar_product : $anar_product;

            // Validate required properties
            if (!isset($anar_product->variants)) {
                throw new \InvalidArgumentException("Product is missing 'variants' property");
            }

            $sync = Sync::get_instance();

            // Identify if it's a simple or variable product
            if (count($anar_product->variants) == 1) {
                $sync->processSimpleProduct($anar_product);
            } else {
                $sync->processVariableProduct($anar_product);
            }

            // Log successful update
            $this->log("Successfully updated product with SKU: " . ($anar_product->id ?? 'Unknown'));
        } catch (\Exception $e) {
            // Log the full error details
            $this->log(
                "Error updating product: " . $e->getMessage() .
                "\nTrace: " . $e->getTraceAsString(),
                'error'
            );

            // Re-throw or handle as needed
            throw $e;
        }
    }


    /**
     * Call the Anar API for a specific product
     * 
     * @param string $apiUrl Full API URL
     * @return object|\WP_Error API response or error
     */
    private function call_anar_api($apiUrl) {
        return ApiDataHandler::callAnarApi($apiUrl);
    }


    /**
     * Log a message
     */
    private function log($message) {
        $this->logger->log($message, 'sync');
    }



    /**
     * AJAX handler for syncing outdated products
     */
    public function sync_outdated_products_ajax() {
        // Verify nonce for security

//        if(!check_ajax_referer('anar_sync_outdated_products', 'security')){
//            wp_send_json_error('فرم نامعتبر است. صفحه را مجدد بارگذاری کنید');
//        }

        if (!current_user_can('manage_woocommerce')) {
            wp_send_json_error('شما این مجوز را ندارید!');
            wp_die();
        }

        // Get pagination parameters
        $limit = isset($_POST['limit']) ? intval($_POST['limit']) : 10;

        try {
            // Get outdated products
            $outdated_products = $this->get_outdated_products($limit);

            $response = [
                'success' => true,
                'found_products' => $outdated_products['total_products'],
                'loop_products' => count($outdated_products['products']),
                'has_more' => $outdated_products['has_more']
            ];

            // Process products
            $processed_products = $this->process_outdated_products_batch($outdated_products['products']);
            $response['processed'] = $processed_products;

        } catch (\Exception $e) {
            $response = [
                'success' => false,
                'message' => $e->getMessage()
            ];
            $this->log("Sync outdated products failed: " . $e->getMessage());
        }

        wp_send_json($response);
        wp_die();
    }



    /**
     * Retrieve outdated products
     *
     * @param int $limit Number of products to retrieve
     * @return array Associative array with product data
     */
    private function get_outdated_products($limit) {
        // Time threshold for outdated products (1 hour)
        $time_ago = date('Y-m-d H:i:s', strtotime('-1 hour'));

        $args = array(
            'post_type' => 'product',
            'post_status' => ['publish', 'draft'],
            'posts_per_page' => $limit,
            'meta_query' => array(
                'relation' => 'AND',
                array(
                    'key' => '_anar_sku',
                    'compare' => 'EXISTS'
                ),
                array(
                    'relation' => 'OR',
                    array(
                        'key' => '_anar_last_sync_time',
                        'compare' => 'NOT EXISTS'
                    ),
                    array(
                        'key' => '_anar_last_sync_time',
                        'value' => $time_ago,
                        'compare' => '<',
                        'type' => 'DATETIME'
                    )
                )
            ),
            'fields' => 'ids' // Only get product IDs to reduce memory usage
        );

        $query = new \WP_Query($args);

        // Prepare product data with SKUs
        $products = [];
        foreach ($query->posts as $product_id) {
            $anar_sku = get_post_meta($product_id, '_anar_sku', true);
            if ($anar_sku) {
                $products[] = [
                    'ID' => $product_id,
                    'anar_sku' => $anar_sku
                ];
            }
        }

        // Check if there are more outdated products
        $total_outdated_query = new \WP_Query(array_merge($args, ['posts_per_page' => -1]));
        $total_outdated_count = $total_outdated_query->found_posts;
        $has_more = $total_outdated_count > count($products);

        return [
            'products' => $products,
            'total_products' => $total_outdated_count,
            'has_more' => $has_more
        ];
    }



    /**
     * Process a batch of outdated products
     *
     * @param array $products Array of product data
     * @return array Processed product details
     */
    private function process_outdated_products_batch($products) {
        $processed = [];
        $deprecated_counter = 0;

        foreach ($products as $product_data) {
            try {
                // Construct API URL for specific product
                $apiUrl = "https://api.anar360.com/wp/products/{$product_data['anar_sku']}";

                // Attempt to fetch product from Anar API
                $api_response = ApiDataHandler::callAnarApi($apiUrl);

                // Check if it's a WordPress error
                if (is_wp_error($api_response)) {
                    $this->log(
                        "WP Error for SKU {$product_data['anar_sku']}: " .
                        $api_response->get_error_message(),
                        'error'
                    );

                    $processed[] = [
                        'id' => $product_data['ID'],
                        'sku' => $product_data['anar_sku'],
                        'status' => 'wp_error',
                        'message' => $api_response->get_error_message()
                    ];
                    continue;
                }

                // Parse the response body
                $data = json_decode($api_response['body']);
                $response_body = wp_remote_retrieve_body($api_response);
                $parsed_body = json_decode($response_body, true);

                // Get the HTTP response code
                $response_code = wp_remote_retrieve_response_code($api_response);

                // Handle different response scenarios
                if ($response_code == 200) {
                    // Successful response - update product
                    $this->log('Try to update product #' . $product_data['ID']);
                    $this->update_product_from_anar($data);

                    $processed[] = [
                        'id' => $product_data['ID'],
                        'sku' => $product_data['anar_sku'],
                        'status' => 'updated'
                    ];

                } elseif ($response_code == 404 ||
                    (isset($parsed_body['statusCode']) && $parsed_body['statusCode'] == 404)) {
                    // Product not found - deprecate it (remove sku to prevent sell)
                    ProductManager::set_product_out_of_stock($product_data['ID'], 'sync_outdated_job', 'sync', true);
                    $deprecated_counter++;

                    $processed[] = [
                        'id' => $product_data['ID'],
                        'sku' => $product_data['anar_sku'],
                        'status' => 'out_of_stock',
                        'reason' => $parsed_body['message'] ?? 'Product not found'
                    ];

                } else {
                    // Other error scenarios
                    $this->log(
                        "Unexpected API response for SKU {$product_data['anar_sku']}: " .
                        "Code: {$response_code}, Message: " .
                        json_encode($parsed_body),
                        'error'
                    );

                    $processed[] = [
                        'id' => $product_data['ID'],
                        'sku' => $product_data['anar_sku'],
                        'status' => 'error',
                        'code' => $response_code,
                        'message' => $parsed_body['message'] ?? 'Unknown error'
                    ];
                }
            } catch (\Exception $e) {
                // Log any unexpected exceptions
                $this->log(
                    "Exception processing SKU {$product_data['anar_sku']}: " . $e->getMessage(),
                    'error'
                );

                $processed[] = [
                    'id' => $product_data['ID'],
                    'sku' => $product_data['anar_sku'],
                    'status' => 'exception',
                    'message' => $e->getMessage()
                ];
            }
        }

        if($deprecated_counter > 0) {
            update_option('awca_deprecated_products_count', $deprecated_counter);
            update_option('awca_deprecated_products_time', current_time('mysql'));
        }
        return $processed;
    }



    public function process_outdated_products_job() {
        // Check if another sync process is already running
        if ($this->isSyncInProgress()) {
            $current_lock = get_transient('awca_sync_outdated_lock');
            $this->log("Another sync process is already running (JobID: {$current_lock}). Skipping this execution.");
            return;
        }

        $start_time = time();
        $max_execution_time = $this->max_execution_time;
        $batch_size = 30; // Number of products per batch
        $total_processed = 0;

        $this->set_jobID();
        $this->lockSync();
        $this->setStartTime();

        try {
            $is_complete = false;

            while (!$is_complete) {
                // Check if we're approaching the execution time limit
                if (time() - $start_time > $max_execution_time) {
                    $this->log("Approaching execution time limit after processing {$total_processed} products.");

                    // Schedule an immediate follow-up job to continue processing
                    $this->schedule_immediate_followup();

                    $this->log("Scheduled immediate follow-up to continue processing remaining products.");
                    break;
                }

                // Get a batch of outdated products
                $outdated_products = $this->get_outdated_products($batch_size);

                if (!empty($outdated_products['products'])) {
                    $batch_result = $this->process_outdated_products_batch($outdated_products['products']);
                    $total_processed += count($batch_result);
                    $this->log("Processed batch of " . count($batch_result) . " products. Total processed: {$total_processed}");
                }

                if (!$outdated_products['has_more']) {
                    // No more outdated products
                    $is_complete = true;
                    $this->setLastSyncTime();
                    $this->log("All products synchronized successfully. Total processed: {$total_processed}");
                }
            }
        } catch (\Exception $e) {
            $this->log("Error during sync process: " . $e->getMessage() . "\n" . $e->getTraceAsString(), 'error');
        } finally {
            $this->unlockSync();
            $this->logTotalTime();
        }
    }


    /**
     * Schedule an immediate follow-up job to continue processing
     */
    private function schedule_immediate_followup() {
        // Clear any existing scheduled events
        wp_clear_scheduled_hook('anar_process_outdated_products_continued');

        // Schedule the event to run as soon as possible
        wp_schedule_single_event(time() + 10, 'anar_process_outdated_products_continued');
    }

    /**
     * Hook for the continued processing of outdated products
     */
    public function continue_outdated_products_processing() {
        $this->log("Continuing outdated products processing from previous execution");
        $this->process_outdated_products_job();
    }


    private function set_jobID(){
        $fresh_unique_jobID = uniqid('anar_sync_outdated_job_');
        $this->jobID = $fresh_unique_jobID;
    }


    private function isSyncInProgress() {
        return get_transient('awca_sync_outdated_lock');
    }

    private function lockSync() {
        $this->log("lock syncing outdated products... jobID:" . $this->jobID);
        set_transient('awca_sync_outdated_lock', $this->jobID, 300); // Lock for 5 minutes
    }

    private function unlockSync() {
        $this->log("unlock syncing ".$this->jobID."...");
        delete_transient('awca_sync_outdated_lock');
    }


    public function setLastSyncTime() {
        update_option('anar_last_sync_outdated_time', current_time('mysql'));
    }

    public function getLastSyncTime() {
        return get_option('anar_last_sync_outdated_time');
    }

    public function setStartTime() {
        $this->startTime = current_time('mysql');
    }

    public function getStartTime(){
        return $this->startTime;
    }

    // Calculate and log total time taken for sync process
    public function logTotalTime() {
        $elapsedTime = strtotime(current_time('mysql')) - strtotime($this->getStartTime());
        $minutes = round($elapsedTime / 60, 2);
        $this->log("## Sync outdated done. Total sync time: {$minutes} minute(s).");
        $this->log('-------------------------------- End Sync '.$this->jobID.' --------------------------------');
    }
}

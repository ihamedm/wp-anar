<?php

namespace Anar;

use Anar\Core\Activation;
use Anar\Core\Logger;
use Anar\Wizard\ProductManager;

class Sync {
    private static $instance;
    private $baseApiUrl;
    private $limit;
    private $page;
    private $syncedCounter;
    private $startTime;
    private $jobID;
    private $logger;
    public $triggerBy;
    public $max_execution_time;

    /**
     * time in milliseconds
     * @var
     */
    private $updatedSince;

    public static function get_instance() {
        if ( ! isset( self::$instance ) ) {
            self::$instance = new self();
        }
        return self::$instance;
    }


    public function __construct() {
        $this->baseApiUrl = 'https://api.anar360.com/wp/products';
        $this->limit = 100;
        $this->page = 1;
        $this->syncedCounter = 0;
        $this->triggerBy = 'Cronjob';
        $this->max_execution_time = 240; // 4 minutes (to stay safely under 5 min limit)

        $this->logger = new Logger();

        // query for products that updated since 10 min ago
        $this->updatedSince = 10 * 60000;

        // Only register AJAX hooks if not in a WP-Cron context
        if (!defined('DOING_CRON') || !DOING_CRON) {
            // Hooking the method into AJAX actions
            add_action('wp_ajax_awca_sync_products_price_and_stocks', array($this, 'syncProductsPriceAndStocksAjax'));
        }

        // Register cron schedule and hook
        add_filter('cron_schedules', array($this, 'add_cron_interval'));
        add_action('anar_sync_products', array($this, 'run_sync_cron'));

        $this->schedule_cron();
    }

    /**
     * Add custom cron interval
     */
    public function add_cron_interval($schedules) {
        $schedules['anar_sync_interval'] = array(
            'interval' => 600,
            'display'  => 'هر ۱۰ دقیقه'
        );
        return $schedules;
    }

    /**
     * Run sync process via cron
     */
    public function run_sync_cron() {
        $this->triggerBy = 'Cronjob';
        $this->limit = 100;
        $this->syncProducts();
    }


    /**
     * Schedule the cron jobs if not already scheduled
     */
    public function schedule_cron() {
        if (!wp_next_scheduled('anar_sync_products')) {
            wp_schedule_event(time(), 'anar_sync_interval', 'anar_sync_products');
        }
    }

    /**
     * Unschedule the cron jobs
     */
    public function unschedule_cron() {
        $timestamp = wp_next_scheduled('anar_sync_products');
        if ($timestamp) {
            wp_unschedule_event($timestamp, 'anar_sync_products');
        }
    }

    public function syncProducts($ajax_call = false) {

        if(!Activation::is_active()){
            $this->log('Sync Products is Stopped!! Anar is not active, Token is invalid!');
            return;
        }

        if ($this->isSyncInProgress()) {
            // Keep this log as it's an early exit condition
            $this->log('Sync job already in progress, exiting to prevent overlap.');
            return;
        }

        if(awca_is_import_products_running()){
             // Keep this log as it's an early exit condition
            $this->log('Importing in progress, skipping sync.');
            return;
        }


        // Set Job ID early to use in logs
        $this->set_jobID();

        // Capture the start time before beginning the sync process
        $this->setStartTime();

        $this->lockSync(); // Lock after setting Job ID and start time

        $completed = false; // Initialize completion status

        try {
            $this->log(':: Start Sync Job: '.$this->jobID);
            $this->log('## Triggered by [ '.$this->triggerBy.' ] ' );


            $completed = $this->processPages();

            // Only perform final cleanup if truly complete
            if ($completed) {
                $this->log("Sync job {$this->jobID} completed processing pages.");
                $this->setLastSyncTime();
            } else {
                 $this->log("Sync job {$this->jobID} did not complete all pages in this run.");
            }

        } finally {
            // Log the total time and finish marker *before* unlocking
            $totalTimeMessage = $this->getTotalTimeMessage(); // Get the formatted time message
            $status = $completed ? 'Completed' : 'Finished Run (Incomplete)';
            $this->log(':: '.$status.' Sync Job: '.$this->jobID.' ('.$totalTimeMessage.') ');

            $this->unlockSync();

            if($ajax_call){
                $response = array(
                    'success' => true,
                    'message' => sprintf('همگام سازی %s محصول با موفقیت انجام شد', $this->syncedCounter),
                );
                wp_send_json($response);
            }

        }
    }


    private function processPages() {
        $start_time = time();
        $max_execution_time = $this->max_execution_time;
        $syncCompleted = false;
        
        // Increase batch size for full sync to reduce API calls
        $batch_size = $this->limit;
        
        while (true) {
            // Check if we're approaching the execution time limit
            if (time() - $start_time > $max_execution_time) {
                $this->log("Regular sync approaching execution time limit. Consider optimizing or reducing the updatedSince window.", 'warning');
                break;
            }

            $api_args = array(
                'page' => $this->page, 
                'limit' => $batch_size, 
                'since' => $this->updatedSince * -1
            );


            $apiUrl = add_query_arg($api_args, $this->baseApiUrl);
            $awcaProducts = ApiDataHandler::tryGetAnarApiResponse($apiUrl);

            if (!$awcaProducts) {
                $this->log('we have a problem to get products data from Anar API!', 'error');
                break;
            }

            if (is_wp_error($awcaProducts)) {
                $this->log('Failed to fetch products from API: ' . $awcaProducts->get_error_message(), 'error');
                break;
            }

            if (empty($awcaProducts->items)) {
                $this->log('we dont have any products on this page');
                break;
            }

            if($this->page == 1){
                $this->log(sprintf('## Find %s products for update.' , $awcaProducts->total));
            }

            if (count($awcaProducts->items)  == 0 ) {
                $this->log('we dont have any items, its last page.');
                $syncCompleted = true;
                break;
            }

            // Process products in smaller chunks to avoid memory issues
            $chunk_size = 50;
            $items = array_chunk($awcaProducts->items, $chunk_size);
            
            foreach ($items as $chunk) {
                $this->processProducts($chunk);
            }

            $this->syncedCounter += count($awcaProducts->items);
            $this->page++;

        }

        return $syncCompleted;
    }

    public function processProducts($products) {
        $processed_products_ids = [];
        $batch_meta_updates = [];
        $current_time = current_time('mysql');

        foreach ($products as $updateProduct) {
            try {
                if (isset($updateProduct->attributes) && !empty($updateProduct->attributes)) {
                    $updated_wc_id = $this->processVariableProduct($updateProduct);
                    $processed_products_ids[] = 'v#' . $updated_wc_id;
                } else {
                    $updated_wc_id = $this->processSimpleProduct($updateProduct);
                    $processed_products_ids[] = '#' . $updated_wc_id;
                }

                // Prepare batch meta updates
                if ($updated_wc_id && is_numeric($updated_wc_id)) {
                    $batch_meta_updates[] = array(
                        'post_id' => $updated_wc_id,
                        'meta_key' => '_anar_last_sync_time',
                        'meta_value' => $current_time
                    );

                    $batch_meta_updates[] = array(
                        'post_id' => $updated_wc_id,
                        'meta_key' => '_anar_pending',
                        'meta_value' => '',
                        'delete' => true
                    );
                }

                $this->log(sprintf('#%s', $updated_wc_id), 'debug');

            } catch (\Exception $e) {
                $this->log("Error processing product: " . $e->getMessage(), 'error');
                continue;
            }
        }

        // Batch update meta values
        if (!empty($batch_meta_updates)) {
            foreach ($batch_meta_updates as $update) {
                if (isset($update['delete']) && $update['delete']) {
                    delete_post_meta($update['post_id'], $update['meta_key']);
                } else {
                    update_post_meta($update['post_id'], $update['meta_key'], $update['meta_value']);
                }
            }
        }

        $this->log(sprintf("%s - Sync %s Products from page %s",
            $this->jobID,
            count($processed_products_ids),
            $this->page
        ));
    }


    public function processSimpleProduct($updateProduct, $wc_product_id = '',  $full = false) {
        try {
            if (!is_array($updateProduct->variants) || empty($updateProduct->variants)) {
                throw new \Exception("No variants found in the product data");
            }

            $sku = $updateProduct->id;

            if (empty($sku)) {
                throw new \Exception("Product ID/SKU is missing");
            }

            if($wc_product_id != '' && is_numeric($wc_product_id)){
                $productId = $wc_product_id;
            }else{
                $productId = ProductData::get_simple_product_by_anar_sku($sku);
            }

            if (is_wp_error($productId)) {
                throw new \Exception($productId->get_error_message());
            }else{
                try {
                    $variant = $updateProduct->variants[0];
                    $product = wc_get_product($productId);

                    if (!$product) {
                        throw new \Exception("Failed to get WooCommerce product with ID: {$productId}");
                    }

                    $this->updateProductStockAndPrice($product, $updateProduct, $variant, $productId);

                    $this->updateProductMetadata($productId, $variant);
                    $this->updateProductVariantMetaData($productId, $variant);

                    if($full){
                        $this->updateProductShipments($productId, $updateProduct);
                    }


                    return $productId;
                } catch (\Exception $e) {
                    $this->log("Error updating product {$sku}: " . $e->getMessage(), 'error');
                    return "Error updating product {$sku}: " . $e->getMessage();
                }
            }

            return sprintf("simple product %s not found in wp", $updateProduct->id);
        } catch (\Exception $e) {
            $this->log("Error processing simple product: " . $e->getMessage(), 'error');
            return "Error processing simple product: " . $e->getMessage();
        }
    }


    public function processVariableProduct($anarProduct, $wc_product_id = '', $full = false) {
        $wp_variation_productId = '';
        $parentId = 0;

        try {
            if (!is_array($anarProduct->variants) || empty($anarProduct->variants)) {
                throw new \Exception("No variants found in the product data");
            }

            if($wc_product_id != '' && is_numeric($wc_product_id)){
                $wc_parent_product_id = $wc_product_id;
            }else{
                $wc_parent_product_id = ProductData::get_simple_product_by_anar_sku($anarProduct->id);
            }

            // first set outofstock all wc_product variation, because maybe some variant on anar removed buy reseller
            if (is_wp_error($wc_parent_product_id)) {
                throw new \Exception($wc_parent_product_id->get_error_message());
            }

            $wc_parent_product = wc_get_product($wc_parent_product_id);
            $variations = $wc_parent_product->get_children();
            $exist_anar_variation_ids = [];
            foreach ($variations as $wc_variation_product_id) {
                ProductManager::set_product_variation_out_of_stock($wc_variation_product_id);
                $anar_variation_id = get_post_meta($wc_variation_product_id, '_anar_variant_id', true);
                if($anar_variation_id)
                    $exist_anar_variation_ids[$anar_variation_id] = $wc_variation_product_id;
            }

            // update variants
            foreach ($anarProduct->variants as $variant) {
                try {
                    if (empty($variant->_id)) {
                        continue; // Skip variants without ID
                    }

                    $anar_variation_id = $variant->_id;
                    if(in_array($anar_variation_id, array_keys($exist_anar_variation_ids))) {
                        $wp_variation_productId = $exist_anar_variation_ids[$anar_variation_id];
                    }else{
                        $wp_variation_productId = ProductData::get_product_variation_by_anar_variation($anar_variation_id);
                    }
                    if (is_wp_error($wp_variation_productId)) {
                        if($wp_variation_productId->get_error_code() == 404){
                            // @todo create the variation
                        }else{
                            throw new \Exception($wp_variation_productId->get_error_message());
                        }

                    }else{
                        $product = wc_get_product($wp_variation_productId);
                        if (!$product) {
                            throw new \Exception("Failed to get WooCommerce product variation with ID: {$wp_variation_productId}");
                        }

                        // Store parent ID for later use
                        if ($parentId == 0) {
                            $parentId = $product->get_parent_id();
                        }

                        $this->updateProductStockAndPrice($product, $anarProduct, $variant, $parentId);
                        $this->updateProductMetadata($parentId, $variant);
                        $this->updateProductVariantMetaData($wp_variation_productId, $variant);

                        if($full){
                            $this->updateProductShipments($parentId, $anarProduct);
                        }
                    }
                } catch (\Exception $e) {
                    $this->log("Error processing variant {$anar_variation_id}: " . $e->getMessage(), 'debug');
                    // Continue processing other variants
                }
            }

            if ($wp_variation_productId) {
                try {
                    $wc_var_item = wc_get_product($wp_variation_productId);
                    if ($wc_var_item) {
                        return $wc_var_item->get_parent_id();
                    }
                } catch (\Exception $e) {
                    $this->log("Error getting parent product for {$wp_variation_productId}: " . $e->getMessage(), 'error');
                }
            }

            return sprintf("variable product %s not found in wp", isset($anarProduct->id) ? $anarProduct->id : 'unknown');
        } catch (\Exception $e) {
            $this->log("Error processing variable product: " . $e->getMessage(), 'error');
            return "Error processing variable product: " . $e->getMessage();
        }
    }


    /**
     * Updates the stock and price for a WooCommerce product based on Anar API data.
     * Assumes 'labelPrice' from API is the Regular Price and 'price' is the Sale Price when applicable.
     *
     * @param \WC_Product $product The WooCommerce product object (simple or variation).
     * @param object $updateProduct The parent product data from Anar API (contains resellStatus).
     * @param object $variant The specific variant data from Anar API (contains stock, price, labelPrice).
     * @return void
     */
    public function updateProductStockAndPrice($product, $updateProduct, $variant, $parentId) {
        awca_log('updateProductStockAndPrice');
        // Update stock
       $variantStock = (isset($updateProduct->resellStatus) && $updateProduct->resellStatus == 'editing-pending') ? 0 : (isset($variant->stock) ? $variant->stock : 0);

        // temporary patch : check if shipments not set stock to zero
        if(empty($updateProduct->shipmentsReferenceId) || empty($updateProduct->shipments)) {
            $variantStock = 0;
            awca_log("shipments is empty for #" . $product->get_id() ." so set as out-of-stock");
        }

        $product->set_stock_quantity($variantStock);
        // Set stock status based on quantity (optional but good practice)
        $product->set_manage_stock(true);
        if ($variantStock > 0) {
            $product->set_stock_status('instock');
        } else {
            $product->set_stock_status('outofstock');
        }


        // Get potential regular and sale prices from API variant data
        $apiPrice = $variant->price ?? null; // Potential Sale Price
        $apiLabelPrice = $variant->labelPrice ?? null; // Potential Regular Price

        // Convert prices to WooCommerce currency (handle nulls gracefully)
        $wcPrice = ($apiPrice !== null) ? awca_convert_price_to_woocommerce_currency($apiPrice) : null;
        $wcLabelPrice = ($apiLabelPrice !== null) ? awca_convert_price_to_woocommerce_currency($apiLabelPrice) : null;

        // Determine prices based on API values provided
        if ($wcLabelPrice !== null && $wcLabelPrice > 0 && $wcPrice !== null && $wcLabelPrice > $wcPrice) {
            // Scenario 1: Both labelPrice and price exist, and labelPrice > price.
            // Treat labelPrice as Regular Price, price as Sale Price.
            $finalRegularPrice = $wcLabelPrice;
            $finalSalePrice = $wcPrice;
            $finalActivePrice = $wcPrice; // Active price is the sale price
        } elseif ($wcPrice !== null) {
            // Scenario 2: Only price exists (or labelPrice is not valid for a sale scenario).
            // Treat price as the Regular Price, no Sale Price.
            $finalRegularPrice = $wcPrice;
            $finalSalePrice = ''; // Ensure sale price is empty
            $finalActivePrice = $wcPrice; // Active price is the regular price
        } else {
             // Scenario 3: Neither price is valid (or both are 0/null). Set empty prices.
             // This might happen if a product is temporarily unavailable or has no price set in Anar.
             $finalRegularPrice = '';
             $finalSalePrice = '';
             $finalActivePrice = '';
             // Optionally log a warning here if prices are expected but missing/invalid
             $this->log("Warning: Product ID {$product->get_id()} received invalid/missing price data from Anar (Price: {$apiPrice}, LabelPrice: {$apiLabelPrice}). Setting empty prices.", 'warning');
        }

        // Set WooCommerce product prices
        $product->set_regular_price($finalRegularPrice);
        $product->set_sale_price($finalSalePrice);
        $product->set_price($finalActivePrice); // Set the active price correctly

        // --- Save Product ---
        $product->save();
        
        wp_cache_delete($product->get_id(), 'post_meta');

        // Clear all relevant caches
        wp_cache_delete($product->get_id(), 'posts');
        wp_cache_delete($product->get_id(), 'product');
        
        // Clear WooCommerce specific caches
        if (function_exists('wc_delete_product_transients')) {
            wc_delete_product_transients($product->get_id());
        }
        
        // Clear object cache for the product
        clean_post_cache($product->get_id());
    }

    /**
     * @param $wcProductParentId
     * @param $variant
     * @return void
     */
    public function updateProductMetadata($wcProductParentId, $variant) {
        update_post_meta($wcProductParentId, '_anar_last_sync_time', current_time('mysql'));
        delete_post_meta($wcProductParentId, '_anar_pending');
    }


    /**
     *
     * used for both simple product and variant of variable product
     *
     * @param $wcProductVariantId
     * @param $variant
     * @return void
     */
    public function updateProductVariantMetaData($wcProductVariantId, $variant) {
        update_post_meta($wcProductVariantId, '_anar_prices',
            [
                'price' => $variant->price,
                'labelPrice' => $variant->labelPrice,
                'priceForResell' => $variant->priceForResell,
                'resellerProfit' => $variant->resellerProfit,
                'sellerDiscount' => $variant->sellerDiscount,
                'minPriceForResell' => $variant->minPriceForResell,
                'maxPriceForResell' => $variant->maxPriceForResell,
            ]
        );
    }

    public function updateProductShipments($wcProductParentId, $anarProduct)
    {
        $prepared_product = ProductManager::product_serializer($anarProduct);
        if(isset($prepared_product['shipments']) && isset($prepared_product['shipments_ref'])){
            update_post_meta($wcProductParentId, '_anar_shipments', $prepared_product['shipments']);
            update_post_meta($wcProductParentId, '_anar_shipments_ref', $prepared_product['shipments_ref']);
        }
    }



    private function set_jobID(){
        $job_prefix = 'anar_'.$this->triggerBy;
        $fresh_unique_jobID = uniqid($job_prefix . '_sync_job_');
        $this->jobID = $fresh_unique_jobID;

    }


    private function log($message, $level = 'info') {
        $this->logger->log($message, 'sync', $level);
    }

    private function isSyncInProgress() {
        return get_transient('awca_sync_all_products_lock');
    }

    private function lockSync() {
        $this->log("lock syncing ".$this->jobID."...");
        set_transient('awca_sync_all_products_lock', $this->jobID, 300); // Lock for 5 minutes
    }

    private function unlockSync() {
        $this->log("unlock syncing ".$this->jobID."...");
        delete_transient('awca_sync_all_products_lock');
    }


    public function setLastSyncTime() {
        update_option('anar_last_sync_time', current_time('mysql'));
    }

    public function getLastSyncTime() {
        return get_option('anar_last_sync_time');
    }

    public function setStartTime() {
        $this->startTime = current_time('mysql');
    }

    public function getStartTime(){
        return $this->startTime;
    }

    /**
     * Calculates the total execution time and returns a formatted message string.
     *
     * @return string Formatted total time message.
     */
    private function getTotalTimeMessage() {
        if ($this->startTime) {
            $endTime = microtime(true);
            $executionTime = round($endTime - $this->startTime, 2);
            return "Total Time: {$executionTime}s";
        }
        return "Total Time: N/A";
    }



    // AJAX handler for syncing products price and stocks
    public function syncProductsPriceAndStocksAjax() {
        $this->triggerBy = 'Manual';
        $this->syncProducts(true);

    }


}


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

    public $fullSync = false;

    public $restBetweenFullSyncs; //seconds

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
        $this->restBetweenFullSyncs = get_option('anar_full_sync_schedule_hours', 6) * 3600;

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
        add_action('anar_full_sync_products', array($this, 'run_full_sync_cron'));

        $this->schedule_cron();
    }

    /**
     * Add custom cron interval
     */
    public function add_cron_interval($schedules) {
        $schedules['anar_sync_interval'] = array(
            'interval' => 600, // 10 minutes in seconds
            'display'  => 'هر ۱۰ دقیقه'
        );
        return $schedules;
    }

    /**
     * Run sync process via cron
     */
    public function run_sync_cron() {
        $this->triggerBy = 'Cronjob';
        $this->fullSync = false;
        $this->limit = 100;
        $this->syncProducts();
    }

    /**
     * Run full sync process via cron
     */
    public function run_full_sync_cron() {
        $this->triggerBy = 'FullSyncCronjob';
        $this->fullSync = true;
        $this->syncProducts();
    }

    /**
     * Schedule the cron jobs if not already scheduled
     */
    public function schedule_cron() {
        if (!wp_next_scheduled('anar_sync_products')) {
            wp_schedule_event(time(), 'anar_sync_interval', 'anar_sync_products');
        }
        if (!wp_next_scheduled('anar_full_sync_products')) {
            wp_schedule_event(time(), 'anar_sync_interval', 'anar_full_sync_products');
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
        
        $timestamp = wp_next_scheduled('anar_full_sync_products');
        if ($timestamp) {
            wp_unschedule_event($timestamp, 'anar_full_sync_products');
        }
    }

    public function syncProducts($ajax_call = false) {

        if(!Activation::is_active()){
            $this->log('Anar is not active, Token is invalid!');
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


        // only check cool down for fullSync jobs
        if($this->fullSync){

            $this->log('Full Sync Cronjob temporary disabled!');
            return;

            $lastSyncTime = $this->getLastSyncTime();
            if($lastSyncTime){
                $msSinceLastSync = strtotime(current_time('mysql')) - strtotime($lastSyncTime);
                if(!$this->haveActiveFullSync() && $msSinceLastSync < $this->restBetweenFullSyncs){
                    $minutes = round(($this->restBetweenFullSyncs - $msSinceLastSync ) / 60, 2);
                    set_transient('anar_since_next_full_sync', $minutes, 3600);
                    // Keep this log as it's an early exit condition
                    $this->log(
                        sprintf('Still %s Minutes left until the next full sync. Last sync: %s',
                            $minutes,
                            $lastSyncTime
                        )
                    );
                    return;
                }
            }
        }

        // Set Job ID early to use in logs
        $this->set_jobID();

        // Capture the start time before beginning the sync process
        $this->setStartTime();

        $this->lockSync(); // Lock after setting Job ID and start time

        $completed = false; // Initialize completion status

        try {
            // Log Start or Continue *after* locking
            if($this->fullSync && $this->haveActiveFullSync()){
                $this->log('-------------------------------- ' . ($this->haveActiveFullSync() ? 'Continue' : 'Start') . ' Sync Job: '.$this->jobID.' --------------------------------');
            }else{
                 $this->log('-------------------------------- Start Sync Job: '.$this->jobID.' --------------------------------');
                 if(!$this->fullSync)
                     $this->log('## Syncing products changed since '.($this->updatedSince / 60000).' minutes ago...');
            }
            $this->log('## Triggered by [ '.$this->triggerBy.' ] ' . ($this->fullSync ? ' [ Full Sync ]' : '') );


            $completed = $this->processPages();

            // Only perform final cleanup if truly complete
            if ($completed) {
                $this->log("Sync job {$this->jobID} completed processing pages.");
                $this->setLastSyncTime();

                if ($this->fullSync) {
                    // After processing all pages in a full sync, check for removed products
                    //ProductManager::handle_removed_products_from_anar('sync', $this->jobID);
                    $this->complete_full_sync_job(); // Mark the full sync job as complete if applicable
                }
            } else {
                 $this->log("Sync job {$this->jobID} did not complete all pages in this run.");
            }

        } finally {
            // Log the total time and finish marker *before* unlocking
            $totalTimeMessage = $this->getTotalTimeMessage(); // Get the formatted time message
            $status = $completed ? 'Completed' : 'Finished Run (Incomplete)';
            $this->log('-------------------------------- '.$status.' Sync Job: '.$this->jobID.' ('.$totalTimeMessage.') --------------------------------');

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
                if ($this->fullSync) {
                    $this->log("Approaching execution time limit, saving progress and will continue in next run.", 'warning');
                    // For full sync, we'll continue on the next run from this page
                    break;
                } else {
                    // For regular sync, log that we couldn't complete in one run
                    $this->log("Regular sync approaching execution time limit. Consider optimizing or reducing the updatedSince window.", 'warning');
                    break;
                }
            }

            if($this->fullSync){
                $last_page = get_transient($this->jobID .'_paged');

                if(!$last_page){
                    set_transient($this->jobID .'_paged', 1, 3600);
                    $this->page = 1;
                }else{
                    $this->page = $last_page;
                }
            }

            $api_args = array(
                'page' => $this->page, 
                'limit' => $batch_size, 
                'since' => $this->updatedSince * -1
            );

            if($this->fullSync)
                unset($api_args['since']);

            $apiUrl = add_query_arg($api_args, $this->baseApiUrl);
            $awcaProducts = $this->callAnarApi($apiUrl);

            if (is_wp_error($awcaProducts)) {
                $this->log('Failed to fetch products from API: ' . $awcaProducts->get_error_message(), 'error');
                return;
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
                
                // Check execution time after each chunk
                if (time() - $start_time > $max_execution_time) {
                    if ($this->fullSync) {
                        $this->log("Approaching execution time limit after processing chunk, saving progress.", 'warning');
                        break 2;
                    }
                }
            }

            $this->syncedCounter += count($awcaProducts->items);



            $this->page++;

            if($this->fullSync){
                set_transient($this->jobID .'_paged', $this->page, 3600);
            }
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

                    if ($this->fullSync) {
                        $batch_meta_updates[] = array(
                            'post_id' => $updated_wc_id,
                            'meta_key' => '_anar_sync_job_id',
                            'meta_value' => $this->jobID
                        );
                    }

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


    public function processSimpleProduct($updateProduct, $full = false) {
        try {
            if (!is_array($updateProduct->variants) || empty($updateProduct->variants)) {
                throw new \Exception("No variants found in the product data");
            }

            $sku = $updateProduct->id;

            if (empty($sku)) {
                throw new \Exception("Product ID/SKU is missing");
            }

            $productId = ProductData::get_simple_product_by_anar_sku($sku);

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


    public function processVariableProduct($updateProduct, $full = false) {
        $wp_variation_productId = '';
        $parentId = 0;

        try {
            if (!is_array($updateProduct->variants) || empty($updateProduct->variants)) {
                throw new \Exception("No variants found in the product data");
            }

            // first set outofstock all wc_product variation, because maybe some variant on anar removed buy reseller
            $wc_parent_product_id = ProductData::get_simple_product_by_anar_sku($updateProduct->id);

            if (is_wp_error($wc_parent_product_id)) {
                throw new \Exception($wc_parent_product_id->get_error_message());
            }

            $wc_parent_product = wc_get_product($wc_parent_product_id);
            $variations = $wc_parent_product->get_children();
            foreach ($variations as $variation_id) {
                ProductManager::set_product_variation_out_of_stock($variation_id);
            }


            // update variants
            foreach ($updateProduct->variants as $variant) {
                try {
                    if (empty($variant->_id)) {
                        continue; // Skip variants without ID
                    }

                    $anar_variation_id = $variant->_id;
                    $wp_variation_productId = ProductData::get_product_variation_by_anar_sku($anar_variation_id);
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

                        $this->updateProductStockAndPrice($product, $updateProduct, $variant, $parentId);
                        $this->updateProductMetadata($parentId, $variant);
                        $this->updateProductVariantMetaData($wp_variation_productId, $variant);

                        if($full){
                            $this->updateProductShipments($parentId, $updateProduct);
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

            return sprintf("variable product %s not found in wp", isset($updateProduct->id) ? $updateProduct->id : 'unknown');
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

        if ($this->fullSync) {
            update_post_meta($wcProductParentId, '_anar_sync_job_id', $this->jobID);
        }

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


    private function haveActiveFullSync(){
        return get_transient('anar_active_full_sync_jobID');
    }

    private function checkForAbandonedJobs() {
        $active_job_id = get_transient('anar_active_full_sync_jobID');
        if ($active_job_id) {
            $job_lock = get_transient('awca_full_sync_products_lock');
            if (!$job_lock) {
                // The job ID exists but the lock is gone - likely an abandoned job
                $this->log("Detected abandoned full sync job: {$active_job_id}. Resuming.");
                return $active_job_id;
            }
        }
        return false;
    }


    private function set_jobID(){
        $abandoned_job = $this->checkForAbandonedJobs();

        if ($this->fullSync && $abandoned_job) {
            $this->jobID = $abandoned_job;
            return;
        }

        $job_prefix = 'anar_'.$this->triggerBy;
        if($this->fullSync){
            $job_prefix .= '_full';
        }

        $fresh_unique_jobID = uniqid($job_prefix . '_sync_job_');
        $this->jobID = $fresh_unique_jobID;


        if($this->fullSync){
            // look for existence job to continue
            $exist_full_sync_jobID = $this->haveActiveFullSync();

            if($exist_full_sync_jobID){
                $this->jobID = $exist_full_sync_jobID;
            }else{
                set_transient('anar_active_full_sync_jobID', $fresh_unique_jobID, 3600);
            }
        }

    }


    private function complete_full_sync_job(){
        delete_transient('anar_active_full_sync_jobID');
        delete_transient($this->jobID .'_paged');
        delete_transient($this->jobID .'_start_time');
    }

    private function callAnarApi($apiUrl) {
        return ApiDataHandler::tryGetAnarApiResponse($apiUrl);
    }

    private function log($message, $level = 'info') {
        $prefix = $this->fullSync ? 'fullSync' : 'sync';
        $this->logger->log($message, $prefix, $level);
    }

    private function isSyncInProgress() {
        if ($this->fullSync) {
            return get_transient('awca_full_sync_products_lock');
        }
        return get_transient('awca_sync_all_products_lock');
    }

    private function lockSync() {
        $lock_name = $this->fullSync ? 'awca_full_sync_products_lock' : 'awca_sync_all_products_lock';
        $this->log("lock syncing ".$this->jobID."...");
        set_transient($lock_name, $this->jobID, 300); // Lock for 5 minutes
    }

    private function unlockSync() {
        $lock_name = $this->fullSync ? 'awca_full_sync_products_lock' : 'awca_sync_all_products_lock';
        $this->log("unlock syncing ".$this->jobID."...");
        delete_transient($lock_name);
    }


    public function setLastSyncTime() {
        $key = 'anar_last_' . ($this->fullSync ? 'full_' : '' ) . 'sync_time';
        update_option($key, current_time('mysql'));
    }

    public function getLastSyncTime() {
        $key = 'anar_last_' . ($this->fullSync ? 'full_' : '' ) . 'sync_time';
        return get_option($key);
    }

    public function setStartTime() {
        if($this->fullSync) {
            set_transient($this->jobID . '_start_time', current_time('mysql'), 3600);
        }else{
            $this->startTime = current_time('mysql');
        }
    }

    public function getStartTime(){
        if($this->fullSync) {
            return get_transient($this->jobID . '_start_time') ?: current_time('mysql');
        }else{
            return $this->startTime;
        }
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
        if(isset($_POST['full_sync']) && $_POST['full_sync'] == 'on') {
            $this->fullSync = true;
            $this->limit = 100;
            $this->restBetweenFullSyncs = 60;
        }

        $this->syncProducts(true);

    }


}


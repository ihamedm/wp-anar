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
        $this->restBetweenFullSyncs = get_option('anar_full_sync_schedule', 5) * 60;

        $this->logger = new Logger();

        // query for products that updated since 10 min ago
        $this->updatedSince = 10 * 60000;

        // Only register AJAX hooks if not in a WP-Cron context
        if (!defined('DOING_CRON') || !DOING_CRON) {
            // Hooking the method into AJAX actions
            add_action('wp_ajax_awca_sync_products_price_and_stocks', array($this, 'syncProductsPriceAndStocksAjax'));
        }
    }


    public function syncProducts() {

        if(!Activation::validate_saved_activation_key_from_anar())
            return;

        if ($this->isSyncInProgress()) {
            $this->log('Sync '.$this->jobID.' already in progress, exiting to prevent overlap.');
            return;
        }

        if(awca_is_import_products_running()){
            $this->log('Importing in progress, skipping sync.');
            return;
        }


        // only check cool down for fullSync jobs
        if($this->fullSync){
            $lastSyncTime = $this->getLastSyncTime();
            if($lastSyncTime){
                $msSinceLastSync = strtotime(current_time('mysql')) - strtotime($lastSyncTime);
                if(!$this->haveActiveFullSync() && $msSinceLastSync < $this->restBetweenFullSyncs){
                    $minutes = round(($this->restBetweenFullSyncs - $msSinceLastSync ) / 60, 2);
                    set_transient('anar_since_next_full_sync', $minutes, 3600);
                    $this->log(
                        sprintf('still %s Minutes left since next fullSync. %s',
                            $minutes,
                            $lastSyncTime
                        )
                    );
                    return;
                }
            }
        }


        $this->set_jobID();

        $this->lockSync();

        // Capture the start time before beginning the sync process
        $this->setStartTime();

        try {
            if($this->fullSync && $this->haveActiveFullSync()){
                $this->log('-------------------------------- Continue Sync '.$this->jobID.' --------------------------------');
            }else{
                $this->log('-------------------------------- Start Sync '.$this->jobID.' --------------------------------');

                if(!$this->fullSync)
                    $this->log('## Sync products that changes since '.($this->updatedSince / 60000).' minutes ago ...');
            }


            $this->log('## Trigger by [ '.$this->triggerBy.' ] ' . ($this->fullSync ? ' [ full sync ]' : '') );
            $completed = $this->processPages();

            // Only perform final cleanup if truly complete
            if ($completed) {
                // After processing all pages in a full sync, check for removed products
                if ($this->fullSync) {
                    ProductManager::handle_removed_products_from_anar('sync', $this->jobID);
                }
                $this->setLastSyncTime();
                $this->complete_full_sync_job();
            }

        } finally {
            $this->unlockSync();
            $this->logTotalTime(); // Log the total time at the end
        }
    }


    private function processPages() {
        $start_time = time();
        $max_execution_time = $this->max_execution_time;
        $syncCompleted = false;
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

            $api_args = array('page' => $this->page, 'limit' => $this->limit, 'since' => $this->updatedSince * -1);

            if($this->fullSync)
                unset($api_args['since']);

            $apiUrl = add_query_arg($api_args, $this->baseApiUrl);
            $awcaProducts = $this->callAnarApi($apiUrl);

            if (is_wp_error($awcaProducts)) {
                $this->log('Failed to fetch products from API: ' . $awcaProducts->get_error_message(), 'error');
                return;
            }

            if (empty($awcaProducts->items)) {
                break;
            }

            if($this->page == 1){
                $this->log(sprintf('## Find %s products for update.' , $awcaProducts->total));
            }

            $this->processProducts($awcaProducts->items);

            $this->syncedCounter += count($awcaProducts->items);

            if (count($awcaProducts->items) < $this->limit) {
                $this->log(sprintf('##items of this page is %s , its last page.' , count($awcaProducts->items)));
                $syncCompleted = true;
                break;
            }

            $this->page++;

            if($this->fullSync){
                set_transient($this->jobID .'_paged', $this->page, 3600);
            }

        }

        return $syncCompleted;
    }

    public function processProducts($products) {

        $processed_products_ids = [];

        foreach ($products as $updateProduct) {
            if (count($updateProduct->variants) == 1) {
                $updated_wc_id = $this->processSimpleProduct($updateProduct);
                $processed_products_ids [] = '#'.$updated_wc_id;
            } else {
                $updated_wc_id = $this->processVariableProduct($updateProduct);
                $processed_products_ids [] = 'v#'.$updated_wc_id;
            }


            $this->log(sprintf('#%s' , $updated_wc_id), 'debug');

        }

        $this->log(sprintf("%s - Sync %s Products from page %s",
            $this->jobID,
            count($processed_products_ids),
            $this->page,
            )
        );
    }

    public function processSimpleProduct($updateProduct) {
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

                    $this->updateProductStockAndPrice($product, $updateProduct, $variant);
                    $this->updateProductMetadata($productId, $variant);

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



    public function processVariableProduct($updateProduct) {
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

                        $this->updateProductStockAndPrice($product, $updateProduct, $variant);

                        // Store parent ID for later use
                        if ($parentId == 0) {
                            $parentId = $product->get_parent_id();
                        }

                        $this->updateProductMetadata($parentId, $variant);
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
     * @param \WC_Product $product
     * @param $updateProduct
     * @param $variant
     * @return void
     */
    public function updateProductStockAndPrice($product, $updateProduct, $variant) {
        $variantStock = (isset($updateProduct->resellStatus) && $updateProduct->resellStatus == 'editing-pending') ? 0 : (isset($variant->stock) ? $variant->stock : 0);
        $variantPrice = $variant->price ?? 0;

        if ($product) {
            $convertedPrice = awca_convert_price_to_woocommerce_currency($variantPrice);
            $product->set_stock_quantity($variantStock);

            if(get_option('anar_conf_feat__optional_price_sync', 'no') == 'no'){
                $product->set_price($convertedPrice);
                $product->set_regular_price($convertedPrice);
            }

            $product->save();
        }
    }

    /**
     * @param $wcProductParentId
     * @param $variant
     * @return void
     */
    public function updateProductMetadata($wcProductParentId, $variant) {
        update_post_meta($wcProductParentId, '_anar_last_sync_time', current_time('mysql'));
        update_post_meta($wcProductParentId, '_anar_prices',
            [
                'price' => $variant->price,
                'labelPrice' => $variant->labelPrice,
                'priceForResell' => $variant->priceForResell,
                'resellerProfit' => $variant->resellerProfit,
                'sellerDiscount' => $variant->sellerDiscount,
            ]
        );

        if ($this->fullSync) {
            update_post_meta($wcProductParentId, '_anar_sync_job_id', $this->jobID);
        }

        delete_post_meta($wcProductParentId, '_anar_pending');
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

    // Calculate and log total time taken for sync process
    public function logTotalTime() {
        $elapsedTime = strtotime(current_time('mysql')) - strtotime($this->getStartTime());
        $minutes = round($elapsedTime / 60, 2);
        $this->log("## Sync done. Total sync time: {$minutes} minute(s).");
        $this->log('-------------------------------- End Sync '.$this->jobID.' --------------------------------');
    }

    // AJAX handler for syncing products price and stocks
    public function syncProductsPriceAndStocksAjax() {
        $this->triggerBy = 'Manual';
        if(isset($_POST['full_sync']) && $_POST['full_sync'] == 'on') {
            $this->fullSync = true;
        }

        $this->syncProducts();

        $response = array(
            'success' => true,
            'message' => sprintf('همگام سازی %s محصول با موفقیت انجام شد', $this->syncedCounter),
        );
        wp_send_json($response);
    }


}


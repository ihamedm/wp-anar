<?php

namespace Anar\Sync;

use Anar\Core\Activation;
use Anar\Core\Logger;
use Anar\ProductData;
use Anar\Sync\ProductTransformation;
use Anar\Wizard\ProductManager;
use WP_Error;

/**
 * Class Sync
 *
 * Core synchronization class for syncing WooCommerce products with Anar360 API.
 * This class provides the base functionality for product synchronization including:
 * - Fetching product data from Anar API
 * - Validating products and activation status
 * - Processing simple and variable products
 * - Updating stock, prices, metadata, and shipments
 *
 * Extended by RealTimeSync, RegularSync, OutdatedSync, and ForceSync for different sync strategies.
 *
 * @package Anar\Sync
 * @since 0.6.0
 */
class Sync {
    /**
     * Base API URL for Anar360 products endpoint
     *
     * @var string
     */
    protected $baseApiUrl;

    /**
     * Logger instance for sync operations
     *
     * Protected so extended classes can use it directly for immediate logging.
     *
     * @var Logger
     */
    protected $logger;

    /**
     * Log collector array for accumulating logs during sync process
     *
     * @var array<string>
     */
    private $logCollector = [];

    /**
     * Meta key for storing last sync time
     *
     * @var string
     */
    const LAST_SYNC_META_KEY = '_anar_last_sync_time';

    /**
     * Default cooldown period between syncs in seconds
     *
     * @var int
     */
    const COOLDOWN_PERIOD = 10; // Seconds

    /**
     * Sync constructor
     *
     * Initializes the base API URL and logger instance.
     */
    public function __construct() {
        $this->logger = new Logger();
    }

    /**
     * Collects a log message for later output (does not print immediately)
     *
     * Logs are collected during the sync process and output only at the final step
     * to keep a clean, single-line log per product sync.
     *
     * For immediate logging (used by extended classes), use `$this->logger->log()` directly.
     *
     * @param string $message The message to collect
     * @param string $level Log level (info, error, warning, debug) - not used for collection but kept for consistency
     * @return void
     */
    protected function log($message, $level = 'info') {
        // Only collect important logs (skip debug level for cleaner logs)
        if ($level !== 'debug') {
            $this->logCollector[] = $message;
        }
    }

    /**
     * Adds a log entry directly to the collector
     *
     * @param string $message The log message to add
     * @return void
     */
    protected function addLog($message) {
        $this->logCollector[] = $message;
    }

    /**
     * Resets the log collector (call at start of each sync)
     *
     * @return void
     */
    protected function resetLogCollector() {
        $this->logCollector = [];
    }

    /**
     * Gets collected logs formatted as a single string
     *
     * @return string Combined logs separated by pipes
     */
    protected function getCollectedLogs() {
        if (empty($this->logCollector)) {
            return '';
        }
        return implode(' | ', $this->logCollector);
    }

    /**
     * Creates a new sync result array with default values
     *
     * @return array{
     *     updated: bool,
     *     product: array|int|string,
     *     status_code: string|int,
     *     message: string,
     *     logs?: string
     * }
     */
    protected function createSyncResult() {
        return [
            'updated' => false,
            'status_code' => '',
            'message' => '',
            'logs' => '',
        ];
    }

    /**
     * Checks if Anar plugin is activated and returns error result if not
     *
     * @return array|null Returns error result array if not active, null if active
     */
    protected function checkActivation() {
        if (!Activation::is_active()) {
            $this->addLog('Anar plugin not active or token invalid');
            $sync_result = $this->createSyncResult();
            $sync_result['status_code'] = 403;
            $sync_result['logs'] = $this->getCollectedLogs();
            $sync_result['message'] = 'پلاگین انار فعال نیست. لطفا به صفحه فعالسازی پلاگین مراجعه کنید.';
            return $sync_result;
        }
        return null;
    }

    /**
     * Core processing logic: applies transformation and processes product
     *
     * This is the common logic used by both syncProduct and syncProductByAnarProductData.
     * It handles product transformation, determines product type, and processes accordingly.
     *
     * @param \WC_Product $wc_product WooCommerce product object
     * @param object $anar_product_data Anar product data from API
     * @param int $wc_product_id WooCommerce product ID
     * @param bool $full_sync Whether to perform full sync including shipments
     * @return array{
     *     updated: bool,
     *     status_code: string,
     *     message: string,
     *     logs: string
     * } Sync result with product processing result
     */
    protected function processProductWithAnarData($wc_product, $anar_product_data, $wc_product_id, $full_sync) {
        $sync_result = $this->createSyncResult();

        // Step 1: Apply product transformation (type conversion if needed)
        $transformation_result = ProductTransformation::apply($wc_product, $anar_product_data, $wc_product_id, [$this, 'addLog']);
        
        if (!$transformation_result['success']) {
            // Transformation failed - return error
            $sync_result['status_code'] = 500;
            $sync_result['message'] = $transformation_result['message'] ?? 'خطا در تبدیل نوع محصول';
            return $sync_result;
        }

        // Step 2: Refresh product object after transformation (type may have changed)
        $wc_product = wc_get_product($wc_product_id);
        if (!$wc_product) {
            $this->addLog("Product #{$wc_product_id} not found after transformation");
            $sync_result['status_code'] = 404;
            $sync_result['message'] = 'محصول پس از تبدیل پیدا نشد!';
            return $sync_result;
        }

        // Step 3: Process product based on type (variable or simple)
        // Variable products have attributes, simple products don't
        if (!empty($anar_product_data->attributes)) {
            $process_result = $this->processVariableProduct($anar_product_data, $wc_product_id, $full_sync);
        } else {
            $process_result = $this->processSimpleProduct($anar_product_data, $wc_product_id, $full_sync);
        }

        // Step 4: Check if processing was successful
        // Both process methods return standardized array: ['success' => bool, 'product_id' => int, 'message' => string]
        if (!$process_result['success']) {
            // Processing failed
            $error_message = $process_result['message'] ?? 'Unknown error occurred during product processing';
            $this->addLog("Processing failed: {$error_message}");
            $sync_result['status_code'] = 500;
            $sync_result['message'] = $error_message;
            return $sync_result;
        }

        // Step 5: Build success result
        $processed_product_id = $process_result['product_id'] ?? $wc_product_id;
        $sync_result['updated'] = true;
        $sync_result['message'] = 'محصول با موفقیت آپدیت شد.';
        $this->addLog("Product #{$processed_product_id} updated successfully");

        return $sync_result;
    }


    /**
     * Syncs a WooCommerce product with Anar360 API
     *
     * Main sync method that handles the complete sync process:
     * 1. Validates activation status
     * 2. Validates WooCommerce product exists
     * 3. Validates product is an Anar product (has _anar_sku)
     * 4. Checks cooldown period to prevent too frequent syncs
     * 5. Fetches fresh product data from Anar API
     * 6. Processes and updates the product
     *
     * @param int $wc_product_id WooCommerce product ID to sync
     * @param array{
     *     sync_strategy?: string,
     *     full_sync?: bool,
     *     deprecate_on_faults?: bool,
     *     cooldown_period?: int
     * } $options Optional configuration array:
     *   - sync_strategy: Sync strategy identifier (default: 'regular_sync')
     *   - full_sync: Whether to perform full sync including shipments (default: true)
     *   - deprecate_on_faults: Whether to deprecate product if API fetch fails (default: false)
     *   - cooldown_period: Cooldown period in seconds between syncs (default: 10)
     * @return array{
     *     updated: bool,
     *     product: array|int|string,
     *     status_code: string|int,
     *     message: string,
     *     logs?: string
     * } Sync result array with success/error information
     * 
     * @example
     * // Simple usage (all defaults)
     * $result = $sync->syncProduct(123);
     * 
     * // With options
     * $result = $sync->syncProduct(123, [
     *     'sync_strategy' => 'realtime-sync',
     *     'full_sync' => true,
     *     'deprecate_on_faults' => false
     * ]);
     * 
     * // Legacy positional parameters still supported for backward compatibility
     * $result = $sync->syncProduct(123, 'realtime-sync', true, false);
     */
    public function syncProduct($wc_product_id, $options = []) {
        // Reset log collector at start of sync
        $this->resetLogCollector();

        // Handle legacy positional parameters for backward compatibility
        // If second parameter is not an array, treat it as old positional parameters
        if (!is_array($options)) {
            $arg_count = func_num_args();
            $options = [
                'sync_strategy' => $arg_count > 1 ? func_get_arg(1) : 'regular_sync',
                'full_sync' => $arg_count > 2 ? func_get_arg(2) : true,
                'deprecate_on_faults' => $arg_count > 3 ? func_get_arg(3) : false,
                'cooldown_period' => $arg_count > 4 ? func_get_arg(4) : self::COOLDOWN_PERIOD,
            ];
        }

        // Merge with defaults
        $options = array_merge([
            'sync_strategy' => 'regular_sync',
            'full_sync' => true,
            'deprecate_on_faults' => false,
            'cooldown_period' => self::COOLDOWN_PERIOD,
        ], $options);

        // Extract options for cleaner code
        $sync_strategy = $options['sync_strategy'];
        $full_sync = $options['full_sync'];
        $deprecate_on_faults = $options['deprecate_on_faults'];
        $cooldown_period = $options['cooldown_period'];

        $sync_result = $this->createSyncResult();

        // Validate activation status - exit early if plugin not activated
        $activation_check = $this->checkActivation();
        if ($activation_check !== null) {
            $sync_result['logs'] = $this->getCollectedLogs();
            return $activation_check;
        }

        // Validate WooCommerce product exists
        $wc_product = wc_get_product($wc_product_id);
        if(!$wc_product){
            $this->addLog("Product #{$wc_product_id} not found");
            $sync_result['status_code'] = 404;
            $sync_result['logs'] = $this->getCollectedLogs();
            $sync_result['message'] = 'محصول در دیتابیس وردپرس پیدا نشد!';
            return $sync_result;
        }

        // Validate product is an Anar product (has Anar SKU)
        $anar_sku = anar_get_product_anar_sku($wc_product_id);
        if (is_wp_error($anar_sku)) {
            // Not an Anar product - skip sync
            $this->addLog("Product #{$wc_product_id} is not an Anar product");
            $sync_result['status_code'] = 400;
            $sync_result['logs'] = $this->getCollectedLogs();
            $sync_result['message'] = 'به نظر میاد این محصول انار نیست!';
            return $sync_result;
        }

        // Check cooldown period - prevent too frequent syncs
        $last_sync_time = get_post_meta($wc_product_id, self::LAST_SYNC_META_KEY, true);
        if ($last_sync_time) {
            $last_sync_timestamp = strtotime($last_sync_time);
            $current_timestamp = current_time('timestamp');
            if (($current_timestamp - $last_sync_timestamp) < $cooldown_period) {
                $this->addLog("Product #{$wc_product_id} in cooldown period");
                $sync_result['status_code'] = 400;
                $sync_result['logs'] = $this->getCollectedLogs();
                $sync_result['message'] = "بین هر آپدیت محصول باید {$cooldown_period} ثانیه صبر کنید.";
                return $sync_result;
            }
        }

        // Fetch fresh product data from Anar API
        $anar_product_data = anar_fetch_product_data_by($anar_sku);
        if (is_wp_error($anar_product_data)) {
            // API fetch failed - deprecate product to prevent sales with outdated data
            ProductManager::set_product_as_deprecated($wc_product_id, $sync_strategy, $deprecate_on_faults);
            $this->addLog("API fetch failed for product #{$wc_product_id}: " . $anar_product_data->get_error_message());
            $this->addLog("Product set as deprecated");
            $sync_result['status_code'] = $anar_product_data->get_error_code();
            $sync_result['logs'] = $this->getCollectedLogs();
            $sync_result['message'] = $anar_product_data->get_error_message();
            return $sync_result;
        }

        // Process and update product using common logic
        $sync_result = $this->processProductWithAnarData($wc_product, $anar_product_data, $wc_product_id, $full_sync);
        
        // Add collected logs to result
        $sync_result['logs'] = $this->getCollectedLogs();
        
        return $sync_result;
    }


    /**
     * Syncs a product using pre-fetched Anar product data
     *
     * This method is used when Anar product data is already available (e.g., from bulk API calls).
     * It skips the API fetch step and directly processes the provided data.
     *
     * @param object $anar_product_data Pre-fetched Anar product data object
     * @param bool $full_sync Whether to perform full sync including shipments data
     * @return array{
     *     updated: bool,
     *     product: array|int|string,
     *     status_code: string|int,
     *     message: string,
     *     logs?: string
     * } Sync result array with success/error information
     */
    public function syncProductByAnarProductData($anar_product_data, $full_sync = true){
        // Reset log collector at start of sync
        $this->resetLogCollector();

        $sync_result = $this->createSyncResult();

        // Validate activation status
        $activation_check = $this->checkActivation();
        if ($activation_check !== null) {
            $sync_result['logs'] = $this->getCollectedLogs();
            return $activation_check;
        }

        // Find WooCommerce product by Anar SKU
        $wc_product_id = ProductData::get_simple_product_by_anar_sku($anar_product_data->id);

        if (is_wp_error($wc_product_id)) {
            // Product not found in WooCommerce
            $this->addLog("Product with Anar SKU {$anar_product_data->id} not found in WooCommerce");
            $sync_result['status_code'] = $wc_product_id->get_error_code();
            $sync_result['logs'] = $this->getCollectedLogs();
            $sync_result['message'] = 'محصول در دیتابیس وردپرس پیدا نشد!';
            return $sync_result;
        }

        // Get WooCommerce product object
        $wc_product = wc_get_product($wc_product_id);

        // Process and update product using common logic
        $sync_result = $this->processProductWithAnarData($wc_product, $anar_product_data, $wc_product_id, $full_sync);
        
        // Add collected logs to result
        $sync_result['logs'] = $this->getCollectedLogs();
        
        return $sync_result;
    }


    /**
     * Processes a simple WooCommerce product with Anar data
     *
     * Handles updating stock, prices, metadata, and shipments for simple products.
     * Simple products have only one variant (the first variant in the variants array).
     *
     * @param object $updateProduct Anar product data object
     * @param int $wc_product_id WooCommerce product ID
     * @param bool $full Whether to perform full sync including shipments
     * @return array{
     *     success: bool,
     *     product_id?: int,
     *     message?: string
     * } Processing result with success status, product ID, and optional error message
     */
    protected function processSimpleProduct($updateProduct, $wc_product_id,  $full = false) {
        try {
            // Validate product has variants
            if (!is_array($updateProduct->variants) || empty($updateProduct->variants)) {
                $error_msg = "No variants found in the product data";
                $this->addLog("Simple product processing failed: {$error_msg}");
                return [
                    'success' => false,
                    'message' => $error_msg
                ];
            }

            // Get product SKU for error reporting
            $sku = $updateProduct->id;

            if (empty($sku)) {
                $error_msg = "Product ID/SKU is missing";
                $this->addLog("Simple product processing failed: {$error_msg}");
                return [
                    'success' => false,
                    'message' => $error_msg
                ];
            }

            $productId = $wc_product_id;
            $this->addLog("Processing simple product #{$productId} (Anar SKU: {$sku})");

            try {
                // Simple products use the first (and only) variant
                $variant = $updateProduct->variants[0];
                $product = wc_get_product($productId);

                if (!$product) {
                    throw new \Exception("Failed to get WooCommerce product with ID: {$productId}");
                }

                $this->addLog("Updating stock and price for product #{$productId}");

                // Update core product data (stock and prices)
                $this->updateProductStockAndPrice($product, $updateProduct, $variant, $productId);

                // Update product metadata (sync time, pending status)
                $this->updateProductMetadata($productId, $variant);

                // Update variant-specific metadata (prices, profit, etc.)
                $this->updateProductVariantMetaData($productId, $variant);

                // Update shipments data if full sync is requested
                if($full){
                    $this->addLog("Performing full sync including shipments for product #{$productId}");
                    $this->updateProductShipments($productId, $updateProduct);
                }

                $this->addLog("Simple product #{$productId} processed successfully");
                return [
                    'success' => true,
                    'product_id' => $productId
                ];
            } catch (\Exception $e) {
                $error_msg = "Error updating product {$sku}: " . $e->getMessage();
                $this->addLog($error_msg);
                return [
                    'success' => false,
                    'message' => $error_msg
                ];
            }

        } catch (\Exception $e) {
            $error_msg = "Error processing simple product: " . $e->getMessage();
            $this->addLog($error_msg);
            return [
                'success' => false,
                'message' => $error_msg
            ];
        }
    }


    /**
     * Processes a variable WooCommerce product with Anar data
     *
     * Handles variable products with multiple variants. The process:
     * 1. Marks all existing variations as out of stock
     * 2. Maps Anar variant IDs to WooCommerce variation IDs
     * 3. Updates each variant that exists in Anar data
     * 4. Variants not in Anar data remain out of stock
     *
     * @param object $anarProduct Anar product data object
     * @param int $wc_product_id WooCommerce parent product ID
     * @param bool $full Whether to perform full sync including shipments
     * @return array{
     *     success: bool,
     *     product_id?: int,
     *     message?: string
     * } Processing result with success status, parent product ID, and optional error message
     */
    protected function processVariableProduct($anarProduct, $wc_product_id, $full = false) {
        $wp_variation_productId = '';
        $parentId = 0;

        try {
            // Validate product has variants
            if (!is_array($anarProduct->variants) || empty($anarProduct->variants)) {
                $error_msg = "No variants found in the product data";
                $this->addLog("Variable product processing failed: {$error_msg}");
                return [
                    'success' => false,
                    'message' => $error_msg
                ];
            }

            $wc_parent_product_id = $wc_product_id;
            $sku = isset($anarProduct->id) ? $anarProduct->id : 'unknown';
            $this->addLog("Processing variable product #{$wc_parent_product_id} (Anar SKU: {$sku}) with " . count($anarProduct->variants) . " variants");

            $wc_parent_product = wc_get_product($wc_parent_product_id);
            if (!$wc_parent_product) {
                $error_msg = "Failed to get WooCommerce parent product with ID: {$wc_parent_product_id}";
                $this->addLog("Variable product processing failed: {$error_msg}");
                return [
                    'success' => false,
                    'message' => $error_msg
                ];
            }

            $variations = $wc_parent_product->get_children();
            $exist_anar_variation_ids = [];
            $this->addLog("Found " . count($variations) . " existing variations for parent product #{$wc_parent_product_id}");

            // Step 1: Mark all existing variations as out of stock and build mapping
            // This ensures variants not in Anar data are marked unavailable
            foreach ($variations as $wc_variation_product_id) {
                ProductManager::set_product_variation_out_of_stock($wc_variation_product_id);
                $anar_variation_id = get_post_meta($wc_variation_product_id, '_anar_variant_id', true);
                if($anar_variation_id)
                    $exist_anar_variation_ids[$anar_variation_id] = $wc_variation_product_id;
            }

            $this->addLog("Built mapping for " . count($exist_anar_variation_ids) . " existing Anar variations");

            // Step 2: Process each variant from Anar data
            $processed_count = 0;
            $skipped_count = 0;
            $error_count = 0;

            foreach ($anarProduct->variants as $variant) {
                try {
                    // Skip variants without ID
                    if (empty($variant->_id)) {
                        $skipped_count++;
                        $this->addLog("Skipping variant without ID");
                        continue;
                    }

                    $anar_variation_id = $variant->_id;

                    // Find corresponding WooCommerce variation
                    // Check existing mapping first, then query database
                    if(in_array($anar_variation_id, array_keys($exist_anar_variation_ids))) {
                        $wp_variation_productId = $exist_anar_variation_ids[$anar_variation_id];
                        $this->addLog("Found existing variation #{$wp_variation_productId} for Anar variant ID: {$anar_variation_id}");
                    }else{
                        $wp_variation_productId = ProductData::get_product_variation_by_anar_variation($anar_variation_id);
                        if (!is_wp_error($wp_variation_productId)) {
                            $this->addLog("Found variation #{$wp_variation_productId} for Anar variant ID: {$anar_variation_id} via database query");
                        }
                    }

                    if (is_wp_error($wp_variation_productId)) {
                        // @todo Variation doesn't exist so create the variation
                        // its needed to refactor and extend ProductManager::create_product_variations and to create a single variation
                        // so we can pass a variant data and parent_wc_id to it and create new variation
                        $error_count++;
                        $this->addLog("Anar variation ID {$anar_variation_id} not found in WooCommerce - " . $wp_variation_productId->get_error_message());

                    }else{
                        // Variation exists - update it
                        $product = wc_get_product($wp_variation_productId);
                        if (!$product) {
                            $error_count++;
                            $this->addLog("Failed to get WooCommerce product variation with ID: {$wp_variation_productId}");
                            continue;
                        }

                        // Store parent ID for later use (needed for metadata updates)
                        if ($parentId == 0) {
                            $parentId = $product->get_parent_id();
                            $this->addLog("Parent product ID determined: {$parentId}");
                        }

                        // Update variation data
                        $this->updateProductStockAndPrice($product, $anarProduct, $variant, $parentId);
                        $this->updateProductMetadata($parentId, $variant);
                        $this->updateProductVariantMetaData($wp_variation_productId, $variant);

                        // Update shipments if full sync requested
                        if($full){
                            $this->updateProductShipments($parentId, $anarProduct);
                        }

                        $processed_count++;
                        $this->addLog("Updated variation #{$wp_variation_productId} successfully");
                    }
                } catch (\Exception $e) {
                    $error_count++;
                    $this->addLog("Error processing variant {$anar_variation_id}: " . $e->getMessage());
                    // Continue processing other variants
                }
            }

            // Validate we have a valid parent ID
            if ($parentId == 0 && !empty($variations)) {
                // Try to get parent ID from first variation if we haven't set it yet
                $first_variation = wc_get_product($variations[0]);
                if ($first_variation) {
                    $parentId = $first_variation->get_parent_id();
                }
            }

            if ($parentId == 0) {
                $error_msg = "Could not determine parent product ID for variable product";
                $this->addLog("Variable product processing failed: {$error_msg}");
                return [
                    'success' => false,
                    'message' => $error_msg
                ];
            }

            // Return success if we processed at least one variation or if no variations exist
            if ($processed_count > 0 || $skipped_count == count($anarProduct->variants)) {
                $this->addLog("Variable product processing completed - Processed: {$processed_count}, Skipped: {$skipped_count}, Errors: {$error_count}");
                return [
                    'success' => true,
                    'product_id' => $parentId
                ];
            }

            // If we have variations but couldn't process any, return error
            if ($error_count > 0 && $processed_count == 0) {
                $error_msg = sprintf("Failed to process any variants for variable product %s", $sku);
                $this->addLog("Variable product processing failed: {$error_msg}");
                return [
                    'success' => false,
                    'message' => $error_msg
                ];
            }

            // Fallback: if we have a parent ID, return success even if no variants processed
            $this->addLog("Variable product processing completed with parent ID: {$parentId}");
            return [
                'success' => true,
                'product_id' => $parentId
            ];

        } catch (\Exception $e) {
            $error_msg = "Error processing variable product: " . $e->getMessage();
            $this->addLog($error_msg);
            return [
                'success' => false,
                'message' => $error_msg
            ];
        }
    }


    /**
     * Updates the stock and price for a WooCommerce product based on Anar API data
     *
     * Handles stock quantity, stock status, and price updates. The price logic:
     * - If both labelPrice and price exist and labelPrice > price: labelPrice = Regular, price = Sale
     * - If only price exists: price = Regular, no Sale price
     * - If neither exists: empty prices (product unavailable)
     *
     * @param \WC_Product $product The WooCommerce product object (simple or variation)
     * @param object $updateProduct The parent product data from Anar API (contains resellStatus, shipments)
     * @param object $variant The specific variant data from Anar API (contains stock, price, labelPrice)
     * @param int $parentId Parent product ID (used for variations, same as product ID for simple products)
     * @return void
     */
    protected function updateProductStockAndPrice($product, $updateProduct, $variant, $parentId) {
        // Calculate stock quantity based on variant stock and product status
        // If product is in editing-pending status, set stock to 0
        $variantStock = (isset($updateProduct->resellStatus) && $updateProduct->resellStatus == 'editing-pending') ? 0 : (isset($variant->stock) ? $variant->stock : 0);

        // Safety check: if shipments data is missing, set stock to 0
        // This prevents selling products without shipping information
        if(empty($updateProduct->shipmentsReferenceId) || empty($updateProduct->shipments)) {
            $variantStock = 0;
            awca_log("shipments is empty for #" . $product->get_id() ." so set as out-of-stock");
        }

        // Update stock quantity and status
        $product->set_stock_quantity($variantStock);
        $product->set_manage_stock(true);
        if ($variantStock > 0) {
            $product->set_stock_status('instock');
        } else {
            $product->set_stock_status('outofstock');
        }

        // Extract prices from API variant data
        $apiPrice = $variant->price ?? null; // Potential Sale Price
        $apiLabelPrice = $variant->labelPrice ?? null; // Potential Regular Price

        // Convert prices to WooCommerce currency (handles currency conversion if needed)
        $wcPrice = ($apiPrice !== null) ? awca_convert_price_to_woocommerce_currency($apiPrice) : null;
        $wcLabelPrice = ($apiLabelPrice !== null) ? awca_convert_price_to_woocommerce_currency($apiLabelPrice) : null;

        // Determine final prices based on API values
        if ($wcLabelPrice !== null && $wcLabelPrice > 0 && $wcPrice !== null && $wcLabelPrice > $wcPrice) {
            // Scenario 1: Both prices exist and labelPrice > price (sale scenario)
            // labelPrice becomes Regular Price, price becomes Sale Price
            $finalRegularPrice = $wcLabelPrice;
            $finalSalePrice = $wcPrice;
            $finalActivePrice = $wcPrice; // Active price is the sale price
        } elseif ($wcPrice !== null) {
            // Scenario 2: Only price exists (or no valid sale scenario)
            // price becomes Regular Price, no Sale Price
            $finalRegularPrice = $wcPrice;
            $finalSalePrice = ''; // Ensure sale price is empty
            $finalActivePrice = $wcPrice; // Active price is the regular price
        } else {
            // Scenario 3: Neither price is valid (product unavailable)
            // Set empty prices to prevent incorrect pricing
            $finalRegularPrice = '';
            $finalSalePrice = '';
            $finalActivePrice = '';
            // Log warning for missing price data
            $this->addLog("Invalid price data for product #{$product->get_id()} (Price: {$apiPrice}, LabelPrice: {$apiLabelPrice})");
        }

        // Set WooCommerce product prices
        $product->set_regular_price($finalRegularPrice);
        $product->set_sale_price($finalSalePrice);
        $product->set_price($finalActivePrice); // Set the active price (what customer sees)

        // Save product changes
        $product->save();

        // Clear WordPress post meta cache
        wp_cache_delete($product->get_id(), 'post_meta');

        // Clear WordPress post cache
        wp_cache_delete($product->get_id(), 'posts');
        wp_cache_delete($product->get_id(), 'product');

        // Clear WooCommerce specific transients
        if (function_exists('wc_delete_product_transients')) {
            wc_delete_product_transients($product->get_id());
        }

        // Clear object cache for the product
        clean_post_cache($product->get_id());
    }

    /**
     * Updates product metadata after successful sync
     *
     * Updates the last sync time and removes pending status flag.
     * This metadata is used for tracking sync history and cooldown periods.
     *
     * @param int $wcProductParentId WooCommerce parent product ID
     * @param object $variant Anar variant data (not directly used, kept for consistency)
     * @return void
     */
    protected function updateProductMetadata($wcProductParentId, $variant) {
        // Update last sync timestamp (used for cooldown period checks)
        update_post_meta($wcProductParentId, self::LAST_SYNC_META_KEY, current_time('mysql'));

        // Remove pending flag (product is now synced)
        delete_post_meta($wcProductParentId, '_anar_pending');
    }


    /**
     * Updates variant-specific metadata with Anar pricing information
     *
     * Stores detailed pricing information from Anar API for later use.
     * Used for both simple products and variations of variable products.
     *
     * @param int $wcProductVariantId WooCommerce product/variation ID
     * @param object $variant Anar variant data containing pricing information
     * @return void
     */
    protected function updateProductVariantMetaData($wcProductVariantId, $variant) {
        // Store comprehensive pricing data from Anar
        // This includes reseller profit, discounts, and price ranges
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


    /**
     * Updates product shipments metadata
     *
     * Stores shipping information from Anar API. This is only updated during full sync
     * to reduce API overhead during regular syncs.
     *
     * @param int $wcProductParentId WooCommerce parent product ID
     * @param object $anarProduct Anar product data containing shipments information
     * @return void
     */
    protected function updateProductShipments($wcProductParentId, $anarProduct){
        // Serialize Anar product data to extract shipments
        $prepared_product = ProductManager::product_serializer($anarProduct);

        // Only update if shipments data is available
        if(isset($prepared_product['shipments']) && isset($prepared_product['shipments_ref'])){
            update_post_meta($wcProductParentId, '_anar_shipments', $prepared_product['shipments']);
            update_post_meta($wcProductParentId, '_anar_shipments_ref', $prepared_product['shipments_ref']);
        }
    }
}


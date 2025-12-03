<?php
namespace Anar\Sync;

use Anar\ApiDataHandler;
use Anar\Core\Activation;

/**
 * Class RegularSync
 *
 * Regular sync strategy that syncs products that were recently updated on Anar server.
 * This strategy:
 * - Runs every 10 minutes via WordPress cron
 * - Fetches products from Anar API that were updated in the last 10 minutes
 * - Processes products in batches to stay within execution time limits
 * - Uses locking mechanism to prevent concurrent sync jobs
 * - Handles pagination automatically
 *
 * Designed to keep products up-to-date when changes occur on Anar server.
 *
 * @package Anar\Sync
 * @since 0.6.0
 */
class RegularSync extends Sync{
    /**
     * Singleton instance
     *
     * @var RegularSync|null
     */
    private static $instance;

    /**
     * Products per page from API
     *
     * @var int
     */
    private $limit;

    /**
     * Current page number for pagination
     *
     * @var int
     */
    private $page;

    /**
     * Counter for synced products in current job
     *
     * @var int
     */
    private $syncedCounter;

    /**
     * Job start time (microseconds)
     *
     * @var float
     */
    private $startTime;

    /**
     * Unique job identifier for logging
     *
     * @var string
     */
    private $jobID;

    /**
     * Maximum execution time in seconds
     *
     * @var int
     */
    public $max_execution_time;

    /**
     * Time window for finding updated products (in milliseconds)
     * Products updated within this window will be synced
     *
     * @var int
     */
    private $updatedSince;

    /**
     * Get singleton instance
     *
     * @return RegularSync
     */
    public static function get_instance() {
        if ( ! isset( self::$instance ) ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * RegularSync constructor
     *
     * Initializes sync parameters, registers cron hooks, and schedules the job.
     */
    public function __construct() {
        parent::__construct();

        // Initialize pagination and counters
        $this->limit = 100;
        $this->page = 1;
        $this->syncedCounter = 0;

        // Set execution time limit (4 minutes to stay safely under 5 min PHP limit)
        $this->max_execution_time = 240;

        // Query for products updated in the last 10 minutes (in milliseconds)
        $this->updatedSince = get_option('anar_regular_sync_update_since', 10) * 60000;

        // Register cron schedule interval and hook
        add_filter('cron_schedules', array($this, 'add_cron_interval'));
        add_action('anar_sync_products', array($this, 'startSyncJob'));

        // Schedule the cron job
        $this->scheduleCron();
    }


    /**
     * Starts the sync job
     *
     * Main entry point for cron job. Handles:
     * - Activation validation
     * - Lock checking (prevents concurrent jobs)
     * - Import status checking
     * - Job execution with timing
     * - Cleanup and unlocking
     *
     * @return void
     */
    public function startSyncJob() {

        // Step 1: Validate activation
        if(!Activation::is_active()){
            $this->logger->log('❌ [REGULAR SYNC] Stopped - Anar is not active, Token is invalid!', 'sync', 'error');
            return;
        }

        // Step 2: Check if another sync job is already running
        if ($this->isSyncInProgress()) {
            $this->logger->log('⚠️  [REGULAR SYNC] Skipped - Another sync job already in progress', 'sync', 'info');
            return;
        }

        // Step 3: Check if import is in progress (skip sync during import)
        /**
        if(anar_is_import_in_progress()){
            $this->logger->log('⚠️  [REGULAR SYNC] Skipped - Import in progress', 'sync', 'info');
            return;
        }
         **/

        // Step 4: Initialize job tracking
        $this->set_jobID();
        $this->startTime = microtime(true);

        // Step 5: Set lock to prevent concurrent jobs
        $this->setSyncInProgress();

        // Step 6: Execute sync job
        $completed = false;

        try {
            $this->logger->log('═══════════════════════════════════════════════════════════', 'sync', 'info');
            $this->logger->log('🚀 [REGULAR SYNC] START - Job ID: ' . $this->jobID, 'sync', 'info');
            $this->logger->log('═══════════════════════════════════════════════════════════', 'sync', 'info');

            // Process pages until complete or time limit reached
            $completed = $this->processPages();

            // Step 7: Finalize job
            if ($completed) {
                $this->logger->log('✅ [REGULAR SYNC] COMPLETED - Job ID: ' . $this->jobID . ' | All pages processed successfully', 'sync', 'info');
                $this->setLastSyncTime();
            } else {
                $this->logger->log('⚠️  [REGULAR SYNC] INCOMPLETE - Job ID: ' . $this->jobID . ' | Did not complete all pages in this run', 'sync', 'info');
            }

        } finally {
            // Step 8: Cleanup - always unlock, even on error
            $totalTimeMessage = $this->getTotalTimeMessage();
            $status = $completed ? '✅ COMPLETED' : '⚠️  INCOMPLETE';
            $this->logger->log('═══════════════════════════════════════════════════════════', 'sync', 'info');
            $this->logger->log('🏁 [REGULAR SYNC] END - ' . $status . ' | Job ID: ' . $this->jobID . ' | ' . $totalTimeMessage, 'sync', 'info');
            $this->logger->log('═══════════════════════════════════════════════════════════', 'sync', 'info');
            $this->setSyncNotInProgress();
        }
    }

    /**
     * Processes API pages until complete or time limit reached
     *
     * Fetches products from Anar API in pages, processes them in chunks,
     * and continues until no more products or execution time limit reached.
     *
     * @return bool True if all pages processed, false if stopped early
     */
    private function processPages() {
        $start_time = time();
        $max_execution_time = $this->max_execution_time;
        $syncCompleted = false;

        // Batch size for API requests (100 products per page)
        $batch_size = $this->limit;

        $this->logger->log('🔄 [REGULAR SYNC] Starting to process pages | Updated since: ' . ($this->updatedSince / 60000) . ' minutes ago', 'sync', 'info');

        // Loop through API pages until complete or time limit reached
        while (true) {
            // Check execution time limit (prevent PHP timeout)
            if (time() - $start_time > $max_execution_time) {
                $this->logger->log('⏱️  [REGULAR SYNC] Warning - Approaching execution time limit. Consider optimizing or reducing the updatedSince window.', 'sync', 'warning');
                break;
            }

            // Build API request arguments
            $api_query_args = array(
                'page' => $this->page,
                'limit' => $batch_size,
                'since' => $this->updatedSince * -1 // Negative value means "updated in last X minutes"
            );

            // Log page being processed
            $this->logger->log('📄 [REGULAR SYNC] Fetching page ' . $this->page . ' from API...', 'sync', 'info');

            // Fetch products from Anar API
            $apiUrl =  ApiDataHandler::getApiUrl('products', $api_query_args);
            $products_response = ApiDataHandler::callAnarApi($apiUrl);

            // Handle API errors
            if (!$products_response) {
                $this->logger->log('❌ [REGULAR SYNC] Error - Failed to get products data from Anar API! Page: ' . $this->page, 'sync', 'error');
                break;
            }

            if (is_wp_error($products_response) ) {
                $this->logger->log('❌ [REGULAR SYNC] Error - Failed to fetch products from API: ' . $products_response->get_error_message() . ' | Page: ' . $this->page, 'sync', 'error');
                break;
            }

            $awcaProducts = json_decode($products_response['body']);

            // Log API response received
            $total_items = isset($awcaProducts->total) ? $awcaProducts->total : 0;
            $items_count = isset($awcaProducts->items) ? count($awcaProducts->items) : 0;
            $this->logger->log('📥 [REGULAR SYNC] API Response received | Page: ' . $this->page . ' | Total: ' . $total_items . ' | Items on page: ' . $items_count, 'sync', 'info');

            // Check if page is empty
            if (empty($awcaProducts->items)) {
                $this->logger->log('ℹ️  [REGULAR SYNC] No products found on page ' . $this->page . ' | Total products in query: ' . $total_items, 'sync', 'info');
                if ($this->page == 1) {
                    $this->logger->log('ℹ️  [REGULAR SYNC] No products to sync in the specified time window', 'sync', 'info');
                }
                $syncCompleted = true; // No more products to process
                break;
            }

            // Log total products found on first page
            if($this->page == 1){
                $this->logger->log('📦 [REGULAR SYNC] Found ' . $total_items . ' products to update | Processing ' . $items_count . ' items on page 1', 'sync', 'info');
            }

            // Check if this is the last page (redundant check, but kept for safety)
            if (count($awcaProducts->items) == 0) {
                $this->logger->log('🏁 [REGULAR SYNC] Last page reached | Page: ' . $this->page, 'sync', 'info');
                $syncCompleted = true;
                break;
            }

            // Process products in smaller chunks to avoid memory issues
            $chunk_size = 50;
            $items = array_chunk($awcaProducts->items, $chunk_size);
            $total_chunks = count($items);

            if ($total_chunks > 1) {
                $this->logger->log('🔄 [REGULAR SYNC] Processing page ' . $this->page . ' in ' . $total_chunks . ' chunks of ' . $chunk_size . ' products each', 'sync', 'info');
            }

            $chunk_number = 1;
            foreach ($items as $chunk) {
                $this->syncBulkProducts($chunk, $this->page, $chunk_number, $total_chunks);
                $chunk_number++;
            }

            // Update counters and move to next page
            $this->syncedCounter += count($awcaProducts->items);
            $this->page++;

            // Check if we've processed all available items (total synced >= total available)
            if (isset($awcaProducts->total) && $this->syncedCounter >= $awcaProducts->total) {
                $this->logger->log('✅ [REGULAR SYNC] All products processed | Total synced: ' . $this->syncedCounter . ' / ' . $awcaProducts->total, 'sync', 'info');
                $syncCompleted = true;
                break;
            }
        }

        // Log final summary
        $this->logger->log('📊 [REGULAR SYNC] Pages processing complete | Total synced: ' . $this->syncedCounter . ' products | Pages processed: ' . ($this->page - 1) . ' | Status: ' . ($syncCompleted ? 'COMPLETED' : 'INCOMPLETE'), 'sync', 'info');

        return $syncCompleted;
    }

    /**
     * Syncs a batch of products from Anar API data
     *
     * Processes each product in the batch using pre-fetched Anar product data.
     * Uses syncProductByAnarProductData to avoid additional API calls.
     *
     * @param array $anar_products Array of Anar product data objects
     * @param int $page Current page number
     * @param int $chunk_number Current chunk number (1-based)
     * @param int $total_chunks Total number of chunks for this page
     * @return void
     */
    public function syncBulkProducts($anar_products, $page = 1, $chunk_number = 1, $total_chunks = 1) {
        $processed = 0;

        // Process each product in the batch
        foreach ($anar_products as $anar_product) {
            try {
                // Use syncProductByAnarProductData to avoid additional API calls
                $sync_result = $this->syncProductByAnarProductData($anar_product);
                $processed++;
                // Individual product sync logs are collected in sync_result['logs'], no need to print here
                // Only log if there's an error or important message
                $this->logger->log('✅ [REGULAR SYNC] ' . $sync_result['logs'], 'sync', 'debug');

            } catch (\Exception $e) {
                // Log error but continue processing other products
                $this->logger->log('❌ [REGULAR SYNC] Error processing product: ' . $e->getMessage(), 'sync', 'error');
                continue;
            }
        }

        // Log batch completion with chunk information
        if ($total_chunks > 1) {
            $this->logger->log('📊 [REGULAR SYNC] Chunk ' . $chunk_number . '/' . $total_chunks . ' complete | Page: ' . $page . ' | Processed: ' . $processed . ' products', 'sync', 'info');
        } else {
            $this->logger->log('📊 [REGULAR SYNC] Batch complete | Page: ' . $page . ' | Processed: ' . $processed . ' products', 'sync', 'info');
        }
    }

    /**
     * Generates a unique job ID for tracking
     *
     * @return void
     */
    private function set_jobID(){
        $this->jobID = uniqid('anar__sync_job_');
    }

    /**
     * Checks if a sync job is currently in progress
     *
     * Uses transient lock to prevent concurrent sync jobs.
     *
     * @return bool True if sync is in progress, false otherwise
     */
    private function isSyncInProgress() {
        return get_transient('awca_sync_all_products_lock');
    }

    /**
     * Sets sync lock to prevent concurrent jobs
     *
     * Lock expires after 5 minutes (300 seconds) as safety measure.
     *
     * @return void
     */
    private function setSyncInProgress() :void{
        $this->logger->log('🔒 [REGULAR SYNC] Lock acquired | Job ID: ' . $this->jobID, 'sync', 'info');
        set_transient('awca_sync_all_products_lock', $this->jobID, 300);
    }

    /**
     * Removes sync lock when job completes
     *
     * @return void
     */
    private function setSyncNotInProgress() :void {
        $this->logger->log('🔓 [REGULAR SYNC] Lock released | Job ID: ' . $this->jobID, 'sync', 'info');
        delete_transient('awca_sync_all_products_lock');
    }

    /**
     * Updates the last sync time option
     *
     * Stores timestamp of successful sync completion.
     *
     * @return void
     */
    public function setLastSyncTime() {
        update_option('anar_last_regular_sync_time', current_time('mysql'));
    }

    /**
     * Gets the last sync time
     *
     * @return string|false Last sync time as MySQL datetime string, or false if not set
     */
    public function getLastSyncTime() {
        return get_option('anar_last_regular_sync_time');
    }

    /**
     * Calculates the total execution time and returns a formatted message string
     *
     * @return string Formatted total time message (e.g., "Total Time: 45.23s")
     */
    private function getTotalTimeMessage() {
        if ($this->startTime and is_numeric($this->startTime)) {
            $endTime = microtime(true);
            $executionTime = round($endTime - $this->startTime, 2);
            return "Total Time: {$executionTime}s";
        }
        return "Total Time: N/A";
    }

    /**
     * Adds custom cron interval for 10 minutes
     *
     * @param array $schedules WordPress cron schedules array
     * @return array Modified schedules array with 'anar_sync_interval'
     */
    public function add_cron_interval($schedules) {
        $schedules['anar_sync_interval'] = array(
            'interval' => 600, // 10 minutes in seconds
            'display'  => 'هر ۱۰ دقیقه'
        );
        return $schedules;
    }

    /**
     * Schedules the cron job if not already scheduled
     *
     * @return void
     */
    public function scheduleCron() {
        if (!wp_next_scheduled('anar_sync_products')) {
            wp_schedule_event(time(), 'anar_sync_interval', 'anar_sync_products');
        }
    }

    /**
     * Unschedules the cron job
     *
     * Call this method when deactivating the plugin to clean up scheduled events.
     *
     * @return void
     */
    public function unscheduleCron() {
        $timestamp = wp_next_scheduled('anar_sync_products');
        if ($timestamp) {
            wp_unschedule_event($timestamp, 'anar_sync_products');
        }
    }

}
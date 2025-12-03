<?php
namespace Anar\Sync;

use Anar\ApiDataHandler;
use Anar\Core\Activation;
use Anar\Wizard\ProductManager;
use SimplePie\Exception;

/**
 * Class OutdatedSync
 *
 * Sync strategy for keeping stale products up-to-date. This strategy:
 * - Runs every 5 minutes via WordPress cron
 * - Processes up to 30 products per run (configurable)
 * - Updates products that haven't been synced in the last 24 hours
 * - Maximum capacity: ~8,640 products per day (30 products × 12 runs/hour × 24 hours)
 *
 * Strategy Benefits:
 * - Low resource usage per run
 * - Predictable execution time
 * - Simple error handling
 * - No complex state management
 * - Easy to maintain and debug
 *
 * Uses direct SQL queries for optimal performance when finding outdated products.
 *
 * @package Anar\Sync
 * @since 0.6.0
 */
class OutdatedSync extends Sync{
    /**
     * Singleton instance
     *
     * @var OutdatedSync|null
     */
    private static $instance;

    /**
     * Number of products to process per cron run
     *
     * @var int
     */
    private $batch_size;

    /**
     * Time threshold for considering products outdated
     *
     * @var string
     */
    private $outdated_threshold = '1 day';

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
     * WordPress cron hook name
     *
     * @var string
     */
    private $cron_hook = 'anar_sync_outdated_products';

    /**
     * Get singleton instance
     *
     * @return OutdatedSync
     */
    public static function get_instance() {
        if (!isset(self::$instance)) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * OutdatedSync constructor
     *
     * Initializes batch size, schedules cron job, and registers hooks.
     */
    public function __construct() {
        parent::__construct();

        // Schedule cron job if not already scheduled
        $this->schedule_cron();

        // Get batch size from options (default: 30)
        $this->batch_size = get_option('anar_sync_outdated_batch_size', 30);

        // Register cron hook handler
        add_action($this->cron_hook, array($this, 'sync_found_outdated_products'));

        // Add custom 5-minute cron interval
        add_filter('cron_schedules', array($this, 'add_cron_interval'));
    }
    

    /**
     * Adds custom cron interval for 5 minutes
     *
     * @param array $schedules WordPress cron schedules array
     * @return array Modified schedules array with 'every_five_min' interval
     */
    public function add_cron_interval($schedules) {
        $schedules['every_five_min'] = array(
            'interval' => 300,
            'display'  => 'Every 5 Minutes'
        );
        return $schedules;
    }


    /**
     * Schedules the cron job if not already scheduled
     *
     * @return void
     */
    public function schedule_cron() {
        if (!wp_next_scheduled($this->cron_hook)) {
            wp_schedule_event(time(), 'every_five_min', $this->cron_hook);
        }
    }

    /**
     * Unschedules the cron job
     *
     * Call this method when deactivating the plugin to clean up scheduled events.
     *
     * @return void
     */
    public function unscheduled_cron() {
        $timestamp = wp_next_scheduled($this->cron_hook);
        if ($timestamp) {
            wp_unschedule_event($timestamp, $this->cron_hook);
        }
    }

    /**
     * Finds and syncs outdated products
     *
     * This is the main cron handler that:
     * 1. Validates activation status
     * 2. Finds products that haven't been synced within the threshold
     * 3. Syncs each found product using outdated-sync strategy
     *
     * Uses direct SQL query for optimal performance when finding outdated products.
     *
     * @return void
     */
    public function sync_found_outdated_products() {
        // Initialize job tracking
        $this->set_jobID();
        $this->startTime = microtime(true);

        // Step 1: Validate activation before proceeding
        if(!Activation::is_active()){
            $this->logger->log('❌ [OUTDATED SYNC] Stopped - Anar is not active, Token is invalid!', 'sync', 'error');
            return;
        }

        try {
            // Step 2: Start logging
            $this->logger->log('═══════════════════════════════════════════════════════════', 'sync', 'info');
            $this->logger->log('🔄 [OUTDATED SYNC] START - Job ID: ' . $this->jobID, 'sync', 'info');
            $this->logger->log('═══════════════════════════════════════════════════════════', 'sync', 'info');

            global $wpdb;

            // Step 3: Calculate cutoff time (products older than this are considered outdated)
            $time_ago = date('Y-m-d H:i:s', strtotime("-{$this->outdated_threshold}"));
            $this->logger->log('🔍 [OUTDATED SYNC] Searching for products outdated more than ' . $this->outdated_threshold . ' | Cutoff: ' . $time_ago, 'sync', 'info');

            // Step 4: Direct SQL query for better performance than WP_Query
            // Finds products with Anar SKU that haven't been synced recently
            // Orders by last sync time (oldest first) to prioritize stale products
            // Excludes products with restore retries >= 3 to prevent infinite loops
            $sql = $wpdb->prepare("
                SELECT DISTINCT p.ID, 
                       COALESCE(sku.meta_value, sku_backup.meta_value) as anar_sku
                FROM {$wpdb->posts} p
                LEFT JOIN {$wpdb->postmeta} sku ON p.ID = sku.post_id AND sku.meta_key = '_anar_sku'
                LEFT JOIN {$wpdb->postmeta} sku_backup ON p.ID = sku_backup.post_id AND sku_backup.meta_key = '_anar_sku_backup'
                LEFT JOIN {$wpdb->postmeta} retries ON p.ID = retries.post_id AND retries.meta_key = '_anar_restore_retries'
                INNER JOIN {$wpdb->postmeta} last_try ON p.ID = last_try.post_id AND last_try.meta_key = '_anar_last_sync_time'
                WHERE p.post_type = 'product'
                AND p.post_status IN ('publish', 'draft')
                AND (sku.meta_value IS NOT NULL OR sku_backup.meta_value IS NOT NULL)
                AND (retries.meta_value IS NULL OR CAST(retries.meta_value AS UNSIGNED) < 3)
                AND last_try.meta_value < %s
                ORDER BY last_try.meta_value ASC
                LIMIT %d
            ", $time_ago, $this->batch_size);

            $results = $wpdb->get_results($sql);
            $found_count = count($results);

            // Step 5: Log search results
            if ($found_count > 0) {
                $this->logger->log('📦 [OUTDATED SYNC] Found ' . $found_count . ' outdated products to sync (batch size: ' . $this->batch_size . ')', 'sync', 'info');
            } else {
                $this->logger->log('✅ [OUTDATED SYNC] No outdated products found - all products are up to date', 'sync', 'info');
            }

            // Step 6: Sync each outdated product
            $synced_count = 0;
            $failed_count = 0;

            foreach ($results as $row) {
                $sync_result = $this->syncProduct($row->ID, [
                    'sync_strategy' => 'outdated-sync',
                    'full_sync' => true,
                    'deprecate_on_faults' => true
                ]);
                
                if ($sync_result && isset($sync_result['updated']) && $sync_result['updated']) {
                    $synced_count++;
                    // Individual product sync logs are collected in sync_result['logs']
                    if (!empty($sync_result['logs'])) {
                        $this->logger->log('📝 [OUTDATED SYNC] Product #' . $row->ID . ' | ' . $sync_result['logs'], 'sync', 'info');
                    }
                } else {
                    $failed_count++;
                    $error_msg = isset($sync_result['message']) ? $sync_result['message'] : 'Unknown error';
                    $this->logger->log('❌ [OUTDATED SYNC] Failed to sync product #' . $row->ID . ' | ' . $error_msg, 'sync', 'error');
                }
            }

            // Step 7: Log completion summary
            $totalTimeMessage = $this->getTotalTimeMessage();
            $this->logger->log('═══════════════════════════════════════════════════════════', 'sync', 'info');
            $this->logger->log('✅ [OUTDATED SYNC] COMPLETED - Job ID: ' . $this->jobID . ' | Found: ' . $found_count . ' | Synced: ' . $synced_count . ' | Failed: ' . $failed_count . ' | ' . $totalTimeMessage, 'sync', 'info');
            $this->logger->log('═══════════════════════════════════════════════════════════', 'sync', 'info');

        } catch (\Exception $e) {
            // Handle any unexpected errors
            $totalTimeMessage = $this->getTotalTimeMessage();
            $this->logger->log('═══════════════════════════════════════════════════════════', 'sync', 'info');
            $this->logger->log('❌ [OUTDATED SYNC] ERROR - Job ID: ' . $this->jobID . ' | Exception: ' . $e->getMessage() . ' | ' . $totalTimeMessage, 'sync', 'error');
            $this->logger->log('═══════════════════════════════════════════════════════════', 'sync', 'info');
        }
    }

    /**
     * Generates a unique job ID for tracking
     *
     * @return void
     */
    private function set_jobID(){
        $this->jobID = uniqid('anar__outdated_job_');
    }

    /**
     * Calculates the total execution time and returns a formatted message string
     *
     * @return string Formatted total time message (e.g., "Total Time: 45.23s")
     */
    private function getTotalTimeMessage() {
        if ($this->startTime && is_numeric($this->startTime)) {
            $endTime = microtime(true);
            $executionTime = round($endTime - $this->startTime, 2);
            return "Total Time: {$executionTime}s";
        }
        return "Total Time: N/A";
    }

}

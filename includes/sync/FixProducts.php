<?php

namespace Anar\Sync;

use Anar\Core\Activation;
use Anar\Core\Logger;

/**
 * Class FixProducts
 *
 * Automatically fixes products with known sync errors by reimporting them.
 * This class:
 * - Runs daily via WordPress cron
 * - Finds products with _anar_need_fix meta
 * - Processes products in batches to avoid timeout
 * - Reimports products based on error type
 * - Clears fix flags on successful fix
 *
 * @package Anar\Sync
 * @since 0.6.0
 */
class FixProducts {
    /**
     * Singleton instance
     *
     * @var FixProducts|null
     */
    private static $instance;

    /**
     * Number of products to process per cron run
     *
     * @var int
     */
    private $batch_size;

    /**
     * WordPress cron hook name
     *
     * @var string
     */
    private $cron_hook = 'anar_fix_products';

    /**
     * Logger instance
     *
     * @var Logger
     */
    private $logger;

    /**
     * Get singleton instance
     *
     * @return FixProducts
     */
    public static function get_instance() {
        if (!isset(self::$instance)) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * FixProducts constructor
     *
     * Initializes batch size, schedules cron job, and registers hooks.
     */
    public function __construct() {
        $this->logger = new Logger();
        $this->logger->set_log_prefix('sync');

        // Get batch size from options (default: 20)
        $this->batch_size = (int) get_option('anar_fix_products_batch_size', 100);

        // Register cron hook handler (use string literal to avoid $this reference issues)
        add_action('anar_fix_products', array($this, 'fix_products_with_errors'));

        // Schedule cron job if not already scheduled
        $this->schedule_cron();
    }

    /**
     * Schedules the daily cron job if not already scheduled
     *
     * @return void
     */
    public function schedule_cron() {
        $hook = 'anar_fix_products';
        if (!wp_next_scheduled($hook)) {
            wp_schedule_event(time(), 'daily', $hook);
        }
    }

    /**
     * Unschedules the cron job
     *
     * Call this method when deactivating the plugin to clean up scheduled events.
     *
     * @return void
     */
    public function unschedule_cron() {
        $hook = 'anar_fix_products';
        $timestamp = wp_next_scheduled($hook);
        if ($timestamp) {
            wp_unschedule_event($timestamp, $hook);
        }
    }

    /**
     * Main cron handler that finds and fixes products with errors
     *
     * Queries products with _anar_need_fix meta, filters by fixable error types,
     * and processes them in batches. Each product is reimported based on its error type.
     * Can be called manually or via cron.
     *
     * @return array{
     *     processed: int,
     *     fixed: int,
     *     failed: int
     * }|void Returns array with results if called manually, void if called via cron
     */
    public function fix_products_with_errors() {
        // Validate activation status
        if (!Activation::is_active()) {
            $this->logger->log('FixProducts: Anar plugin not active, skipping fix job', 'warning');
            return;
        }

        $this->logger->log('FixProducts: Starting fix products job', 'sync');

        // Get fixable error keys from Sync class
        $reflection = new \ReflectionClass(Sync::class);
        $property = $reflection->getProperty('fix_error_keys');
        $property->setAccessible(true);
        $fix_error_keys = $property->getValue();

        if (empty($fix_error_keys)) {
            $this->logger->log('FixProducts: No fixable error keys found', 'sync');
            return;
        }

        // Query products with _anar_need_fix meta
        $products_to_fix = $this->get_products_needing_fix($fix_error_keys);

        if (empty($products_to_fix)) {
            $this->logger->log('FixProducts: No products found needing fix', 'sync');
            return;
        }

        $this->logger->log(sprintf('FixProducts: Found %d products needing fix', count($products_to_fix)), 'sync');

        // Process products in batches
        $processed = 0;
        $fixed = 0;
        $failed = 0;

        foreach (array_slice($products_to_fix, 0, $this->batch_size) as $product_data) {
            $product_id = $product_data['product_id'];
            $fix_key = $product_data['fix_key'];

            try {
                $result = $this->fix_product($product_id, $fix_key);
                
                if ($result['success']) {
                    $fixed++;
                    $this->logger->log(sprintf('FixProducts: Successfully fixed product #%d (error: %s)', $product_id, $fix_key), 'sync');
                } else {
                    $failed++;
                    $this->logger->log(sprintf('FixProducts: Failed to fix product #%d (error: %s): %s', $product_id, $fix_key, $result['message']), 'error');
                }
                
                $processed++;
            } catch (\Exception $e) {
                $failed++;
                $this->logger->log(sprintf('FixProducts: Exception fixing product #%d: %s', $product_id, $e->getMessage()), 'error');
            }
        }

        $this->logger->log(sprintf('FixProducts: Job completed - Processed: %d, Fixed: %d, Failed: %d', $processed, $fixed, $failed), 'sync');

        // Track last run time
        update_option('anar_last_fix_products_run', current_time('mysql'));

        // Return results if called manually (not via cron)
        if (!wp_doing_cron()) {
            return [
                'processed' => $processed,
                'fixed' => $fixed,
                'failed' => $failed
            ];
        }
    }

    /**
     * Gets products that need fixing based on fixable error keys
     *
     * Queries WordPress database for products with _anar_need_fix meta
     * matching one of the fixable error keys.
     *
     * @param array<string> $fix_error_keys Array of fixable error keys
     * @return array<array{product_id: int, fix_key: string}> Array of products needing fix
     */
    private function get_products_needing_fix($fix_error_keys) {
        global $wpdb;

        if (empty($fix_error_keys)) {
            return [];
        }

        // Prepare placeholders for IN clause
        $placeholders = implode(',', array_fill(0, count($fix_error_keys), '%s'));
        
        // Query products with _anar_need_fix meta matching fixable error keys
        $query = $wpdb->prepare("
            SELECT pm.post_id as product_id, pm.meta_value as fix_key
            FROM {$wpdb->postmeta} pm
            INNER JOIN {$wpdb->posts} p ON pm.post_id = p.ID
            WHERE pm.meta_key = %s
            AND pm.meta_value IN ($placeholders)
            AND p.post_type = 'product'
            AND p.post_status IN ('publish', 'draft')
            ORDER BY pm.post_id ASC
        ", Sync::NEED_FIX_META_KEY, ...$fix_error_keys);

        $results = $wpdb->get_results($query, ARRAY_A);

        if (empty($results)) {
            return [];
        }

        return array_map(function($row) {
            return [
                'product_id' => (int) $row['product_id'],
                'fix_key' => $row['fix_key']
            ];
        }, $results);
    }

    /**
     * Fixes a single product based on its error type
     *
     * @param int $product_id WooCommerce product ID
     * @param string $fix_key The fix error key (e.g., 'no_wc_variants')
     * @return array{
     *     success: bool,
     *     message: string
     * } Fix result with success status and message
     */
    private function fix_product($product_id, $fix_key) {
        switch ($fix_key) {
            case Sync::ERROR_NO_WC_VARIANTS:
                return $this->fix_no_wc_variants($product_id);
            
            default:
                return [
                    'success' => false,
                    'message' => sprintf('Unknown fix key: %s', $fix_key)
                ];
        }
    }

    /**
     * Fixes a product with no WooCommerce variations by reimporting it
     *
     * Gets the Anar SKU from the product and calls anar_create_single_product_legacy
     * to reimport the product. On success, clears the fix flags.
     *
     * @param int $product_id WooCommerce product ID
     * @return array{
     *     success: bool,
     *     message: string
     * } Fix result
     */
    private function fix_no_wc_variants($product_id) {
        // Get Anar SKU
        $anar_sku = anar_get_product_anar_sku($product_id);
        
        if (is_wp_error($anar_sku)) {
            return [
                'success' => false,
                'message' => sprintf('Failed to get Anar SKU: %s', $anar_sku->get_error_message())
            ];
        }

        // Reimport product using legacy function
        $result = anar_create_single_product_legacy($anar_sku);

        if (is_wp_error($result)) {
            return [
                'success' => false,
                'message' => sprintf('Reimport failed: %s', $result->get_error_message())
            ];
        }

        // Check if reimport was successful
        if (!isset($result['success']) || !$result['success']) {
            return [
                'success' => false,
                'message' => 'Reimport returned unsuccessful result'
            ];
        }

        $new_product_id = $result['product_id'] ?? $product_id;

        // Clear fix flags on successful fix (clear from original product ID)
        delete_post_meta($product_id, Sync::NEED_FIX_META_KEY);
        delete_post_meta($product_id, Sync::SYNC_ERROR_META_KEY);
        
        // Also clear from new product ID if different
        if ($new_product_id != $product_id) {
            delete_post_meta($new_product_id, Sync::NEED_FIX_META_KEY);
            delete_post_meta($new_product_id, Sync::SYNC_ERROR_META_KEY);
        }

        return [
            'success' => true,
            'message' => sprintf('Product reimported successfully (new ID: %d)', $new_product_id)
        ];
    }
}


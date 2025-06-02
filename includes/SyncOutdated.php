<?php

namespace Anar;
use Anar\Core\Logger;
use Anar\Wizard\ProductManager;

/**
 * SyncOutdated Class
 * 
 * This class implements a simple and efficient strategy for keeping products in sync with Anar:
 * - Runs every 5 minutes via WordPress cron
 * - Processes up to 30 products per run
 * - Updates products that haven't been synced in the last 24 hours
 * - Maximum capacity: ~8,640 products per day (30 products × 12 runs/hour × 24 hours)
 * 
 * Strategy Benefits:
 * - Low resource usage per run
 * - Predictable execution time
 * - Simple error handling
 * - No complex state management
 * - Easy to maintain and debug
 */
class SyncOutdated {
    private static $instance;
    private $logger;
    private $max_execution_time = 240;
    private $batch_size;
    private $outdated_threshold = '1 day';
    private $cron_hook = 'anar_sync_outdated_products_cron';

    public static function get_instance() {
        if (!isset(self::$instance)) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function __construct() {
        $this->logger = new Logger();

        $this->batch_size = get_option('anar_sync_outdated_batch_size', 30);

        // Register AJAX handler
        add_action('wp_ajax_anar_sync_outdated_products', array($this, 'sync_outdated_products_ajax'));
        
        // Register cron hook
        add_action($this->cron_hook, array($this, 'process_outdated_products_job'));
        
        // Add custom cron interval
        add_filter('cron_schedules', array($this, 'add_cron_interval'));
        
        // Schedule the cron if not already scheduled
        if (!wp_next_scheduled($this->cron_hook)) {
            wp_schedule_event(time(), 'every_five_min', $this->cron_hook);
        }
    }

    /**
     * Add custom cron interval for 5 minutes
     */
    public function add_cron_interval($schedules) {
        $schedules['every_five_min'] = array(
            'interval' => 300, // 5 minutes in seconds
            'display'  => 'Every 5 Minutes'
        );
        return $schedules;
    }

    /**
     * Unschedule the cron job
     * Call this method when deactivating the plugin
     */
    public function unschedule_cron() {
        $timestamp = wp_next_scheduled($this->cron_hook);
        if ($timestamp) {
            wp_unschedule_event($timestamp, $this->cron_hook);
        }
    }

    private function log($message, $level = 'info') {
        $this->logger->log($message, 'syncOutdated', $level);
    }

    /**
     * Get outdated products that haven't been synced in the last day
     */
    private function get_outdated_products() {
        $time_ago = date('Y-m-d H:i:s', strtotime("-{$this->outdated_threshold}"));

        $args = array(
            'post_type' => 'product',
            'post_status' => ['publish', 'draft'],
            'posts_per_page' => $this->batch_size,
            'meta_query' => array(
                'relation' => 'AND',
                array(
                    'key' => '_anar_sku',
                    'compare' => 'EXISTS'
                ),
                array(
                    'relation' => 'OR',
                    array(
                        'key' => '_anar_last_try_time',
                        'compare' => 'NOT EXISTS'
                    ),
                    array(
                        'key' => '_anar_last_try_time',
                        'value' => $time_ago,
                        'compare' => '<',
                        'type' => 'DATETIME'
                    )
                )
            ),
            'fields' => 'ids'
        );

        $query = new \WP_Query($args);

        $this->log('Found ' . $query->found_posts . ' products that have been outdated more than '. $this->outdated_threshold);
        
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

        return $products;
    }

    /**
     * Process a single product update
     */
    private function process_product($product_data) {
        try {
            $apiUrl = "https://api.anar360.com/wp/products/{$product_data['anar_sku']}";
            $api_response = ApiDataHandler::callAnarApi($apiUrl);

            if (is_wp_error($api_response)) {
                $this->log("API Error for SKU {$product_data['anar_sku']}: " . $api_response->get_error_message(), 'error');
                return false;
            }

            $response_code = wp_remote_retrieve_response_code($api_response);
            $response_body = wp_remote_retrieve_body($api_response);
            $data = json_decode($response_body);

            if ($response_code === 200 && $data) {
                $sync = new Sync();
                if (isset($data->attributes) && !empty($data->attributes)) {
                    $sync->processVariableProduct($data);
                } else {
                    $sync->processSimpleProduct($data);
                }

                update_post_meta($product_data['ID'], '_anar_last_try_time', current_time('mysql'));
                $this->log('product updated successfully, #' . $product_data['ID'] . '  SKU: ' . $data->id, 'info');

                return true;
            } elseif ($response_code === 404) {
                ProductManager::set_product_as_deprecated($product_data['ID'], 'sync_outdated', 'sync', true);
                return true;
            }

            return false;
        } catch (\Exception $e) {
            $this->log("Error processing product {$product_data['anar_sku']}: " . $e->getMessage(), 'error');
            return false;
        }
    }

    /**
     * Main method to process outdated products
     */
    public function process_outdated_products_job() {
        if ($this->isSyncInProgress()) {
            $this->log("Another sync process is already running. Skipping this execution.");
            return;
        }

        $this->lockSync();
        $start_time = time();
        $processed = 0;
        $failed = 0;

        try {
            $products = $this->get_outdated_products();
            
            foreach ($products as $product) {
                if (time() - $start_time > $this->max_execution_time) {
                    $this->log("Approaching execution time limit. Processed: {$processed}, Failed: {$failed}");
                    break;
                }

                if ($this->process_product($product)) {
                    $processed++;
                } else {
                    $failed++;
                }
            }

            $this->log("Sync completed. Processed: {$processed}, Failed: {$failed}");
            
        } catch (\Exception $e) {
            $this->log("Error during sync process: " . $e->getMessage(), 'error');
        } finally {
            $this->unlockSync();
        }
    }

    /**
     * AJAX handler for manual sync
     */
    public function sync_outdated_products_ajax() {
        if (!current_user_can('manage_woocommerce')) {
            wp_send_json_error('شما این مجوز را ندارید!');
            wp_die();
        }

        try {
            $products = $this->get_outdated_products();
            $processed = 0;
            $failed = 0;

            foreach ($products as $product) {
                if ($this->process_product($product)) {
                    $processed++;
                } else {
                    $failed++;
                }
            }

            wp_send_json([
                'success' => true,
                'processed' => $processed,
                'failed' => $failed,
                'total' => count($products)
            ]);

        } catch (\Exception $e) {
            wp_send_json_error($e->getMessage());
        }
    }

    private function isSyncInProgress() {
        return get_transient('awca_sync_outdated_lock');
    }

    private function lockSync() {
        set_transient('awca_sync_outdated_lock', time(), 300); // Lock for 5 minutes
    }

    private function unlockSync() {
        delete_transient('awca_sync_outdated_lock');
    }
}

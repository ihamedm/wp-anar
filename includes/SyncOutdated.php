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
    private $cron_hook = 'anar_sync_outdated_products';
    private $job_manager;

    public static function get_instance() {
        if (!isset(self::$instance)) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function __construct() {
        $this->logger = new Logger();
        $this->job_manager = JobManager::get_instance();
        $this->schedule_cron();

        $this->batch_size = get_option('anar_sync_outdated_batch_size', 30);

        // Register AJAX handlers
        add_action('wp_ajax_anar_clear_sync_times', array($this, 'clear_sync_times_ajax'));
        
        // Register cron hooks
        add_action($this->cron_hook, array($this, 'process_outdated_products_cronjob'));
        
        // Add custom cron interval
        add_filter('cron_schedules', array($this, 'add_cron_interval'));
    }

    /**
     * Add custom cron interval for 5 minutes
     */
    public function add_cron_interval($schedules) {
        $schedules['every_five_min'] = array(
            'interval' => 300,
            'display'  => 'Every 5 Minutes'
        );
        return $schedules;
    }


    /**
     * Schedule the cron job if not already scheduled
     */
    public function schedule_cron() {
        if (!wp_next_scheduled($this->cron_hook)) {
            wp_schedule_event(time(), 'every_five_min', $this->cron_hook);
        }
    }

    /**
     * Unschedule the cron job
     * Call this method when deactivating the plugin
     */
    public function unscheduled_cron() {
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
                    'relation' => 'OR',
                    array(
                        'key' => '_anar_sku',
                        'compare' => 'EXISTS'
                    ),
                    array(
                        'key' => '_anar_sku_backup',
                        'compare' => 'EXISTS'
                    ),
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
            //ProductManager::restore_product_deprecation($product_id);
            $anar_sku = get_post_meta($product_id, '_anar_sku', true);


            if(!$anar_sku){
                $anar_sku_backup = get_post_meta($product_id, '_anar_sku_backup', true);
                if($anar_sku_backup){
                    ProductManager::restore_product_deprecation($product_id);
                    $this->log('Product #' . $product_id . ' was deprecated. restored to check again.');
                    $anar_sku = $anar_sku_backup;
                }
            }


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
                $sync = Sync::get_instance();
                $wc_product = wc_get_product($product_data['ID']);
                if (isset($data->attributes) && !empty($data->attributes) && $wc_product && $wc_product->get_type() === 'simple') {
                    ProductManager::convert_simple_to_variable($wc_product, $data);
                    $wc_product = wc_get_product($product_data['ID']);
                }
                if (isset($data->attributes) && !empty($data->attributes)) {
                    $sync->processVariableProduct($data, true);
                } else {
                    $sync->processSimpleProduct($data, true);
                }

                update_post_meta($product_data['ID'], '_anar_last_try_time', current_time('mysql'));
                
                $this->log('Updated, #' . $product_data['ID'] . '  SKU: ' . $data->id, 'info');

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
    public function process_outdated_products_cronjob() {
        try {
            $products = $this->get_outdated_products();
            
            // If no products need sync, just return
            if (empty($products)) {
                $this->log("No products need sync at this time");
                return;
            }
            
            $start_time = time();
            $processed = 0;
            $failed = 0;

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
        }
    }

    /**
     * AJAX handler for clearing sync times
     */
    public function clear_sync_times_ajax() {
        if (!current_user_can('manage_woocommerce')) {
            wp_send_json_error('شما این مجوز را ندارید!');
            wp_die();
        }

        try {
            global $wpdb;

            // Get total count of products with _anar_sku
            $total_products = $wpdb->get_var("
                SELECT COUNT(DISTINCT post_id) 
                FROM {$wpdb->postmeta} 
                WHERE meta_key = '_anar_sku'
            ");

            if ($total_products === null) {
                wp_send_json_error('خطا در دریافت تعداد محصولات');
                return;
            }

            // Delete all _anar_last_try_time meta data
            $deleted = $wpdb->delete(
                $wpdb->postmeta,
                array('meta_key' => '_anar_last_try_time')
            );

            if ($deleted === false) {
                wp_send_json_error('خطا در پاک کردن زمان‌های بروزرسانی');
                return;
            }

            $this->log("Cleared sync times for {$deleted} products", 'info');

            wp_send_json_success([
                'message' => 'زمان‌های بروزرسانی با موفقیت پاک شدند',
                'total_products' => $total_products,
                'cleared_count' => $deleted
            ]);

        } catch (\Exception $e) {
            wp_send_json_error([
                'message' => $e->getMessage()
            ]);
        }
    }

}

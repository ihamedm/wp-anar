<?php

namespace Anar;
use Anar\Core\Activation;
use Anar\Core\Logger;
use Anar\Core\Mock;
use Anar\Wizard\ProductManager;
use SimplePie\Exception;

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


    private function get_outdated_products() {
        global $wpdb;
        
        $time_ago = date('Y-m-d H:i:s', strtotime("-{$this->outdated_threshold}"));
        
        // Use direct SQL query for better performance
        // Since we ensure all Anar products have _anar_last_sync_time meta, we don't need NOT EXISTS logic
        $sql = $wpdb->prepare("
            SELECT DISTINCT p.ID, 
                   COALESCE(sku.meta_value, sku_backup.meta_value) as anar_sku
            FROM {$wpdb->posts} p
            LEFT JOIN {$wpdb->postmeta} sku ON p.ID = sku.post_id AND sku.meta_key = '_anar_sku'
            LEFT JOIN {$wpdb->postmeta} sku_backup ON p.ID = sku_backup.post_id AND sku_backup.meta_key = '_anar_sku_backup'
            INNER JOIN {$wpdb->postmeta} last_try ON p.ID = last_try.post_id AND last_try.meta_key = '_anar_last_sync_time'
            WHERE p.post_type = 'product'
            AND p.post_status IN ('publish', 'draft')
            AND (sku.meta_value IS NOT NULL OR sku_backup.meta_value IS NOT NULL)
            AND last_try.meta_value < %s
            ORDER BY last_try.meta_value ASC
            LIMIT %d
        ", $time_ago, $this->batch_size);
        
        $results = $wpdb->get_results($sql);
        
        $this->log('Found ' . count($results) . ' products that have been outdated more than '. $this->outdated_threshold);
        
        $products = [];
        foreach ($results as $row) {
            if ($row->anar_sku) {
                // Check if we need to restore from backup
                // Only restore if product doesn't have _anar_sku but has _anar_sku_backup
                $anar_sku = get_post_meta($row->ID, '_anar_sku', true);
                if (!$anar_sku) {
                    $anar_sku_backup = get_post_meta($row->ID, '_anar_sku_backup', true);
                    if ($anar_sku_backup) {
                        ProductManager::restore_product_deprecation($row->ID);
                        $this->log('Product #' . $row->ID . ' was deprecated. restored to check again.');
                        $row->anar_sku = $anar_sku_backup;
                    }
                }
                
                $products[] = [
                    'ID' => $row->ID,
                    'anar_sku' => $row->anar_sku
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
                throw new Exception($api_response->get_error_message());
            }

            $response_code = wp_remote_retrieve_response_code($api_response);
            $response_body = wp_remote_retrieve_body($api_response);
            $data = json_decode($response_body);


            if ($response_code === 200 && $data) {
                $sync = Sync::get_instance();
                $wc_product = wc_get_product($product_data['ID']);
                if (isset($data->attributes) && !empty($data->attributes) && $wc_product && $wc_product->get_type() === 'simple') {
                    // some products changed to variable because of adding more than 1 variant on Anar by supplier
                    ProductManager::convert_simple_to_variable($wc_product, $data);
                }
                if (isset($data->attributes) && !empty($data->attributes)) {
                    $sync->processVariableProduct($data,$product_data['ID'], true);
                } else {
                    $sync->processSimpleProduct($data, $product_data['ID'], true);
                }


                $this->log('Updated, #' . $product_data['ID'] . '  SKU: ' . $data->id, 'info');

                return true;
            } elseif ($response_code === 404) {
                ProductManager::set_product_as_deprecated($product_data['ID'], 'sync_outdated', 'sync', true);
                return true;
            }elseif ($response_code === 403) {
                // 403 means token is invalid - this will affect all subsequent API calls
                // Log the error and return a special status to indicate authentication failure
                $this->log("Authentication failed (403) for product {$product_data['anar_sku']}. Token may be invalid.", 'error');
                return 'auth_failed';
            }

            return false;
        } catch (\Exception $e) {
            $this->log("Error processing product {$product_data['anar_sku']}: " . $e->getMessage(), 'error');
            return false;
        }
    }

    /**
     * Main method to process outdated products
     * 
     * @param bool $return_results Whether to return results (for manual execution)
     * @return array|void Results array if $return_results is true, void otherwise
     */
    public function process_outdated_products_cronjob($return_results = false) {

        if(!Activation::is_active()){
            $this->log('SyncOutdated Products is Stopped!! Anar is not active!');
            return $return_results ? ['processed' => 0, 'failed' => 0, 'total_checked' => 0] : null;
        }

        try {
            $products = $this->get_outdated_products();
            
            // If no products need sync, just return
            if (empty($products)) {
                $this->log("No products need sync at this time");
                return $return_results ? ['processed' => 0, 'failed' => 0, 'total_checked' => 0] : null;
            }
            
            $start_time = time();
            $processed = 0;
            $failed = 0;
            $total_checked = count($products);

            foreach ($products as $product) {
                if (time() - $start_time > $this->max_execution_time) {
                    $this->log("Approaching execution time limit. Processed: {$processed}, Failed: {$failed}");
                    break;
                }

                $result = $this->process_product($product);
                
                if ($result === true) {
                    $processed++;
                } elseif ($result === 'auth_failed') {
                    // Authentication failed - stop processing as all subsequent calls will fail
                    $this->log("Authentication failed. Stopping sync process. Processed: {$processed}, Failed: {$failed}");
                    break;
                } else {
                    $failed++;
                }
            }

            $this->log("Sync completed. Processed: {$processed}, Failed: {$failed}");
            
            if ($return_results) {
                return [
                    'processed' => $processed,
                    'failed' => $failed,
                    'total_checked' => $total_checked
                ];
            }
            
        } catch (\Exception $e) {
            $this->log("Error during sync process: " . $e->getMessage(), 'error');
            if ($return_results) {
                return ['processed' => 0, 'failed' => 0, 'total_checked' => 0, 'error' => $e->getMessage()];
            }
        }
    }



}

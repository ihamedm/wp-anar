<?php
namespace Anar;

use Anar\Core\CronJobs;
use Anar\Core\Image_Downloader;
use Anar\Core\Logger;
use Anar\Wizard\ProductManager;
use WP_Query;

class ImportSlow {

    /**
     * @var string
     */
    public $logger = null;
    private static $instance = null;
    private int $stuck_process_timeout = 180; // 3 minutes
    private int $heartbeat_timeout = 300; // 5 minutes
    private Image_Downloader $image_downloader;

    public function __construct()
    {
        $this->logger = new Logger();
        $this->image_downloader = new Image_Downloader();
        
        // Initialize failed products tracking if not exists
        if (!get_option('awca_failed_products')) {
            update_option('awca_failed_products', array());
        }
    }

    public function log($message, $level = 'info') {
        $this->logger->log($message,'import', $level);
    }

    public static function get_instance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function process_the_row() {
        if(self::is_create_products_cron_locked()){
            return;
        }

        if(self::is_process_a_row_on_progress()){
            $this->log('start new row process, but find a process on progress..., wait until next cronjob trigger.');
            return;
        }

        // Set start time for this row process
        set_transient('awca_create_product_row_start_time', time(), 30 * MINUTE_IN_SECONDS);

        try {
            global $wpdb;

            // Fetch one unprocessed row from the database
            $table_name = $wpdb->prefix . ANAR_DB_NAME;
            $row = $wpdb->get_results("SELECT * FROM $table_name WHERE `key` = 'products' AND `processed` = 0 ORDER BY `page` ASC LIMIT 1", ARRAY_A);

            if (empty($row)) {
                $this->log('No more unprocessed product pages found.');
                $this->complete();
                return;
            }

            $row = $row[0];
            $this->log("Processing row {$row['page']}");
            self::set_process_a_row_on_progress();

            // Create job for first page
            if ($row['page'] == 1) {
                $awca_products = maybe_unserialize($row['response']);
                if ($awca_products && isset($awca_products->total)) {
                    update_option('awca_total_products', $awca_products->total);
                    update_option('awca_proceed_products', 0);
                }
            }

            // Process the row
            $this->create_products(array(
                'row_id' => $row['id'],
                'row_page_number' => $row['page']
            ));

        } catch (\Exception $e) {
            $this->log('Error processing row: ' . $e->getMessage(), 'error');
            self::set_process_row_complete();
            delete_transient('awca_create_product_row_start_time');
        } finally {
            (new ProductData())->count_anar_products(true);
            self::set_process_row_complete();
        }
    }

    protected function create_products($item) {
        global $wpdb;
        $table_name = $wpdb->prefix . ANAR_DB_NAME;

        try {
            if (!isset($item['row_id'])) {
                $this->log('Invalid row data, skipping.', 'warning');
                return false;
            }

            $row = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table_name WHERE id = %d", $item['row_id']), ARRAY_A);

            if (!$row) {
                $this->log("No data found for ID {$item['row_id']}, skipping.");
                return false;
            }

            $page = $row['page'];
            $serialized_response = $row['response'];

            $this->log("-------- Processing page {$page} -------");

            $awca_products = maybe_unserialize($serialized_response);
            if ($awca_products === false || !isset($awca_products->items)) {
                $this->log("Failed to deserialize or invalid response for page $page.", 'error');
                return false;
            }

            $attributeMap = get_option('attributeMap');
            $categoryMap = get_option('categoryMap');

            // Initialize counters for this page
            $page_created_products = 0;
            $page_exist_products = 0;
            $page_failed_products = 0;
            $proceed_products = get_option('awca_proceed_products', 0);

            // Process one product at a time
            foreach ($awca_products->items as $product_item) {
                set_transient('awca_create_product_heartbeat', time(), 10 * MINUTE_IN_SECONDS);
                
                $wpdb->query('START TRANSACTION');
                
                try {
                    $prepared_product = ProductManager::product_serializer($product_item);
                    $sku = $prepared_product['sku'];
                    
                    // Check if this product should be skipped due to previous failures
                    if ($this->handle_failed_product($sku)) {
                        $this->log("Skipping product SKU {$sku} due to previous failures", 'warning');
                        $page_failed_products++;
                        $wpdb->query('ROLLBACK');
                        continue;
                    }
                    
                    $product_creation_data = array(
                        'name' => $prepared_product['name'],
                        'regular_price' => $prepared_product['regular_price'],
                        'description' => $prepared_product['description'],
                        'image' => $prepared_product['image'],
                        'categories' => $prepared_product['categories'],
                        'category' => $prepared_product['category'],
                        'stock_quantity' => $prepared_product['stock_quantity'],
                        'gallery_images' => $prepared_product['gallery_images'],
                        'attributes' => $prepared_product['attributes'],
                        'variants' => $prepared_product['variants'],
                        'sku' => $sku,
                        'shipments' => $prepared_product['shipments'],
                        'shipments_ref' => $prepared_product['shipments_ref'],
                    );

                    $this->log('--------------------------------------------', 'debug');
                    $this->log('Start to process product sku: ' . $sku, 'debug');

                    $product_creation_result = ProductManager::create_wc_product($product_creation_data, $attributeMap, $categoryMap);

                    if ($product_creation_result) {
                        $product_id = $product_creation_result['product_id'];
                        if ($product_creation_result['created']) {
                            $page_created_products++;
                            $this->image_downloader->set_product_thumbnail($product_id, $prepared_product['image']);
                            $this->log('Slow :: Created new product - ID: ' . $product_id . ', SKU: ' . $sku, 'info');
                            $this->clear_failed_product($sku);
                        } else {
                            $page_exist_products++;
                            $this->log('Slow :: Product already exists - ID: ' . $product_id . ', SKU: ' . $sku, 'info');
                            $this->clear_failed_product($sku);
                        }
                    }

                    $proceed_products++;
                    update_option('awca_proceed_products', $proceed_products);

                    $wpdb->query('COMMIT');
                    
                    // Clear object from memory
                    unset($prepared_product);
                    unset($product_creation_data);
                    
                } catch (\Exception $e) {
                    $wpdb->query('ROLLBACK');
                    $this->log('Error creating product: ' . $e->getMessage() . ' - SKU: ' . $prepared_product['sku'], 'error');
                    $page_failed_products++;
                    $this->handle_failed_product($prepared_product['sku']);
                    continue;
                }

                // Sleep between products to reduce server load
                sleep(2);
            }

            // Mark the page as processed
            $wpdb->update(
                $table_name,
                array('processed' => 1),
                array('id' => $item['row_id']),
                array('%d'),
                array('%d')
            );
            
            // Clear process-related transients
            delete_transient('awca_create_product_row_on_progress');
            delete_transient('awca_create_product_row_start_time');
            delete_transient('awca_create_product_heartbeat');
            
            $this->log("Page $page processed and marked as complete. Total products created: {$page_created_products}, " . 
                "Total existing: {$page_exist_products}, Total failed: {$page_failed_products}");
            return true;

        } catch (\Exception $e) {
            $this->log("Error processing page {$page}: " . $e->getMessage(), 'error');
            delete_transient('awca_create_product_row_on_progress');
            delete_transient('awca_create_product_row_start_time');
            delete_transient('awca_create_product_heartbeat');
            return false;
        }
    }

    private function notice_completed(){
        $response = ApiDataHandler::postAnarApi('https://api.anar360.com/wp/status', ['status'=> 'synced']);

        if (is_wp_error($response)) {
            $this->log('set status completed in Anar failed, Error: '. $response->get_error_message(), 'error');
        } else {
            $this->log('set status completed in Anar done successfully. $response:' . print_r($response['body'], true));
        }
    }

    protected function complete() {
        $this->notice_completed();
        $this->lock_create_products_cron();

        delete_option('awca_proceed_products');
        delete_option('awca_product_save_lock');
        $this->log('Background Complete method : All products have been processed.');

        // Calculate and log the total time taken
        $start_time = get_option('awca_cron_create_products_start_time');
        if ($start_time) {
            $end_time = current_time('timestamp');
            $total_time = $end_time - $start_time; // Time in seconds

            $hours = floor($total_time / 3600);
            $minutes = floor(($total_time % 3600) / 60);
            $seconds = $total_time % 60;
            delete_option('awca_cron_create_products_start_time');

            $this->log("Import products completed. Total time taken: {$hours} hours, {$minutes} minutes, {$seconds} seconds.");
        }
    }

    public function lock_create_products_cron(){
        update_option('awca_create_product_cron_lock', 'lock');
        $this->log('create products locked.');
        CronJobs::get_instance()->reschedule_events();
    }

    public static function unlock_create_products_cron(){
        update_option('awca_create_product_cron_lock', 'unlock');
        CronJobs::get_instance()->reschedule_events();
    }

    public static function is_create_products_cron_locked(){
        return get_option('awca_create_product_cron_lock') === 'lock';
    }

    public static function set_process_a_row_on_progress(){
        set_transient('awca_create_product_row_on_progress', 'yes', 5 * MINUTE_IN_SECONDS);
    }

    public static function set_process_row_complete(){
        delete_transient('awca_create_product_row_on_progress');
    }

    public static function is_process_a_row_on_progress(){
        return get_transient('awca_create_product_row_on_progress') === 'yes';
    }

    public function check_for_stuck_processes() {
        $process_started = get_transient('awca_create_product_row_start_time');
        $last_heartbeat = get_transient('awca_create_product_heartbeat');
        $current_time = time();

        if (self::is_process_a_row_on_progress()) {
            $this->log("Found a process marked as in-progress, checking if it's stuck...", 'warning');

            $is_stuck = false;

            if ($process_started && ($current_time - $process_started > $this->stuck_process_timeout)) {
                $this->log("Process has been running for more than 5 minutes, likely stuck", 'warning');
                $is_stuck = true;
            }

            if ($last_heartbeat && ($current_time - $last_heartbeat > $this->heartbeat_timeout)) {
                $this->log("No heartbeat detected for more than 8 minutes, likely stuck", 'warning');
                $is_stuck = true;
            }

            if ($is_stuck) {
                $this->log("Resetting stuck process locks to allow processing to continue", 'warning');
                delete_transient('awca_create_product_row_on_progress');
                delete_transient('awca_create_product_row_start_time');
                delete_transient('awca_create_product_heartbeat');
            }
        }
    }

    public function get_progress_data() {
        $total_products = get_option('awca_total_products', 0);
        $proceed_products = get_option('awca_proceed_products', 0);
        $start_time = get_option('awca_cron_create_products_start_time');

        // Prevent showing processed products number greater than total
        $proceed_products = min($proceed_products, $total_products);

        $estimate_minutes = 0;
        if ($start_time && $proceed_products > 0) {
            $current_time = current_time('timestamp');
            $elapsed_time = $current_time - $start_time;
            $avg_time_per_product = $elapsed_time / $proceed_products;
            $remaining_products = $total_products - $proceed_products;
            $estimate_minutes = ceil(($avg_time_per_product * $remaining_products) / 60);
        }

        return [
            'total' => $total_products,
            'processed' => $proceed_products,
            'estimated_minutes' => $estimate_minutes
        ];
    }

    private function handle_failed_product($sku, $max_retries = 3) {
        $failed_products = get_option('awca_failed_products', array());
        
        if (!isset($failed_products[$sku])) {
            $failed_products[$sku] = array(
                'attempts' => 1,
                'last_attempt' => time()
            );
        } else {
            $failed_products[$sku]['attempts']++;
            $failed_products[$sku]['last_attempt'] = time();
        }
        
        update_option('awca_failed_products', $failed_products);
        
        if ($failed_products[$sku]['attempts'] >= $max_retries) {
            $this->log("Product SKU {$sku} has failed {$max_retries} times. Skipping permanently.", 'warning');
            return true;
        }
        
        return false;
    }

    private function clear_failed_product($sku) {
        $failed_products = get_option('awca_failed_products', array());
        if (isset($failed_products[$sku])) {
            unset($failed_products[$sku]);
            update_option('awca_failed_products', $failed_products);
        }
    }
} 
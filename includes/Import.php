<?php
namespace Anar;

use Anar\Core\CronJobs;
use Anar\Core\Image_Downloader;
use Anar\Core\Logger;
use Anar\Wizard\ProductManager;
use WP_Query;

class Import {

    /**
     * @var string
     */
    public $logger = null;
    private static $instance = null;
    private int $stuck_process_timeout = 180; // 3 minutes
    private int $heartbeat_timeout = 300; // 5 minutes
    private Image_Downloader $image_downloader;
    private JobManager $job_manager;


    public function __construct()
    {
        $this->logger = new Logger();
        $this->job_manager = JobManager::get_instance();

        //add_action( 'admin_notices', array( $this, 'admin_notice' ) );
        add_action('admin_notices', [$this, 'show_removed_products_notice']);

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

        $start_time = microtime(true);
        // Set a limit slightly below your server's PHP execution time
        $max_execution_time = 240;
        $rows_processed = 0;
        $batch_size = 5; // Process 5 products at a time
        $sleep_interval = 1; // Sleep for 1 second between batches

        // Get the job ID at the start of the process
        $job_id = $this->get_jobID();
        if (!$job_id) {
            $this->log('No active job ID found. Starting new import process.', 'info');
        } else {
            $this->log('Continuing import process with job ID: ' . $job_id, 'info');
        }

        do {
            try {
                global $wpdb;

                // Fetch all unprocessed rows from the database
                $table_name = $wpdb->prefix . ANAR_DB_NAME;
                $row = $wpdb->get_results("SELECT * FROM $table_name WHERE `key` = 'products' AND `processed` = 0 ORDER BY `page` ASC LIMIT 1", ARRAY_A);

                if (empty($row)) {
                    $this->log('No more unprocessed product pages found.');
                    $this->complete();
                    break;
                }

                $row = $row[0];
                $this->log("start to create products of row {$row['page']}");
                self::set_process_a_row_on_progress();

                // Create job only for the first page if no job exists
                if (!$job_id && $row['page'] == 1) {
                    // Create new job for first page
                    $awca_products = maybe_unserialize($row['response']);
                    if ($awca_products && isset($awca_products->total)) {
                        $job = $this->job_manager->create_job('anar_api', $awca_products->total);
                        if ($job) {
                            $job_id = $job['job_id'];
                            $this->set_jobID($job_id);
                            $this->log("Created new import job: {$job_id} with total products: {$awca_products->total}", 'info');
                        }
                    }
                }

                // Process products in smaller batches
                $this->create_products(array(
                    'row_id' => $row['id'],
                    'row_page_number' => $row['page'],
                    'batch_size' => $batch_size,
                    'sleep_interval' => $sleep_interval,
                    'job_id' => $job_id
                ));

                $rows_processed++;

                // Sleep between rows to reduce CPU load
                if ($rows_processed < 10) {
                    sleep($sleep_interval);
                }

            } catch (\Exception $e) {
                $this->log('Error processing row: ' . $e->getMessage(), 'error');
                if ($job_id) {
                    $this->job_manager->complete_job($job_id, 'failed', $e->getMessage());
                }
                self::set_process_row_complete();
                delete_transient('awca_create_product_row_start_time');
                break;
            } finally {
                (new ProductData())->count_anar_products(true);
                self::set_process_row_complete();
            }

            // Check if we're approaching the time limit
            $elapsed_time = microtime(true) - $start_time;

        } while($elapsed_time < $max_execution_time && $rows_processed < 10);
    }



    /**
     * Task to perform for each item in the queue.
     *
     * @param array $item The item to process.
     * @return mixed
     */
    protected function create_products($item) {
        global $wpdb;
        $table_name = $wpdb->prefix . ANAR_DB_NAME;

        try {
            if (!isset($item['row_id'])) {
                $this->log('Invalid row data, skipping.', 'warning');
                return false;
            }

            if ($item['row_page_number'] == 1) {
                $start_time = current_time('timestamp');
                update_option('awca_cron_create_products_start_time', $start_time);
                update_option('awca_proceed_products', 0);
            }

            $row = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table_name WHERE id = %d", $item['row_id']), ARRAY_A);

            if (!$row) {
                $this->log("No data found for ID {$item['row_id']}, skipping.");
                return false;
            }

            $page = $row['page'];
            $job_id = $item['job_id'];
            
            if (!$job_id) {
                $this->log("No job ID provided for page {$page}, skipping job updates.", 'warning');
            }

            $serialized_response = $row['response'];

            $this->log("-------- Background cron Task Start, jobID {$job_id}, page {$page} -------", );

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
            update_option('awca_total_products', $awca_products->total);
            $proceed_products = get_option('awca_proceed_products', 0);

            // Process products in batches
            $batch_size = isset($item['batch_size']) ? $item['batch_size'] : 5;
            $sleep_interval = isset($item['sleep_interval']) ? $item['sleep_interval'] : 1;
            $total_products = count($awca_products->items);
            $batches = array_chunk($awca_products->items, $batch_size);

            foreach ($batches as $batch_index => $batch) {
                $batch_created = 0;
                $batch_exist = 0;
                $batch_failed = 0;

                foreach ($batch as $product_item) {
                    set_transient('awca_create_product_heartbeat', time(), 10 * MINUTE_IN_SECONDS);
                    
                    $wpdb->query('START TRANSACTION');
                    
                    try {
                        $prepared_product = ProductManager::product_serializer($product_item);
                        $sku = $prepared_product['sku'];
                        
                        // Check if this product should be skipped due to previous failures
                        if ($this->handle_failed_product($sku)) {
                            $this->log("Skipping product SKU {$sku} due to previous failures", 'warning');
                            $batch_failed++;
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

                        $product_id = $product_creation_result['product_id'];
                        if ($product_creation_result['created']) {
                            $batch_created++;
                            $this->image_downloader->set_product_thumbnail($product_id, $prepared_product['image']);
                            $this->log('Created new product - ID: ' . $product_id . ', SKU: ' . $sku, 'info');
                            // Clear failed status if product was successfully created
                            $this->clear_failed_product($sku);
                        } else {
                            $batch_exist++;
                            $this->log('Product already exists - ID: ' . $product_id . ', SKU: ' . $sku, 'info');
                            // Clear failed status if product exists
                            $this->clear_failed_product($sku);
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
                        $batch_failed++;
                        // Track the failed product
                        $this->handle_failed_product($prepared_product['sku']);
                        continue;
                    }
                }

                // Update page counters
                $page_created_products += $batch_created;
                $page_exist_products += $batch_exist;
                $page_failed_products += $batch_failed;

                // Update job progress after each batch
                if ($job_id) {
                    $this->log("Updating job {$job_id} progress - Processed: {$proceed_products}, Created: {$page_created_products}, " . 
                        "Existing: {$page_exist_products}, Failed: {$page_failed_products}", 'debug');
                    
                    $this->job_manager->update_job_progress(
                        $job_id,
                        $proceed_products,
                        $page_created_products,
                        $page_exist_products,
                        $page_failed_products
                    );
                }

                // Sleep between batches to reduce CPU load
                if ($batch_index < count($batches) - 1) {
                    sleep($sleep_interval);
                }

                // Force garbage collection after each batch
                if (function_exists('gc_collect_cycles')) {
                    gc_collect_cycles();
                }
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
            
            // Update job status if there's an error
            if ($job_id) {
                $this->job_manager->complete_job($job_id, 'failed', $e->getMessage());
            }
            
            delete_transient('awca_create_product_row_on_progress');
            delete_transient('awca_create_product_row_start_time');
            delete_transient('awca_create_product_heartbeat');
            
            return false;
        }
    }



    /**
     * We must notice Anar that creation products complete done.
     * @return void
     */
    private function notice_completed(){
        $response = ApiDataHandler::postAnarApi('https://api.anar360.com/wp/status', ['status'=> 'synced']);

        if (is_wp_error($response)) {
            $this->log('set status completed in Anar failed, Error: '. $response->get_error_message(), 'error');
        } else {
            $this->log('set status completed in Anar done successfully. $response:' . print_r($response['body'], true));
        }
    }


    // Add the notice
    public function show_removed_products_notice() {
        // Handle the dismiss action
        if (isset($_GET['awca_hide_notice']) && wp_verify_nonce($_GET['nonce'], 'awca_hide_notice')) {
            delete_option('awca_deprecated_products_count');
            return;
        }

        if (isset($_GET['anar_deprecated'])) {
            delete_option('awca_deprecated_products_count');
            return;
        }

        // Only show to administrators
        if (!current_user_can('manage_options')) {
            return;
        }

        $removed_count = get_option('awca_deprecated_products_count', 0);

        if ($removed_count > 0) {
            // Create query parameters for the products page
            $query_args = array(
                'post_type' => 'product',
                'anar_deprecated' => 'true'
            );

            // Generate the URL for viewing deprecated products
            $view_url = add_query_arg($query_args, admin_url('edit.php'));

            // Generate the dismiss URL (redirect back to current page after dismissing)
            $current_url = add_query_arg(null, null);
            $dismiss_nonce = wp_create_nonce('awca_hide_notice');
            $dismiss_url = add_query_arg([
                'awca_hide_notice' => '1',
                'nonce' => $dismiss_nonce
            ], $current_url);

            // Notice HTML
            $notice = sprintf(
                '<div class="notice notice-warning is-dismissible">
            <p>
                <strong>انار۳۶۰:</strong> 
                 محصولاتی از پنل انار شما حذف شده‌اند که در وب‌سایت به حالت ناموجود تغییر وضعیت داده شدند. 
                می‌توانید لیست آنها را برای تعیین تکلیف ببینید.
                <a href="%s" class="button button-small" style="margin-right: 10px;">مشاهده محصولات غیر فعال شده</a>
                <a href="%s" style="margin-right: 15px; color: #999; text-decoration: none;">دیگر نشان نده</a>
            </p>
        </div>',
                esc_url($view_url),
                esc_url($dismiss_url)
            );

            echo wp_kses_post($notice);
        }
    }


    /**
     * Complete
     *
     * Called when the background process is complete.
     */
    protected function complete() {
        $this->notice_completed();
        $this->lock_create_products_cron();

        $import_jobID = $this->get_jobID();
        if ($import_jobID) {
            // Complete the job
            $this->job_manager->complete_job($import_jobID, 'completed');
        }

        ProductManager::handle_removed_products_from_anar('import', $import_jobID);

        delete_option('awca_proceed_products');
        delete_transient('anar_import_products_job_id');
        delete_option('awca_product_save_lock');
        $this->log("------------------------ completed import job {$import_jobID} ----------------------");
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


    public function set_jobID($page){
        if($page == 1){
            $new_jobID = uniqid('awca_job_');
            set_transient('anar_import_products_job_id', $new_jobID, 7200);

            return $new_jobID;
        }
        return $this->get_jobID();
    }

    public function get_jobID(){
        return get_transient('anar_import_products_job_id');
    }


    /**
     * deactivate cron event that create products when all products processed
     * @return void
     */
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
        // Check if a process has been running too long
        $process_started = get_transient('awca_create_product_row_start_time');
        $last_heartbeat = get_transient('awca_create_product_heartbeat');

        $current_time = time();

        // If process has been flagged as in-progress
        if (self::is_process_a_row_on_progress()) {
            $this->log("Found a process marked as in-progress, checking if it's stuck...", 'warning');

            $is_stuck = false;

            // Check start time - if it's been more than 5 minutes, consider it stuck
            if ($process_started && ($current_time - $process_started > $this->stuck_process_timeout)) {
                $this->log("Process has been running for more than 5 minutes, likely stuck", 'warning');
                $is_stuck = true;
            }

            // Also check heartbeat - if no heartbeat for 8 minutes, consider it stuck
            if ($last_heartbeat && ($current_time - $last_heartbeat > $this->heartbeat_timeout)) {
                $this->log("No heartbeat detected for more than 8 minutes, likely stuck", 'warning');
                $is_stuck = true;
            }

            // If we've determined the process is stuck
            if ($is_stuck) {
                $this->log("Resetting stuck process locks to allow processing to continue", 'warning');
                delete_transient('awca_create_product_row_on_progress');
                delete_transient('awca_create_product_row_start_time');
                delete_transient('awca_create_product_heartbeat');

                // Optionally, you could log which row was being processed
                $progress_data = get_transient('awca_product_creation_progress');
                if ($progress_data) {
                    $this->log("Stuck process was working on row ID: " . $progress_data['row_id'] .
                        ", processed " . count($progress_data['products_processed']) . " products");
                    delete_transient('awca_product_creation_progress');
                }
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

    /**
     * Track failed product and determine if it should be skipped
     * 
     * @param string $sku Product SKU
     * @param int $max_retries Maximum number of retries before skipping
     * @return bool True if product should be skipped, false otherwise
     */
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

    /**
     * Clear failed product tracking
     * 
     * @param string $sku Product SKU
     */
    private function clear_failed_product($sku) {
        $failed_products = get_option('awca_failed_products', array());
        if (isset($failed_products[$sku])) {
            unset($failed_products[$sku]);
            update_option('awca_failed_products', $failed_products);
        }
    }

}
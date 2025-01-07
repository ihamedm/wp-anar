<?php
namespace Anar;

use Anar\Core\Image_Downloader;
use Anar\Core\Logger;
use Anar\Lib\BackgroundProcessing\WP_Background_Process;
use Anar\Wizard\Product;

class Background_Process_Products extends WP_Background_Process {

    /**
     * @var string
     */
    protected $prefix = 'awca';
    protected $action = 'create_products';
    public $logger = null;
    private static $instance = null;

    private $thumbnail_generator = null;


    private function __construct()
    {
        $this->logger = new Logger();

        parent::__construct();
        add_action('wp_ajax_handle_process_actions', [$this,'handle_process_actions']);
        add_action('wp_ajax_awca_run_product_creation_background_process_ajax', [$this, 'process_handler']);
        add_action('wp_ajax_nopriv_awca_run_product_creation_background_process_ajax', [$this, 'process_handler']);
        add_action( 'admin_notices', array( $this, 'admin_notice' ) );

        $this->thumbnail_generator = Background_Process_Thumbnails::get_instance();
    }


    public static function get_instance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }


    public function process_handler() {
        // Verify nonce for the AJAX call
        $this->logger->log('POST data: ' . print_r($_POST, true));
//        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'awca_ajax_nonce')) {
//            wp_send_json_error('Invalid security token sent.');
//            wp_die();
//        }

        try {
            global $wpdb;

            // Fetch all unprocessed rows from the database
            $table_name = $wpdb->prefix . ANAR_DB_NAME;
            $rows = $wpdb->get_results("SELECT * FROM $table_name WHERE `key` = 'products' AND `processed` = 0 ORDER BY `page` ASC", ARRAY_A);

            if ($rows) {
                $this->log('Start to create products in background.', true);

                $total_rows = count($rows);
                foreach ($rows as $row) {
                    $this->push_to_queue(array(
                        'row_id' => $row['id'],
                        'row_page_number' => $row['page'],
                        'total_rows' => $total_rows,
                    ));
                }

                // Save the queue
                $this->save();

                // Now dispatch
                $dispatch_result = $this->dispatch();

                if ($dispatch_result === false) {
                    $this->logger->log('Background process already running or failed to start.');
                }else{
                    $this->logger->log('All rows dispatched');
                }

            } else {
                $this->logger->log('No unprocessed product pages found.');
                wp_send_json_success('No items to process');
            }
        } catch (\Exception $e) {
            $this->logger->log($e->getMessage(), true);
            wp_send_json_error('An error occurred: ' . $e->getMessage());
        }

    }


    protected function verify_request() {
        if (($this->is_processing() || $this->is_queued()) && isset($_GET['nonce'])) {
            return wp_verify_nonce($_GET['nonce'], $this->identifier);
        }
        return false;
    }


    public function admin_notice() {
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }

        $status = $this->get_status();
        if(!$status)
            return;


        // Display the admin notice
        echo '<div class="notice notice-info is-dismissible" id="handle-bg-process" >';
        echo '<p>ساخت محصولات انار در پس زمینه در حال انجام است.';

        if($status == 'processing'){
            echo '<a href="#" data-action="pause_process" style="display:none">وقفه</a> | ';
        }elseif($status == 'queued'){
            echo '<a href="#" data-action="resume_process"  style="display:none">ادامه</a> | ';
        }
        echo '<a href="#" data-action="cancel_process">لغو</a></p></div>';
    }

    public function push_to_queue( $data ) {
        $this->log("push_to_queue - page {$data['row_page_number']} from {$data['total_rows']}");

        return parent::push_to_queue( $data );
    }

    /**
     * Task to perform for each item in the queue.
     *
     * @param array $item The item to process.
     * @return mixed
     */
    protected function task( $item ) {
        global $wpdb;
        $table_name = $wpdb->prefix . ANAR_DB_NAME;

        // Check if the item contains the necessary data
        if ( ! isset( $item['row_id'] ) ) {
            $this->log('Invalid task data, skipping.');
            return false;
        }

        if ($item['row_page_number'] == 1) {
            $start_time = time(); // Current timestamp
            update_option($this->prefix.'_'.$this->action . '_start_time', $start_time);
        }


        // Retrieve the full row data from the database using the ID
        $row = $wpdb->get_row( $wpdb->prepare("SELECT * FROM $table_name WHERE id = %d", $item['row_id']), ARRAY_A );

        if ( ! $row ) {
            $this->log("No data found for ID {$item['row_id']}, skipping.");
            return false;
        }


        // Extract necessary data from the item
        $page = $row['page'];
        $serialized_response = $row['response'];

        $this->log("-------- Background Task Start ,page {$page} -------");

        // deserialize the response
        $awca_products = maybe_unserialize($serialized_response);
        if ($awca_products === false) {
            $this->log("Failed to deserialize the response for page $page.");
            return false;
        }

        if (!isset($awca_products->items)) {
            $this->log("deserialized data does not contain 'items' for page $page.");
            return false;
        }

        // Get necessary options and mappings
        $attributeMap = get_option('attributeMap');
        $combinedCategories = get_option('combinedCategories');
        $categoryMap = get_option('categoryMap');

        $created_product_ids = [];
        $exist_product_ids = [];
        update_option('awca_total_products', $awca_products->total);
        $proceed_products = get_option('awca_proceed_products', 0);

//        $image_downloader = new Image_Downloader();

        // Loop through products and create them in WooCommerce
        foreach ($awca_products->items as $product_item) {
            $prepared_product = Product::product_serializer($product_item);

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
                'sku' => $prepared_product['sku'],
                'shipments' => $prepared_product['shipments'],
                'shipments_ref' => $prepared_product['shipments_ref'],
            );

            // Create or update the product
            $product_creation_result = Product::create_wc_product($product_creation_data, $attributeMap, $categoryMap);

            $product_id = $product_creation_result['product_id'];
            if ($product_creation_result['created']) {
                $created_product_ids[] = $product_id;
//                $image_downloader->set_product_thumbnail($product_id, $prepared_product['image']);
            } else {
                $exist_product_ids[] = $product_id;
            }

            $proceed_products++;
            update_option('awca_proceed_products', $proceed_products);

            $this->log('Product create progress - Created: ' . count($created_product_ids) . ', Exist: ' . count($exist_product_ids));
        }

        // Mark the page as processed
        $wpdb->update(
            $table_name,
            array('processed' => 1),
            array('id' => $item['row_id']),
            array('%d'),
            array('%d')
        );

        if($created_product_ids > 0){
            $this->thumbnail_generator->push_to_queue(['total_products' => $awca_products->total, 'products' => $created_product_ids , 'page_number' => $page] );
            $this->thumbnail_generator->save();
        }


        $this->log("Page $page processed and marked as complete.");

        // Continue processing
        return true;
    }



    public function log($message, $new=false) {
        $this->logger->log('Products :: ' .$message);
    }

    public function get_status() {

        if($this->is_processing())
            return 'processing';
        if($this->is_queued())
            return 'queued';

        return false;
    }


    public function handle_process_actions() {
        // Verify nonce if required

        if (isset($_POST['process_action'])) {
            $process_action = sanitize_text_field($_POST['process_action']);

            switch ($process_action) {
                case 'resume_process':
                    $this->resume();
                    break;
                case 'pause_process':
                    $this->pause();
                    break;
                case 'cancel_process':
                    $this->cancel();
                    break;
            }

            // Return a success message or status
            wp_send_json_success('Action ' . $process_action . ' executed successfully.');
        } else {
            wp_send_json_error('No action specified.');
        }
    }


    protected function cancelled()
    {
        $this->log('------------------------ Cancelled ----------------------');
        delete_transient('awca_product_creation_lock'); // Clear the lock
        delete_option('awca_proceed_products'); // Reset the proceed products
        delete_option('awca_product_save_lock'); // open the lock of getting product from anar (Stepper)
        parent::cancelled();
    }

    protected function paused()
    {
        $this->log('------------------------ Paused --------------------------');
        parent::paused();
    }

    protected function resumed()
    {
        $this->log('------------------------ Resumed --------------------------');
        parent::resumed();
    }


    /**
     * We must notice Anar that creation products complete done.
     * @return void
     */
    private function notice_completed(){
        $response = ApiDataHandler::postAnarApi('https://api.anar360.com/wp/status', ['status'=> 'synced']);

        if (is_wp_error($response)) {
            $this->log('set status completed in Anar failed, Error: '. $response->get_error_message());
        } else {
            $this->log('set status completed in Anar done successfully. $response:' . print_r($response['body'], true));
        }
    }


    private function add_to_awake_list(){
        // Cloudflare Worker URL
        $worker_url = 'https://awake.anarwp.workers.dev/';

        $args = array(
            'headers' => array(
                'x-url' => site_url('wp-cron.php'), // The URL to save
                'Content-Type' => 'application/json',
            ),
            'method' => 'POST',
        );

        // Make the HTTP request to the Cloudflare Worker
        $response = wp_remote_post($worker_url, $args);

        // Handle the response
        if (is_wp_error($response)) {
            // There was an error
            $error_message = $response->get_error_message();
            error_log("Cloudflare Worker API call failed: $error_message");
            return false; // Or handle the error as needed
        }

        // Get the body of the response
        $response_body = wp_remote_retrieve_body($response);
        $this->log('add_to_awake_list:' . json_decode($response_body, true));

        return false;
    }


    /**
     * Complete
     *
     * Called when the background process is complete.
     */
    protected function complete() {
        parent::complete();
        $this->notice_completed();
//        $this->add_to_awake_list();
        delete_transient('awca_product_creation_lock'); // Clear the lock
        delete_option('awca_proceed_products'); // Reset the proceed products
        delete_option('awca_total_products'); // Reset the proceed products
        delete_option('awca_product_save_lock'); // open the lock of getting product from anar (Stepper)
        $this->log('------------------------ completed ----------------------');
        $this->log('Background Complete method : All products have been processed.');


        // Calculate and log the total time taken
        $start_time = get_option($this->prefix.'_'.$this->action . '_start_time');
        if ($start_time) {
            $end_time = time();
            $total_time = $end_time - $start_time; // Time in seconds

            $hours = floor($total_time / 3600);
            $minutes = floor(($total_time % 3600) / 60);
            $seconds = $total_time % 60;
            delete_option($this->prefix.'_'.$this->action . '_start_time');

            $this->log("Background process of Product creation completed. Total time taken: {$hours} hours, {$minutes} minutes, {$seconds} seconds.");
        }


        $this->thumbnail_generator->dispatch();
    }



}
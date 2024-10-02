<?php
namespace Anar;

use Anar\Core\Image_Downloader;
use Anar\Lib\Anar_WP_Background_Process;
use Anar\Wizard\Product;

class Background_Process_Products extends Anar_WP_Background_Process {

    /**
     * @var string
     */
    protected $prefix = 'awca';
    protected $action = 'create_products';
    private static $instance = null;

    private $thumbnail_generator = null;


    private function __construct()
    {
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
        global $wpdb;

        // Fetch all unprocessed rows from the database
        $table_name = $wpdb->prefix . 'awca_large_api_responses';
        $rows = $wpdb->get_results("SELECT * FROM $table_name WHERE `key` = 'products' AND `processed` = 0 ORDER BY `page` ASC", ARRAY_A);

        // Loop through each row and push it to the background process
        if ($rows) {
            $total_rows = count($rows);

            foreach ($rows as $row) {
                $this->push_to_queue(array(
                    'row_id' => $row['id'],
                    'row_page_number' => $row['page'],
                    'total_rows' => $total_rows,
                ));
            }

            // Save and dispatch the tasks
            $this->save()->dispatch();
        } else {
            awca_log('No unprocessed product pages found.');
        }
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
        awca_log("push_to_queue - page {$data['row_page_number']} from {$data['total_rows']}");

        return parent::push_to_queue( $data );
    }

    /**
     * Task to perform for each item in the queue.
     *
     * @param array $item The item to process.
     * @return mixed
     */
    protected function task( $item ) {
        awca_log(print_r($item, true));
        global $wpdb;
        $table_name = $wpdb->prefix . 'awca_large_api_responses';

        // Check if the item contains the necessary data
        if ( ! isset( $item['row_id'] ) ) {
            awca_log('Invalid task data, skipping.');
            return false;
        }

        if ($item['row_page_number'] == 1) {
            $start_time = time(); // Current timestamp
            update_option($this->prefix.'_'.$this->action . '_start_time', $start_time);
        }


        // Retrieve the full row data from the database using the ID
        $row = $wpdb->get_row( $wpdb->prepare("SELECT * FROM $table_name WHERE id = %d", $item['row_id']), ARRAY_A );

        if ( ! $row ) {
            awca_log("No data found for ID {$item['row_id']}, skipping.");
            return false;
        }


        // Extract necessary data from the item
        $page = $row['page'];
        $serialized_response = $row['response'];

        awca_log("-------- Background Task Start ,page {$page} -------");

        // Unserialize the response
        $awca_products = maybe_unserialize($serialized_response);
        if ($awca_products === false) {
            awca_log("Failed to unserialize the response for page $page.");
            return false;
        }

        if (!isset($awca_products->items)) {
            awca_log("Unserialized data does not contain 'items' for page $page.");
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

            awca_log('Product create progress - Created: ' . count($created_product_ids) . ', Exist: ' . count($exist_product_ids));
        }

        // Mark the page as processed
        $wpdb->update(
            $table_name,
            array('processed' => 1),
            array('id' => $item['row_id']),
            array('%d'),
            array('%d')
        );


        $this->thumbnail_generator->push_to_queue(['total_products' => $awca_products->total, 'products' => $created_product_ids , 'page_number' => $page] );
        $this->thumbnail_generator->save();


        awca_log("Page $page processed and marked as complete.");

        // Continue processing
        return true;
    }



    public function log_status() {
        awca_log("Background product creation => Processing: {$this->is_processing()}, Queued: {$this->is_queued()}, Active: {$this->is_cancelled()}");
    }

    public function get_status() {
//        $this->log_status();

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
        awca_log('------------------------ Cancelled ----------------------');
        delete_transient('awca_product_creation_lock'); // Clear the lock
        delete_option('awca_proceed_products'); // Reset the proceed products
        delete_option('awca_product_save_lock'); // open the lock of getting product from anar (Stepper)
        parent::cancelled(); // TODO: Change the autogenerated stub
    }

    protected function paused()
    {
        awca_log('------------------------ Paused --------------------------');
        parent::paused(); // TODO: Change the autogenerated stub
    }

    protected function resumed()
    {
        awca_log('------------------------ Resumed --------------------------');
        parent::resumed(); // TODO: Change the autogenerated stub
    }


    /**
     * We must notice Anar that creation products complete done.
     * @return void
     */
    private function notice_completed(){
        $response = ApiDataHandler::postAnarApi('https://api.anar360.com/api/360/status', ['status'=> 'synced']);

        if (is_wp_error($response)) {
            awca_log('set status completed in Anar failed, Error: '. $response->get_error_message());
        } else {
            awca_log('set status completed in Anar done successfully. $response:' . print_r($response['body'], true));
        }
    }


    /**
     * Complete
     *
     * Called when the background process is complete.
     */
    protected function complete() {
        parent::complete();
        $this->notice_completed();
        delete_transient('awca_product_creation_lock'); // Clear the lock
        delete_option('awca_proceed_products'); // Reset the proceed products
        delete_option('awca_total_products'); // Reset the proceed products
        delete_option('awca_product_save_lock'); // open the lock of getting product from anar (Stepper)
        awca_log('------------------------ completed ----------------------');
        awca_log('Background Complete method : All products have been processed.');


        // Calculate and log the total time taken
        $start_time = get_option($this->prefix.'_'.$this->action . '_start_time');
        if ($start_time) {
            $end_time = time();
            $total_time = $end_time - $start_time; // Time in seconds

            $hours = floor($total_time / 3600);
            $minutes = floor(($total_time % 3600) / 60);
            $seconds = $total_time % 60;
            delete_option($this->prefix.'_'.$this->action . '_start_time');

            awca_log("Background process of Product creation completed. Total time taken: {$hours} hours, {$minutes} minutes, {$seconds} seconds.");
        }


        $this->thumbnail_generator->dispatch();
    }


}
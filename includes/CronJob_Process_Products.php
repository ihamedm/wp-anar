<?php
namespace Anar;

use Anar\Core\CronJobs;
use Anar\Core\Image_Downloader;
use Anar\Core\Logger;
use Anar\Wizard\ProductManager;
use WP_Query;

class CronJob_Process_Products {

    /**
     * @var string
     */
    public $logger = null;
    private static $instance = null;

    private $thumbnail_generator = null;


    private function __construct()
    {
        $this->logger = new Logger();

        //add_action( 'admin_notices', array( $this, 'admin_notice' ) );
        add_action('admin_notices', [$this, 'show_removed_products_notice']);

        $this->thumbnail_generator = Background_Process_Thumbnails::get_instance();
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

        try {
            global $wpdb;

            // Fetch all unprocessed rows from the database
            $table_name = $wpdb->prefix . ANAR_DB_NAME;
            $row = $wpdb->get_results("SELECT * FROM $table_name WHERE `key` = 'products' AND `processed` = 0 ORDER BY `page` ASC LIMIT 1", ARRAY_A);
            $row = $row[0];
            if ($row) {
                $this->log("start to create products of row {$row['page']}", true);

                $this->create_products(array(
                    'row_id' => $row['id'],
                    'row_page_number' => $row['page'],
                ));


            } else {
                $this->logger->log('No unprocessed product pages found.');
                $this->complete();
            }
        } catch (\Exception $e) {
            $this->logger->log($e->getMessage(), true);
            wp_send_json_error('An error occurred: ' . $e->getMessage());
        }

    }



    /**
     * Task to perform for each item in the queue.
     *
     * @param array $item The item to process.
     * @return mixed
     */
    protected function create_products( $item ) {
        global $wpdb;
        $table_name = $wpdb->prefix . ANAR_DB_NAME;

        // Check if the item contains the necessary data
        if ( ! isset( $item['row_id'] ) ) {
            $this->log('Invalid task data, skipping.');
            return false;
        }

        if ($item['row_page_number'] == 1) {
            $start_time = current_time('timestamp'); // Current timestamp
            update_option('awca_cron_create_products_job_id', uniqid('awca_job_', true));
            update_option( 'awca_cron_create_products_start_time', $start_time);
            update_option( 'awca_proceed_products', 0);
            $this->add_to_awake_list();
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

        $this->log("-------- Background cron Task Start ,page {$page} -------");

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

        $image_downloader = new Image_Downloader();

        // Loop through products and create them in WooCommerce
        foreach ($awca_products->items as $product_item) {
            $prepared_product = ProductManager::product_serializer($product_item);

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
            $product_creation_result = ProductManager::create_wc_product($product_creation_data, $attributeMap, $categoryMap);

            $product_id = $product_creation_result['product_id'];
            if ($product_creation_result['created']) {
                $created_product_ids[] = $product_id;
                $image_downloader->set_product_thumbnail($product_id, $prepared_product['image']);
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



        $this->log("Page $page processed and marked as complete.");

        // Continue processing
        return true;
    }



    public function log($message, $new=false) {
        $this->logger->log('Cron[Products] :: ' .$message);
    }


    public function admin_notice() {
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }

        if(!self::is_create_products_cron_locked())
            return;


        // Display the admin notice
        echo '<div class="notice notice-info is-dismissible" id="handle-bg-process" >';
        echo '<p>ساخت محصولات انار در پس زمینه در حال انجام است.';

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
                'x-url' => site_url('wp-cron.php'),
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

    public function detect_removed_products_from_anar() {
        global $wpdb;

        $this->log('detect removed products from Anar.');

        $job_id = get_option('awca_cron_create_products_job_id');

        if(!$job_id) {
            return;
        }


        $query_result = $wpdb->prepare(
            "SELECT DISTINCT 
            p.ID, 
            p.post_title 
            FROM {$wpdb->posts} AS p
            INNER JOIN {$wpdb->postmeta} AS mt1 ON (p.ID = mt1.post_id)
            LEFT JOIN {$wpdb->postmeta} AS mt2 ON (
                p.ID = mt2.post_id 
                AND mt2.meta_key = '_anar_import_job_id'
            )
            WHERE 1=1 
            AND p.post_type = 'product'
            AND p.post_status != 'trash'
            AND mt1.meta_key = '_anar_products'
            AND (mt2.meta_value IS NULL OR mt2.meta_value != %s)",
            $job_id
        );

        $products = $wpdb->get_results($query_result);
        $product_count = count($products);
        $this->log("found $product_count products that removed from Anar.");

        if (!empty($products)) {
            // Store the count of removed products
            update_option('awca_removed_products_count', count($products));
            update_option('awca_removed_products_time', current_time('mysql', true)); // Store UTC time

            foreach ($products as $product) {
                ProductManager::deprecate_anar_product($product->ID, $job_id);
            }
        }
    }

    // Add the notice
    public function show_removed_products_notice() {
        // Handle the dismiss action
        if (isset($_GET['awca_hide_notice']) && wp_verify_nonce($_GET['nonce'], 'awca_hide_notice')) {
            delete_option('awca_removed_products_count', 0);
            return;
        }

        // Only show to administrators
        if (!current_user_can('manage_options')) {
            return;
        }

        $removed_count = get_option('awca_removed_products_count', 0);

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
        $this::lock_create_products_cron();

        $this->detect_removed_products_from_anar();

        delete_option('awca_proceed_products');
//        delete_option('awca_total_products');
        delete_option('awca_product_save_lock'); // open the lock of getting product from anar (Stepper)
        $this->log('------------------------ completed ----------------------');
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

            $this->log("Background process of Product creation completed. Total time taken: {$hours} hours, {$minutes} minutes, {$seconds} seconds.");
        }

    }


    /**
     * deactivate cron event that create products when all products processed
     * @return void
     */
    public static function lock_create_products_cron(){
        update_option('awca_create_product_cron_lock', 'lock');
        awca_log('create products locked.');
        CronJobs::get_instance()->reschedule_events();
    }


    public static function unlock_create_products_cron(){
        update_option('awca_create_product_cron_lock', 'unlock');
        awca_log('create products unlocked.');
        CronJobs::get_instance()->reschedule_events();
    }

    public static function is_create_products_cron_locked(){
        return get_option('awca_create_product_cron_lock') === 'lock';
    }


}
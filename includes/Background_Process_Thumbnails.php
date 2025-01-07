<?php
namespace Anar;
use Anar\Core\Image_Downloader;
use Anar\Core\Logger;
use Anar\Lib\BackgroundProcessing\WP_Background_Process;

class Background_Process_Thumbnails extends WP_Background_Process {

    /**
     * @var string
     */
    protected $prefix = 'awca';
    protected $action = 'generate_product_thumbnails';
    private static $instance = null;
    public $logger = null;


    private function __construct()
    {
        parent::__construct();
        $this->logger = new Logger();
    }


    public static function get_instance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }


    public function process_handler() {

        $posts_per_page = 30; // Number of products per page
        $paged = 1 ;
        $save_process_data = [];

        do {
            // Setup WP_Query arguments
            $args = array(
                'post_type' => 'product',
                'post_status' => array('publish', 'draft'), // Include both published and draft posts
                'meta_query' => array(
                    array(
                        'key' => '_product_image_url',
                        'value' => '',
                        'compare' => '!=', // Ensure meta_value is not empty
                    ),
                ),
                'posts_per_page' => $posts_per_page, // Limit to posts_per_page posts
                'paged' => $paged, // Set the current page
            );

            // Perform the query
            $query = new \WP_Query($args);
            $queued_products = count($query->posts);
            $this->log('////////////////////// process_handler - page ' . $paged . ' => ' . $queued_products);

            /**
             * Save processed data on options to show on front end via ajax
             */
            if ($paged == 1) {
                /**
                 * because in the same time we push products to the queue ,
                 * task method run and download is starting, probably the found_posts decrease in next query
                 **/
                $this->reset_process_data();
                $save_process_data['total_products'] = $query->found_posts;
            }
            // this value increment every time
            $save_process_data['queued_products'] = $queued_products;
            $this->update_process_data($save_process_data);


            if ($query->have_posts()) {
                $products = [];
                while ($query->have_posts()) {
                    $query->the_post();
                    $products[] = get_the_ID();
                }

                $this->push_to_queue(array(
                    'products' => $products,
                    'page_number' => $paged,
                ));

                $this->save();

                $this->log("push to queue => {$queued_products} products");
                wp_reset_postdata();


            } else {
                // not found any products
                $this->log('No more products found with non-empty _product_image_url on page ' . $paged);

            }
            $paged++; // Move to the next page
        }while($query->max_num_pages >= $paged);

        $this->dispatch();

    }


    public function push_to_queue( $data ) {
        $this->log('pushed to queue: $data[paged] => '. $data['page_number']);
        return parent::push_to_queue( $data );
    }

    /**
     * Task to perform for each item in the queue.
     *
     * @param array $item The item to process.
     * @return mixed
     */
    protected function task( $item ) {
        if(!isset($item['products'])){
            $this->log('Invalid task data, skipping.');
            return false;
        }

        if ($item['page_number'] == 1) {

            $this->reset_process_data();

            $start_time = time(); // Current timestamp
            update_option($this->prefix.'_'.$this->action . '_start_time', $start_time);
            $this->update_process_data(['total_products' => $item['total_products']]);
        }

        foreach ($item['products'] as $index => $product_id){

            $thumbnail_url = get_post_meta($product_id, '_product_image_url', true);

            // If gallery images exist, set the product gallery
            if ($thumbnail_url) {
                $image_downloader = new Image_Downloader();
                $attachment_id = $image_downloader->set_product_thumbnail($product_id, $thumbnail_url);

                if(is_wp_error($attachment_id)){
                    $this->log('Thumbnail of Product #'.$product_id.' set error: '.$attachment_id->get_error_message());
                }

                $this->log('Thumbnail #'.$attachment_id. ' of Product #'.$product_id . ' is set.');
            }

        }



        $this->update_process_data([
            'processed_products' => count($item['products']),
        ]);

        $this->log("Task => Page {$item['page_number']} Processed");

        // Continue processing
        return true;
    }



    public function log($message, $new = false) {
        $this->logger->log('Thumbnail :: '.$message);
    }

    public function get_status() {
        if($this->is_processing())
            return 'processing';
        if($this->is_queued())
            return 'queued';

        return false;
    }


    public function get_process_data(){

        $default_data = [
            'total_products' => 0,
            'queued_products' => 0,
            'processed_products' => 0,
        ];

        $process_data = get_option('awca_product_thumbnails_bg_process_data', $default_data);

        $process_data['process_status'] = $this->get_status();

        return $process_data;
    }

    protected function update_process_data($new_data){

        $process_data = $this->get_process_data();

        // update this key from task()
        if(isset($new_data['processed_products'])){
            $process_data['processed_products'] += $new_data['processed_products'];
        }

        // update this key from push_to_queue()
        if(isset($new_data['queued_products'])){
            $process_data['queued_products'] += $new_data['queued_products'];
        }
        // update this key from push_to_queue()
        if(isset($new_data['total_products'])){
            $process_data['total_products'] = $new_data['total_products'];
        }

        update_option('awca_product_thumbnails_bg_process_data', $process_data);
    }

    protected function reset_process_data(){
        delete_option('awca_product_thumbnails_bg_process_data');
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


    public function get_process_data_ajax(){
        if(!$this->get_status()){
            wp_send_json_error(['message' => 'background process not runs']);
        }else{
            wp_send_json_success($this->get_process_data());
        }
    }

    protected function cancelled()
    {
        $this->reset_process_data();
        $this->log('------------------------ Cancelled ----------------------');
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
     * Complete
     *
     * Called when the background process is complete.
     */
    protected function complete() {
        parent::complete();

        // Calculate and log the total time taken
        $start_time = get_option($this->prefix.'_'.$this->action . '_start_time');
        if ($start_time) {
            $end_time = time();
            $total_time = $end_time - $start_time; // Time in seconds

            $hours = floor($total_time / 3600);
            $minutes = floor(($total_time % 3600) / 60);
            $seconds = $total_time % 60;
            delete_option($this->prefix.'_'.$this->action . '_start_time');

            $this->log("Background process of Thumbnail generation completed. Total time taken: {$hours} hours, {$minutes} minutes, {$seconds} seconds.");
        }


        $this->reset_process_data();
        $this->log('------------------------ completed ----------------------');
        $this->log('Background Complete method : All products have been processed.');
    }


}
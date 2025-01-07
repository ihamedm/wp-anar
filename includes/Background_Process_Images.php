<?php
namespace Anar;
use Anar\Core\Image_Downloader;
use Anar\Lib\BackgroundProcessing\WP_Background_Process;

class Background_Process_Images extends WP_Background_Process {

    /**
     * @var string
     */
    protected $prefix = 'awca';
    protected $action = 'product_gallery_images';
    private static $instance = null;


    private function __construct()
    {
        parent::__construct();
        add_action('wp_ajax_awca_handle_dl_product_gallery_images_process_actions', [$this,'handle_process_actions']);
        add_action('wp_ajax_awca_dl_product_gallery_images_bg_process_data_ajax', [$this,'get_process_data_ajax']);

        add_action('wp_ajax_awca_run_dl_product_gallery_images_bg_process_ajax', [$this, 'process_handler']);
        add_action('wp_ajax_nopriv_awca_run_dl_product_gallery_images_bg_process_ajax', [$this, 'process_handler']);

    }


    public static function get_instance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }


    public function process_handler() {

        $paged = isset($_POST['paged']) ? (int) $_POST['paged'] : 1; // Get the current page from the AJAX request
        $posts_per_page = 10; // Number of products per page
        $finished = false;
        $products = [];
        $save_process_data = [];

        // Setup WP_Query arguments
        $args = array(
            'post_type'      => 'product',
            'post_status'    => array('publish', 'draft'), // Include both published and draft posts
            'meta_query'     => array(
                array(
                    'key'     => '_anar_gallery_images',
                    'value'   => '',
                    'compare' => '!=', // Ensure meta_value is not empty
                ),
            ),
            'posts_per_page' => $posts_per_page, // Limit to posts_per_page posts
            'paged'          => $paged, // Set the current page
        );

        // Perform the query
        $query = new \WP_Query($args);
        $queued_products = count($query->posts);
        awca_log('////////////////////// process_handler - page '.$paged.' => ' . $queued_products);

        /**
         * Save processed data on options to show on front end via ajax
         */
        if($paged == 1) {
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
            while ($query->have_posts()) {
                $query->the_post();
                $products[] = get_the_ID();
            }

            $this->push_to_queue(array(
                'products' => $products,
                'page_number' => $paged,
            ));

            $this->save();
            awca_log("push to queue => {$queued_products} products");
            wp_reset_postdata();


        } else {
            $this->dispatch();
            // not found any products
            awca_log('No more products found with non-empty _anar_gallery_images on page ' . $paged);
            $message = 'همه محصولات به صف دانلود گالری اضافه شدند.';
            $finished = true;

        }

        $process_data = [
            'paged' => $paged,
            'next_paged' => $paged + 1,
            'queued_products' => $queued_products,
            'finished' => $finished,
            'message' => $message ?? ''
        ];


        // Send back progress to JavaScript
        wp_send_json_success($process_data);
    }


    public function push_to_queue( $data ) {
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
            awca_log('Invalid task data, skipping.');
            return false;
        }

        foreach ($item['products'] as $product_id){

            $gallery_image_urls = get_post_meta($product_id, '_anar_gallery_images', true);

            // If gallery images exist, set the product gallery
            if (is_array($gallery_image_urls)) {

                // Only use the first 5 image URLs if there are more than 5
                if (count($gallery_image_urls) > 5) {
                    $gallery_image_urls = array_slice($gallery_image_urls, 0, 5);
                }

                $image_downloader = new Image_Downloader();
                $image_downloader->set_product_gallery($product_id, $gallery_image_urls);
            }
        }



        $this->update_process_data([
            'processed_products' => count($item['products']),
        ]);

        awca_log("Task => Page {$item['page_number']} Processed");

        // Continue processing
        return true;
    }



    public function log_status() {
        awca_log("Product Gallery Images : Processing: {$this->is_processing()}, Queued: {$this->is_queued()}, Active: {$this->is_cancelled()}");
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

        $process_data = get_option('awca_product_gallery_bg_process_data', $default_data);

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

        update_option('awca_product_gallery_bg_process_data', $process_data);
    }

    protected function reset_process_data(){
        delete_option('awca_product_gallery_bg_process_data');
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
        awca_log('------------------------ Cancelled ----------------------');
        parent::cancelled();
    }

    protected function paused()
    {
        awca_log('------------------------ Paused --------------------------');
        parent::paused();
    }

    protected function resumed()
    {
        awca_log('------------------------ Resumed --------------------------');
        parent::resumed();
    }


    /**
     * Complete
     *
     * Called when the background process is complete.
     */
    protected function complete() {
        parent::complete();
        $this->reset_process_data();
        awca_log('------------------------ completed ----------------------');
        awca_log('Background Complete method : All products have been processed.');
    }


}
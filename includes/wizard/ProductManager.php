<?php
namespace Anar\Wizard;

use Anar\Core\Logger;
use Anar\Import;
use Anar\ProductData;
use WC_Product_Simple;
use WC_Product_Variable;
use WC_Product_Variation;

class ProductManager{

    protected static $instance;

    public static $logger;
    
    public static function get_instance() {
        if ( ! isset( self::$instance ) ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function __construct(){
        
        self::$logger = new Logger();
        
        add_action( 'wp_ajax_awca_get_products_save_on_db_ajax', [$this, 'fetch_and_save_products_from_api_to_db_ajax'] );
        add_action( 'wp_ajax_nopriv_awca_get_products_save_on_db_ajax', [$this, 'fetch_and_save_products_from_api_to_db_ajax'] );
        add_action( 'wp_ajax_awca_publish_draft_products_ajax', [$this, 'publish_draft_products_ajax'] );

    }

    public static function log($message, $level = 'info') {
        self::$logger->log($message, 'general', $level);
    }

    public function fetch_and_save_products_from_api_to_db_ajax() {
        $page = isset($_POST['page']) ? intval($_POST['page']) : 1;
        $api_data_handler = new \Anar\ApiDataHandler('products', 'https://api.anar360.com/wp/products');
        $result = $api_data_handler->fetchAndStoreApiResponseByPage($page);

        if ($result !== false) {
            if($page == 1){
                // lock to prevent run again until all product created , after that we unset this lock
                update_option('awca_product_save_lock', true);

                // reset counter
                update_option('awca_proceed_products', 0);
            }
            $total_items = $result['total_items'];
            $total_products = $result['total_products']; // Assuming the API response includes total products count

            // Check if there are more pages to fetch
            $has_more = ($total_items > 0 && $page * 30 < $total_products);

            $response = array(
                'success' => true,
                'has_more' => $has_more,
                'total_added' => $total_items,
                'total_products' => $total_products
            );

            // allow to start create product cronjob
            if(!$has_more){
                Import::unlock_create_products_cron();
            }

            wp_send_json($response);
        } else {
            $response = array(
                'success' => false,
                'message' => 'مشکلی در ارتباط با سرور انار پیش آمده است.  '
            );
            wp_send_json($response);
        }
    }


    public static function create_wc_product($product_data, $attributeMap, $categoryMap) {
        set_time_limit(300);

        try {
            if (!function_exists('wc_get_product')) {
                return false;
            }

            $existing_product_id = ProductData::check_for_existence_product($product_data['sku']);

            $product_id = 0;
            $product_created = true;

            if ($existing_product_id) {
                $product = wc_get_product($existing_product_id);
                self::update_existing_product($product, $product_data, $attributeMap);
                $product_created = false;
            } else {
                $product = self::initialize_new_product($product_data);
                self::setup_new_product($product, $product_data, $attributeMap, $categoryMap);
            }

            $product_id = $product->get_id();
            self::update_common_meta_data($product, $product_data);

            return ['product_id' => $product_id, 'created' => $product_created];

        } catch (\Throwable $th) {
            self::log('Error in awca_create_woocommerce_product: ' . $th->getMessage(), 'error');
            return false;
        }
    }

    private static function update_existing_product($product, $product_data, $attributeMap) {
        $product_type = $product->get_type();

        if ($product_type == 'simple') {
            self::update_simple_product($product, $product_data);
        } elseif ($product_type == 'variable') {
            self::update_variable_product($product, $product_data, $attributeMap);
        }

        if (!empty($product_data['image'])) {
            update_post_meta($product->get_id(), '_product_image_url', $product_data['image']);
        }

        delete_post_meta($product->get_id(), '_anar_pending');

        $product->save();

        self::log('Product Exist: Type[' . $product->get_type() . '], Name: ' . $product->get_name() .
            ' ID: #' . $product->get_id() . ' SKU: ' . $product_data['sku'], 'info');
    }

    public static function update_simple_product($product, $product_data) {
        $product->set_price(awca_convert_price_to_woocommerce_currency($product_data['price']));
        $product->set_regular_price(awca_convert_price_to_woocommerce_currency($product_data['regular_price']));
        $product->set_stock_quantity($product_data['stock_quantity']);
        $product->set_manage_stock(true);
    }

    public static function update_variable_product($product, $product_data, $attributeMap) {
        // Delete variations

        $variations = $product->get_children();
        foreach ($variations as $variation_id) {
            wp_delete_post($variation_id, true);
        }

        // Reset and recreate attributes and variations
        $product->set_attributes(array());
        $product->save();

        self::setup_attributes_and_variations($product, $product_data, $attributeMap);
    }

    public static function initialize_new_product($product_data) {
        return !empty($product_data['attributes'])
            ? new WC_Product_Variable()
            : new WC_Product_Simple();
    }

    public static function setup_new_product($product, $product_data, $attributeMap, $categoryMap) {
        $product->set_name($product_data['name']);
        $product->set_status('draft');
        $product->set_description($product_data['description']);
        $product->set_category_ids(
            \Anar\wizard\Category::map_anar_product_cats_with_saved_cats($product_data['categories'], $categoryMap)
        );

        $product_id = $product->save();

        update_post_meta($product_id, '_anar_sku', $product_data['sku']);
        update_post_meta($product_id, '_anar_sku_backup', $product_data['sku']);

        // _anar_variant_id used on Order
        update_post_meta($product_id, '_anar_variant_id', $product_data['variants'][0]->_id);

        if (isset($product_data['attributes']) && !empty($product_data['attributes'])) {
            self::setup_attributes_and_variations($product, $product_data, $attributeMap);
        } else {
            self::setup_simple_product_data($product, $product_data);
        }
    }

    public static function setup_attributes_and_variations($product, $product_data, $attributeMap) {
        $attrsObject = Attributes::create_attributes($product_data['attributes']);
        $product->set_props(['attributes' => $attrsObject]);
        $product->save();
        self::create_product_variations($product->get_id(), $product_data['variants'], $product_data['attributes'], $attributeMap);
    }

    public static function setup_simple_product_data($product, $product_data) {
        $product->set_price(awca_convert_price_to_woocommerce_currency($product_data['price']));
        $product->set_regular_price(awca_convert_price_to_woocommerce_currency($product_data['regular_price']));
        $product->set_stock_quantity($product_data['stock_quantity']);
        $product->set_manage_stock(true);
        $product->save();
    }

    public static function update_common_meta_data($product, $product_data) {
        $product_id = $product->get_id();
        $import_job_id = get_transient('awca_cron_create_products_job_id');


        update_post_meta($product_id, '_anar_products', 'true');
        update_post_meta($product_id, '_anar_last_sync_time', current_time('mysql'));
        update_post_meta($product_id, '_anar_shipments', $product_data['shipments']);
        update_post_meta($product_id, '_anar_shipments_ref', $product_data['shipments_ref']);

        // mark this product process on last job session (to find deprecated products)
        if($import_job_id)
            update_post_meta($product_id, '_anar_import_job_id', $import_job_id);


        $author_id = awca_get_first_admin_user_id();
        if($author_id){
            wp_update_post(array(
                'ID'          => $product_id,
                'post_author' => $author_id,
            ));
        }

        // Image and gallery
        if (!empty($product_data['image'])) {
            update_post_meta($product_id, '_product_image_url', $product_data['image']);
        }

        if (isset($product_data['gallery_images']) && is_array($product_data['gallery_images'])) {
            update_post_meta($product_id, '_anar_gallery_images', $product_data['gallery_images']);
        }

        $product->save();

    }


    public static function product_serializer($product)
    {
        if (!is_object($product)) {
            return null;
        }

        $prepared_product = array();


        // change array keys
        $prepared_product['image'] = isset($product->mainImage) ? $product->mainImage : '';
        $prepared_product['name'] = isset($product->title) ? $product->title : '';
        $prepared_product['sku'] = isset($product->id) ? $product->id : '';
        $prepared_product['description'] = isset($product->description) ? preg_replace('/<a.*>(.*)<\/a>/isU','$1',$product->description) : '';
        $prepared_product['regular_price'] = isset($product->variants[0]->price) ? $product->variants[0]->price : 0;
        $prepared_product['stock_quantity'] = isset($product->variants[0]->stock) ? $product->variants[0]->stock : 0;
        $prepared_product['categories'] = isset($product->categories) ? $product->categories : '';
        $prepared_product['category'] = isset($product->categories) ? Category::find_best_match_category_from_categories($product->categories) : '';
        $prepared_product['formatted_price'] = number_format($product->variants[0]->priceForResell);
        $prepared_product['shipments'] = isset($product->shipments) ? json_encode($product->shipments, JSON_UNESCAPED_UNICODE) : '';

        if(isset($product->shipmentsReferenceId) && isset($product->shipmentsReferenceState) && isset($product->shipmentsReferenceCity)){

            $prepared_product['shipments_ref'] = array(
                'shipmentsReferenceId' => $product->shipmentsReferenceId,
                'shipmentsReferenceState' => $product->shipmentsReferenceState,
                'shipmentsReferenceCity' => $product->shipmentsReferenceCity,
            );

        }


        $categories = isset($product->categories) ? $product->categories : array();
        $category_names = array();
        foreach ($categories as $category) {
            $category_names[] = isset($category->name) ? $category->name : 'این دسته بندی است';
        }
        $prepared_product['categories'] = $category_names;


        $attributes = isset($product->attributes) ? $product->attributes : array();
        $attribute_data = array();

        foreach ($attributes as $attribute) {
            $attribute_key = isset($attribute->key) ? $attribute->key : '';
            $attribute_name = isset($attribute->name) ? $attribute->name : '';
            $attribute_values = isset($attribute->values) ? $attribute->values : array();

            $attribute_data[$attribute_key] = array(
                'name' => $attribute_name,
                'values' => $attribute_values,
                'key' => $attribute_key
            );
        }

        $prepared_product['attributes'] = $attribute_data;


        $variants = isset($product->variants) ? $product->variants : array();
        $variant_data = array();

        foreach ($variants as $variant) {
            $variant_data[] = $variant;
        }

        $prepared_product['variants'] = $variant_data;

        $gallery_images = array();
        if (isset($product->images) && is_array($product->images)) {
            foreach ($product->images as $image) {
                if (isset($image->_type) && $image->_type === 'image' && isset($image->src)) {
                    $gallery_images[] = $image->src;
                }
            }
        }
        $prepared_product['gallery_images'] = count($gallery_images) > 0 ? $gallery_images : false;

        return $prepared_product;
    }


    public static function create_product_variations($wc_parent_product_id, $variations, $attributes, $attributeMap) {
        // Process variations in batches of 5
        $batch_size = 5;
        $total_variations = count($variations);
        $batches = array_chunk($variations, $batch_size);
        
        self::log("Starting to create {$total_variations} variations for product #{$wc_parent_product_id} in " . count($batches) . " batches", 'debug');
        
        foreach ($batches as $batch_index => $batch) {
            // Start transaction for this batch
            global $wpdb;
            $wpdb->query('START TRANSACTION');
            
            try {
                foreach ($batch as $variation_data) {
                    $variation = new WC_Product_Variation();
                    $variation->set_parent_id($wc_parent_product_id);
                    $variation->set_regular_price(awca_convert_price_to_woocommerce_currency($variation_data->price));
                    $variation->set_stock_quantity($variation_data->stock);
                    $variation->set_manage_stock(true);

                    $variation_attributes = array();
                    if (!empty($attributes)) {
                        $theseAttributesCalculated = [];
                        foreach ($variation_data->attributes as $attr_key => $attr_value) {
                            $attr_name = $attributes[$attr_key]['name'];

                            // if need to map
                            if ($attributeMap != null && $attributeMap != '' ) {
                                if(isset($attributeMap[$attr_name])){
                                    $mapped_attribute = $attributeMap[$attr_name];
                                }elseif(isset($attributeMap[$attr_key])){
                                    $mapped_attribute = $attributeMap[$attr_key];
                                }else{
                                    continue;
                                }

                                if (isset($mapped_attribute['name'])) {
                                    $attr_name = $mapped_attribute['name'];
                                } else {
                                    continue;
                                }

                                if (isset($mapped_attribute['map'])) {
                                    $attr_slug = ($mapped_attribute['map'] == "select") ? $mapped_attribute['key'] : $mapped_attribute['map'];
                                } else {
                                    $attr_slug = $attr_name;
                                }

                                $attr_slug = sanitize_title($attr_slug);
                            } else {
                                $attr_slug = sanitize_title($attr_name);
                            }

                            $theseAttributesCalculated['pa_' . $attr_slug] = sanitize_title($attr_value);
                        }
                    }

                    if(isset($theseAttributesCalculated)) {
                        $variation->set_attributes($theseAttributesCalculated);
                    }

                    $variation->save();

                    $variation_id = $variation->get_id();

                    if ($variation_id) {
                        update_post_meta($variation_id, '_anar_sku', $variation_data->_id);
                        update_post_meta($variation_id, '_anar_variant_id', $variation_data->_id);
                    } else {
                        self::log("Failed to save variation for product ID: " . $wc_parent_product_id, 'error');
                    }
                    
                    // Clear object from memory
                    unset($variation);
                }
                
                // Commit this batch
                $wpdb->query('COMMIT');
                
                // Log progress
                $processed = min(($batch_index + 1) * $batch_size, $total_variations);
                self::log("Created batch {$batch_index} of variations for product #{$wc_parent_product_id} ({$processed}/{$total_variations})", 'debug');
                
                // Force garbage collection after each batch
                if (function_exists('gc_collect_cycles')) {
                    gc_collect_cycles();
                }
                
            } catch (\Exception $e) {
                // Rollback this batch on error
                $wpdb->query('ROLLBACK');
                self::log("Error creating variation batch {$batch_index} for product #{$wc_parent_product_id}: " . $e->getMessage(), 'error');
                // Continue with next batch
                continue;
            }
        }
        
        self::log("Completed creating {$total_variations} variations for product #{$wc_parent_product_id}", 'debug');
    }


    public static function handle_removed_products_from_anar($process, $job_id) {

        if(!$job_id) {
            return;
        }

        $key = ($process == 'import') ? '_anar_import_job_id' : '_anar_sync_job_id';
        $log_file = ($process == 'import') ? 'general' : 'fullSync';

        if(awca_is_import_products_running()){
            self::$logger->log("detect importing in progress, so skipp deprecating until next job.", $log_file);
            return;
        }

        self::$logger->log("detect removed products from anar. jobID: $job_id, key $key", $log_file);

        $args = [
            'posts_per_page' => -1,
            'post_type'      => 'product',
            'post_status'    => ['publish', 'draft'],
            'fields'         => 'ids',
            'meta_query' => array(
                'relation' => 'AND',
                array(
                    'key' => '_anar_products',
                    'compare' => 'EXISTS'
                ),
                array(
                    'relation' => 'OR',
                    array(
                        'key' => $key,
                        'compare' => 'NOT EXISTS'
                    ),
                    array(
                        'key' => $key,
                        'value' => $job_id,
                        'compare' => '!=',
                    )
                )
            )
        ];

        $products = get_posts($args);

        $product_count = count($products);
        self::$logger->log("found $product_count products that removed from Anar. " , $log_file);

        if (!empty($products)) {
            // Store the count of removed products
            update_option('awca_deprecated_products_count', count($products));
            update_option('awca_deprecated_products_time', current_time('mysql'));


            foreach ($products as $product) {
                self::set_product_as_deprecated($product, $job_id, $log_file);
            }
        }
    }

    /**
     * Deprecates a WooCommerce product from Anar integration
     * Handles both simple and variable products
     *
     * @param int $product_id The product ID to deprecate
     * @param string $job_id The job ID to mark as deprecated
     * @return bool True if deprecation was successful, false otherwise
     */
    public static function set_product_as_deprecated($product_id, $job_id, $log_file, $deprecate = false) {
        try {
            $wc_product = wc_get_product($product_id);

            if (!$wc_product) {
                self::$logger->log("Error: Product #{$product_id} not found", $log_file, 'error');
                return false;
            }


            self::$logger->log("Deprecating product #{$product_id} from Anar.", $log_file, 'debug');

            // Update product meta
            if($deprecate){
                update_post_meta($product_id, '_anar_deprecated', $job_id);

                // make a backup from sku then delete
                self::backup_anar_meta_data($product_id);

                delete_post_meta($product_id, '_anar_sku');
                delete_post_meta($product_id, '_anar_products');
            }else{
                update_post_meta($product_id, '_anar_pending', $job_id);
            }


            if ($wc_product->is_type('variable')) {
                // Handle variable product variations
                $variations = $wc_product->get_children();

                foreach ($variations as $variation_id) {
                    self::set_product_variation_out_of_stock($variation_id, $deprecate);
                }
            }

            // Update parent product (works for both simple and variable)
            $wc_product->set_stock_quantity(0);
            $wc_product->set_stock_status('outofstock');
//            $wc_product->set_status('draft');
            $wc_product->save();

            return true;

        } catch (\Exception $e) {
            self::$logger->log("Error deprecating product #{$product_id}: " . $e->getMessage(), $log_file);
            return false;
        }
    }


    public static function restore_product_deprecation($product_id, $log = '') {
        $anar_sku_backup = get_post_meta($product_id, '_anar_sku_backup', true);
        $anar_products_backup = get_post_meta($product_id, '_anar_products_backup', true);
        $wc_product = wc_get_product($product_id);

        if($anar_sku_backup && $anar_products_backup){
            update_post_meta($product_id, '_anar_sku', $anar_sku_backup);
            update_post_meta($product_id, '_anar_products', $anar_products_backup);
            delete_post_meta($product_id, '_anar_deprecated');
        }


        if($wc_product->is_type('variable')) {
            $variations = $wc_product->get_children();

            foreach ($variations as $variation_id) {
                $v_anar_sku_backup = get_post_meta($variation_id, '_anar_sku_backup', true);
                $v_anar_variant_id = get_post_meta($variation_id, '_anar_variant_id', true);

                // @todo : remove in future, backward compatibility to fix a bug that we set _anar_products for variation product
                delete_post_meta($variation_id, '_anar_products');
                if($v_anar_sku_backup){
                    update_post_meta($variation_id, '_anar_sku', $v_anar_sku_backup);
                }elseif($v_anar_variant_id){
                    update_post_meta($variation_id, '_anar_sku', $v_anar_variant_id);
                }
            }
        }
    }


    public static function update_product_logs($product_id, $new_log) {
        $logs = get_post_meta($product_id, '_anar_logs', true);
        update_post_meta($product_id, '_anar_logs', $logs .'<hr>'. $new_log);
    }


    public static function set_product_variation_out_of_stock($wc_variation_id, $deprecate = false) {
        $variation = wc_get_product($wc_variation_id);
        if ($variation instanceof WC_Product_Variation) {
            try {
                $variation->set_stock_quantity(0);
                $variation->set_stock_status('outofstock');
                $variation->save();

                if ($deprecate) {
                    self::backup_anar_meta_data($wc_variation_id);
                    delete_post_meta($wc_variation_id, '_anar_sku');
                }
            } catch (\Exception $e) {
                awca_log($wc_variation_id, 'Error setting out of stock: ' . $e->getMessage(), 'debug');
            }
        } else {
            awca_log($wc_variation_id, 'Invalid variation or not found.', 'debug');
        }
    }


    public static function backup_anar_meta_data($product_id){
        $anar_sku = get_post_meta($product_id, '_anar_sku', true);
        if($anar_sku){
            update_post_meta($product_id, '_anar_sku_backup', $anar_sku);
        }
        $anar_products = get_post_meta($product_id, '_anar_products', true);
        if($anar_products){
            update_post_meta($product_id, '_anar_products_backup', $anar_products);
        }
    }

    public function publish_draft_products_ajax() {
        // Verify nonce
        if (!isset($_POST['security_nonce']) || !wp_verify_nonce($_POST['security_nonce'], 'publish_anar_products_ajax_nonce')) {
            wp_send_json_error(array(
                'message' => 'فرم نامعتبر است.'
            ));
        }

        // Check user capabilities
        if (!current_user_can('manage_woocommerce')) {
            wp_send_json_error(array(
                'message' => 'شما مجوز این کار را ندارید!'
            ));
        }

        $page = isset($_POST['page']) ? intval($_POST['page']) : 1;
        $limit = isset($_POST['limit']) ? intval($_POST['limit']) : 100;
        $has_more = true;
        $total_published = 0;
        $found_products = 0;
        $this_loop_products = 0;

        // Query arguments to get draft products
        $query_args = array(
            'post_type'      => 'product',
            'post_status'    => 'draft',
            'fields'         => 'ids',
            'posts_per_page' => $limit,
            'meta_query'     => array(
                array(
                    'key'     => '_anar_products',
                    'compare' => 'EXISTS'
                )
            )
        );

        // Add stock status check if needed
        if (isset($_POST['skipp_out_of_stocks']) && $_POST['skipp_out_of_stocks'] === 'true') {
            $query_args['meta_query'][] = array(
                'key'     => '_stock_status',
                'value'   => 'instock',
                'compare' => '='
            );
        }



        try {
            $products_query = new \WP_Query($query_args);

            $found_products = $products_query->found_posts;
            $this_loop_products = count($products_query->posts);
            $products_to_clear = [];

            if ($products_query->have_posts()) :
                while ($products_query->have_posts()) :
                    $products_query->the_post();
                    $product_id = get_the_ID();

                    // Get the product object
                    $product = wc_get_product($product_id);

                    if (!$product) {
                        continue;
                    }

                    $product->set_status('publish');

                    if ($product->save()) {
                        $products_to_clear[] = $product_id;

                        // Trigger action for other plugins
                        do_action('woocommerce_product_published', $product_id, $product);
                    }

                    $total_published++;
                    // Clean up product object to free memory
                    unset($product);
                endwhile;
            else:
                $has_more = false;
                return;
            endif;

            // Clear batch cache
            $this->clear_batch_cache($products_to_clear);

            // Reset post data
            wp_reset_postdata();

            // Clear some memory
            if (function_exists('gc_collect_cycles')) {
                gc_collect_cycles();
            }

            // Final cache clear
            wc_delete_product_transients();

        }catch (\Exception $exception){
            $response = [
                'success' => false,
                'has_more' => false,
                'total_published' => $total_published,
                'loop_products' => $this_loop_products,
                'found_products' => $found_products,
                'message' => $exception->getMessage()
            ];
            wp_send_json($response);

        } finally {

            self::log(sprintf('Completed page %d. Found Products %s, total Published %d , Loop Products %s, Has More? %s, $_POST: %s',
                $page,
                $found_products,
                $total_published,
                $this_loop_products,
                print_r($has_more, true),
                print_r($_POST, true)
            ), 'debug');

            $response = [
                'success' => true,
                'has_more' => $has_more,
                'total_published' => $total_published,
                'loop_products' => $this_loop_products,
                'found_products' => $found_products,
            ];
            wp_send_json($response);
        }

    }


    /**
     * Clear cache for a batch of products
     *
     * @param array $product_ids Array of product IDs to clear cache for
     */
    private function clear_batch_cache($product_ids)
    {
        if (empty($product_ids)) {
            return;
        }

        foreach ($product_ids as $product_id) {
            wp_cache_delete($product_id, 'post_meta');
            wp_cache_delete('product-' . $product_id, 'products');
        }

        // Clear some common WooCommerce transients that might be affected
        delete_transient('wc_products_onsale');
        delete_transient('wc_featured_products');
    }

}
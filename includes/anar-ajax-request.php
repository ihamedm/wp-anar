<?php

add_action('wp_ajax_awca_abort_job', 'awca_abort_job');
function awca_abort_job() {
    $job_id = $_POST['job_id'];

    set_transient('abort_job_' . $job_id, 'aborted', 100 * MINUTE_IN_SECONDS);

    wp_send_json_success();
}

add_action('wp_ajax_awca_handle_token_activation_ajax', 'awca_handle_token_activation_ajax');
add_action('wp_ajax_nopriv_awca_handle_token_activation_ajax', 'awca_handle_token_activation_ajax');

function awca_handle_token_activation_ajax()
{
    if (!isset($_POST['awca_handle_token_activation_ajax_field']) || !wp_verify_nonce($_POST['awca_handle_token_activation_ajax_field'], 'awca_handle_token_activation_ajax_nonce')) {
        $response = array(
            'success' => false,
            'message' => 'اعتبار اطلاعات فرم به پایان رسیده است',
        );
        wp_send_json($response);
    }
    $activation = awca_save_activation_key();
    if ($activation) {
        $activation_status = awca_check_activation_state();
        if ($activation_status) {
            $response = array(
                'success' => true,
                'message' => 'توکن شما با موفقیت ثبت و تایید شد',
            );
        } else {
            $response = array(
                'success' => false,
                'message' => 'توکن شما از سمت انار تایید نشد',
            );
        }
    } else {
        $activation_status = awca_check_activation_state();
        if ($activation_status) {
            $response = array(
                'success' => true,
                'message' => 'توکن شما معتبر و سمت انار مورد تایید است ',
            );
        } else {
            $response = array(
                'success' => false,
                'message' => 'توکن شما از سمت انار تایید نشد',
            );
        }
    }

    wp_send_json($response);
}

add_action('wp_ajax_awca_sync_products_price_and_stocks', 'awca_sync_products_price_and_stocks');
add_action('wp_ajax_nopriv_awca_sync_products_price_and_stocks', 'awca_sync_products_price_and_stocks');

function awca_sync_products_price_and_stocks()
{
    awca_sync_all_products();

    $response = array(
        'success' => true,
        'message' => 'همگام سازی با موفقیت انجام شد'
    );
    wp_send_json($response);
}

add_action('wp_ajax_awca_handle_pair_categories_ajax', 'awca_handle_pair_categories_ajax');
add_action('wp_ajax_nopriv_awca_handle_pair_categories_ajax', 'awca_handle_pair_categories_ajax');

function awca_handle_pair_categories_ajax()
{
    $anarCats = $_POST['anar-cats'];
    $wooCats = $_POST['product_categories'];

    set_transient('_anar_api_categories_transient', $anarCats, WEEK_IN_SECONDS);
    set_transient('_anar_woocomerce_categories_transient', $wooCats, WEEK_IN_SECONDS);

    $categoryMap = [];
    foreach($anarCats as $key => $anarCat) {
        $categoryMap[$anarCat] = $wooCats[$key];
    }

    update_option('categoryMap', $categoryMap);

    if ($anarCats !== false && $wooCats !== false) {
        $combinedCategories = awca_combine_cats_arrays($anarCats, $wooCats);
        update_option('combinedCategories', $combinedCategories);
    } else {
        $response = array(
            'success' => false,
            'message' => 'اطلاعات به درستی ارسال نشده است',
        );
        wp_send_json_error($response);
        return;
    }

    foreach($categoryMap as $key => $val) {
        if ($val == 'select') {
            awca_create_woocommerce_category($key);
        }
    }

    $response = array(
        'success' => true,
//        'anar' =>  get_transient('_anar_api_categories_transient'),
//        'woo' => get_transient('_anar_woocomerce_categories_transient'),
        'message' => 'معادل سازی دسته بندی ها با موفقیت ذخیره شد'
    );
    awca_log('categories saved successfully');
    wp_send_json($response);
}



add_action('wp_ajax_awca_handle_pair_attributes_ajax', 'awca_handle_pair_attributes_ajax');
add_action('wp_ajax_nopriv_awca_handle_pair_attributes_ajax', 'awca_handle_pair_attributes_ajax');

function awca_handle_pair_attributes_ajax()
{
    $attributeMap = isset($_POST['product_attributes']) ? $_POST['product_attributes'] : '';
    $attributeMapFirstTime = isset($_POST['anar-atts']) ? $_POST['anar-atts'] : '';

    if ($attributeMap == null || $attributeMap == '') {
        update_option('attributeMap', $attributeMapFirstTime);
    } else {
        update_option('attributeMap', $attributeMap);
    }

    $response = array(
        'success' => true,
        'message' => 'معادل سازی ویژگی ها ها با موفقیت ذخیره شد'
    );
    awca_log('attributes saved successfully');
    wp_send_json($response);
}


add_action('wp_ajax_awca_handle_product_creation_ajax', 'awca_handle_product_creation_ajax');
add_action('wp_ajax_nopriv_awca_handle_product_creation_ajax', 'awca_handle_product_creation_ajax');

function awca_handle_product_creation_ajax($job_id = null) {

    /**
     * Manage the job process
     */
    $job_id = $job_id ?? $_GET['job_id'];
    $is_job_aborted = awca_is_job_aborted($job_id);
    set_transient('awca_sync_all_products_lock', true, 3600); // Lock for 1 hour
    awca_log('--------------- AJAX[Start]: handle_product_creation_ajax job ID: '.$job_id.' -----------------');


    /**
     * Get Categories & Attributes needed for product creation
     */
    $attributeMap = get_option('attributeMap');
    $combinedCategories = get_option('combinedCategories');
    $categoryMap = get_option('categoryMap');


    /**
     * Global variables
     */
    $responses = [];
    $created_product_ids = [];
    $exist_product_ids = [];
    $serialized_products = [];
    $limit = 30; // Number of products per page
    $page = 1; // Starting page
    $has_more_pages = true;


    /**
     * Loop throw products and create them
     */
    while ($has_more_pages) {
        // Fetch products from API with retry mechanism
//        $paged_url = add_query_arg(array('page' => $page, 'limit' => $limit), $api_url);
//        $awca_products = awca_get_data_from_api($paged_url);
        $awca_products = awca_get_stored_response_paged('products', $page);

        if ($awca_products === false) {
            awca_log("Failed to fetch products data from API after multiple retries. Job ID: $job_id, Page: $page");
            $response = array(
                'success' => false,
                'message' => 'Failed to fetch products data from API after multiple retries.',
            );
            wp_send_json_error($response);
            return;
        }

        $total_products = $awca_products->total;

        // Check for last page using total products count
        if ($page * $limit >= $awca_products->total) {
            $has_more_pages = false;
        } else {
            $has_more_pages = true;
        }

        // Log the page number and product count for each page
        awca_log("Processing Page: $page, Products Retrieved: " . count($awca_products->items));

        // Loop through products and create an array of modified data of products
        foreach ($awca_products->items as $index => $item) {
            $prepared_product = awca_product_list_serializer($item);
            $serialized_products[] = $prepared_product;

            $product_attributes = [];
            $prepared_product['attributes'] = $product_attributes;
        }

        // Pass data to helper create wc product function
        foreach ($serialized_products as $index => $product_item) {
            $product_creation_data = array(
                'name' => $product_item['name'],
                'regular_price' => $product_item['regular_price'],
                'description' => $product_item['description'],
                'image' => $product_item['image'],
                'categories' => $product_item['categories'],
                'category' => $product_item['category'],
                'stock_quantity' => $product_item['stock_quantity'],
                'gallery_images' => $product_item['gallery_images'],
                'attributes' => $product_item['attributes'],
                'variants' => $product_item['variants'],
                'sku' => $product_item['sku'],
                'shipments' => $product_item['shipments'],
                'shipments_ref' => $product_item['shipments_ref'],
            );

            $product_creation_result = awca_create_woocommerce_product($product_creation_data, $combinedCategories, $attributeMap, $categoryMap);

            $product_id = $product_creation_result['product_id'];
            if ($product_creation_result['created']) {
                $created_product_ids[] = $product_id;
            } else {
                $exist_product_ids[] = $product_id;
            }


            if ($product_id) {
                $responses[] = array(
                    'success' => true,
                    'message' => "Product with ID $product_id created",
                    'data' => $product_creation_data,
                );
            } else {
                $responses[] = array(
                    'success' => false,
                    'message' => "A product was not created",
                    'data' => $product_creation_data,
                );
            }


            // Save the progress in a transient
            $creation_progress_data = ['created' => count($created_product_ids), 'exist' => count($exist_product_ids)];

            awca_log('Product create progress - Created: ' . $creation_progress_data['created'] . ', Exist: ' . $creation_progress_data['exist']);
            $progress_message = 'انار - افزودن محصول ' . count($created_product_ids) .'/' .$total_products;
            set_transient('awca_product_creation_progress', $progress_message, 3 * MINUTE_IN_SECONDS);
        }

        // Increment the page counter for the next loop
        $page++;
    }

    $response = array(
        'success' => true,
        'woo_url' => admin_url('edit.php?post_type=product'),
        'responses' => $responses,
    );

    awca_log('--------------- AJAX[End]: handle_product_creation_ajax -----------------');

    // All products successfully added so sync can start from now
    delete_transient('awca_sync_all_products_lock');

    awca_dl_all_product_images_ajax();
    wp_send_json($response);
}



add_action( 'wp_ajax_awca_fetch_products_paginate_ajax', 'awca_fetch_products_paginate_ajax' );
add_action( 'wp_ajax_nopriv_awca_fetch_products_paginate_ajax', 'awca_fetch_products_paginate_ajax' );
function awca_fetch_products_paginate_ajax() {

    // Start time
    $start_time = microtime(true);

    if ( !isset( $_GET['page'] ) || !is_numeric( $_GET['page'] ) ) {
        wp_send_json_error( array( 'message' => 'Invalid page number', 'code' => 'invalid_page_number' ) );
    }

    $page = intval( $_GET['page'] );
    $api_url = "https://api.anar360.com/api/360/products?page=$page&limit=10";
    awca_log("Fetching products from page: $page");
    awca_log($api_url);

    // Fetch the products from the API
    $token = awca_get_activation_key();
    $response = wp_remote_get(
        $api_url,
        array(
            'headers' => array(
                'Authorization' => 'Bearer ' . $token,
            ),
            'timeout' => 30, // Increase timeout if necessary
        )
    );

    if ( is_wp_error( $response ) ) {
        $error_message = $response->get_error_message();
        awca_log("API request failed: $error_message");
        wp_send_json_error( array( 'message' => 'API request failed', 'code' => 'api_request_failed', 'details' => $error_message ) );
    }

    $http_code = wp_remote_retrieve_response_code( $response );
    if ( $http_code != 200 ) {
        awca_log("API request returned HTTP status code: $http_code");
        wp_send_json_error( array( 'message' => 'Unexpected HTTP status code', 'code' => 'http_status_error', 'status_code' => $http_code ) );
    }

    $body = wp_remote_retrieve_body( $response );
    $products = json_decode( $body );

    // End time
    $end_time = microtime(true);

    // Calculate and log the time taken
    $time_taken = $end_time - $start_time;
    awca_log("Time taken to fetch products from API : " . $time_taken . " seconds");

    if ( json_last_error() !== JSON_ERROR_NONE ) {
        $json_error = json_last_error_msg();
        awca_log("JSON decoding failed: $json_error");
        wp_send_json_error( array( 'message' => 'JSON decoding failed', 'code' => 'json_decoding_failed', 'details' => $json_error ) );
    }

    if ( empty( $products ) ) {
        wp_send_json_error( array( 'message' => 'No products found', 'code' => 'no_products_found' ) );
    }

    wp_send_json_success( $products );
}


add_action( 'wp_ajax_awca_get_categories_save_on_db_ajax', 'awca_get_categories_save_on_db_ajax' );
add_action( 'wp_ajax_nopriv_awca_get_categories_save_on_db_ajax', 'awca_get_categories_save_on_db_ajax' );
function awca_get_categories_save_on_db_ajax() {
    $products_api = 'https://api.anar360.com/api/360/categories';
    $result = awca_fetch_and_store_api_response('categories', $products_api);

    if ($result) {
        wp_send_json_success('Categories fetched and stored successfully.');
    } else {
        wp_send_json_error('Failed to fetch and store categories.');
    }
}


add_action( 'wp_ajax_awca_get_attributes_save_on_db_ajax', 'awca_get_attributes_save_on_db_ajax' );
add_action( 'wp_ajax_nopriv_awca_get_attributes_save_on_db_ajax', 'awca_get_attributes_save_on_db_ajax' );
function awca_get_attributes_save_on_db_ajax()
{
    $products_api = 'https://api.anar360.com/api/360/attributes';
    $result = awca_fetch_and_store_api_response('attributes', $products_api);

    if ($result) {
        wp_send_json_success('attributes fetched and stored successfully.');
    } else {
        wp_send_json_error('Failed to fetch and store categories.');
    }

}

add_action( 'wp_ajax_awca_get_products_save_on_db_ajax', 'awca_get_products_save_on_db_ajax' );
add_action( 'wp_ajax_nopriv_awca_get_products_save_on_db_ajax', 'awca_get_products_save_on_db_ajax' );
function awca_get_products_save_on_db_ajax() {
    $page = isset($_POST['page']) ? intval($_POST['page']) : 1;
    $products_api = "https://api.anar360.com/api/360/products";
    $total_items_per_page = 0 ;

    $result = awca_fetch_and_store_api_response_by_page('products', $products_api, $page);
    if ($result !== false) {
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
        wp_send_json($response);
    } else {
        $response = array(
            'success' => false,
            'message' => 'مشکلی در ارتباط با سرور انار پیش آمده است.  '
        );
        wp_send_json($response);
    }
}



add_action('wp_ajax_awca_get_product_creation_progress', 'awca_get_product_creation_progress');
add_action('wp_ajax_nopriv_awca_get_product_creation_progress', 'awca_get_product_creation_progress');
function awca_get_product_creation_progress() {
    $progress = get_transient('awca_product_creation_progress');
    if ($progress === false) {
        $progress = '...';
    }

    awca_log('progress' . $progress);

    $response = array(
        'success' => true,
        'state_message' => $progress,
    );

    wp_send_json($response);
}


add_action('wp_ajax_awca_dl_the_product_images_ajax', 'awca_dl_the_product_images_ajax');
add_action('wp_ajax_nopriv_awca_dl_the_product_images_ajax', 'awca_dl_the_product_images_ajax');
function awca_dl_the_product_images_ajax(){

    if ( !isset( $_POST['product_id'] ) ) {
        wp_send_json_error( array( 'message' => 'product_id required') );
    }

    $gallery_image_limit = $_POST['gallery_image_limit'] ?? 5;

    $product_id = intval( $_POST['product_id'] );

    $image_url = get_post_meta($product_id, '_product_image_url', true);
    if (!empty($image_url)) {
        $res = awca_set_product_image_from_url($product_id, $image_url);

        if(is_wp_error($res)){
            wp_send_json_error( array( 'message' => $res->get_error_message() ) );
        }

    }

    $gallery_image_urls = get_post_meta($product_id, '_anar_gallery_images', true);
    if (!empty($gallery_image_urls)) {
        $res_gallery = awcs_set_product_gallery_from_array_urls($product_id, $gallery_image_urls, $gallery_image_limit);

        if(is_wp_error($res_gallery)){
            wp_send_json_error( array( 'message' => $res_gallery->get_error_message() ) );
        }
    }

    wp_send_json_success(array('message' => 'تصاویر با موفقیت دانلود و به محصول افزوده شد.'));
}


add_action('wp_ajax_awca_dl_all_product_images_ajax', 'awca_dl_all_product_images_ajax');
add_action('wp_ajax_nopriv_awca_dl_all_product_images_ajax', 'awca_dl_all_product_images_ajax');
function awca_dl_all_product_images_ajax(){

    // Set a transient to lock the function
    set_transient('awca_dl_all_product_images_lock', true, 3600); // Lock for 1 hour


    \Anar\AWCA_Woocommerce::dl_and_set_product_image_job();

    delete_transient('awca_dl_all_product_images_lock');

}


add_action('wp_ajax_awca_dl_all_product_gallery_images_ajax', 'awca_dl_all_product_gallery_images_ajax');
add_action('wp_ajax_nopriv_awca_dl_all_product_gallery_images_ajax', 'awca_dl_all_product_gallery_images_ajax');
function awca_dl_all_product_gallery_images_ajax(){

    // Set a transient to lock the function
    set_transient('awca_dl_all_product_gallery_images_lock', true, 3600); // Lock for 1 hour


    \Anar\AWCA_Woocommerce::dl_and_set_product_gallery_job();

    delete_transient('awca_dl_all_product_gallery_images_lock');

    wp_send_json_success(['message' => 'همه تصاویر گالری با موفقیت دانلود شدند.']);
}
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

    $response = array(
        'success' => true,
        'anar' =>  get_transient('_anar_api_categories_transient'),
        'woo' => get_transient('_anar_woocomerce_categories_transient'),
        'message' => 'معادل سازی دسته بندی ها با موفقیت ذخیره شد'
    );
    wp_send_json($response);
}


add_action('wp_ajax_awca_handle_product_creation_ajax', 'awca_handle_product_creation_ajax');
add_action('wp_ajax_nopriv_awca_handle_product_creation_ajax', 'awca_handle_product_creation_ajax');
function awca_handle_product_creation_ajax($job_id) {
    $anarCats = get_transient('_anar_api_categories_transient');
    $wooCats = get_transient('_anar_woocomerce_categories_transient');
    $attributeMap = isset($_POST['product_attributes']) ? $_POST['product_attributes'] : '';
    $attributeMapFirstTime = isset($_POST['anar-atts']) ? $_POST['anar-atts'] : '';

    $job_id = $job_id ?? $_GET['job_id'];

    // Set sync transient to prevent sync start before all products added
    set_transient('awca_sync_all_products_lock', true, 3600); // Lock for 1 hour

    awca_log('--------------- AJAX[Start]: handle_product_creation_ajax job ID: '.$job_id.' -----------------');

    $catDic = [];
    foreach($anarCats as $key => $anarCat) {
        $catDic[$anarCat] = $wooCats[$key];
    }

    $categoryMap = $catDic;
    update_option('categoryMap', $categoryMap);

    foreach($categoryMap as $key => $val) {
        if ($val == 'select') {
            awca_create_woocommerce_category($key);
        }
    }

    if ($attributeMap == null || $attributeMap == '') {
        update_option('attributeMap', $attributeMapFirstTime);
    } else {
        update_option('attributeMap', $attributeMap);
    }

    if ($anarCats !== false && $wooCats !== false) {
        $combinedCategories = awca_combine_cats_arrays($anarCats, $wooCats);
    } else {
        $response = array(
            'success' => false,
            'message' => 'ابتدا نیاز هست دسته بندی ها را در تب قبلی معادل سازی کنید',
        );
        wp_send_json_error($response);
    }

    $responses = [];
    $created_product_ids = [];
    $exist_product_ids = [];
    $formatted_product_data = [];
    $prepared_products = [];
    $product_creation = [];

    $api_url = 'https://api.anar360.com/api/360/products';
    $limit = 10; // Number of products per page
    $page = 1; // Starting page

    $has_more_pages = true;
    $is_job_aborted = awca_is_job_aborted($job_id);

    while ($has_more_pages) {

        // get page (x) of products
        $paged_url = add_query_arg(array('page' => $page, 'limit' => $limit), $api_url);
        $awca_products = awca_get_data_from_api($paged_url);


        // if get products is ok
        if ($awca_products && !is_wp_error($awca_products)) {

            // Check for last page using total products count
            if ($page * $limit >= $awca_products->total) {
                $has_more_pages = false;
            } else {
                $has_more_pages = true;
            }

            // loop through products and create an array of modified data of products
            foreach ($awca_products->items as $index => $item) {
                $prepared_product = awca_prepare_results_for_product($item);
                $prepared_products[] = $prepared_product;

                $product_attributes = [];
                $prepared_product['attributes'] = $product_attributes;

                $formatted_product_data[] = $prepared_product;
            }

            // pass data to helper create wc product function
            foreach ($prepared_products as $index => $product_item) {
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
                    'sku' => $product_item['sku']
                );

                $product_creation_result = awca_create_woocommerce_product($product_creation_data, $combinedCategories, $attributeMap, $categoryMap);

                $product_id = $product_creation_result['product_id'];
                if ($product_creation_result['created']){
                    $created_product_ids[] = $product_id;
                }else{
                    $exist_product_ids[] = $product_id;
                }


                $product_creation[] = [
                    'product_item' => $product_item,
                    'formatted_product_data' => $product_creation_data,
                    'product_id' => $product_id
                ];

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
                //awca_log('product create progress - created : ' . $creation_progress_data['created']. '  exist : ' .$creation_progress_data['exist']);

                $progress_message = sprintf('ساخت محصولات - %s محصول جدید ساخته شد', count($created_product_ids));
                set_transient('awca_product_creation_progress', $progress_message, 3 * MINUTE_IN_SECONDS);
            }

            // Increment the page counter for the next loop
            $page++;

        } else {
            $response = array(
                'success' => false,
                'message' => 'Failed to fetch products data from API: ' . ($awca_products ? $awca_products->get_error_message() : 'Unknown error'),
            );
            wp_send_json_error($response);
            return;
        }
    }


    $response = array(
        'success' => true,
        'woo_url' => admin_url('edit.php?post_type=product'),
        'responses' => $responses,
    );

    awca_log('--------------- AJAX[End]: handle_product_creation_ajax -----------------');

    // all products successfully add so sync can start from now
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
function awca_get_attributes_save_on_db_ajax() {
    $products_api = 'https://api.anar360.com/api/360/attributes';
    $result = awca_fetch_and_store_api_response('attributes', $products_api);

    if ($result) {
        wp_send_json_success('Categories fetched and stored successfully.');
    } else {
        wp_send_json_error('Failed to fetch and store categories.');
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
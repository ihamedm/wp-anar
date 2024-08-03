<?php

function awca_slugify($str, $options = array()) {
    $str = mb_convert_encoding((string)$str, 'UTF-8', mb_list_encodings());

    $defaults = array(
        'delimiter' => '-',
        'limit' => null,
        'lowercase' => true,
        'replacements' => array(),
        'transliterate' => false,
    );

    // Merge options
    $options = array_merge($defaults, $options);

    $char_map = array(
        // Latin
        'À' => 'A', 'Á' => 'A', 'Â' => 'A', 'Ã' => 'A', 'Ä' => 'A', 'Å' => 'A', 'Æ' => 'AE', 'Ç' => 'C',
        'È' => 'E', 'É' => 'E', 'Ê' => 'E', 'Ë' => 'E', 'Ì' => 'I', 'Í' => 'I', 'Î' => 'I', 'Ï' => 'I',
        'Ð' => 'D', 'Ñ' => 'N', 'Ò' => 'O', 'Ó' => 'O', 'Ô' => 'O', 'Õ' => 'O', 'Ö' => 'O', 'Ő' => 'O',
        'Ø' => 'O', 'Ù' => 'U', 'Ú' => 'U', 'Û' => 'U', 'Ü' => 'U', 'Ű' => 'U', 'Ý' => 'Y', 'Þ' => 'TH',
        'ß' => 'ss',
        'à' => 'a', 'á' => 'a', 'â' => 'a', 'ã' => 'a', 'ä' => 'a', 'å' => 'a', 'æ' => 'ae', 'ç' => 'c',
        'è' => 'e', 'é' => 'e', 'ê' => 'e', 'ë' => 'e', 'ì' => 'i', 'í' => 'i', 'î' => 'i', 'ï' => 'i',
        'ð' => 'd', 'ñ' => 'n', 'ò' => 'o', 'ó' => 'o', 'ô' => 'o', 'õ' => 'o', 'ö' => 'o', 'ő' => 'o',
        'ø' => 'o', 'ù' => 'u', 'ú' => 'u', 'û' => 'u', 'ü' => 'u', 'ű' => 'u', 'ý' => 'y', 'þ' => 'th',
        'ÿ' => 'y',

        // Latin symbols
        '©' => '(c)',

        // Greek
        'Α' => 'A', 'Β' => 'B', 'Γ' => 'G', 'Δ' => 'D', 'Ε' => 'E', 'Ζ' => 'Z', 'Η' => 'H', 'Θ' => '8',
        'Ι' => 'I', 'Κ' => 'K', 'Λ' => 'L', 'Μ' => 'M', 'Ν' => 'N', 'Ξ' => '3', 'Ο' => 'O', 'Π' => 'P',
        'Ρ' => 'R', 'Σ' => 'S', 'Τ' => 'T', 'Υ' => 'Y', 'Φ' => 'F', 'Χ' => 'X', 'Ψ' => 'PS', 'Ω' => 'W',
        'Ά' => 'A', 'Έ' => 'E', 'Ί' => 'I', 'Ό' => 'O', 'Ύ' => 'Y', 'Ή' => 'H', 'Ώ' => 'W', 'Ϊ' => 'I',
        'Ϋ' => 'Y',
        'α' => 'a', 'β' => 'b', 'γ' => 'g', 'δ' => 'd', 'ε' => 'e', 'ζ' => 'z', 'η' => 'h', 'θ' => '8',
        'ι' => 'i', 'κ' => 'k', 'λ' => 'l', 'μ' => 'm', 'ν' => 'n', 'ξ' => '3', 'ο' => 'o', 'π' => 'p',
        'ρ' => 'r', 'σ' => 's', 'τ' => 't', 'υ' => 'y', 'φ' => 'f', 'χ' => 'x', 'ψ' => 'ps', 'ω' => 'w',
        'ά' => 'a', 'έ' => 'e', 'ί' => 'i', 'ό' => 'o', 'ύ' => 'y', 'ή' => 'h', 'ώ' => 'w', 'ς' => 's',
        'ϊ' => 'i', 'ΰ' => 'y', 'ϋ' => 'y', 'ΐ' => 'i',

        // Turkish
        'Ş' => 'S', 'İ' => 'I', 'Ç' => 'C', 'Ü' => 'U', 'Ö' => 'O', 'Ğ' => 'G',
        'ş' => 's', 'ı' => 'i', 'ç' => 'c', 'ü' => 'u', 'ö' => 'o', 'ğ' => 'g',

        // Russian
        'А' => 'A', 'Б' => 'B', 'В' => 'V', 'Г' => 'G', 'Д' => 'D', 'Е' => 'E', 'Ё' => 'Yo', 'Ж' => 'Zh',
        'З' => 'Z', 'И' => 'I', 'Й' => 'J', 'К' => 'K', 'Л' => 'L', 'М' => 'M', 'Н' => 'N', 'О' => 'O',
        'П' => 'P', 'Р' => 'R', 'С' => 'S', 'Т' => 'T', 'У' => 'U', 'Ф' => 'F', 'Х' => 'H', 'Ц' => 'C',
        'Ч' => 'Ch', 'Ш' => 'Sh', 'Щ' => 'Sh', 'Ъ' => '', 'Ы' => 'Y', 'Ь' => '', 'Э' => 'E', 'Ю' => 'Yu',
        'Я' => 'Ya',
        'а' => 'a', 'б' => 'b', 'в' => 'v', 'г' => 'g', 'д' => 'd', 'е' => 'e', 'ё' => 'yo', 'ж' => 'zh',
        'з' => 'z', 'и' => 'i', 'й' => 'j', 'к' => 'k', 'л' => 'l', 'м' => 'm', 'н' => 'n', 'о' => 'o',
        'п' => 'p', 'р' => 'r', 'с' => 's', 'т' => 't', 'у' => 'u', 'ф' => 'f', 'х' => 'h', 'ц' => 'c',
        'ч' => 'ch', 'ш' => 'sh', 'щ' => 'sh', 'ъ' => '', 'ы' => 'y', 'ь' => '', 'э' => 'e', 'ю' => 'yu',
        'я' => 'ya',

        // Ukrainian
        'Є' => 'Ye', 'І' => 'I', 'Ї' => 'Yi', 'Ґ' => 'G',
        'є' => 'ye', 'і' => 'i', 'ї' => 'yi', 'ґ' => 'g',

        // Czech
        'Č' => 'C', 'Ď' => 'D', 'Ě' => 'E', 'Ň' => 'N', 'Ř' => 'R', 'Š' => 'S', 'Ť' => 'T', 'Ů' => 'U',
        'Ž' => 'Z',
        'č' => 'c', 'ď' => 'd', 'ě' => 'e', 'ň' => 'n', 'ř' => 'r', 'š' => 's', 'ť' => 't', 'ů' => 'u',
        'ž' => 'z',

        // Polish
        'Ą' => 'A', 'Ć' => 'C', 'Ę' => 'e', 'Ł' => 'L', 'Ń' => 'N', 'Ó' => 'o', 'Ś' => 'S', 'Ź' => 'Z',
        'Ż' => 'Z',
        'ą' => 'a', 'ć' => 'c', 'ę' => 'e', 'ł' => 'l', 'ń' => 'n', 'ó' => 'o', 'ś' => 's', 'ź' => 'z',
        'ż' => 'z',

        // Latvian
        'Ā' => 'A', 'Č' => 'C', 'Ē' => 'E', 'Ģ' => 'G', 'Ī' => 'i', 'Ķ' => 'k', 'Ļ' => 'L', 'Ņ' => 'N',
        'Š' => 'S', 'Ū' => 'u', 'Ž' => 'Z',
        'ā' => 'a', 'č' => 'c', 'ē' => 'e', 'ģ' => 'g', 'ī' => 'i', 'ķ' => 'k', 'ļ' => 'l', 'ņ' => 'n',
        'š' => 's', 'ū' => 'u', 'ž' => 'z'
    );

    // Make custom replacements
    $str = preg_replace(array_keys($options['replacements']), $options['replacements'], $str);

    // Transliterate characters to ASCII
    if ($options['transliterate']) {
        $str = str_replace(array_keys($char_map), $char_map, $str);
    }

    // Replace non-alphanumeric characters with our delimiter
    $str = preg_replace('/[^\p{L}\p{Nd}]+/u', $options['delimiter'], $str);

    // Remove duplicate delimiters
    $str = preg_replace('/(' . preg_quote($options['delimiter'], '/') . '){2,}/', '$1', $str);

    // Truncate slug to max. characters
    $str = mb_substr($str, 0, ($options['limit'] ? $options['limit'] : mb_strlen($str, 'UTF-8')), 'UTF-8');

    // Remove delimiter from ends
    $str = trim($str, $options['delimiter']);

    return $options['lowercase'] ? mb_strtolower($str, 'UTF-8') : $str;
}


function awca_hashIT( $string, $size = 8 ) {
    return hexdec(substr(sha1($string), 0, $size));
}

function awca_limit_chars($string, $maxLength){
    // Ensure the string is in UTF-8 encoding
    $string = mb_convert_encoding($string, 'UTF-8', 'auto');

    // Truncate the string to the desired length
    $truncated = mb_strimwidth($string, 0, $maxLength, '...', 'UTF-8');

    return $truncated;
}

function awca_get_data_from_api($api_url)
{
    $retries = 5;
    $retry_delay = 10; // seconds
    $token = awca_get_activation_key();

    while ($retries > 0) {
        try {
            $response = wp_remote_get(
                $api_url,
                array(
                    'headers' => array(
                        'Authorization' => 'Bearer ' . $token,
                    ),
                    'timeout' => 300,
                )
            );

            if (!is_wp_error($response) && $response['response']['code'] === 200) {
                $data = json_decode($response['body']);
                return $data;
            } else {
                $error_message = '';
                if (is_array($response)) {
                    $error_message = $response['response']['message'];
                } elseif (is_wp_error($response)) {
                    $error_message = $response->get_error_message();
                } else {
                    $error_message = 'Unknown error';
                }
                awca_log('Failed to fetch data from API: ' . $api_url . '  Error: ' . $error_message . '. Retries left: ' . ($retries - 1));
                sleep($retry_delay); // wait before retrying
            }
        } catch (Exception $e) {
            awca_log('Exception caught while fetching data from API: ' . $e->getMessage() . '. Retries left: ' . ($retries - 1));
            sleep($retry_delay); // wait before retrying
        }

        $retries--;
    }

    awca_log('Failed to fetch data from API after multiple retries: ' . $api_url);
    return false;
}


function awca_fetch_and_store_api_response($key, $api_url, $record_per_page = false) {
    set_time_limit(300);
    set_transient('awca_sync_all_products_lock', true, 3600); // Lock for 1 hour

    awca_log('Run fetch API and Store, key: ' . $key . ', record_per_page: ' . $record_per_page);
    global $wpdb;
    $table_name = $wpdb->prefix . 'awca_large_api_responses';

    // Remove existing records for the key
    $wpdb->delete($table_name, array('key' => $key), array('%s'));

    $start_time = microtime(true);
    $page = 1;
    $limit = 30;
    $has_more_pages = true;
    $all_data = array();
    $max_retries = 5;
    $retry_delay = 5; // seconds

    while ($has_more_pages) {
        $paged_url = add_query_arg(array('page' => $page, 'limit' => $limit), $api_url);
        awca_log('Get: ' . $paged_url);

        $token = awca_get_activation_key();
        $response = false;
        $retries = 0;

        // Retry mechanism
        while ($retries < $max_retries) {
            $response = wp_remote_get(
                $paged_url,
                array(
                    'headers' => array(
                        'Authorization' => 'Bearer ' . $token,
                    ),
                    'timeout' => 300,
                )
            );

            if (!is_wp_error($response)) {
                break;
            }

            $error_message = $response->get_error_message();
            awca_log("API request failed (attempt " . ($retries + 1) . "): $error_message");
            $retries++;
            sleep($retry_delay); // wait before retrying
        }

        if (is_wp_error($response)) {
            $error_message = $response->get_error_message();
            awca_log("API request failed after $max_retries attempts: $error_message");
            delete_transient('awca_sync_all_products_lock');
            return false;
        }

        $http_code = wp_remote_retrieve_response_code($response);
        if ($http_code != 200) {
            awca_log("API request returned HTTP status code: $http_code");
            delete_transient('awca_sync_all_products_lock');
            return false;
        }

        $body = wp_remote_retrieve_body($response);
        if (empty($body)) {
            awca_log("API response body is empty");
            delete_transient('awca_sync_all_products_lock');
            return false;
        }

        $data = json_decode($body);

        // Determine if there are more pages
        if ($page * $limit >= $data->total) {
            $has_more_pages = false;
        }

        $progress_message = 'انار - دریافت محصولات ' . ($page * $limit) . '/' . $data->total;
        set_transient('awca_product_creation_progress', $progress_message, 3 * MINUTE_IN_SECONDS);

        awca_log(count($data->items) . ' items in this page');

        if ($record_per_page) {
            // Save each page's data as a new record
            $serialized_data = maybe_serialize($data);
            $current_time = current_time('mysql'); // Get the current time in MySQL DATETIME format

            // Insert a new record
            $inserted = $wpdb->insert(
                $table_name,
                array(
                    'response' => $serialized_data,
                    'processed' => 0,
                    'key' => $key,
                    'page' => $page,
                    'created_at' => $current_time // Insert the date field
                ),
                array(
                    '%s',
                    '%d',
                    '%s',
                    '%d',
                    '%s'
                )
            );

            if ($inserted === false) {
                $wpdb_error = $wpdb->last_error;
                awca_log("Failed to insert API response into the database: $wpdb_error");
                delete_transient('awca_sync_all_products_lock');
                return false;
            }

            awca_log("API response for page $page successfully fetched and stored");
        } else {
            $all_data = array_merge($all_data, $data->items);
        }

        $page++;
    }

    if (!$record_per_page) {
        awca_log('All items: ' . count($all_data));
        $serialized_data = maybe_serialize($all_data);
        $current_time = current_time('mysql'); // Get the current time in MySQL DATETIME format

        // Insert a new record
        $inserted = $wpdb->insert(
            $table_name,
            array(
                'response' => $serialized_data,
                'processed' => 0,
                'key' => $key,
                'created_at' => $current_time // Insert the date field
            ),
            array(
                '%s',
                '%d',
                '%s',
                '%s'
            )
        );

        if ($inserted === false) {
            $wpdb_error = $wpdb->last_error;
            awca_log("Failed to insert API response into the database: $wpdb_error");
            delete_transient('awca_sync_all_products_lock');
            return false;
        }

        awca_log("API response successfully fetched and stored");
    }

    $end_time = microtime(true);
    $time_taken = $end_time - $start_time;
    awca_log("Time taken to fetch and store API response: " . $time_taken . " seconds");
    delete_transient('awca_product_creation_progress');
    delete_transient('awca_sync_all_products_lock');

    return true;
}


function awca_fetch_and_store_api_response_by_page($key, $api_url, $page, $limit = 30) {
    set_time_limit(300);

    awca_log('Run fetch API and Store by page, key: ' . $key . ', page: ' . $page . ', limit: ' . $limit);
    global $wpdb;
    $table_name = $wpdb->prefix . 'awca_large_api_responses';
    // Remove existing records for the key

    if($page == 1)
        $wpdb->delete($table_name, array('key' => $key), array('%s'));

    $paged_url = add_query_arg(array('page' => $page, 'limit' => $limit), $api_url);
    awca_log('Get: ' . $paged_url);

    $token = awca_get_activation_key();
    $response = false;
    $retries = 0;
    $max_retries = 5;
    $retry_delay = 5; // seconds
    $data_items = array();
    $total_products = 0;

    // Retry mechanism
    while ($retries < $max_retries) {
        $response = wp_remote_get(
            $paged_url,
            array(
                'headers' => array(
                    'Authorization' => 'Bearer ' . $token,
                ),
                'timeout' => 300,
            )
        );

        if (!is_wp_error($response)) {
            break;
        }

        $error_message = $response->get_error_message();
        awca_log("API request failed (attempt " . ($retries + 1) . "): $error_message");
        $retries++;
        sleep($retry_delay); // wait before retrying
    }

    if (is_wp_error($response)) {
        $error_message = $response->get_error_message();
        awca_log("API request failed after $max_retries attempts: $error_message");
        return false;
    }

    $http_code = wp_remote_retrieve_response_code($response);
    if ($http_code != 200) {
        awca_log("API request returned HTTP status code: $http_code");
        return false;
    }

    $body = wp_remote_retrieve_body($response);
    if (empty($body)) {
        awca_log("API response body is empty");
        return false;
    }

    $data = json_decode($body);
    $data_items = $data->items;
    $total_items = count($data_items);
    $total_products = $data->total; // Assuming the total number of products is included in the API response

    $progress_message = 'انار - دریافت محصولات ' . ($page * $limit) . '/' . $total_products;
    set_transient('awca_product_creation_progress', $progress_message, 3 * MINUTE_IN_SECONDS);

    awca_log($total_items . ' items in this page');

    // Save each page's data as a new record
    $serialized_data = maybe_serialize($data);
    $current_time = current_time('mysql'); // Get the current time in MySQL DATETIME format

    // Insert a new record
    $inserted = $wpdb->insert(
        $table_name,
        array(
            'response' => $serialized_data,
            'processed' => 0,
            'key' => $key,
            'page' => $page,
            'created_at' => $current_time // Insert the date field
        ),
        array(
            '%s',
            '%d',
            '%s',
            '%d',
            '%s'
        )
    );

    if ($inserted === false) {
        $wpdb_error = $wpdb->last_error;
        awca_log("Failed to insert API response into the database: $wpdb_error");
        return false;
    }

    awca_log("API response for page $page successfully fetched and stored");

    return array('total_items' => $total_items, 'data_items' => $data_items, 'total_products' => $total_products);
}



function awca_get_stored_response($key)
{
    global $wpdb;
    $table_name = $wpdb->prefix . 'awca_large_api_responses';

    // Prepare the SQL query with the key parameter
    $query = $wpdb->prepare(
        "SELECT * FROM $table_name WHERE `key` = %s",
        $key
    );

    // Execute the query
    $response_row = $wpdb->get_row($query, ARRAY_A);

    // Check if a row was returned
    if ($response_row) {
        if (!empty($response_row['response'])) {

            $response = array(
                'response' => maybe_unserialize($response_row['response']),
                'created_at' => $response_row['created_at'],
                'processed' => $response_row['processed']
            );

            return $response;
        } else {
            awca_log("The response for key $key is empty after unserialization.");
            return false;
        }
    } else {
        awca_log("No row found for key $key.");
        return false;
    }
}


function awca_get_stored_response_paged($key, $page) {
    global $wpdb;
    $table_name = $wpdb->prefix . 'awca_large_api_responses';

    // Prepare the SQL query with the key and page parameters
    $query = $wpdb->prepare(
        "SELECT * FROM $table_name WHERE `key` = %s AND `page` = %d",
        $key, $page
    );

    // Execute the query
    $response_row = $wpdb->get_row($query, ARRAY_A);

    // Check if a row was returned
    if ($response_row) {
        if (!empty($response_row['response'])) {

            return maybe_unserialize($response_row['response']);
        } else {
            awca_log("The response for key $key page $page is empty after unserialization.");
            return false;
        }
    } else {
        awca_log("No row found for key $key page $page.");
        return false;
    }
}






function awca_product_short_desc($desc)
{
    $desc = trim($desc); // Trim whitespace
    if (strlen($desc) > 100) {
        $last_space = strrpos(substr($desc, 0, 100), ' ');

        if ($last_space !== false) {
            $limited_desc = substr($desc, 0, $last_space) . '...';
        } else {
            $limited_desc = substr($desc, 0, 100) . '...';
        }
    } else {
        $limited_desc = $desc;
    }
    return $limited_desc;
}

function awca_default_product_image()
{
    return ANAR_WC_API_PLUGIN_URL . '/assets/images/default.png';
}

function awca_product_price_digits_seprator($price)
{
    return number_format($price);
}

function awca_get_product_variation_by_anar_sku($anar_sku) {
    global $wpdb;

    // Search for variations with the custom SKU
    $variation_id = $wpdb->get_var($wpdb->prepare("
        SELECT post_id 
        FROM {$wpdb->postmeta} 
        WHERE meta_key = '_anar_sku' 
        AND meta_value = %s
    ", $anar_sku));

    if ($variation_id) {
        return $variation_id;
    }

    // If not found, return false
    return false;
}

function awca_get_simple_product_by_anar_sku($anar_sku) {
    global $wpdb;
    $sql = "SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key = '_anar_sku' AND meta_value = %s";


    // Search for variations with the custom SKU
    $variation_id = $wpdb->get_var($wpdb->prepare($sql, $anar_sku));

    if ($variation_id) {
        return $variation_id;
    }

    // If not found, return false
    return false;
}

function awca_find_best_match_category_from_categories($categories)
{
    $longestRouteLen = 0;
    $matchCategoryName = "Not Found";
    foreach($categories as $category) {
        $route = $category->route;
        $routeLen = count($route);
        if ($routeLen > $longestRouteLen) {
            $longestRouteLen = $routeLen;
            $matchCategoryName = end($route);
        }
    }

    return $matchCategoryName;
}

function awca_get_final_attribute_name_from_attribute_and_category($attributeName, $categoryName)
{
    return $attributeName . '-(' . $categoryName . ')';
}

/**
 * Sync all products from external API and update WooCommerce products.
 * @TODO keep sync report simple & variable products, success and errors, the log it
 */
function awca_sync_all_products_depricated() {
    $api_url = 'https://api.anar360.com/api/360/products';
    $awca_products = awca_get_data_from_api($api_url);

    awca_log('------- sync start --------');
    if (!$awca_products) {
        awca_log('Failed to fetch products from API: ' . $awca_products->get_error_message());
        return;
    }

    foreach ($awca_products->items as $index => $updateProduct) {

        if(count($updateProduct->variants) == 1){
            // simple wc product

            $variant = $updateProduct->variants[0];
            $sku = $updateProduct->id;
            $product_id = awca_get_simple_product_by_anar_sku( $sku );
            if( $product_id ) {

                $variant_stock = ($updateProduct->resellStatus == 'editing-pending') ? 0 : $variant->stock;

                $product = wc_get_product( $product_id );
                $product->set_stock_quantity($variant_stock);
                $product->set_price(awca_convert_price_to_woocommerce_currency($variant->price));
                $product->set_regular_price(awca_convert_price_to_woocommerce_currency($variant->price));
                $product->save();
            }
        }else{
            // variable wc product

            foreach ($updateProduct->variants as $variant) {
                $sku = $variant->_id;
//            $product_id = wc_get_product_id_by_sku( $sku );
                $product_id = awca_get_product_variation_by_anar_sku( $sku );
                if( $product_id ) {

                    $variant_stock = ($updateProduct->status == 'editing-pending') ? 0 : $variant->stock;

                    $product = wc_get_product( $product_id );
                    $product->set_stock_quantity($variant_stock);
                    $product->set_price(awca_convert_price_to_woocommerce_currency($variant->price));
                    $product->set_regular_price(awca_convert_price_to_woocommerce_currency($variant->price));
                    $product->save();
                }
            }
        }


    }

    awca_log('------- sync end --------');
}


function awca_sync_all_products() {

    if (get_transient('awca_sync_all_products_lock')) {
        awca_log('Sync already in progress, exiting to prevent overlap.');
        return;
    }

    // Set a transient to lock the function
    set_transient('awca_sync_all_products_lock', true, 3600); // Lock for 1 hour

    try{
        $base_api_url = 'https://api.anar360.com/api/360/products';
        $limit = 10; // Number of products per page
        $page = 1; // Starting page
        $synced_counter = 0;
        awca_log('------- sync start --------');

        while (true) {
            $api_url = add_query_arg(array('page' => $page, 'limit' => $limit), $base_api_url);
            $awca_products = awca_get_data_from_api($api_url);

            awca_log('sync '.$synced_counter.' products - page ' . $page );

            if (is_wp_error($awca_products)) {
                awca_log('Failed to fetch products from API: ' . $awca_products->get_error_message());
                return;
            }

            if (empty($awca_products->items)) {
                // No more products to fetch
                break;
            }

            foreach ($awca_products->items as $index => $updateProduct) {

                if(count($updateProduct->variants) == 1){
                    // simple wc product

                    $variant = $updateProduct->variants[0];
                    $sku = $updateProduct->id;
                    $product_id = awca_get_simple_product_by_anar_sku( $sku );
                    if( $product_id ) {

                        $variant_stock = ($updateProduct->resellStatus == 'editing-pending') ? 0 : $variant->stock;

                        $product = wc_get_product( $product_id );
                        $product->set_stock_quantity($variant_stock);
                        $product->set_price(awca_convert_price_to_woocommerce_currency($variant->price));
                        $product->set_regular_price(awca_convert_price_to_woocommerce_currency($variant->price));
                        $product->save();
                    }
                }else{
                    // variable wc product

                    foreach ($updateProduct->variants as $variant) {
                        $sku = $variant->_id;
//            $product_id = wc_get_product_id_by_sku( $sku );
                        $product_id = awca_get_product_variation_by_anar_sku( $sku );
                        if( $product_id ) {

                            $variant_stock = ($updateProduct->status == 'editing-pending') ? 0 : $variant->stock;

                            $product = wc_get_product( $product_id );
                            $product->set_stock_quantity($variant_stock);
                            $product->set_price(awca_convert_price_to_woocommerce_currency($variant->price));
                            $product->set_regular_price(awca_convert_price_to_woocommerce_currency($variant->price));
                            $product->save();
                        }
                    }
                }


            }

            $synced_counter = $synced_counter + count($awca_products->items);

            // Check if we've fetched less than the limit, which means this is the last page
            if (count($awca_products->items) < $limit) {
                break;
            }

            // Increment the page number for the next iteration
            $page++;
        }

        update_option('awca_last_sync_time', current_time('mysql'));

    }finally {
        // Release the lock
        delete_transient('awca_sync_all_products_lock');
    }

    awca_log('------- sync end --------');
}


function awca_process_products_cron_function() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'awca_large_api_responses';

    // Check if the lock is already set
    if (get_transient('awca_product_creation_lock')) {
        awca_log('Cron job skipped because lock is set.');
        return; // Exit if lock is set
    }

    // Set the lock
    set_transient('awca_product_creation_lock', true, 3600); // Lock for 1 hour

    try {
        // Fetch the next unprocessed page of products
        $row = $wpdb->get_row("SELECT * FROM $table_name WHERE `key` = 'products' AND `processed` = 0 ORDER BY `page` ASC LIMIT 1", ARRAY_A);

        if ($row) {
            $page = $row['page'];
            $serialized_response = $row['response'];
            $awca_products = maybe_unserialize($serialized_response);

            if ($awca_products === false) {
                awca_log("Failed to unserialize the response for page $page.");
                throw new Exception("Failed to unserialize the response for page $page.");
            }

            if (!isset($awca_products->items)) {
                awca_log("Unserialized data does not contain 'items' for page $page.");
                throw new Exception("Unserialized data does not contain 'items' for page $page.");
            }

            awca_log("Processing Page: $page, Products Retrieved: " . count($awca_products->items));

            // Get Categories & Attributes needed for product creation
            $attributeMap = get_option('attributeMap');
            $combinedCategories = get_option('combinedCategories');
            $categoryMap = get_option('categoryMap');

            $responses = [];
            $created_product_ids = [];
            $exist_product_ids = [];
            $serialized_products = [];
            $total_products = $awca_products->total;

            // Loop through products and create them in WooCommerce
            foreach ($awca_products->items as $item) {
                $prepared_product = awca_product_list_serializer($item);
                $serialized_products[] = $prepared_product;

                $product_attributes = [];
                $prepared_product['attributes'] = $product_attributes;

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

                $product_creation_result = awca_create_woocommerce_product($product_creation_data, $combinedCategories, $attributeMap, $categoryMap);

                $product_id = $product_creation_result['product_id'];
                if ($product_creation_result['created']) {
                    $created_product_ids[] = $product_id;

                    awca_set_product_image_from_url($product_id, $prepared_product['image']);
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
                $creation_progress_data = ['created' => (count($created_product_ids) + (($page-1) * 30)), 'exist' => count($exist_product_ids)];
                awca_log('Product create progress - Created: ' . $creation_progress_data['created'] . ', Exist: ' . $creation_progress_data['exist']);
                $progress_message = 'انار - افزودن محصول ' . count($created_product_ids) . '/' . $total_products;
                set_transient('awca_product_creation_progress', $progress_message, 4 * MINUTE_IN_SECONDS);
            }

            // Mark the page as processed
            $wpdb->update(
                $table_name,
                array('processed' => 1),
                array('id' => $row['id']),
                array('%d'),
                array('%d')
            );

            awca_log("Page $page processed and marked as complete.");
        } else {
            awca_log('No unprocessed product pages found.');
            delete_transient('awca_product_creation_lock');
        }
    } catch (Exception $e) {
        awca_log('Error processing products: ' . $e->getMessage());
    } finally {
        // Remove the lock
        delete_transient('awca_product_creation_lock');
    }
}



/**
 * Convert a price from IRT (Iranian Toman) to the WooCommerce configured currency.
 *
 * This function checks the current WooCommerce currency setting and converts the price
 * from IRT (Iranian Toman) to IRR (Iranian Rial) if necessary. The conversion rate is
 * assumed to be 1 IRT = 10 IRR.
 *
 * @param float $price_in_irt The price in IRT (Iranian Toman) to be converted.
 * @return float The converted price in the WooCommerce configured currency.
 */
function awca_convert_price_to_woocommerce_currency($price_in_irt) {
    // Define the conversion rate from IRT to IRR
    $conversion_rate = 10; // 1 IRT = 10 IRR

    // Get the current WooCommerce currency
    $woocommerce_currency = get_woocommerce_currency();

    // Convert the price based on the WooCommerce currency
    if ($woocommerce_currency === 'IRR') {
        // Convert IRT to IRR
        $price_in_irr = $price_in_irt * $conversion_rate;
        return $price_in_irr;
    } else {
        // Return the price in IRT (no conversion needed)
        return $price_in_irt;
    }
}



/**
 * Download an image from a URL, insert it into the media library, and set it as the product's featured image.
 *
 * @param int $product_id The ID of the product to set the image for.
 * @param string $image_url The URL of the image to download.
 * @return int|WP_Error The attachment ID on success, or a WP_Error on failure.
 */
function awca_set_product_image_from_url($product_id, $image_url) {
    // Insert the attachment into the WordPress media library
    $attachment_id = awca_download_and_insert_attachment($image_url, $product_id);

    // Check if there was an error in downloading and inserting the image
    if (is_wp_error($attachment_id)) {
        awca_log("Failed to download and insert attachment for product ID: $product_id. Error: " . $attachment_id->get_error_message());
        return $attachment_id;
    }

    // Set the downloaded image as the product's featured image
    $thumbnail_result = set_post_thumbnail($product_id, $attachment_id);

    if (!$thumbnail_result) {
        awca_log("Failed to set product thumbnail for product ID: $product_id");
        return new WP_Error('thumbnail_set_failed', __('Failed to set product thumbnail.'));
    }

    // Clean this meta to skip from cron job check
    update_post_meta($product_id, '_product_image_url', false);
    awca_log('Product #'.$product_id.' thumbnail is set');
    return $attachment_id;
}



/**
 * Download images from URLs, insert them into the media library, and set them as the product's gallery images.
 *
 * @param int $product_id The ID of the product to set the gallery images for.
 * @param array $image_urls An array of image URLs to download and set as the product gallery.
 * @return array|WP_Error An array of attachment IDs on success, or a WP_Error on failure.
 */
function awcs_set_product_gallery_from_array_urls($product_id, $image_urls, $gallery_image_limit = 5) {
    $attachment_ids = array();
    $counter = 0;

    foreach ($image_urls as $image_url) {

        if ($counter >= $gallery_image_limit) {
            break;
        }

        // Download and upload the image
        $attachment_id = awca_download_and_insert_attachment($image_url, $product_id);

        if (is_wp_error($attachment_id)) {
            awca_log("Failed to download and insert attachment for product ID: $product_id. Error: " . $attachment_id->get_error_message());
            return $attachment_id; // Return WP_Error if download/upload fails
        }

        $attachment_ids[] = $attachment_id;

        $counter++;
    }

    // Set product gallery images if there are attachment IDs
    if (!empty($attachment_ids)) {
        $product = wc_get_product($product_id);

        if ($product) {
            $product->set_gallery_image_ids($attachment_ids);
            $product->save();

            // Clean this meta to skip from cron job check
            update_post_meta($product_id, '_anar_gallery_images', false);
        } else {
            awca_log("Failed to get product with ID: $product_id");
            return new WP_Error('product_not_found', __('Product not found.'));
        }
    }
    awca_log('Product #'.$product_id.' gallery is set');
    return $attachment_ids;
}


function awca_transform_image_url($url) {
    // Check if the URL starts with the expected prefix
    $prefix = "https://s3.c22.wtf/";
    if (strpos($url, $prefix) === 0) {
        // Replace the initial part of the URL and add the new prefix
        $new_prefix = "https://s3.anar360.com/_img/width_1024/https://s3.anar360.com/";
        $new_url = str_replace($prefix, $new_prefix, $url);
        return $new_url;
    } else {
        // If the URL does not match the expected pattern, return it unchanged
        return $url;
    }
}


/**
 * Download an image from a URL and insert it into the WordPress media library.
 *
 * @param string $image_url The URL of the image to download.
 * @param int $product_id The ID of the product to attach the image to.
 * @return int|WP_Error The attachment ID on success, or a WP_Error on failure.
 */
function awca_download_and_insert_attachment($image_url, $product_id) {
    // Transform the image URL if needed
    $image_url = awca_transform_image_url($image_url);

    // Get the image file name from the URL
    $image_name = basename($image_url);

    // Get the WordPress upload directory
    $upload_dir = wp_upload_dir();

    // Use wp_remote_get to fetch the image data
    $response = wp_remote_get($image_url, array(
        'timeout' => 30,
        'redirection' => 10,
    ));

    // Check for errors in the response
    if (is_wp_error($response)) {
        awca_log("Failed to download image from URL: $image_url");
        awca_log("Error: " . $response->get_error_message());
        return new WP_Error('image_download_failed', __('Failed to download image.'));
    }

    // Get the HTTP response code and image data
    $http_code = wp_remote_retrieve_response_code($response);
    $image_data = wp_remote_retrieve_body($response);

    // Check for invalid response
    if ($http_code !== 200 || empty($image_data)) {
        awca_log("Failed to download image from URL: $image_url");
        awca_log("HTTP Code: $http_code");
        return new WP_Error('image_download_failed', __('Failed to download image.'));
    }

    // Ensure the file name is unique in the upload directory
    $unique_file_name = wp_unique_filename($upload_dir['path'], $image_name);
    $file_path = $upload_dir['path'] . '/' . $unique_file_name;

    // Save the image data to the file
    $file_saved = @file_put_contents($file_path, $image_data);
    if ($file_saved === false) {
        awca_log("Failed to save image to file: $file_path");
        return new WP_Error('image_save_failed', __('Failed to save image.'));
    }

    // Check the file type for the attachment metadata
    $wp_filetype = wp_check_filetype($file_path, null);

    // Prepare the attachment data array
    $attachment_data = array(
        'guid'           => $upload_dir['url'] . '/' . $unique_file_name,
        'post_mime_type' => $wp_filetype['type'],
        'post_title'     => sanitize_file_name($image_name),
        'post_content'   => '',
        'post_status'    => 'inherit'
    );

    // Insert the attachment into the WordPress media library
    $attachment_id = wp_insert_attachment($attachment_data, $file_path, $product_id);
    if (is_wp_error($attachment_id)) {
        awca_log("Failed to insert attachment into media library: " . $attachment_id->get_error_message());
        return $attachment_id;
    }

    // Ensure the necessary file includes for generating attachment metadata
    require_once(ABSPATH . 'wp-admin/includes/image.php');

    // Generate and update the attachment metadata
    $attachment_metadata = wp_generate_attachment_metadata($attachment_id, $file_path);
    $metadata_result = wp_update_attachment_metadata($attachment_id, $attachment_metadata);
    if (!$metadata_result) {
        awca_log("Failed to update attachment metadata for attachment ID: $attachment_id");
        //return new WP_Error('metadata_update_failed', __('Failed to update attachment metadata.'));
    }

    awca_log('-------- image uploaded ------ ' . print_r($product_id, true));
    return $attachment_id;
}

// Function to check if the job is aborted
function awca_is_job_aborted($job_id) {
    return get_transient('abort_job_' . $job_id) === 'aborted';
}


function print_anar($title='', $var){

    echo '<div style="background:#efefef; padding:24px; clear:both; width:100%"><pre style="direction:ltr; text-align:left">';
    echo $title ? '<h2>'.$title.'</h2>' : '';
    print_r($var);
    echo '</pre></div>';
}

function awca_log($message) {
    // Define the log file path
    $log_file = WP_CONTENT_DIR . '/anar.log';

    // Create the log file if it doesn't exist
    if (!file_exists($log_file)) {
        $file_handle = fopen($log_file, 'w');
        fclose($file_handle);
    }

    // Ensure the log file is writable
    if (is_writable($log_file)) {
        // Append the message to the log file
        $timestamp = date("Y-m-d H:i:s");
        file_put_contents($log_file, "[$timestamp] $message" . PHP_EOL, FILE_APPEND);
    } else {
        awca_log("Cannot write to log file: $log_file");
    }
}


function awca_get_mock_data($mock_file_path) {
    $file_path = ANAR_WC_API_PLUGIN_DIR . $mock_file_path;

    // Check if the file exists
    if (!file_exists($file_path)) {
        return new WP_Error('file_not_found', 'Mock data file not found.');
    }

    // Get the file contents
    $json_data = file_get_contents($file_path);

    // Decode the JSON data
    $mock_data = json_decode($json_data);

    if (is_wp_error($mock_data)) {
        $error_message = $mock_data->get_error_message();
        // Handle the error as needed
        awca_log($error_message);
        return false;
    }else{
        // Check if the data was decoded successfully
        if (json_last_error() !== JSON_ERROR_NONE) {
            return new WP_Error('json_decode_error', 'Error decoding JSON data.');
        }

        return $mock_data;
    }


}

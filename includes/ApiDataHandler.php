<?php

namespace Anar;

use Exception;
/**
 * Class ApiDataHandler
 *
 * This class manages the interactions with the Anar API. It allows
 * for the retrieval of stored API responses and can fetch new data
 * from the API when provided with the necessary URL.
 *
 * Responsibilities:
 * - Retrieve stored responses from the Anar API.
 * - Fetch updated data from the Anar API using a specified URL.
 *
 */
class ApiDataHandler
{
    private $key;

    private $token;
    private $api_url;
    private $wpdb;
    private $table_name;

    public function __construct($key, $api_url = null)
    {
        global $wpdb;
        $this->token = anar_get_saved_token();
        $this->key = $key;
        $this->api_url = $api_url;
        $this->wpdb = $wpdb;
        $this->table_name = $wpdb->prefix . ANAR_DB_NAME;
    }


    public static function callAnarApi($url){

        if(get_option('anar_conf_feat__api_validate', 'new') == 'new'){
            $check_args = ["check" => "true"];
            $url = add_query_arg($check_args, $url);
        }

        $token = anar_get_saved_token();

        if(!$token)
            return new \WP_Error(401, 'پلاگین انار فعال نیست. توکن وارد نشده است.');

        if(!$url)
            return new \WP_Error(400, 'URL invalid!');


        return wp_remote_get($url, [
            'headers' => [
                'Authorization' => $token,
                'Accept' => 'application/json',
                'wp-header' => get_site_url()
            ],
            'timeout' => 300,
        ]);

    }


    public static function postAnarApi($url, $data)
    {
        $token = anar_get_saved_token();

        if(!$token)
            return new \WP_Error(401, 'توکن انار معتبر نیست.');

        if(!$url)
            return new \WP_Error(400, 'URL invalid!');


        if(!$data)
            return new \WP_Error(400, 'Data invalid!');


        return  wp_remote_post($url, [
            'method' => 'POST',
            'body' => json_encode($data),
            'headers' => [
                'Content-Type' => 'application/json',
                'Accept'       => 'application/json',
                'Authorization' => $token,
                'wp-header' => get_site_url()
            ],
            'timeout' => 300,
        ]);

    }


    /**
     * @param $url
     * @return false|mixed
     */
    public static function tryGetAnarApiResponse($url)
    {
        $retries = 3;
        $retry_delay = 2;

        while ($retries > 0) {
            try {
                $response = self::callAnarApi($url);

                if($response['response']['code'] === 403){
                    return false;
                }

                if (!is_wp_error($response) && $response['response']['code'] === 200) {
                    return json_decode($response['body']);
                } else {
                    $error_message = '';
                    if (is_array($response)) {
                        $error_message = $response['response']['message'];
                    } elseif (is_wp_error($response)) {
                        $error_message = $response->get_error_message();
                    } else {
                        $error_message = 'Unknown error';
                    }
                    awca_log('Failed to fetch data from API: ' . $url . '  Error: ' . $error_message . '. Retries left: ' . ($retries - 1));
                    sleep($retry_delay); // wait before retrying
                }
            } catch (Exception $e) {
                awca_log('Exception caught while fetching data from API: ' . $e->getMessage() . '. Retries left: ' . ($retries - 1));
                sleep($retry_delay); // wait before retrying
            }

            $retries--;
        }

        awca_log('Failed to fetch data from API after multiple retries: ' . $url);
        return false;
    }


    /**
     * Fetch and store Anar API response.
     *
     * @param bool $record_per_page
     * @return bool
     * @throws Exception
     */
    public function fetchAndStoreApiResponse(bool $record_per_page = false): bool
    {
        if ($this->api_url == null){
            awca_log("API URL is required for fetching data.");
            return false;
        }

        set_time_limit(300);
        set_transient('awca_sync_all_products_lock', true, 3600); // Lock for 1 hour
        awca_log('Run fetch API and Store, key: ' . $this->key . ', record_per_page: ' . $record_per_page, 'import');

        // Remove existing records for the key
        $this->wpdb->delete($this->table_name, ['key' => $this->key], ['%s']);

        $page = 1;
        $limit = 30;
        $has_more_pages = true;
        $all_data = [];
        $max_retries = 5;
        $retry_delay = 5; // seconds

        while ($has_more_pages) {
            $paged_url = add_query_arg(['page' => $page, 'limit' => $limit], $this->api_url);
            awca_log('Get: ' . $paged_url);


            $response = $this->retryRequest($paged_url, $max_retries, $retry_delay);

            if (!$response) {
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
            $has_more_pages = $page * $limit < $data->total;
            $this->handleApiResponse($data, $record_per_page, $page, $all_data);

            $page++;
        }

        // Handle saving all data if not recording per page
        if (!$record_per_page && !$this->storeApiResponse($all_data)) {
            delete_transient('awca_sync_all_products_lock');
            return false;
        }

        delete_transient('awca_sync_all_products_lock');
        return true;
    }

    /**
     * Fetch and store Anar API response by page.
     *
     * @param int $page
     * @param int $limit
     * @return array|false
     */
    public function fetchAndStoreApiResponseByPage(int $page, int $limit = 30)
    {
        if ($this->api_url == null)
            throw new Exception("API URL is required for fetching data.");

        set_time_limit(300);
        awca_log('Run fetch API and Store by page, key: ' . $this->key . ', page: ' . $page . ', limit: ' . $limit, 'import');

        if ($page == 1) {
            $this->wpdb->delete($this->table_name, ['key' => $this->key], ['%s']);
        }

        $paged_url = add_query_arg(['page' => $page, 'limit' => $limit], $this->api_url);
        $response = $this->retryRequest($paged_url, 5, 5);

        if (!$response) {
            return false;
        }

        $body = wp_remote_retrieve_body($response);
        if (empty($body)) {
            awca_log("API response body is empty", 'import');
            return false;
        }

        $data = json_decode($body);
        return $this->storePageData($data, $page);
    }


    /**
     * Retrieves the stored API response.
     *
     * This method accesses the internal storage of API responses
     * and returns the relevant data. It does not require an API URL
     * as it operates on previously fetched data.
     *
     * @return mixed The stored API response data, or null if no data exists.
     */
    public function getStoredApiResponse()
    {
        $query = $this->wpdb->prepare(
            "SELECT * FROM $this->table_name WHERE `key` = %s",
            $this->key
        );
        $response_row = $this->wpdb->get_row($query, ARRAY_A);

        return $this->processStoredResponse($response_row);
    }

    /**
     * Retrieve stored Anar API response by key and page.
     *
     * @param int $page
     * @return mixed|false
     */
    public function getStoredApiResponseByPage(int $page)
    {
        $query = $this->wpdb->prepare(
            "SELECT * FROM $this->table_name WHERE `key` = %s AND `page` = %d",
            $this->key, $page
        );
        $response_row = $this->wpdb->get_row($query, ARRAY_A);

        return $this->processStoredResponse($response_row, true);
    }

    /**
     * Retry mechanism for the API request.
     *
     * @param string $url
     * @param string $token
     * @param int $max_retries
     * @param int $retry_delay
     * @return false|array|WP_Error
     */
    private function retryRequest($url, $max_retries, $retry_delay)
    {
        $response = false;
        $retries = 0;

        while ($retries < $max_retries) {

            $response = self::callAnarApi($url);

            if (!is_wp_error($response)) {
                return $response;
            }

            awca_log("API request failed (attempt " . ($retries + 1) . "): " . $response->get_error_message());
            $retries++;
            sleep($retry_delay);
        }

        return false;
    }

    /**
     * Store the API response.
     *
     * @param array $data
     * @return bool
     */
    private function storeApiResponse($data): bool
    {
        $serialized_data = maybe_serialize($data);
        $current_time = current_time('mysql');

        $inserted = $this->wpdb->insert(
            $this->table_name,
            [
                'response' => $serialized_data,
                'processed' => 0,
                'key' => $this->key,
                'created_at' => $current_time,
            ],
            ['%s', '%d', '%s', '%s']
        );

        if ($inserted === false) {
            awca_log("Failed to insert API response into the database: " . $this->wpdb->last_error);
            return false;
        }

        awca_log("API response successfully fetched and stored");
        return true;
    }

    /**
     * Handle storing page data.
     */
    private function storePageData($data, $page)
    {
        $serialized_data = maybe_serialize($data);
        $current_time = current_time('mysql');

        $inserted = $this->wpdb->insert(
            $this->table_name,
            [
                'response' => $serialized_data,
                'processed' => 0,
                'key' => $this->key,
                'page' => $page,
                'created_at' => $current_time,
            ],
            ['%s', '%d', '%s', '%d', '%s']
        );

        if ($inserted === false) {
            awca_log("Failed to insert API response into the database: " . $this->wpdb->last_error);
            return false;
        }

        return [
            'total_items' => count($data->items),
            'data_items' => $data->items,
            'total_products' => $data->total,
        ];
    }

    /**
     * Process the stored response.
     *
     * @param array $response_row
     * @param bool $is_page
     * @return array|false
     */
    private function processStoredResponse($response_row, $is_page = false)
    {
        if ($response_row && !empty($response_row['response'])) {
            return $is_page
                ? maybe_unserialize($response_row['response'])
                : [
                    'response' => maybe_unserialize($response_row['response']),
                    'created_at' => $response_row['created_at'],
                    'processed' => $response_row['processed'],
                ];
        }

        awca_log("No data found for key {$this->key} " . ($is_page ? "page {$response_row['page']}" : ""));
        return false;
    }

    /**
     * Handle API response data (either store per page or accumulate).
     */
    private function handleApiResponse($data, $record_per_page, $page, &$all_data)
    {
        if ($record_per_page) {
            $this->storePageData($data, $page);
        } else {
            $all_data = array_merge($all_data, $data->items);
        }

        awca_log(count($data->items) . ' items in this page');
    }
}

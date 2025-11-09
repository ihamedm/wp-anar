<?php
namespace Anar\Core;
use Anar\OrderReports;

/**
 * UsageData Class
 *
 * Collects and sends WordPress site data to a Cloudflare Worker
 */
class UsageData {

    private static $instance;

    /**
     * @var string The URL of the Cloudflare Worker
     */
    private $worker_url = 'https://awake.anarwp.workers.dev/';

    /**
     * @var int Timeout in seconds for the API request
     */
    private $timeout;

    /**
     * @var bool Whether to verify SSL certificate
     */
    private $ssl_verify;


    public static function instance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Constructor
     *
     * @param int $timeout Timeout in seconds for the API request (default: 30)
     * @param bool $ssl_verify Whether to verify SSL certificate (default: true)
     */
    public function __construct( $timeout = 30, $ssl_verify = true) {
        $this->timeout = $timeout;
        $this->ssl_verify = $ssl_verify;
    }

    /**
     * Get days and months passed since first Anar product import
     *
     * @return array Array with 'days' and 'months' keys
     */
    private function get_days_since_first_import() {
        global $wpdb;
        
        try {
            // Query to find the oldest Anar product (existence of meta key only)
            $query = $wpdb->prepare("
                SELECT MIN(p.post_date) as oldest_date
                FROM {$wpdb->posts} p
                INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
                WHERE p.post_type = %s
                AND p.post_status IN ('publish', 'draft')
                AND pm.meta_key = %s
            ", 'product', '_anar_products');
            
            $oldest_date = $wpdb->get_var($query);
            
            if (!$oldest_date) {
                return ['days' => 0, 'months' => 0];
            }
            
            $first_import = new \DateTime($oldest_date);
            $current_date = new \DateTime();
            $diff = $current_date->diff($first_import);
            
            $days = $diff->days;
            $months = ceil($days / 30); // Round up division by 30
            
            return ['days' => $days, 'months' => $months];
            
        } catch (\Exception $e) {
            anar_log("Error calculating days since first import: " . $e->getMessage(), 'error');
            return ['days' => 0, 'months' => 0];
        }
    }

    /**
     * Sends collected site data to Cloudflare Worker
     *
     * @param array $additional_data Optional additional data to include
     * @return array Response with status and message
     */
    public function send($additional_data = []) {
        $site_url = get_site_url();
        $domain = parse_url($site_url, PHP_URL_HOST);

        // Collect basic site data
        $data = array(
            'domain' => $domain,
            'site_name' => get_bloginfo('name'),
            'wordpress_version' => get_bloginfo('version'),
            'php_version' => phpversion(),
            'anar_version' => ANAR_PLUGIN_VERSION,
            'anar_products' => get_transient(OPT_KEY__COUNT_ANAR_PRODUCT_ON_DB),
            'orders' => OrderReports::count_anar_orders(),
            'orders_submitted' => OrderReports::count_anar_orders_submited(),
            'token_validation' => get_option('_anar_token_validation', ''),
            'sub_renew_times' => $this->get_days_since_first_import()['months'],
            'sub_remain_days' => round((get_option('_anar_subscription_remaining', 0) / 86400000)),
            'sub_plan' => get_option('_anar_subscription_plan', ''),
            'sub_shop' => get_option('_anar_shop_url', ''),
            'first_activation' => wp_date( 'Y-m-d H:i:s' ,strtotime(get_option('_anar_activation_first_time_at'))),
            'connected_at' => wp_date('Y-m-d H:i:s' , strtotime(get_option('_anar_domain_connected_at'))),
            'days_since_first_import' => $this->get_days_since_first_import()['days'],
            'update_this_report' => wp_date( 'Y-m-d H:i:s' ),
        );

        anar_log(print_r($data, true), 'info');

        // Merge with any additional data provided
        if (!empty($additional_data) && is_array($additional_data)) {
            $data = array_merge($data, $additional_data);
        }

        // Setup the request arguments
        $args = array(
            'method'    => 'POST',
            'timeout'   => $this->timeout,
            'headers'   => array(
                'Content-Type' => 'application/json',
                'x-url'        => $site_url,
            ),
            'body'      => json_encode($data),
            'sslverify' => $this->ssl_verify,
        );

        // Log the attempt (optional)
        anar_log("Sending data to Cloudflare Worker: {$this->worker_url}");
        anar_log(print_r($args, true));

        // Send the request to the Cloudflare Worker with retry logic
        $max_retries = 3;
        $retry_delay = 2; // seconds
        $last_error = null;
        
        for ($attempt = 1; $attempt <= $max_retries; $attempt++) {
            anar_log("Attempt {$attempt}/{$max_retries} to send data to Cloudflare Worker");
            
            $response = wp_remote_post($this->worker_url, $args);
            
            // Check for WP_Error (network issues, timeouts, etc.)
            if (is_wp_error($response)) {
                $error_message = $response->get_error_message();
                $error_code = $response->get_error_code();
                $last_error = "WP_Error [{$error_code}]: {$error_message}";
                
                anar_log("Attempt {$attempt} failed with WP_Error: {$last_error}", 'error');
                
                // If this is not the last attempt, wait before retrying
                if ($attempt < $max_retries) {
                    anar_log("Retrying in {$retry_delay} seconds...");
                    sleep($retry_delay);
                    $retry_delay *= 2; // Exponential backoff
                    continue;
                }
                
                return array(
                    'success' => false,
                    'message' => "Failed after {$max_retries} attempts. Last error: {$last_error}",
                    'error_code' => $error_code,
                    'attempts' => $attempt
                );
            }
            
            // Get response code and body
            $response_code = wp_remote_retrieve_response_code($response);
            $response_body = wp_remote_retrieve_body($response);
            $response_headers = wp_remote_retrieve_headers($response);
            
            // Log response details for debugging
            anar_log("Response code: {$response_code}, Body length: " . strlen($response_body));
            
            // Check if response is successful
            if ($response_code >= 200 && $response_code < 300) {
                anar_log("Data sent successfully to Cloudflare Worker on attempt {$attempt}");
                
                return array(
                    'success' => true,
                    'message' => 'Data sent successfully to Cloudflare Worker',
                    'response' => $response_body,
                    'response_code' => $response_code,
                    'attempts' => $attempt
                );
            }
            
            // Handle specific error codes
            $last_error = "HTTP {$response_code}";
            if ($response_code >= 500) {
                // Server errors - retry
                anar_log("Server error {$response_code} on attempt {$attempt}. Response: {$response_body}", 'error');
                if ($attempt < $max_retries) {
                    anar_log("Retrying in {$retry_delay} seconds...");
                    sleep($retry_delay);
                    $retry_delay *= 2;
                    continue;
                }
            } elseif ($response_code >= 400) {
                // Client errors - don't retry
                anar_log("Client error {$response_code} on attempt {$attempt}. Response: {$response_body}", 'error');
                return array(
                    'success' => false,
                    'message' => "Client error: HTTP {$response_code}",
                    'response' => $response_body,
                    'response_code' => $response_code,
                    'attempts' => $attempt
                );
            } else {
                // Other status codes - retry
                anar_log("Unexpected response code {$response_code} on attempt {$attempt}. Response: {$response_body}", 'error');
                if ($attempt < $max_retries) {
                    anar_log("Retrying in {$retry_delay} seconds...");
                    sleep($retry_delay);
                    $retry_delay *= 2;
                    continue;
                }
            }
        }
        
        // If we get here, all retries failed
        return array(
            'success' => false,
            'message' => "Failed after {$max_retries} attempts. Last error: {$last_error}",
            'attempts' => $max_retries
        );
    }


}

?>
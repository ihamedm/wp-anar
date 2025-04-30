<?php
namespace Anar\Core;
use Anar\OrderData;
use Anar\ProductData;
use Anar\SyncTools;

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
     * Sends collected site data to Cloudflare Worker
     *
     * @param array $additional_data Optional additional data to include
     * @return array Response with status and message
     */
    public function send($additional_data = []) {
        // Get the site URL
        $site_url = get_site_url();
        $product_data = new ProductData();
        $sync_tools = SyncTools::get_instance();
        $sync = \Anar\Sync::get_instance();

        // Collect basic site data
        $data = array(
            'site_url' => $site_url,
            'site_name' => get_bloginfo('name'),
            'wordpress_version' => get_bloginfo('version'),
            'php_version' => phpversion(),
            'last_updated' => current_time('mysql'),
            'anar_version' => ANAR_PLUGIN_VERSION,
            'anar_products' =>  $product_data->count_anar_products(),
            'not_synced' =>  $sync_tools->found_not_synced_products(1),
            'last_full_sync' =>  mysql2date('j F Y - H:i', $sync->getLastSyncTime(true)),
            'last_partial_sync' =>  mysql2date('j F Y - H:i', $sync->getLastSyncTime()),
            'anar_orders' => OrderData::count_anar_orders(),
            'anar_registered_orders' => OrderData::count_anar_orders_submited(),
        );

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
        awca_log("Sending data to Cloudflare Worker: {$this->worker_url}");

        // Send the request to the Cloudflare Worker
        $response = wp_remote_post($this->worker_url, $args);

        // Check for errors
        if (is_wp_error($response)) {
            $error_message = $response->get_error_message();
            awca_log("Failed to send data: {$error_message}", 'error');

            return array(
                'success' => false,
                'message' => $error_message
            );
        }

        // Get response code and body
        $response_code = wp_remote_retrieve_response_code($response);
        $response_body = wp_remote_retrieve_body($response);

        if ($response_code === 200) {
            awca_log("Data sent successfully to Cloudflare Worker");

            return array(
                'success' => true,
                'message' => 'Data sent successfully to Cloudflare Worker',
                'response' => $response_body
            );
        } else {
            awca_log("Failed with status code: {$response_code}", 'error');

            return array(
                'success' => false,
                'message' => "Failed with status code: {$response_code}",
                'response' => $response_body
            );
        }
    }


}

?>
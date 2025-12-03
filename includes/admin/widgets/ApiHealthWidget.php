<?php

namespace Anar\Admin\Widgets;

use Anar\ApiDataHandler;

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

/**
 * API Health Report Widget
 * Tests all API endpoints to verify connectivity
 */
class ApiHealthWidget extends AbstractReportWidget
{
    /**
     * Custom URLs to test for connectivity
     * Format: 'name' => 'url'
     * 
     * @var array
     */
    private static $custom_urls = [
        'GitHub API' => 'https://api.github.com',
    ];

    /**
     * Get custom URLs for connectivity testing
     * 
     * @return array Array of name => url pairs
     */
    public static function get_custom_urls()
    {
        /**
         * Filter custom URLs for connectivity testing
         * 
         * @param array $urls Array of name => url pairs
         * @return array Filtered array
         */
        return apply_filters('anar_api_health_custom_urls', self::$custom_urls);
    }

    protected function init()
    {
        $this->widget_id = 'anar-api-health-widget';
        $this->title = 'سلامت API';
        $this->description = 'بررسی اتصال به API انار';
        $this->icon = '<span class="dashicons dashicons-cloud"></span>';
        $this->ajax_action = 'anar_get_api_health';
        $this->button_text = 'تست اتصال API';
        $this->button_class = 'button-primary';

        // Register AJAX handler
        add_action('wp_ajax_' . $this->ajax_action, [$this, 'handle_ajax']);
    }

    protected function get_report_data()
    {
        $endpoints = ApiDataHandler::ENDPOINTS;
        $results = [
            'endpoints' => [],
            'custom_urls' => [],
            'total_tested' => 0,
            'total_success' => 0,
            'total_failed' => 0,
            'api_domain' => ApiDataHandler::getApiDomain(),
            'timestamp' => current_time('Y-m-d H:i:s')
        ];

        // Test each endpoint
        foreach ($endpoints as $endpoint_key => $endpoint_path) {
            $endpoint_result = $this->test_endpoint($endpoint_key);
            $results['endpoints'][$endpoint_key] = $endpoint_result;
            $results['total_tested']++;
            
            if ($endpoint_result['success']) {
                $results['total_success']++;
            } else {
                $results['total_failed']++;
            }
        }

        // Test custom URLs for connectivity
        $custom_urls = self::get_custom_urls();
        foreach ($custom_urls as $name => $url) {
            $url_result = $this->test_custom_url($name, $url);
            $results['custom_urls'][$name] = $url_result;
            $results['total_tested']++;
            
            if ($url_result['success']) {
                $results['total_success']++;
            } else {
                $results['total_failed']++;
            }
        }

        // Calculate overall health score (0-10)
        if ($results['total_tested'] > 0) {
            $results['health_score'] = round(($results['total_success'] / $results['total_tested']) * 10, 2);
        } else {
            $results['health_score'] = 0;
        }

        return $results;
    }

    /**
     * Test a single API endpoint
     *
     * @param string $endpoint_key Endpoint key (e.g., 'products', 'categories')
     * @return array Test results
     */
    private function test_endpoint($endpoint_key)
    {
        $start_time = microtime(true);
        
        try {
            // Build API URL with page=1 and limit=25
            $api_url = ApiDataHandler::getApiUrl($endpoint_key, [
                'page' => 1,
                'limit' => 25
            ]);

            // Make API call
            $response = ApiDataHandler::callAnarApi($api_url);
            
            $end_time = microtime(true);
            $duration = round(($end_time - $start_time) * 1000, 2); // Convert to milliseconds

            // Check response
            if (is_wp_error($response)) {
                return [
                    'success' => false,
                    'endpoint' => $endpoint_key,
                    'url' => $api_url,
                    'status_code' => 0,
                    'duration_ms' => $duration,
                    'error' => $response->get_error_message(),
                    'error_code' => $response->get_error_code()
                ];
            }

            $status_code = isset($response['response']['code']) ? $response['response']['code'] : 0;
            $response_message = isset($response['response']['message']) ? $response['response']['message'] : '';
            
            // Consider 200-299 as success
            $is_success = $status_code >= 200 && $status_code < 300;
            
            // Get response body size if available
            $body_size = 0;
            if (isset($response['body'])) {
                $body_size = strlen($response['body']);
            }

            return [
                'success' => $is_success,
                'endpoint' => $endpoint_key,
                'url' => $api_url,
                'status_code' => $status_code,
                'status_message' => $response_message,
                'duration_ms' => $duration,
                'body_size' => $body_size,
                'body_size_formatted' => size_format($body_size, 2)
            ];

        } catch (\Exception $e) {
            $end_time = microtime(true);
            $duration = round(($end_time - $start_time) * 1000, 2);
            
            return [
                'success' => false,
                'endpoint' => $endpoint_key,
                'url' => '',
                'status_code' => 0,
                'duration_ms' => $duration,
                'error' => $e->getMessage(),
                'error_code' => 'exception'
            ];
        }
    }

    /**
     * Test a custom URL for connectivity
     *
     * @param string $name Display name for the URL
     * @param string $url URL to test
     * @return array Test results
     */
    private function test_custom_url($name, $url)
    {
        $start_time = microtime(true);
        
        try {
            // Make HTTP request to test connectivity
            $response = wp_remote_get($url, [
                'timeout' => 10,
                'sslverify' => true,
                'redirection' => 5
            ]);
            
            $end_time = microtime(true);
            $duration = round(($end_time - $start_time) * 1000, 2); // Convert to milliseconds

            // Check response
            if (is_wp_error($response)) {
                return [
                    'success' => false,
                    'name' => $name,
                    'url' => $url,
                    'status_code' => 0,
                    'duration_ms' => $duration,
                    'error' => $response->get_error_message(),
                    'error_code' => $response->get_error_code()
                ];
            }

            $status_code = wp_remote_retrieve_response_code($response);
            $response_message = wp_remote_retrieve_response_message($response);
            
            // Consider 200-399 as success (including redirects)
            $is_success = $status_code >= 200 && $status_code < 400;
            
            // Get response body size if available
            $body = wp_remote_retrieve_body($response);
            $body_size = strlen($body);

            return [
                'success' => $is_success,
                'name' => $name,
                'url' => $url,
                'status_code' => $status_code,
                'status_message' => $response_message,
                'duration_ms' => $duration,
                'body_size' => $body_size,
                'body_size_formatted' => size_format($body_size, 2)
            ];

        } catch (\Exception $e) {
            $end_time = microtime(true);
            $duration = round(($end_time - $start_time) * 1000, 2);
            
            return [
                'success' => false,
                'name' => $name,
                'url' => $url,
                'status_code' => 0,
                'duration_ms' => $duration,
                'error' => $e->getMessage(),
                'error_code' => 'exception'
            ];
        }
    }
}


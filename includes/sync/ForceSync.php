<?php

namespace Anar\Sync;

use Anar\ProductData;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

/**
 * Class ForceSync
 *
 * REST API-based sync strategy that allows external systems (Anar server) to force sync products.
 * This strategy provides a secure REST endpoint that:
 * - Authenticates requests using Bearer token (same as Anar API token)
 * - Enforces rate limiting (1 request per configurable window)
 * - Limits the number of SKUs per request (default: 20)
 * - Processes products synchronously and returns detailed results
 *
 * Designed to be called from Anar's server infrastructure for on-demand product updates.
 *
 * @package Anar\Sync
 * @since 0.6.0
 */
class ForceSync extends Sync
{
    /**
     * Singleton instance
     *
     * @var ForceSync|null
     */
    private static $instance;

    /**
     * WordPress REST API namespace
     *
     * @var string
     */
    const REST_NAMESPACE = 'anar/v1';

    /**
     * REST API route path
     *
     * @var string
     */
    const REST_ROUTE = '/force-sync';

    /**
     * Option name for maximum SKUs per request
     *
     * @var string
     */
    const OPTION_MAX_PER_REQUEST = 'anar_force_sync_max_per_request';

    /**
     * Option name for rate limit window
     *
     * @var string
     */
    const OPTION_RATE_WINDOW = 'anar_force_sync_rate_window';

    /**
     * Default maximum SKUs per request
     *
     * @var int
     */
    const DEFAULT_MAX_PER_REQUEST = 20;

    /**
     * Default rate limit window in seconds
     *
     * @var int
     */
    const DEFAULT_RATE_WINDOW_SEC = 10;

    /**
     * Transient key for rate limiting
     *
     * @var string
     */
    const RL_TRANSIENT_KEY = 'anar_force_sync_rl';

    /**
     * Get singleton instance
     *
     * @return ForceSync
     */
    public static function get_instance()
    {
        if (!isset(self::$instance)) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * ForceSync constructor
     *
     * Initializes the class and registers REST API routes.
     */
    public function __construct()
    {
        parent::__construct();
        add_action('rest_api_init', [$this, 'register_routes']);
    }

    /**
     * Registers REST API routes for force sync endpoint
     *
     * Registers POST endpoint at /wp-json/anar/v1/force-sync
     *
     * @return void
     */
    public function register_routes()
    {
        register_rest_route(
            self::REST_NAMESPACE,
            self::REST_ROUTE,
            [
                'methods' => 'POST',
                'callback' => [$this, 'handle_force_sync'],
                'permission_callback' => '__return_true',
                'args' => [
                    'skus' => [
                        'required' => true,
                        'type' => 'array',
                    ],
                    'full_sync' => [
                        'required' => false,
                        'type' => 'boolean',
                        'default' => true,
                    ],
                ],
            ]
        );
    }

    /**
     * Handles the force sync REST API request
     *
     * Processes the request with the following steps:
     * 1. Validates Bearer token authentication
     * 2. Checks rate limiting (prevents too frequent requests)
     * 3. Validates and processes request body (SKUs array)
     * 4. Enforces maximum SKUs per request
     * 5. Syncs each product and aggregates results
     *
     * @param WP_REST_Request $request WordPress REST API request object
     * @return WP_REST_Response Response with sync results or error
     */
    public function handle_force_sync(WP_REST_Request $request)
    {
        // Step 1: Authenticate request using Bearer token
        // Token must match the _anar_token option (same token used for Anar API)
        $auth_header = $request->get_header('authorization');
        $token = $this->extract_bearer_token($auth_header);
        $expected = get_option('_anar_token');

        // Validate token using secure comparison
        if (empty($token) || empty($expected) || !hash_equals((string)$expected, (string)$token)) {
            return new WP_REST_Response([
                'success' => false,
                'message' => 'Unauthorized',
            ], 401);
        }

        // Step 2: Check rate limiting (prevents abuse)
        // Default: 1 request per 10 seconds (configurable via option or filter)
        $rate_window = (int) get_option(self::OPTION_RATE_WINDOW, self::DEFAULT_RATE_WINDOW_SEC);
        $rate_window = (int) apply_filters('anar_force_sync_rate_window', $rate_window);
        if (get_transient(self::RL_TRANSIENT_KEY)) {
            return new WP_REST_Response([
                'success' => false,
                'message' => 'Too Many Requests',
                'rate_window_sec' => $rate_window,
            ], 429);
        }
        // Set rate limit lock
        set_transient(self::RL_TRANSIENT_KEY, 1, $rate_window);

        // Step 3: Parse and validate request body
        $body = $request->get_json_params();
        $skus = isset($body['skus']) && is_array($body['skus']) ? array_values(array_filter(array_map('strval', $body['skus']))) : [];
        $full_sync = !isset($body['full_sync']) || $body['full_sync'];
        $deprecate_on_fault = !isset($body['deprecate']) || $body['deprecate'];

        // Validate SKUs array is not empty
        if (empty($skus)) {
            return new WP_REST_Response([
                'success' => false,
                'message' => 'Invalid body: skus[] is required',
            ], 400);
        }

        // Step 4: Enforce maximum SKUs per request (prevent memory issues)
        $max_per_request = (int) get_option(self::OPTION_MAX_PER_REQUEST, self::DEFAULT_MAX_PER_REQUEST);
        $max_per_request = (int) apply_filters('anar_force_sync_max_skus', $max_per_request);
        if (count($skus) > $max_per_request) {
            // Silently truncate to max (could also return error)
            $skus = array_slice($skus, 0, $max_per_request);
        }

        // Step 5: Process each SKU and sync product
        $results = [];
        $processed = 0;

        foreach ($skus as $sku) {
            // Find WooCommerce product by Anar SKU
            $wc_product_id = ProductData::get_simple_product_by_anar_sku($sku);

            if (is_wp_error($wc_product_id)) {
                // Product not found - log error and continue
                $results[] = [
                    'sku' => $sku,
                    'success' => false,
                    'status_code' => $wc_product_id->get_error_code(),
                    'message' => $wc_product_id->get_error_message(),
                ];
                continue;
            }

            // Sync product using force-sync strategy
            $sync_result = $this->syncProduct($wc_product_id, [
                'sync_strategy' => 'force-sync',
                'full_sync' => $full_sync,
                'deprecate_on_faults' => $deprecate_on_fault
            ]);

            // Aggregate results
            $results[] = [
                'sku' => $sku,
                'success' => (bool) ($sync_result['updated'] ?? false),
                'status_code' => $sync_result['status_code'] ?? 200,
                'message' => $sync_result['message'] ?? '',
                'data' => $sync_result,
            ];
            $processed++;
        }

        $rest_result = [
            'success' => true,
            'processed' => $processed,
            'results' => $results,
            'rate_window_sec' => $rate_window,
        ];

        $this->logger->log(print_r($rest_result, true), 'sync', 'info');

        return new WP_REST_Response([
            'success' => true,
            'processed' => $processed,
            'results' => $results,
            'rate_window_sec' => $rate_window,
        ], 200);
    }

    /**
     * Extracts Bearer token from Authorization header
     *
     * Parses "Authorization: Bearer <token>" header format.
     *
     * @param string|null $auth_header Authorization header value
     * @return string Extracted token or empty string if invalid
     */
    private function extract_bearer_token($auth_header)
    {
        if (!is_string($auth_header)) {
            return '';
        }
        // Check for "Bearer " prefix (case-insensitive)
        if (stripos($auth_header, 'Bearer ') === 0) {
            return trim(substr($auth_header, 7));
        }
        return '';
    }
}



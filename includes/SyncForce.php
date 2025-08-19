<?php

namespace Anar;

use Anar\Core\Logger;
use Anar\Wizard\ProductManager;

/**
 * Class SyncForce
 *
 * Handles the force synchronization of WooCommerce products with the Anar360 API.
 *
 * Responsibilities:
 * - Manages the process of force syncing products, including batch processing and job tracking.
 * - Integrates with WordPress AJAX and cron systems to allow both manual and scheduled sync operations.
 * - Tracks sync jobs using a JobManager, supporting job control (pause, resume, cancel) and progress reporting.
 * - Logs sync activity and errors for monitoring and debugging.
 * - Ensures that only one force sync job runs at a time, and can recover from stalled or incomplete jobs.
 *
 * Main Features:
 * - AJAX endpoints for starting sync, controlling jobs, and querying job status/history.
 * - Batch processing of products to avoid timeouts and improve reliability.
 * - Integration with Anar360 API to fetch and update product data.
 * - Handles both simple and variable products, and marks deprecated products as needed.
 * - Provides admin feedback and error handling throughout the sync process.
 *
 * @package Anar
 */
class SyncForce {
    private static $instance;
    private $logger;
    private $max_execution_time = 240;
    private $batch_size;
    private $cron_hook = 'anar_force_sync_products';
    private $job_manager;

    public static function get_instance() {
        if (!isset(self::$instance)) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function __construct() {
        $this->logger = new Logger();
        $this->job_manager = JobManager::get_instance();
        $this->batch_size = get_option('anar_sync_outdated_batch_size', 30);

        // Register AJAX handlers
        add_action('wp_ajax_anar_force_sync_products', array($this, 'force_sync_products_ajax'));
        add_action('wp_ajax_anar_control_sync_job', array($this, 'control_sync_job_ajax'));
        add_action('wp_ajax_anar_get_job_status', array($this, 'get_job_status_ajax'));
        add_action('wp_ajax_anar_get_recent_jobs', array($this, 'get_recent_jobs_ajax'));
        
        // Register cron hooks
        add_action($this->cron_hook, array($this, 'process_force_sync_cronjob'));
    }

    private function log($message, $level = 'info') {
        $this->logger->log($message, 'syncOutdated', $level);
    }

    /**
     * Process a single product update
     */
    private function process_product($product_data) {
        try {
            $apiUrl = "https://api.anar360.com/wp/products/{$product_data['anar_sku']}";
            $api_response = ApiDataHandler::callAnarApi($apiUrl);

            if (is_wp_error($api_response)) {
                $this->log("API Error for SKU {$product_data['anar_sku']}: " . $api_response->get_error_message(), 'error');
                return false;
            }

            $response_code = wp_remote_retrieve_response_code($api_response);
            $response_body = wp_remote_retrieve_body($api_response);
            $data = json_decode($response_body);

            if ($response_code === 200 && $data) {
                $sync = Sync::get_instance();
                if (isset($data->attributes) && !empty($data->attributes)) {
                    $sync->processVariableProduct($data, $product_data['ID'],true);
                } else {
                    $sync->processSimpleProduct($data, $product_data['ID'], true);
                }

                update_post_meta($product_data['ID'], '_anar_last_sync_time', current_time('mysql'));
                
                // Get job ID
                $job_id = get_transient('anar_force_sync_job_id');
                $job = $job_id ? $this->job_manager->get_job_by_id($job_id) : null;

                $this->log('Updated, #' . $product_data['ID'] . '  SKU: ' . $data->id . ' ['.$job['source'].':' . $job_id . ']', 'info');

                return true;
            } elseif ($response_code === 404) {
                ProductManager::set_product_as_deprecated($product_data['ID'], 'sync_force', 'sync', true);
                return true;
            }

            return false;
        } catch (\Exception $e) {
            $this->log("Error processing product {$product_data['anar_sku']}: " . $e->getMessage(), 'error');
            return false;
        }
    }

    /**
     * Check if there's an active force sync job
     */
    private function isSyncInProgress() {
        $active_jobs = $this->job_manager->get_active_jobs('sync_force');
        return !empty($active_jobs);
    }

    /**
     * Get the current active force sync job
     */
    private function getActiveJob() {
        $active_jobs = $this->job_manager->get_active_jobs('sync_force');
        return !empty($active_jobs) ? $active_jobs[0] : null;
    }

    /**
     * Get products that need force sync
     */
    private function get_force_sync_products() {
        global $wpdb;

        // Get the current job ID from transient
        $current_job_id = get_transient('anar_force_sync_job_id');
        if (!$current_job_id) {
            return [];
        }

        $args = array(
            'post_type' => 'product',
            'post_status' => ['publish', 'draft'],
            'posts_per_page' => $this->batch_size,
            'meta_query' => array(
                'relation' => 'AND',
                array(
                    'key' => '_anar_sku',
                    'compare' => 'EXISTS'
                ),
                array(
                    'relation' => 'OR',
                    // Products that don't have a job ID
                    array(
                        'key' => '_anar_force_sync_job_id',
                        'compare' => 'NOT EXISTS'
                    ),
                    // Products that have a job ID but it's not the current job
                    array(
                        'key' => '_anar_force_sync_job_id',
                        'value' => $current_job_id,
                        'compare' => '!='
                    )
                )
            ),
            'fields' => 'ids'
        );

        $query = new \WP_Query($args);
        
        $products = [];
        foreach ($query->posts as $product_id) {
            $anar_sku = get_post_meta($product_id, '_anar_sku', true);
            if ($anar_sku) {
                $products[] = [
                    'ID' => $product_id,
                    'anar_sku' => $anar_sku
                ];
            }
        }

        return $products;
    }

    /**
     * AJAX handler for force sync
     */
    public function force_sync_products_ajax() {
        if (!current_user_can('manage_woocommerce')) {
            wp_send_json_error('شما این مجوز را ندارید!');
            wp_die();
        }

        try {
            if ($this->isSyncInProgress()) {
                $active_job = $this->getActiveJob();
                wp_send_json_error([
                    'message' => 'Another force sync process is already running',
                    'active_job' => $active_job
                ]);
                return;
            }

            // Get total count of products that need sync
            $args = array(
                'post_type' => 'product',
                'post_status' => ['publish', 'draft'],
                'posts_per_page' => -1,
                'meta_query' => array(
                    array(
                        'key' => '_anar_sku',
                        'compare' => 'EXISTS'
                    )
                ),
                'fields' => 'ids'
            );

            $query = new \WP_Query($args);
            $total_products = $query->found_posts;

            // Create a new job for tracking
            $job = $this->job_manager->create_job('sync_force', $total_products);
            
            if (!$job) {
                wp_send_json_error('Failed to create sync job');
                return;
            }

            // Set job status to in progress
            $this->job_manager->update_job_status($job['job_id'], JobManager::STATUS_IN_PROGRESS);

            // Store the job ID in a transient to ensure it's available for the cron job
            set_transient('anar_force_sync_job_id', $job['job_id'], 3600); // 1 hour

            // Start the sync process immediately
            $this->process_force_sync_cronjob();

            wp_send_json_success([
                'message' => 'Force sync process started successfully',
                'job_id' => $job['job_id']
            ]);

        } catch (\Exception $e) {
            if (isset($job)) {
                $this->job_manager->complete_job($job['job_id'], 'failed', $e->getMessage());
            }
            wp_send_json_error([
                'message' => $e->getMessage(),
                'job_id' => $job['job_id'] ?? null
            ]);
        }
    }

    /**
     * Process force sync job
     */
    public function process_force_sync_cronjob() {
        // First check for any stalled jobs
        $stalled_jobs = $this->job_manager->get_stale_jobs(300); // 5 minutes
        foreach ($stalled_jobs as $stalled_job) {
            if ($stalled_job['source'] === 'sync_force' && $stalled_job['status'] === JobManager::STATUS_IN_PROGRESS) {
                $this->log("Found stalled force sync job: {$stalled_job['job_id']}. Resuming.", 'warning');
                // Update the transient with the stalled job ID
                set_transient('anar_force_sync_job_id', $stalled_job['job_id'], 3600); // 1 hour
                break;
            }
        }

        // Get the job ID from transient
        $job_id = get_transient('anar_force_sync_job_id');
        if (!$job_id) {
            // Check for any incomplete force sync jobs
            $incomplete_jobs = $this->job_manager->get_jobs([
                'source' => 'sync_force',
                'status' => JobManager::STATUS_IN_PROGRESS,
                'limit' => 1,
                'orderby' => 'start_time',
                'order' => 'DESC'
            ]);

            if (!empty($incomplete_jobs)) {
                $job_id = $incomplete_jobs[0]['job_id'];
                $this->log("Found incomplete force sync job: {$job_id}. Resuming.", 'info');
                set_transient('anar_force_sync_job_id', $job_id, 3600); // 1 hour
            } else {
                $this->log("No active force sync job found", 'info');
                return;
            }
        }

        // Get the job
        $job = $this->job_manager->get_job_by_id($job_id);
        if (!$job) {
            $this->log("No job found with ID: {$job_id}", 'error');
            delete_transient('anar_force_sync_job_id');
            return;
        }

        // Check if job is already completed or failed
        if (in_array($job['status'], ['completed', 'failed', 'cancelled'])) {
            $this->log("Job {$job_id} is already {$job['status']}", 'info');
            delete_transient('anar_force_sync_job_id');
            return;
        }

        $start_time = time();
        $processed = 0;
        $failed = 0;

        try {
            $products = $this->get_force_sync_products();
            
            // If no products to process, check if we're done
            if (empty($products)) {
                // Check if all products have been processed
                $total_processed = $job['processed_products'] + $job['failed_products'];
                if ($total_processed >= $job['total_products']) {
                    $this->job_manager->complete_job($job_id, 'completed');
                    $this->log("Force sync completed. Total processed: {$total_processed}", 'info');
                    delete_transient('anar_force_sync_job_id');
                } else {
                    // Schedule next batch immediately
                    wp_schedule_single_event(time(), $this->cron_hook);
                    $this->log("No more products to process in this batch. Scheduling next batch.", 'info');
                }
                return;
            }
            
            // Set job status to in progress if not already
            if ($job['status'] !== JobManager::STATUS_IN_PROGRESS) {
                $this->job_manager->update_job_status($job_id, JobManager::STATUS_IN_PROGRESS);
            }

            foreach ($products as $product) {
                // Check if job is still active
                if (!$this->job_manager->is_job_active($job_id)) {
                    $this->log("Job {$job_id} is no longer active. Stopping sync process.");
                    break;
                }

                // Check execution time limit (4 minutes to ensure we have time to schedule next batch)
                if (time() - $start_time > ($this->max_execution_time - 60)) {
                    $this->log("Approaching execution time limit. Processed: {$processed}, Failed: {$failed}");
                    // Schedule next batch immediately
                    wp_schedule_single_event(time(), $this->cron_hook);
                    break;
                }

                // Update job heartbeat
                $this->job_manager->update_job_heartbeat($job_id);

                // Mark product with current job ID before processing
                update_post_meta($product['ID'], '_anar_force_sync_job_id', $job_id);

                if ($this->process_product($product)) {
                    $processed++;
                } else {
                    $failed++;
                }

                // Update job progress with cumulative totals
                $total_processed = $job['processed_products'] + $processed;
                $total_failed = $job['failed_products'] + $failed;
                $this->job_manager->update_job_progress($job_id, $total_processed + $total_failed, $total_processed, 0, $total_failed);

                // Check if we've processed all products
                if ($total_processed + $total_failed >= $job['total_products']) {
                    $this->job_manager->complete_job($job_id, 'completed');
                    $this->log("Force sync completed. Total processed: " . ($total_processed + $total_failed), 'info');
                    delete_transient('anar_force_sync_job_id');
                    return;
                }
            }

            // Schedule next batch immediately if we haven't processed all products
            if (!wp_next_scheduled($this->cron_hook)) {
                wp_schedule_single_event(time(), $this->cron_hook);
                $this->log("Scheduling next batch for job {$job_id}", 'info');
            }
            
        } catch (\Exception $e) {
            $this->job_manager->complete_job($job_id, 'failed', $e->getMessage());
            $this->log("Error during force sync process: " . $e->getMessage(), 'error');
            delete_transient('anar_force_sync_job_id');
        }
    }

    /**
     * AJAX handler for job control
     */
    public function control_sync_job_ajax() {
        if (!current_user_can('manage_woocommerce')) {
            wp_send_json_error('شما این مجوز را ندارید!');
            wp_die();
        }

        try {
            $job_id = isset($_POST['job_id']) ? sanitize_text_field($_POST['job_id']) : '';
            $action = isset($_POST['action_type']) ? sanitize_text_field($_POST['action_type']) : '';
            
            if (empty($job_id) || empty($action)) {
                wp_send_json_error('Missing required parameters');
                return;
            }

            $job = $this->job_manager->get_job_by_id($job_id);
            if (!$job) {
                wp_send_json_error('Job not found');
                return;
            }

            $success = false;
            $message = '';
            $new_status = '';

            switch ($action) {
                case 'pause':
                    $success = $this->job_manager->pause_job($job_id);
                    $message = $success ? 'Job paused successfully' : 'Failed to pause job';
                    $new_status = JobManager::STATUS_PAUSED;
                    break;
                case 'resume':
                    $success = $this->job_manager->resume_job($job_id);
                    $message = $success ? 'Job resumed successfully' : 'Failed to resume job';
                    $new_status = JobManager::STATUS_IN_PROGRESS;
                    break;
                case 'cancel':
                    $success = $this->job_manager->cancel_job($job_id);
                    $message = $success ? 'بروزرسانی لغو شد' : 'مشکلی در لغو بروزرسانی پیش آمد';
                    $new_status = JobManager::STATUS_CANCELLED;
                    break;
                case 'status':
                    $success = true;
                    $message = 'Job status retrieved successfully';
                    $new_status = $job['status'];
                    break;
                default:
                    wp_send_json_error('Invalid action type');
                    return;
            }

            if ($success) {
                wp_send_json_success([
                    'message' => $message,
                    'job_id' => $job_id,
                    'status' => $new_status,
                    'job_data' => $this->job_manager->get_job_by_id($job_id)
                ]);
            } else {
                wp_send_json_error([
                    'message' => $message,
                    'job_id' => $job_id,
                    'current_status' => $job['status']
                ]);
            }

        } catch (\Exception $e) {
            wp_send_json_error([
                'message' => $e->getMessage(),
                'job_id' => $job_id ?? null
            ]);
        }
    }

    /**
     * AJAX handler for getting job status
     */
    public function get_job_status_ajax() {
        if (!current_user_can('manage_woocommerce')) {
            wp_send_json_error('شما این مجوز را ندارید!');
            wp_die();
        }

        try {
            $job_id = isset($_POST['job_id']) ? sanitize_text_field($_POST['job_id']) : '';
            
            if (empty($job_id)) {
                wp_send_json_error('Missing job ID');
                return;
            }

            $job = $this->job_manager->get_job_by_id($job_id);
            if (!$job) {
                wp_send_json_error('Job not found');
                return;
            }

            $response = [
                'job_id' => $job_id,
                'status' => $job['status'],
                'progress' => [
                    'processed' => $job['processed_products'] ?? 0,
                    'failed' => $job['failed_products'] ?? 0,
                    'total' => $job['total_products'] ?? 0
                ],
                'timing' => [
                    'start_time' => $job['start_time'],
                    'last_heartbeat' => $job['last_heartbeat'],
                    'end_time' => $job['end_time'] ?? null
                ]
            ];

            // If job is completed or failed, include additional info
            if (in_array($job['status'], ['completed', 'failed'])) {
                $response['completed'] = true;
                $response['error_log'] = $job['error_log'] ?? null;
            }

            wp_send_json_success($response);

        } catch (\Exception $e) {
            wp_send_json_error([
                'message' => $e->getMessage(),
                'job_id' => $job_id ?? null
            ]);
        }
    }

    /**
     * AJAX handler for getting recent jobs
     */
    public function get_recent_jobs_ajax() {
        if (!current_user_can('manage_woocommerce')) {
            wp_send_json_error('شما این مجوز را ندارید!');
            wp_die();
        }

        try {
            $jobs = $this->job_manager->get_jobs([
                'limit' => 10,
                'orderby' => 'start_time',
                'order' => 'DESC'
            ]);

            wp_send_json_success([
                'jobs' => $jobs
            ]);

        } catch (\Exception $e) {
            wp_send_json_error([
                'message' => $e->getMessage()
            ]);
        }
    }
} 
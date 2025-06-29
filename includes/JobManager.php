<?php
namespace Anar;

use Anar\Core\Logger;

class JobManager {
    private static $instance = null;
    private $logger;
    private $table_name;

    // Define job statuses
    const STATUS_PENDING = 'pending';
    const STATUS_IN_PROGRESS = 'in_progress';
    const STATUS_COMPLETED = 'completed';
    const STATUS_FAILED = 'failed';
    const STATUS_PAUSED = 'paused';
    const STATUS_CANCELLED = 'cancelled';

    private function __construct() {
        global $wpdb;
        $this->table_name = $wpdb->prefix . 'anar_jobs';
        $this->logger = new Logger();
        
        // Initialize cleanup cron job
        add_action('init', array($this, 'init_cleanup_cron'));
    }

    public static function get_instance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Create a new import job
     * 
     * @param string $source Source of the import (e.g., dropshipper name)
     * @param int $total_products Total number of products to import
     * @return array|false Job data on success, false on failure
     */
    public function create_job($source, $total_products) {
        global $wpdb;

        $job_id = uniqid('anar_job_');
        $data = array(
            'job_id' => $job_id,
            'status' => 'pending',
            'source' => $source,
            'total_products' => $total_products,
            'start_time' => current_time('mysql'),
            'last_heartbeat' => current_time('mysql')
        );

        $result = $wpdb->insert($this->table_name, $data);

        if ($result === false) {
            $this->logger->log("Failed to create job: " . $wpdb->last_error, 'error');
            return false;
        }

        $this->logger->log("Created new import job: {$job_id} for source: {$source}", 'info');
        return $this->get_job_by_id($job_id);
    }

    /**
     * Get a job by its ID
     * 
     * @param string $job_id The job ID to retrieve
     * @return array|false Job data on success, false on failure
     */
    public function get_job_by_id($job_id) {
        global $wpdb;

        $job = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM {$this->table_name} WHERE job_id = %s",
                $job_id
            ),
            ARRAY_A
        );

        return $job ?: false;
    }

    /**
     * Update job status and progress
     * 
     * @param string $job_id The job ID to update
     * @param array $data Array of fields to update
     * @return bool True on success, false on failure
     */
    public function update_job($job_id, $data) {
        global $wpdb;

        // Always update last_heartbeat
        $data['last_heartbeat'] = current_time('mysql');

        $result = $wpdb->update(
            $this->table_name,
            $data,
            array('job_id' => $job_id)
        );

        if ($result === false) {
            $this->logger->log("Failed to update job {$job_id}: " . $wpdb->last_error, 'error');
            return false;
        }

        return true;
    }

    /**
     * Update job progress
     * 
     * @param string $job_id The job ID to update
     * @param int $processed Number of processed products
     * @param int $created Number of created products
     * @param int $existing Number of existing products
     * @param int $failed Number of failed products
     * @return bool True on success, false on failure
     */
    public function update_job_progress($job_id, $processed, $created, $existing, $failed) {
        return $this->update_job($job_id, array(
            'processed_products' => $processed,
            'created_products' => $created,
            'existing_products' => $existing,
            'failed_products' => $failed
        ));
    }

    /**
     * Complete a job
     * 
     * @param string $job_id The job ID to complete
     * @param string $status Final status (completed or failed)
     * @param string|null $error_log Optional error message
     * @return bool True on success, false on failure
     */
    public function complete_job($job_id, $status = 'completed', $error_log = null) {
        $data = array(
            'status' => $status,
            'end_time' => current_time('mysql')
        );

        if ($error_log) {
            $data['error_log'] = $error_log;
        }

        return $this->update_job($job_id, $data);
    }

    /**
     * Get all jobs with optional filters
     * 
     * @param array $args Query arguments
     * @return array Array of jobs
     */
    public function get_jobs($args = array()) {
        global $wpdb;

        $defaults = array(
            'status' => null,
            'source' => null,
            'orderby' => 'created_at',
            'order' => 'DESC',
            'limit' => 20,
            'offset' => 0
        );

        $args = wp_parse_args($args, $defaults);
        $where = array();
        $values = array();

        if ($args['status']) {
            if (is_array($args['status'])) {
                $placeholders = array_fill(0, count($args['status']), '%s');
                $where[] = 'status IN (' . implode(', ', $placeholders) . ')';
                $values = array_merge($values, $args['status']);
            } else {
                $where[] = 'status = %s';
                $values[] = $args['status'];
            }
        }

        if ($args['source']) {
            if (is_array($args['source'])) {
                $placeholders = array_fill(0, count($args['source']), '%s');
                $where[] = 'source IN (' . implode(', ', $placeholders) . ')';
                $values = array_merge($values, $args['source']);
            } else {
                $where[] = 'source = %s';
                $values[] = $args['source'];
            }
        }

        $where_clause = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';
        $order_clause = "ORDER BY {$args['orderby']} {$args['order']}";
        $limit_clause = $wpdb->prepare("LIMIT %d OFFSET %d", $args['limit'], $args['offset']);

        $query = "SELECT * FROM {$this->table_name} {$where_clause} {$order_clause} {$limit_clause}";

        if (!empty($values)) {
            $query = $wpdb->prepare($query, $values);
        }

        return $wpdb->get_results($query, ARRAY_A);
    }

    /**
     * Delete a job
     * 
     * @param string $job_id The job ID to delete
     * @return bool True on success, false on failure
     */
    public function delete_job($job_id) {
        global $wpdb;

        $result = $wpdb->delete(
            $this->table_name,
            array('job_id' => $job_id)
        );

        if ($result === false) {
            $this->logger->log("Failed to delete job {$job_id}: " . $wpdb->last_error, 'error');
            return false;
        }

        $this->logger->log("Deleted job {$job_id}", 'info');
        return true;
    }

    /**
     * Get job statistics
     * 
     * @return array Job statistics
     */
    public function get_job_stats() {
        global $wpdb;

        $stats = $wpdb->get_row("
            SELECT 
                COUNT(*) as total_jobs,
                SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed_jobs,
                SUM(CASE WHEN status = 'failed' THEN 1 ELSE 0 END) as failed_jobs,
                SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending_jobs,
                SUM(CASE WHEN status = 'in_progress' THEN 1 ELSE 0 END) as in_progress_jobs,
                SUM(total_products) as total_products,
                SUM(created_products) as total_created,
                SUM(existing_products) as total_existing,
                SUM(failed_products) as total_failed
            FROM {$this->table_name}
        ", ARRAY_A);

        return $stats ?: array(
            'total_jobs' => 0,
            'completed_jobs' => 0,
            'failed_jobs' => 0,
            'pending_jobs' => 0,
            'in_progress_jobs' => 0,
            'total_products' => 0,
            'total_created' => 0,
            'total_existing' => 0,
            'total_failed' => 0
        );
    }

    /**
     * Update job status
     * 
     * @param string $job_id The job ID to update
     * @param string $status New status
     * @param string|null $error_log Optional error message
     * @return bool True on success, false on failure
     */
    public function update_job_status($job_id, $status, $error_log = null) {
        $data = array(
            'status' => $status,
            'last_heartbeat' => current_time('mysql')
        );

        if ($error_log) {
            $data['error_log'] = $error_log;
        }

        return $this->update_job($job_id, $data);
    }

    /**
     * Pause a job
     * 
     * @param string $job_id The job ID to pause
     * @return bool True on success, false on failure
     */
    public function pause_job($job_id) {
        $job = $this->get_job_by_id($job_id);
        if (!$job) {
            return false;
        }

        // Only allow pausing jobs that are in progress
        if ($job['status'] !== self::STATUS_IN_PROGRESS) {
            $this->logger->log("Cannot pause job {$job_id}: Current status is {$job['status']}", 'warning');
            return false;
        }

        return $this->update_job_status($job_id, self::STATUS_PAUSED);
    }

    /**
     * Resume a paused job
     * 
     * @param string $job_id The job ID to resume
     * @return bool True on success, false on failure
     */
    public function resume_job($job_id) {
        $job = $this->get_job_by_id($job_id);
        if (!$job) {
            return false;
        }

        // Only allow resuming paused jobs
        if ($job['status'] !== self::STATUS_PAUSED) {
            $this->logger->log("Cannot resume job {$job_id}: Current status is {$job['status']}", 'warning');
            return false;
        }

        return $this->update_job_status($job_id, self::STATUS_IN_PROGRESS);
    }

    /**
     * Cancel a job
     * 
     * @param string $job_id The job ID to cancel
     * @return bool True on success, false on failure
     */
    public function cancel_job($job_id) {
        $job = $this->get_job_by_id($job_id);
        if (!$job) {
            return false;
        }

        // Only allow cancelling jobs that are pending, in progress, or paused
        if (!in_array($job['status'], [self::STATUS_PENDING, self::STATUS_IN_PROGRESS, self::STATUS_PAUSED])) {
            $this->logger->log("Cannot cancel job {$job_id}: Current status is {$job['status']}", 'warning');
            return false;
        }

        return $this->update_job_status($job_id, self::STATUS_CANCELLED, 'Job cancelled by user');
    }

    /**
     * Check if a job is active (in progress or paused)
     * 
     * @param string $job_id The job ID to check
     * @return bool True if job is active, false otherwise
     */
    public function is_job_active($job_id) {
        $job = $this->get_job_by_id($job_id);
        if (!$job) {
            return false;
        }

        return in_array($job['status'], [self::STATUS_IN_PROGRESS, self::STATUS_PAUSED]);
    }

    /**
     * Get active jobs
     * 
     * @return array Array of active jobs
     */
    public function get_active_jobs($source = 'import') {
        return $this->get_jobs([
            'source' => $source,
            'status' => [self::STATUS_IN_PROGRESS, self::STATUS_PAUSED]
        ]);
    }

    /**
     * Update job heartbeat
     * 
     * @param string $job_id The job ID to update
     * @return bool True on success, false on failure
     */
    public function update_job_heartbeat($job_id) {
        return $this->update_job($job_id, [
            'last_heartbeat' => current_time('mysql')
        ]);
    }

    /**
     * Check for stale jobs (jobs that haven't updated their heartbeat)
     * 
     * @param int $timeout_seconds Number of seconds to consider a job stale
     * @return array Array of stale jobs
     */
    public function get_stale_jobs($timeout_seconds = 300) {
        global $wpdb;

        $timeout = date('Y-m-d H:i:s', strtotime("-{$timeout_seconds} seconds"));
        
        $query = $wpdb->prepare(
            "SELECT * FROM {$this->table_name} 
            WHERE status IN (%s, %s) 
            AND last_heartbeat < %s",
            self::STATUS_IN_PROGRESS,
            self::STATUS_PAUSED,
            $timeout
        );

        return $wpdb->get_results($query, ARRAY_A);
    }

    /**
     * Initialize the cleanup cron job
     */
    public function init_cleanup_cron() {
        // Register the cleanup cron hook
        add_action('anar_cleanup_jobs', array($this, 'cleanup_jobs'));

        // Schedule the cleanup job if it's not already scheduled
        if (!wp_next_scheduled('anar_cleanup_jobs')) {
            wp_schedule_event(time(), 'daily', 'anar_cleanup_jobs');
        }
    }

    /**
     * Clean up scheduled events
     */
    public function cleanup_cron() {
        wp_clear_scheduled_hook('anar_cleanup_jobs');
    }

    /**
     * Clean up old job records, keeping only the last 100 records
     * 
     * @return int Number of records deleted
     */
    public function cleanup_jobs() {
        global $wpdb;

        // Get the ID of the 100th most recent record
        $threshold_id = $wpdb->get_var("
            SELECT id FROM {$this->table_name}
            ORDER BY created_at DESC
            LIMIT 1 OFFSET 99
        ");

        if (!$threshold_id) {
            return 0;
        }

        // Delete all records older than the threshold
        $result = $wpdb->query($wpdb->prepare("
            DELETE FROM {$this->table_name}
            WHERE id < %d
        ", $threshold_id));

        if ($result === false) {
            $this->logger->log("Failed to cleanup jobs: " . $wpdb->last_error, 'error');
            return 0;
        }

        $this->logger->log("Cleaned up {$result} old job records", 'info');
        return $result;
    }
} 
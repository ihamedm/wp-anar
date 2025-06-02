<?php
namespace Anar;

use Anar\Core\Logger;

class JobManager {
    private static $instance = null;
    private $logger;
    private $table_name;

    private function __construct() {
        global $wpdb;
        $this->table_name = $wpdb->prefix . 'anar_jobs';
        $this->logger = new Logger();
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
            $where[] = 'status = %s';
            $values[] = $args['status'];
        }

        if ($args['source']) {
            $where[] = 'source = %s';
            $values[] = $args['source'];
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
} 
<?php
namespace Anar\Init;

defined( 'ABSPATH' ) || exit;

class StatusTools{

    private static $instance;

    private $batch_size;
    private $outdated_threshold = '1 day';

    public static function get_instance(){
        if( null === self::$instance ){
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function __construct(){
        $this->batch_size = get_option('anar_sync_outdated_batch_size', 30);

        // Ensure database indexes are created for optimal performance
        //self::ensure_indexes_created();

        // Register AJAX handlers
        add_action('wp_ajax_anar_create_indexes', array($this, 'create_indexes_ajax'));
        add_action('wp_ajax_anar_clear_sync_times', array($this, 'clear_sync_times_ajax'));
        add_action('wp_ajax_anar_test_query_performance', array($this, 'test_query_performance_ajax'));
        add_action('wp_ajax_anar_check_index_status', array($this, 'check_index_status_ajax'));
        add_action('wp_ajax_anar_manual_sync_outdated', array($this, 'manual_sync_outdated_ajax'));
    }

    /**
     * Ensure database indexes are created for optimal performance
     */
    private function ensure_indexes_created() {
        $indexes_created = get_option('anar_indexes_created', false);

        if (!$indexes_created) {
            self::create_sync_indexes();
            awca_log('Database indexes created for sync performance', 'info');
        }
    }

    /**
     * AJAX handler for creating sync indexes
     */
    public function create_indexes_ajax() {
        if (!current_user_can('manage_woocommerce')) {
            wp_send_json_error('شما این مجوز را ندارید!');
            wp_die();
        }

        try {
            // Force recreate indexes to ensure they are properly created
            self::force_recreate_indexes();

            awca_log("Sync indexes force recreated successfully", 'info');

            wp_send_json_success([
                'message' => 'ایندکس‌های پایگاه داده با موفقیت ایجاد شدند',
                'indexes_created' => true
            ]);

        } catch (\Exception $e) {
            wp_send_json_error([
                'message' => 'خطا در ایجاد ایندکس‌ها: ' . $e->getMessage()
            ]);
        }
    }

    /**
     * AJAX handler for clearing sync times
     * Updates _anar_last_sync_time to old date instead of deleting for better performance
     */
    public function clear_sync_times_ajax() {
        if (!current_user_can('manage_woocommerce')) {
            wp_send_json_error('شما این مجوز را ندارید!');
            wp_die();
        }

        try {
            global $wpdb;

            // Get total count of products with _anar_sku
            $total_products = $wpdb->get_var("
                SELECT COUNT(DISTINCT post_id) 
                FROM {$wpdb->postmeta} 
                WHERE meta_key = '_anar_sku'
            ");

            if ($total_products === null) {
                wp_send_json_error('خطا در دریافت تعداد محصولات');
                return;
            }

            // Set a very old date (1 year ago) to mark all products as needing sync
            // This is better than deleting because:
            // 1. Maintains index efficiency
            // 2. Keeps data integrity
            // 3. Faster than delete operations
            $old_date = date('Y-m-d H:i:s', strtotime('-1 year'));

            // First, update existing _anar_last_sync_time records
            $updated = $wpdb->update(
                $wpdb->postmeta,
                array('meta_value' => $old_date),
                array('meta_key' => '_anar_last_sync_time')
            );

            if ($updated === false) {
                wp_send_json_error('خطا در بروزرسانی زمان‌های بروزرسانی');
                return;
            }

            // Then, insert _anar_last_sync_time for parent products that don't have it yet
            // Only add to parent products, not variants
            $inserted = $wpdb->query($wpdb->prepare("
                INSERT IGNORE INTO {$wpdb->postmeta} (post_id, meta_key, meta_value)
                SELECT DISTINCT pm.post_id, '_anar_last_sync_time', %s
                FROM {$wpdb->postmeta} pm
                INNER JOIN {$wpdb->posts} p ON pm.post_id = p.ID
                LEFT JOIN {$wpdb->postmeta} sync_time ON pm.post_id = sync_time.post_id AND sync_time.meta_key = '_anar_last_sync_time'
                WHERE pm.meta_key = '_anar_sku'
                AND p.post_type = 'product'
                AND p.post_parent = 0
                AND sync_time.post_id IS NULL
            ", $old_date));

            $total_processed = $updated + ($inserted ? $inserted : 0);

            awca_log("Reset sync times for {$updated} existing products and added for {$inserted} new products", 'info');

            wp_send_json_success([
                'message' => 'زمان‌های بروزرسانی با موفقیت به تاریخ قدیمی تنظیم شدند',
                'total_products' => $total_products,
                'updated_count' => $updated,
                'inserted_count' => $inserted,
                'total_processed' => $total_processed,
                'reset_date' => $old_date
            ]);

        } catch (\Exception $e) {
            wp_send_json_error([
                'message' => $e->getMessage()
            ]);
        }
    }

    /**
     * AJAX handler for testing query performance
     */
    public function test_query_performance_ajax() {
        if (!current_user_can('manage_woocommerce')) {
            wp_send_json_error('شما این مجوز را ندارید!');
            wp_die();
        }

        try {
            $performance_data = $this->test_query_performance();
            wp_send_json_success($performance_data);
        } catch (\Exception $e) {
            wp_send_json_error([
                'message' => $e->getMessage()
            ]);
        }
    }

    /**
     * AJAX handler for checking index status
     */
    public function check_index_status_ajax() {
        if (!current_user_can('manage_woocommerce')) {
            wp_send_json_error('شما این مجوز را ندارید!');
            wp_die();
        }

        try {
            $index_status = self::check_indexes_status();

            if ($index_status === false) {
                wp_send_json_error([
                    'message' => 'خطا در بررسی وضعیت ایندکس‌ها'
                ]);
                return;
            }

            wp_send_json_success($index_status);

        } catch (\Exception $e) {
            wp_send_json_error([
                'message' => 'خطا در بررسی وضعیت ایندکس‌ها: ' . $e->getMessage()
            ]);
        }
    }

    /**
     * Test query performance - compare old vs new approach
     */
    public function test_query_performance() {
        global $wpdb;

        $time_ago = date('Y-m-d H:i:s', strtotime("-{$this->outdated_threshold}"));

        // Test old approach (WP_Query with complex meta_query)
        $start_time = microtime(true);

        $args = array(
            'post_type' => 'product',
            'post_status' => ['publish', 'draft'],
            'posts_per_page' => $this->batch_size,
            'meta_query' => array(
                'relation' => 'AND',
                array(
                    'relation' => 'OR',
                    array(
                        'key' => '_anar_sku',
                        'compare' => 'EXISTS'
                    ),
                    array(
                        'key' => '_anar_sku_backup',
                        'compare' => 'EXISTS'
                    ),
                ),
                array(
                    'relation' => 'OR',
                    array(
                        'key' => '_anar_last_sync_time',
                        'compare' => 'NOT EXISTS'
                    ),
                    array(
                        'key' => '_anar_last_sync_time',
                        'value' => $time_ago,
                        'compare' => '<',
                        'type' => 'DATETIME'
                    )
                )
            ),
            'fields' => 'ids'
        );

        $query = new \WP_Query($args);
        $old_approach_time = microtime(true) - $start_time;
        $old_approach_count = $query->found_posts;

        // Test new approach (direct SQL)
        $start_time = microtime(true);

        $sql = $wpdb->prepare("
            SELECT DISTINCT p.ID, 
                   COALESCE(sku.meta_value, sku_backup.meta_value) as anar_sku
            FROM {$wpdb->posts} p
            LEFT JOIN {$wpdb->postmeta} sku ON p.ID = sku.post_id AND sku.meta_key = '_anar_sku'
            LEFT JOIN {$wpdb->postmeta} sku_backup ON p.ID = sku_backup.post_id AND sku_backup.meta_key = '_anar_sku_backup'
            INNER JOIN {$wpdb->postmeta} last_try ON p.ID = last_try.post_id AND last_try.meta_key = '_anar_last_sync_time'
            WHERE p.post_type = 'product'
            AND p.post_status IN ('publish', 'draft')
            AND (sku.meta_value IS NOT NULL OR sku_backup.meta_value IS NOT NULL)
            AND last_try.meta_value < %s
            ORDER BY last_try.meta_value ASC
            LIMIT %d
        ", $time_ago, $this->batch_size);


        $results = $wpdb->get_results($sql);
        $new_approach_time = microtime(true) - $start_time;
        $new_approach_count = count($results);

        return [
            'old_approach' => [
                'time' => round($old_approach_time * 1000, 2), // Convert to milliseconds
                'count' => $old_approach_count
            ],
            'new_approach' => [
                'time' => round($new_approach_time * 1000, 2), // Convert to milliseconds
                'count' => $new_approach_count
            ],
            'improvement' => round((($old_approach_time - $new_approach_time) / $old_approach_time) * 100, 2)
        ];
    }



    /**
     * Create indexes for better performance of sync queries
     * This should be called during plugin activation or update
     */
    public static function create_sync_indexes() {
        global $wpdb;

        $indexes_created = get_option('anar_indexes_created', false);

        if ($indexes_created) {
            awca_log('Indexes already created!');
            return;
        }

        try {
            // Check if indexes already exist to avoid errors
            $existing_indexes = self::get_existing_indexes();

            // Define indexes to create (without WHERE clauses for MySQL compatibility)
            $indexes_to_create = [
                [
                    'table' => $wpdb->postmeta,
                    'name' => 'idx_anar_sku',
                    'columns' => '(meta_key, meta_value(50))'
                ],
                [
                    'table' => $wpdb->postmeta,
                    'name' => 'idx_anar_sku_backup',
                    'columns' => '(meta_key, meta_value(50))'
                ],
                [
                    'table' => $wpdb->postmeta,
                    'name' => 'idx_anar_last_sync_time',
                    'columns' => '(meta_key, meta_value(50))'
                ],
                [
                    'table' => $wpdb->posts,
                    'name' => 'idx_posts_product_sync',
                    'columns' => '(post_type, post_status, ID)'
                ]
            ];

            $created_count = 0;

            foreach ($indexes_to_create as $index) {
                // Check if index already exists
                if (in_array($index['name'], $existing_indexes)) {
                    awca_log("Index {$index['name']} already exists, skipping");
                    continue;
                }

                // Create index
                $sql = "CREATE INDEX {$index['name']} ON {$index['table']} {$index['columns']}";
                $result = $wpdb->query($sql);

                if ($result === false) {
                    awca_log("Failed to create index {$index['name']}: " . $wpdb->last_error);
                } else {
                    awca_log("Index {$index['name']} created successfully");
                    $created_count++;
                }
            }

            // Mark indexes as created
            update_option('anar_indexes_created', true);
            awca_log("Sync indexes creation completed. {$created_count} new indexes created.");

        } catch (\Exception $e) {
            awca_log('Error creating sync indexes: ' . $e->getMessage());
        }
    }

    /**
     * Get list of existing indexes for the tables we need
     */
    private static function get_existing_indexes() {
        global $wpdb;

        $existing_indexes = [];

        try {
            // Get indexes from postmeta table
            $postmeta_indexes = $wpdb->get_results("SHOW INDEX FROM {$wpdb->postmeta}");
            foreach ($postmeta_indexes as $index) {
                if ($index->Key_name !== 'PRIMARY') {
                    $existing_indexes[] = $index->Key_name;
                }
            }

            // Get indexes from posts table
            $posts_indexes = $wpdb->get_results("SHOW INDEX FROM {$wpdb->posts}");
            foreach ($posts_indexes as $index) {
                if ($index->Key_name !== 'PRIMARY') {
                    $existing_indexes[] = $index->Key_name;
                }
            }

        } catch (\Exception $e) {
            awca_log('Error getting existing indexes: ' . $e->getMessage());
        }

        return array_unique($existing_indexes);
    }

    /**
     * Remove sync indexes (for cleanup if needed)
     */
    public static function remove_indexes() {
        global $wpdb;

        try {
            $existing_indexes = self::get_existing_indexes();
            $indexes_to_remove = [
                'idx_anar_sku',
                'idx_anar_sku_backup',
                'idx_anar_last_sync_time',
                'idx_posts_product_sync'
            ];

            $removed_count = 0;

            foreach ($indexes_to_remove as $index_name) {
                if (in_array($index_name, $existing_indexes)) {
                    // Determine which table this index belongs to
                    $table = $wpdb->postmeta; // Default to postmeta
                    if ($index_name === 'idx_posts_product_sync') {
                        $table = $wpdb->posts;
                    }

                    $drop_sql = "DROP INDEX {$index_name} ON {$table}";
                    $result = $wpdb->query($drop_sql);

                    if ($result === false) {
                        awca_log("Failed to remove index {$index_name}: " . $wpdb->last_error);
                    } else {
                        awca_log("Index {$index_name} removed successfully");
                        $removed_count++;
                    }
                } else {
                    awca_log("Index {$index_name} does not exist, skipping");
                }
            }

            delete_option('anar_indexes_created');
            awca_log("Sync indexes removal completed. {$removed_count} indexes removed.");

        } catch (\Exception $e) {
            awca_log('Error removing sync indexes: ' . $e->getMessage());
        }
    }

    /**
     * Force recreate sync indexes (removes existing ones and creates new ones)
     */
    public static function force_recreate_indexes() {
        try {
            // First remove existing indexes
            self::remove_indexes();

            // Reset the option to force recreation
            delete_option('anar_indexes_created');

            // Then create new indexes
            self::create_sync_indexes();

            awca_log('Sync indexes force recreated successfully');

        } catch (\Exception $e) {
            awca_log('Error force recreating sync indexes: ' . $e->getMessage());
        }
    }

    /**
     * Check the status of sync indexes
     */
    public static function check_indexes_status() {
        global $wpdb;

        try {
            $existing_indexes = self::get_existing_indexes();
            $required_indexes = [
                'idx_anar_sku',
                'idx_anar_sku_backup',
                'idx_anar_last_sync_time',
                'idx_posts_product_sync'
            ];

            $status = [];
            $missing_indexes = [];
            $existing_count = 0;

            foreach ($required_indexes as $index_name) {
                if (in_array($index_name, $existing_indexes)) {
                    $status[$index_name] = 'exists';
                    $existing_count++;
                } else {
                    $status[$index_name] = 'missing';
                    $missing_indexes[] = $index_name;
                }
            }

            return [
                'status' => $status,
                'total_required' => count($required_indexes),
                'existing_count' => $existing_count,
                'missing_count' => count($missing_indexes),
                'missing_indexes' => $missing_indexes,
                'all_exist' => $existing_count === count($required_indexes)
            ];

        } catch (\Exception $e) {
            awca_log('Error checking sync indexes status: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * AJAX handler for manually triggering sync outdated products
     */
    public function manual_sync_outdated_ajax() {
        if (!current_user_can('manage_woocommerce')) {
            wp_send_json_error('شما این مجوز را ندارید!');
            wp_die();
        }

        try {
            // Import the SyncOutdated class
            $sync_outdated = \Anar\SyncOutdated::get_instance();
            
            // Manually trigger the sync process with results
            $results = $sync_outdated->process_outdated_products_cronjob(true);
            
            if (isset($results['error'])) {
                wp_send_json_error([
                    'message' => 'خطا در اجرای همگام‌سازی: ' . $results['error']
                ]);
                return;
            }
            
            // Return the actual results
            wp_send_json_success([
                'message' => 'همگام‌سازی محصولات منسوخ با موفقیت اجرا شد',
                'processed' => $results['processed'],
                'failed' => $results['failed'],
                'total_checked' => $results['total_checked']
            ]);

        } catch (\Exception $e) {
            wp_send_json_error([
                'message' => 'خطا در اجرای همگام‌سازی: ' . $e->getMessage()
            ]);
        }
    }

}
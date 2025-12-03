<?php
namespace Anar\Init;

class Db{

    public static function make_tables() {
        global $wpdb;

        // Log the current and new database versions
        $installed_version = get_option('awca_db_version');
        awca_log('Current DB Version: ' . $installed_version);
        awca_log('New DB Version: ' . ANAR_DB_VERSION);

        $table_name = $wpdb->prefix . ANAR_DB_NAME;
        $jobs_table_name = $wpdb->prefix . 'anar_jobs';
        $products_table_name = $wpdb->prefix . ANAR_DB_PRODUCTS_NAME;
        $data_table_name = $wpdb->prefix . 'anar_data';
        $charset_collate = $wpdb->get_charset_collate();

        // Check if main table exists
        $table_exists = $wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $table_name)) === $table_name;

        if (!$table_exists) {
            // Create main table using direct SQL
            $sql = "CREATE TABLE IF NOT EXISTS $table_name (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            response longtext NOT NULL,
            `key` varchar(255) NOT NULL,
            processed tinyint(1) NOT NULL DEFAULT '0',
            page int(11) NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY  (id)
        ) $charset_collate;";

            // Execute the SQL directly
            $result = $wpdb->query($sql);

            if ($result !== false) {
                awca_log('Table ' . $table_name . ' created successfully.');
                update_option('awca_db_version', ANAR_DB_VERSION);
            } else {
                awca_log('Failed to create table ' . $table_name . '. Error: ' . $wpdb->last_error);
            }
        }

        // Check if jobs table exists
        $jobs_table_exists = $wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $jobs_table_name)) === $jobs_table_name;

        if (!$jobs_table_exists) {
            // Create jobs table
            $sql = "CREATE TABLE IF NOT EXISTS $jobs_table_name (
                id bigint(20) NOT NULL AUTO_INCREMENT,
                job_id varchar(50) NOT NULL,
                status varchar(20) NOT NULL DEFAULT 'pending',
                source varchar(100) NOT NULL,
                total_products int(11) NOT NULL DEFAULT 0,
                processed_products int(11) NOT NULL DEFAULT 0,
                created_products int(11) NOT NULL DEFAULT 0,
                existing_products int(11) NOT NULL DEFAULT 0,
                failed_products int(11) NOT NULL DEFAULT 0,
                start_time datetime DEFAULT NULL,
                end_time datetime DEFAULT NULL,
                last_heartbeat datetime DEFAULT NULL,
                error_logs text DEFAULT NULL,
                retry_count int(11) NOT NULL DEFAULT 0,
                created_at datetime DEFAULT CURRENT_TIMESTAMP,
                updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY  (id),
                UNIQUE KEY job_id (job_id),
                KEY status (status),
                KEY source (source),
                KEY created_at (created_at)
            ) $charset_collate;";

            // Execute the SQL directly
            $result = $wpdb->query($sql);

            if ($result !== false) {
                awca_log('Table ' . $jobs_table_name . ' created successfully.');
            } else {
                awca_log('Failed to create table ' . $jobs_table_name . '. Error: ' . $wpdb->last_error);
            }
        } else {
            // Table exists, check if we need to update schema
            if ($installed_version !== ANAR_DB_VERSION) {
                // Add your schema update logic here if needed
                // For example:
                // $wpdb->query("ALTER TABLE $table_name ADD COLUMN new_column varchar(255)");

                update_option('awca_db_version', ANAR_DB_VERSION);
                awca_log('Table schema updated to version ' . ANAR_DB_VERSION);
            }
        }

        // Check if products table exists (for ImportSimple)
        $products_table_exists = $wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $products_table_name)) === $products_table_name;

        if (!$products_table_exists) {
            // Create products table for ImportSimple method
            $sql = "CREATE TABLE IF NOT EXISTS $products_table_name (
                anar_sku varchar(255) NOT NULL,
                product_data longtext NOT NULL,
                wc_product_id bigint(20) DEFAULT NULL,
                status varchar(20) NOT NULL DEFAULT 'pending',
                created_at datetime DEFAULT CURRENT_TIMESTAMP,
                updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (anar_sku),
                KEY status (status),
                KEY wc_product_id (wc_product_id),
                KEY created_at (created_at)
            ) $charset_collate;";

            // Execute the SQL directly
            $result = $wpdb->query($sql);

            if ($result !== false) {
                awca_log('Table ' . $products_table_name . ' created successfully.');
            } else {
                awca_log('Failed to create table ' . $products_table_name . '. Error: ' . $wpdb->last_error);
            }
        }

        // Check if anar_data table exists (for paginated storage)
        $data_table_exists = $wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $data_table_name)) === $data_table_name;

        if (!$data_table_exists) {
            // Create anar_data table for paginated storage of categories, attributes, and products
            $sql = "CREATE TABLE IF NOT EXISTS $data_table_name (
                id bigint(20) NOT NULL AUTO_INCREMENT,
                `key` varchar(255) NOT NULL,
                `_id` varchar(255) NOT NULL,
                `data` longtext NOT NULL,
                `status` varchar(20) NOT NULL DEFAULT 'pending',
                `wc_id` bigint(20) DEFAULT NULL,
                `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
                `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                UNIQUE KEY unique_key_id (`key`, `_id`),
                KEY idx_key (`key`),
                KEY idx_status (`status`),
                KEY idx_wc_id (`wc_id`),
                KEY idx_created_at (`created_at`)
            ) $charset_collate;";

            // Execute the SQL directly
            $result = $wpdb->query($sql);

            if ($result !== false) {
                awca_log('Table ' . $data_table_name . ' created successfully.');
            } else {
                awca_log('Failed to create table ' . $data_table_name . '. Error: ' . $wpdb->last_error);
            }
        }

    }

    /**
     * Truncate the table (remove all rows).
     *
     * @return bool True on success, false on failure.
     */
    public static function truncate_table() {
        global $wpdb;

        $table_name = $wpdb->prefix . ANAR_DB_NAME;

        // Use TRUNCATE TABLE to remove all rows and reset the auto-increment counter
        $result = $wpdb->query("TRUNCATE TABLE $table_name");

        if ($result !== false) {
            awca_log('Table ' . $table_name . ' truncated successfully.');
            return true;
        } else {
            awca_log('Failed to truncate table ' . $table_name . '.');
            return false;
        }
    }



}
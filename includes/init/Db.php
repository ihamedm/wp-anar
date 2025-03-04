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
        $charset_collate = $wpdb->get_charset_collate();

        // Check if table exists
        $table_exists = $wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $table_name)) === $table_name;

        if (!$table_exists) {
            // Create table using direct SQL
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
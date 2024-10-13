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

        // Remove the unique constraint if it exists
        $wpdb->query("ALTER TABLE $table_name DROP INDEX unique_key");

        $sql = "CREATE TABLE $table_name (
        id mediumint(9) NOT NULL AUTO_INCREMENT,
        response longtext NOT NULL,
        `key` varchar(255) NOT NULL,
        processed tinyint(1) NOT NULL DEFAULT 0,
        page int(11) DEFAULT NULL,
        created_at datetime DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY  (id)
    ) $charset_collate;";

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);

        // Check if the table was created/updated successfully
        $table_exists = $wpdb->get_var("SHOW TABLES LIKE '$table_name'") == $table_name;
        if ($table_exists) {
            awca_log('Table ' . $table_name . ' created/updated successfully.');
            // Update the database version option
            update_option('awca_db_version', ANAR_DB_VERSION);
        } else {
            awca_log('Failed to create/update table ' . $table_name . '.');
        }
    }

}
<?php

namespace Anar\Core;

class SystemStatus{

    /**
     * Verify the health of the plugin's database table
     *
     * @return array Array containing health check results
     */
    public static function verify_db_table_health() {
        global $wpdb;
        $results = [];
        $table_name = $wpdb->prefix . ANAR_DB_NAME;

        try {
            // Check 1: Table Existence
            $table_exists = $wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $table_name)) === $table_name;
            $results['table_exists'] = [
                'status' => $table_exists ? 'good' : 'critical',
                'message' => $table_exists ? 'Table exists' : 'Table does not exist',
                'label' => 'Table Existence'
            ];

            if (!$table_exists) {
                throw new \Exception('Table does not exist');
            }

            // Check 2: Table Structure
            $expected_columns = [
                'id' => ['Type' => 'mediumint(9)', 'Null' => 'NO', 'Key' => 'PRI', 'Extra' => 'auto_increment'],
                'response' => ['Type' => 'longtext', 'Null' => 'NO'],
                'key' => ['Type' => 'varchar(255)', 'Null' => 'NO'],
                'processed' => ['Type' => 'tinyint(1)', 'Null' => 'NO', 'Default' => '0'],
                'page' => ['Type' => 'int(11)', 'Null' => 'YES'],
                'created_at' => ['Type' => 'datetime', 'Default' => 'CURRENT_TIMESTAMP']
            ];

            $table_structure = $wpdb->get_results("DESCRIBE $table_name", ARRAY_A);
            $missing_columns = [];
            $incorrect_columns = [];

            foreach ($table_structure as $column) {
                $column_name = $column['Field'];
                if (isset($expected_columns[$column_name])) {
                    foreach ($expected_columns[$column_name] as $property => $expected_value) {
                        if (isset($column[$property]) && $column[$property] !== $expected_value) {
                            $incorrect_columns[] = "{$column_name} ({$property}: expected {$expected_value}, got {$column[$property]})";
                        }
                    }
                }
            }

            foreach ($expected_columns as $column_name => $properties) {
                if (!in_array($column_name, array_column($table_structure, 'Field'))) {
                    $missing_columns[] = $column_name;
                }
            }

            $results['table_structure'] = [
                'status' => (empty($missing_columns) && empty($incorrect_columns)) ? 'good' : 'critical',
                'message' => (empty($missing_columns) && empty($incorrect_columns))
                    ? 'Table structure is correct'
                    : sprintf(
                        'Table structure issues found: %s%s',
                        !empty($missing_columns) ? ' Missing columns: ' . implode(', ', $missing_columns) : '',
                        !empty($incorrect_columns) ? ' Incorrect columns: ' . implode(', ', $incorrect_columns) : ''
                    ),
                'label' => 'Table Structure'
            ];

            // Check 3: Database Version
            $installed_version = get_option('awca_db_version');
            $results['db_version'] = [
                'status' => ($installed_version === ANAR_DB_VERSION) ? 'good' : 'warning',
                'message' => sprintf(
                    'Installed version: %s, Expected version: %s',
                    $installed_version,
                    ANAR_DB_VERSION
                ),
                'label' => 'Database Version'
            ];

            // Check 4: Table Size and Row Count
            $table_status = $wpdb->get_row("SHOW TABLE STATUS LIKE '$table_name'");
            $results['table_status'] = [
                'status' => 'good',
                'message' => sprintf(
                    'Rows: %d, Data Size: %s, Index Size: %s',
                    $table_status->Rows,
                    size_format($table_status->Data_length),
                    size_format($table_status->Index_length)
                ),
                'label' => 'Table Statistics'
            ];

            // Check 5: Auto Increment Status
            $results['auto_increment'] = [
                'status' => ($table_status->Auto_increment > 0) ? 'good' : 'warning',
                'message' => sprintf('Current Auto Increment value: %d', $table_status->Auto_increment),
                'label' => 'Auto Increment Status'
            ];

        } catch (\Exception $e) {
            // Log the error
            awca_log('Table health check failed: ' . $e->getMessage());

            $results['error'] = [
                'status' => 'critical',
                'message' => 'Health check failed: ' . $e->getMessage(),
                'label' => 'Error'
            ];
        }

        // Add timestamp to results
        $results['last_checked'] = [
            'status' => 'good',
            'message' => current_time('mysql'),
            'label' => 'Last Checked'
        ];

        return $results;
    }

    /**
     * Get a formatted health report string
     *
     * @return string Formatted health report
     */
    /**
     * Get a formatted health report string
     *
     * @return string Formatted health report
     */
    public static function get_db_health_report() {
        $results = self::verify_db_table_health();
        $output = "\n\n=== ANAR Database Health Report ===\n";
        $output .= "Generated: " . current_time('mysql') . "\n\n";

        foreach ($results as $key => $check) {
            $status_icon = $check['status'] === 'good' ? '✓' : ($check['status'] === 'warning' ? '⚠' : '✗');
            $output .= sprintf(
                "%s %s: %s\n",
                $status_icon,
                $check['label'],
                $check['message']
            );
        }

        return $output;
    }

    /**
     * Check the health and status of WordPress cron jobs
     *
     * @return array Array containing health check results for cron system
     */
    public static function verify_cron_health() {
        $results = [];
        $current_time = current_time('timestamp', true); // Get UTC timestamp

        try {
            // Check 1: WordPress Cron Constant
            $results['wp_cron_enabled'] = [
                'status' => (!defined('DISABLE_WP_CRON') || !DISABLE_WP_CRON) ? 'good' : 'warning',
                'message' => (!defined('DISABLE_WP_CRON') || !DISABLE_WP_CRON)
                    ? 'WordPress cron is enabled'
                    : 'WordPress cron is disabled via DISABLE_WP_CRON constant',
                'label' => 'WP Cron Status'
            ];

            // Check 2: Alternative Cron Setting
            $results['alternate_cron'] = [
                'status' => (!defined('ALTERNATE_WP_CRON') || !ALTERNATE_WP_CRON) ? 'good' : 'warning',
                'message' => (!defined('ALTERNATE_WP_CRON') || !ALTERNATE_WP_CRON)
                    ? 'Using standard WordPress cron'
                    : 'Using alternative WordPress cron',
                'label' => 'Cron Mode'
            ];

            // Check 3: Get all scheduled cron jobs
            $cron_array = _get_cron_array();
            $our_cron_jobs = [];
            $missed_cron_jobs = [];
            $next_scheduled = PHP_INT_MAX;

            // Plugin-specific cron jobs prefix (adjust this to match your plugin's prefix)
            $cron_prefix = 'awca_'; // Assuming your plugin prefix is 'awca_'

            foreach ($cron_array as $timestamp => $crons) {
                foreach ($crons as $hook => $cron_details) {
                    if (strpos($hook, $cron_prefix) === 0) {
                        $our_cron_jobs[] = [
                            'hook' => $hook,
                            'timestamp' => $timestamp,
                            'schedule' => reset($cron_details)['schedule'] ?? 'single'
                        ];

                        // Check for missed cron jobs (more than 1 hour late)
                        if ($timestamp + 3600 < $current_time) {
                            $missed_cron_jobs[] = $hook;
                        }

                        // Track next scheduled job
                        if ($timestamp > $current_time && $timestamp < $next_scheduled) {
                            $next_scheduled = $timestamp;
                        }
                    }
                }
            }

            // Check 4: Plugin's Cron Jobs Status
            $results['plugin_crons'] = [
                'status' => !empty($our_cron_jobs) ? 'good' : 'warning',
                'message' => sprintf(
                    'Found %d scheduled cron jobs for this plugin',
                    count($our_cron_jobs)
                ),
                'label' => 'Plugin Cron Jobs'
            ];

            // Check 5: Missed Cron Jobs
            $results['missed_crons'] = [
                'status' => empty($missed_cron_jobs) ? 'good' : 'warning',
                'message' => empty($missed_cron_jobs)
                    ? 'No missed cron jobs'
                    : sprintf('Missed cron jobs: %s', implode(', ', $missed_cron_jobs)),
                'label' => 'Missed Cron Jobs'
            ];

            // Check 6: Next Scheduled Run
            $results['next_scheduled'] = [
                'status' => 'good',
                'message' => $next_scheduled !== PHP_INT_MAX
                    ? sprintf(
                        'Next job scheduled for %s (in %s)',
                        gmdate('Y-m-d H:i:s', $next_scheduled),
                        human_time_diff($current_time, $next_scheduled)
                    )
                    : 'No upcoming scheduled jobs',
                'label' => 'Next Scheduled Job'
            ];

            // Check 7: System Time Check
            $system_time = time();
            $wp_time = current_time('timestamp', true);
            $time_diff = abs($system_time - $wp_time);

            $results['time_sync'] = [
                'status' => ($time_diff < 300) ? 'good' : 'critical', // 5 minutes threshold
                'message' => ($time_diff < 300)
                    ? 'System time is properly synchronized'
                    : sprintf('System time differs by %s seconds from WordPress time', $time_diff),
                'label' => 'Time Synchronization'
            ];

            // Check 8: Cron Lock Status
            $doing_cron = get_transient('doing_cron');
            $stuck_cron_threshold = 3600; // 1 hour

            if ($doing_cron) {
                $cron_lock_time = $doing_cron - $current_time;
                $results['cron_lock'] = [
                    'status' => ($cron_lock_time > $stuck_cron_threshold) ? 'critical' : 'warning',
                    'message' => sprintf(
                        'Cron appears to be running for %s',
                        human_time_diff($doing_cron, $current_time)
                    ),
                    'label' => 'Cron Lock Status'
                ];
            } else {
                $results['cron_lock'] = [
                    'status' => 'good',
                    'message' => 'No cron process is currently running',
                    'label' => 'Cron Lock Status'
                ];
            }

        } catch (\Exception $e) {
            awca_log('Cron health check failed: ' . $e->getMessage());

            $results['error'] = [
                'status' => 'critical',
                'message' => 'Cron health check failed: ' . $e->getMessage(),
                'label' => 'Error'
            ];
        }

        // Add timestamp to results
        $results['last_checked'] = [
            'status' => 'good',
            'message' => current_time('mysql'),
            'label' => 'Last Checked'
        ];

        return $results;
    }

    /**
     * Get a formatted cron health report string
     *
     * @return string Formatted health report
     */
    public static function get_cron_health_report() {
        $results = self::verify_cron_health();
        $output = "\n\n=== ANAR Cron Health Report ===\n";
        $output .= "Generated: " . current_time('mysql') . "\n\n";

        foreach ($results as $key => $check) {
            $status_icon = $check['status'] === 'good' ? '✓' : ($check['status'] === 'warning' ? '⚠' : '✗');
            $output .= sprintf(
                "%s %s: %s\n",
                $status_icon,
                $check['label'],
                $check['message']
            );
        }

        return $output;
    }

}
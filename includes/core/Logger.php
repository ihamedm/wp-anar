<?php
namespace Anar\Core;

class Logger {

    private static $instance;

    private $log_dir;
    private $log_file_prefix;
    private $log_file_postfix;
    private $max_file_size;

    // Define log levels as constants
    const LEVEL_ERROR = 100;
    const LEVEL_WARNING = 200;
    const LEVEL_INFO = 300;
    const LEVEL_DEBUG = 400;

    // Map of log level names to their numeric values
    private static $log_levels = [
        'error' => self::LEVEL_ERROR,
        'warning' => self::LEVEL_WARNING,
        'info' => self::LEVEL_INFO,
        'debug' => self::LEVEL_DEBUG
    ];

    // Default log level (info for backward compatibility)
    private $current_log_level;

    public static function get_instance() {
        if ( ! isset( self::$instance ) ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    // Constructor to initialize default values
    public function __construct($log_dir = WP_CONTENT_DIR . '/wp-anar-logs', $log_file_prefix = 'general', $log_file_postfix = '', $max_file_size = 1024000) {
        $this->log_dir = $log_dir;
        $this->log_file_prefix = $log_file_prefix;
        $this->log_file_postfix = $log_file_postfix;
        $this->max_file_size = $max_file_size;

        // Set the current log level from options or default to INFO
        $this->set_log_level(get_option('anar_log_level', 'info'));

        // Create the log directory if it doesn't exist
        if (!file_exists($this->log_dir)) {
            mkdir($this->log_dir, 0755, true); // Creates the directory with proper permissions
        }
    }

    /**
     * Set the current log level
     *
     * @param string $level The log level (error, warning, info, debug)
     * @return void
     */
    public function set_log_level($level) {
        $level = strtolower($level);
        if (isset(self::$log_levels[$level])) {
            $this->current_log_level = self::$log_levels[$level];
        } else {
            // Default to INFO level for backward compatibility
            $this->current_log_level = self::LEVEL_INFO;
        }
    }

    /**
     * Get the current log level
     *
     * @return string The current log level name
     */
    public function get_log_level() {
        $current = $this->current_log_level;
        return array_search($current, self::$log_levels) ?: 'info';
    }

    /**
     * Check if the given log level should be logged
     *
     * @param int $level The log level to check
     * @return bool Whether the log level should be logged
     */
    private function should_log($level) {
        return $level <= $this->current_log_level;
    }

    // Method to get the current log file name
    private function get_log_file() {
        return $this->log_dir . '/' . $this->log_file_prefix . $this->log_file_postfix . '.log';
    }

    // Method to rotate log files if they exceed the max size or a new file is requested
    private function rotate_log_file($log_file) {
        if (file_exists($log_file)) {
            // Append current datetime to the old log file name for archiving
            $new_log_file = $this->log_dir . '/' . $this->log_file_prefix . '-' . date("Y-m-d_H-i-s") . $this->log_file_postfix . '.log';
            rename($log_file, $new_log_file); // Rename the current log file to archive it
        }
    }

    /**
     * Log an error message
     *
     * @param string $message The message to log
     * @param string|null $prefix Optional log file prefix
     * @param bool $new_file Whether to create a new log file
     * @return void
     */
    public function error($message, $prefix = null, $new_file = false) {
        if ($this->should_log(self::LEVEL_ERROR)) {
            $this->log($message, $prefix, $new_file, 'ERROR');
        }
    }

    /**
     * Log a warning message
     *
     * @param string $message The message to log
     * @param string|null $prefix Optional log file prefix
     * @param bool $new_file Whether to create a new log file
     * @return void
     */
    public function warning($message, $prefix = null, $new_file = false) {
        if ($this->should_log(self::LEVEL_WARNING)) {
            $this->log($message, $prefix, $new_file, 'WARNING');
        }
    }

    /**
     * Log an info message
     *
     * @param string $message The message to log
     * @param string|null $prefix Optional log file prefix
     * @param bool $new_file Whether to create a new log file
     * @return void
     */
    public function info($message, $prefix = null, $new_file = false) {
        if ($this->should_log(self::LEVEL_INFO)) {
            $this->log($message, $prefix, $new_file, 'INFO');
        }
    }

    /**
     * Log a debug message
     *
     * @param string $message The message to log
     * @param string|null $prefix Optional log file prefix
     * @param bool $new_file Whether to create a new log file
     * @return void
     */
    public function debug($message, $prefix = null, $new_file = false) {
        if ($this->should_log(self::LEVEL_DEBUG)) {
            $this->log($message, $prefix, $new_file, 'DEBUG');
        }
    }

    /**
     * Method to log messages with an optional new file creation
     *
     * @param string $message The message to log
     * @param string|null $prefix Optional log file prefix
     * @param bool $new_file Whether to create a new log file
     * @param string $level The log level (defaults to INFO for backward compatibility)
     * @return void
     */
    public function log($message, $prefix = null, $level = 'INFO', $new_file = false) {
        // For backward compatibility, if log() is called directly, treat it as INFO level
        $numeric_level = isset(self::$log_levels[strtolower($level)]) ?
            self::$log_levels[strtolower($level)] : self::LEVEL_INFO;

        // Skip logging if the level is higher than the current log level
        if (!$this->should_log($numeric_level)) {
            return;
        }

        // If a new prefix is provided, use it; otherwise, use the default prefix
        if ($prefix !== null) {
            $this->log_file_prefix = $prefix;
        }

        // Get the log file path
        $log_file = $this->get_log_file();

        // Rotate the log file if a new file is requested or it exceeds the max file size
        if ($new_file || (file_exists($log_file) && filesize($log_file) > $this->max_file_size)) {
            $this->rotate_log_file($log_file);
        }

        // Create the log file if it doesn't exist
        if (!file_exists($log_file)) {
            $file_handle = fopen($log_file, 'w');
            fclose($file_handle);
        }

        // Ensure the log file is writable
        if (is_writable($log_file)) {
            // Append the message to the log file with a timestamp and level
            $timestamp = current_time("mysql");
            file_put_contents($log_file, "[$timestamp] [$level] $message" . PHP_EOL, FILE_APPEND);
        } else {
            // Log an error if the file is not writable
            error_log("Cannot write to log file: $log_file");
        }

        // Clean up old log files for the current prefix
        $this->cleanup_logs();
    }

    // Method to clean up old log files (keep only 3 recent logs per prefix)
    public function cleanup_logs() {
        // Get all log files with the current prefix
        $files = glob($this->log_dir . '/' . $this->log_file_prefix . '*.log');

        if (count($files) > 15) {
            // Sort files by modification time (oldest first)
            usort($files, function($a, $b) {
                return filemtime($a) - filemtime($b);
            });

            // Delete the oldest files, keeping only the 3 most recent
            while (count($files) > 10) {
                $oldest_file = array_shift($files);
                unlink($oldest_file);
                $this->log("Deleted log file: $oldest_file");
            }
        }
    }


    /**
     * Get information about all log files for system status report
     *
     * @return array Array containing log files information and status
     */
    public static function get_logs_status() {
        $instance = self::get_instance();
        $results = [];

        try {
            // Add current log level to the status report
            $results['log_level'] = [
                'status' => 'good',
                'message' => sprintf(
                    'Current Log Level: %s',
                    strtoupper($instance->get_log_level())
                ),
                'label' => 'Log Level'
            ];

            // Check log directory existence and permissions
            $results['log_directory'] = [
                'status' => file_exists($instance->log_dir) && is_writable($instance->log_dir) ? 'good' : 'critical',
                'message' => sprintf(
                    'Log Directory: %s (%s)',
                    $instance->log_dir,
                    file_exists($instance->log_dir)
                        ? (is_writable($instance->log_dir) ? 'Writable' : 'Not Writable')
                        : 'Does Not Exist'
                ),
                'label' => 'Log Directory Status'
            ];

            // Get all log files
            $log_files = [];
            $total_size = 0;

            if (file_exists($instance->log_dir)) {
                $files = glob($instance->log_dir . '/*.log');

                foreach ($files as $file) {
                    $filename = basename($file);
                    $filesize = filesize($file);
                    $total_size += $filesize;
                    $modified = filemtime($file);

                    // Get the log type (prefix) from filename
                    $prefix = explode('-', $filename)[0];

                    // Create URL for the log file
                    $url = content_url('wp-anar-logs/' . $filename);

                    $log_files[] = [
                        'name' => $filename,
                        'size' => size_format($filesize, 2),
                        'modified' => gmdate('Y-m-d H:i:s', $modified),
                        'url' => $url,
                        'prefix' => $prefix,
                        'writable' => is_writable($file)
                    ];
                }
            }

            // Sort log files by modification time (newest first)
            usort($log_files, function($a, $b) {
                return strtotime($b['modified']) - strtotime($a['modified']);
            });

            // Group log files by prefix
            $grouped_logs = [];
            foreach ($log_files as $log) {
                $grouped_logs[$log['prefix']][] = $log;
            }

            // Add log files information to results
            foreach ($grouped_logs as $prefix => $logs) {
                $results['logs_' . $prefix] = [
                    'status' => 'good',
                    'message' => sprintf(
                        "Found %d log file(s)\n%s",
                        count($logs),
                        implode("\n", array_map(function($log) {
                            return sprintf(
                                "- %s (%s, modified: %s) %s",
                                $log['name'],
                                $log['size'],
                                $log['modified'],
                                $log['writable'] ? '' : '[NOT WRITABLE]'
                            );
                        }, $logs))
                    ),
                    'label' => ucfirst($prefix) . ' Logs',
                    'urls' => array_column($logs, 'url')
                ];
            }

            // Add total size information
            $results['total_size'] = [
                'status' => 'good',
                'message' => sprintf(
                    'Total log files size: %s',
                    size_format($total_size, 2)
                ),
                'label' => 'Total Logs Size'
            ];

            // Check for any unwritable files
            $unwritable_files = array_filter($log_files, function($log) {
                return !$log['writable'];
            });

            if (!empty($unwritable_files)) {
                $results['file_permissions'] = [
                    'status' => 'critical',
                    'message' => sprintf(
                        'Found %d unwritable log file(s): %s',
                        count($unwritable_files),
                        implode(', ', array_column($unwritable_files, 'name'))
                    ),
                    'label' => 'File Permissions'
                ];
            }

        } catch (\Exception $e) {
            $results['error'] = [
                'status' => 'critical',
                'message' => 'Error getting log files status: ' . $e->getMessage(),
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
     * Get a formatted log files status report
     *
     * @return string Formatted status report
     */
    public static function get_logs_status_report() {
        $results = self::get_logs_status();
        $output = "\n\n=== ANAR Log Files Status Report ===\n";
        $output .= "Generated: " . current_time('mysql') . "\n";
        $output .= "Generated by: " . wp_get_current_user()->user_login . "\n\n";

        foreach ($results as $key => $check) {
            $status_icon = $check['status'] === 'good' ? '✓' : ($check['status'] === 'warning' ? '⚠' : '✗');
            $output .= sprintf(
                "%s %s:\n%s\n",
                $status_icon,
                $check['label'],
                $check['message']
            );

            // Add URLs if they exist
            if (isset($check['urls']) && !empty($check['urls'])) {
                $output .= "URLs:\n" . implode("\n", array_map(function($url) {
                        return "  - " . $url;
                    }, $check['urls'])) . "\n";
            }

            $output .= "\n";
        }

        return $output;
    }
}
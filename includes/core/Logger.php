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
            // Basic Status Checks
            $results['log_level'] = [
                'label' => 'Log Level',
                'message' => strtoupper($instance->get_log_level()),
                'status' => 'good'
            ];

            $dir_exists = file_exists($instance->log_dir);
            $dir_writable = $dir_exists && is_writable($instance->log_dir);
            
            $results['log_directory'] = [
                'label' => 'Log Directory',
                'message' => $instance->log_dir,
                'status' => $dir_writable ? 'good' : 'critical',
                'details' => $dir_exists 
                    ? ($dir_writable ? 'Writable' : 'Not Writable') 
                    : 'Does Not Exist'
            ];

            if (!$dir_exists) {
                return $results;
            }

            // Log Files Analysis
            $files = glob($instance->log_dir . '/*.log');
            $total_size = 0;
            $grouped_logs = [];

            foreach ($files as $file) {
                $filename = basename($file);
                $size = filesize($file);
                $total_size += $size;
                $prefix = explode('-', $filename)[0];
                $writable = is_writable($file);

                if (!isset($grouped_logs[$prefix])) {
                    $grouped_logs[$prefix] = [
                        'files' => [],
                        'total_size' => 0,
                        'has_issues' => false
                    ];
                }

                $grouped_logs[$prefix]['files'][] = [
                    'name' => $filename,
                    'size' => size_format($size, 2),
                    'modified' => wp_date('Y-m-d H:i:s', filemtime($file)),
                    'url' => content_url('wp-anar-logs/' . $filename),
                    'writable' => $writable
                ];
                
                $grouped_logs[$prefix]['total_size'] += $size;
                $grouped_logs[$prefix]['has_issues'] = $grouped_logs[$prefix]['has_issues'] || !$writable;
            }

            // Add grouped logs to results
            foreach ($grouped_logs as $prefix => $group) {
                $results['logs_' . $prefix] = [
                    'label' => ucfirst($prefix) . ' Logs',
                    'message' => sprintf(
                        '%d files (%s total)',
                        count($group['files']),
                        size_format($group['total_size'], 2)
                    ),
                    'status' => $group['has_issues'] ? 'warning' : 'good',
                    'files' => $group['files']
                ];
            }

            // Total size summary
            $results['total_size'] = [
                'label' => 'Total Size',
                'message' => size_format($total_size, 2),
                'status' => 'good'
            ];

        } catch (\Exception $e) {
            $results['error'] = [
                'label' => 'Error',
                'message' => $e->getMessage(),
                'status' => 'critical'
            ];
        }

        return $results;
    }

}
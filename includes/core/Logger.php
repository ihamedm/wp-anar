<?php
namespace Anar\Core;

class Logger {
    private $log_dir;
    private $log_file_prefix;
    private $log_file_postfix;
    private $max_file_size;

    // Constructor to initialize default values
    public function __construct($log_dir = WP_CONTENT_DIR . '/wp-anar-logs', $log_file_prefix = 'anar', $log_file_postfix = '', $max_file_size = 1024000) {
        $this->log_dir = $log_dir;
        $this->log_file_prefix = $log_file_prefix;
        $this->log_file_postfix = $log_file_postfix;
        $this->max_file_size = $max_file_size;

        // Create the log directory if it doesn't exist
        if (!file_exists($this->log_dir)) {
            mkdir($this->log_dir, 0755, true); // Creates the directory with proper permissions
        }
    }

    // Method to get the current log file name
    private function get_log_file() {
        return $this->log_dir . '/' . $this->log_file_prefix . $this->log_file_postfix . '.log';
    }

    // Method to rotate log files if they exceed the max size
    private function rotate_log_file($log_file) {
        if (file_exists($log_file) && filesize($log_file) > $this->max_file_size) {
            // Append current datetime to the old log file name
            $new_log_file = $this->log_dir . '/' . $this->log_file_prefix . '-' . date("Y-m-d_H-i-s") . $this->log_file_postfix . '.log';
            rename($log_file, $new_log_file); // Rename the current log file to archive it
        }
    }

    // Method to log messages
    public function log($message) {
        // Get the log file path
        $log_file = $this->get_log_file();

        // Rotate the log file if it exceeds the max file size
        $this->rotate_log_file($log_file);

        // Create the log file if it doesn't exist
        if (!file_exists($log_file)) {
            $file_handle = fopen($log_file, 'w');
            fclose($file_handle);
        }

        // Ensure the log file is writable
        if (is_writable($log_file)) {
            // Append the message to the log file with a timestamp
            $timestamp = date("Y-m-d H:i:s");
            file_put_contents($log_file, "[$timestamp] $message" . PHP_EOL, FILE_APPEND);
        } else {
            // Log an error if the file is not writable
            error_log("Cannot write to log file: $log_file");
        }
    }


    public function cleanup_logs() {
        $files = glob($this->log_dir . '/*.log');

        if (count($files) > 5) {
            // Sort files by modification time (oldest first)
            usort($files, function($a, $b) {
                return filemtime($a) - filemtime($b);
            });

            // Delete the oldest files, keeping only the 5 most recent
            while (count($files) > 5) {
                $oldest_file = array_shift($files);
                unlink($oldest_file);
                $this->log("Deleted log file: $oldest_file");
            }
        }
    }
}

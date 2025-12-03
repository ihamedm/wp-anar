<?php

namespace Anar\Admin\Widgets;

use Anar\Sync\RegularSync;
use Anar\Sync\OutdatedSync;

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

/**
 * Cron Health Report Widget
 * Tests cron job health and status
 */
class CronHealthWidget extends AbstractReportWidget
{
    protected function init()
    {
        $this->widget_id = 'anar-cron-health-widget';
        $this->title = 'سلامت Cron';
        $this->description = 'بررسی وضعیت Cron Job ها';
        $this->icon = '<span class="dashicons dashicons-clock"></span>';
        $this->ajax_action = 'anar_get_cron_health';
        $this->button_text = 'بررسی Cron';
        $this->button_class = 'button-primary';

        // Register AJAX handler
        add_action('wp_ajax_' . $this->ajax_action, [$this, 'handle_ajax']);
    }

    protected function get_report_data()
    {
        $important_jobs = $this->check_important_jobs();
        
        $results = [
            'wp_cron_disabled' => $this->check_wp_cron_disabled(),
            'wp_cron_status' => $this->check_wp_cron_status($important_jobs),
            'important_jobs' => $important_jobs,
            'cron_spawn_test' => $this->test_cron_spawn(),
            'timestamp' => current_time('Y-m-d H:i:s')
        ];

        // Determine if cron is working (simple yes/no)
        $results['is_working'] = $this->is_cron_working($results);

        return $results;
    }

    /**
     * Check if WP Cron is disabled in wp-config
     * Note: This is just a warning, not an error, as many professional users
     * disable WP Cron and use server cron instead for better performance
     *
     * @return array
     */
    private function check_wp_cron_disabled()
    {
        $disabled = defined('DISABLE_WP_CRON') && DISABLE_WP_CRON;
        
        return [
            'disabled' => $disabled,
            'message' => $disabled 
                ? 'WP Cron در wp-config غیرفعال است (ممکن است از Cron سرور استفاده شود)' 
                : 'WP Cron فعال است',
            'status' => $disabled ? 'warning' : 'success'
        ];
    }

    /**
     * Check WordPress cron status
     * Uses the most recent execution time from important jobs
     *
     * @param array $important_jobs The important jobs data
     * @return array
     */
    private function check_wp_cron_status($important_jobs = [])
    {
        // Use time() for UTC timestamp comparison (stored times are converted to UTC)
        $current_time = time();
        $most_recent_execution = 0;
        
        $status = [
            'last_run' => null,
            'last_run_formatted' => null,
            'time_since_last_run' => null,
            'time_since_last_run_formatted' => null,
            'status' => 'unknown',
            'message' => ''
        ];

        // Find the most recent execution time from important jobs
        if (!empty($important_jobs)) {
            foreach ($important_jobs as $job) {
                if (isset($job['last_run']) && $job['last_run'] && $job['last_run'] > $most_recent_execution) {
                    $most_recent_execution = $job['last_run'];
                }
            }
        }
        
        if ($most_recent_execution > 0) {
            $time_diff = $current_time - $most_recent_execution;
            
            $status['last_run'] = $most_recent_execution;
            $status['last_run_formatted'] = wp_date('Y-m-d H:i:s', $most_recent_execution);
            $status['time_since_last_run'] = $time_diff;
            $status['time_since_last_run_formatted'] = $this->format_time_diff($time_diff);
            
            // If the most recent execution was more than 1 hour ago, it's a problem
            if ($time_diff > 3600) {
                $status['status'] = 'error';
                $status['message'] = sprintf('آخرین اجرای Cron %s پیش بوده است', $status['time_since_last_run_formatted']);
            } elseif ($time_diff > 1800) { // More than 30 minutes
                $status['status'] = 'warning';
                $status['message'] = sprintf('آخرین اجرای Cron %s پیش بوده است', $status['time_since_last_run_formatted']);
            } else {
                $status['status'] = 'success';
                $status['message'] = sprintf('Cron %s پیش اجرا شده است', $status['time_since_last_run_formatted']);
            }
        } else {
            $status['status'] = 'warning';
            $status['message'] = 'اطلاعاتی از آخرین اجرای Cron یافت نشد';
        }

        return $status;
    }

    /**
     * Check important cron jobs
     *
     * @return array
     */
    private function check_important_jobs()
    {
        $jobs = [
            'anar_sync_products' => [
                'name' => 'anar_sync_products',
                'display_name' => 'همگام‌سازی محصولات',
                'interval' => 'هر ۱۰ دقیقه',
                'interval_seconds' => 600
            ],
            'anar_sync_outdated_products' => [
                'name' => 'anar_sync_outdated_products',
                'display_name' => 'همگام‌سازی محصولات',
                'interval' => 'هر ۵ دقیقه',
                'interval_seconds' => 300
            ]
        ];

        foreach ($jobs as $hook => &$job) {
            // Add the hook name (event action name) to the job data
            $job['hook_name'] = $hook;
            
            // Check if scheduled
            $next_scheduled = wp_next_scheduled($hook);
            
            $job['scheduled'] = $next_scheduled !== false;
            $job['next_run'] = $next_scheduled;
            $job['next_run_formatted'] = $next_scheduled ? wp_date('Y-m-d H:i:s', $next_scheduled) : null;
            
            // Get last execution time
            $job['last_run'] = $this->get_last_job_execution($hook);
            $job['last_run_formatted'] = $job['last_run'] ? wp_date('Y-m-d H:i:s', $job['last_run']) : null;
            
            // Calculate time since last run
            if ($job['last_run']) {
                // Use time() for UTC timestamp comparison (job['last_run'] is already UTC)
                $time_diff = time() - $job['last_run'];
                $job['time_since_last_run'] = $time_diff;
                $job['time_since_last_run_formatted'] = $this->format_time_diff($time_diff);
                
                // Check if overdue (more than 2x the interval)
                $overdue_threshold = $job['interval_seconds'] * 2;
                if ($time_diff > $overdue_threshold) {
                    $job['status'] = 'error';
                    $job['message'] = sprintf('اجرا نشده در %s (باید هر %s اجرا شود)', 
                        $job['time_since_last_run_formatted'], 
                        $job['interval']
                    );
                } elseif ($time_diff > $job['interval_seconds']) {
                    $job['status'] = 'warning';
                    $job['message'] = sprintf('تأخیر دارد: %s از آخرین اجرا گذشته است', 
                        $job['time_since_last_run_formatted']
                    );
                } else {
                    $job['status'] = 'success';
                    $job['message'] = sprintf('آخرین اجرا: %s پیش', 
                        $job['time_since_last_run_formatted']
                    );
                }
            } else {
                $job['status'] = 'error';
                $job['message'] = 'هرگز اجرا نشده است';
            }
            
            // Check if scheduled
            if (!$job['scheduled']) {
                $job['status'] = 'error';
                $job['message'] = 'زمان‌بندی نشده است';
            }
        }

        return $jobs;
    }

    /**
     * Get last execution time for a job
     * Converts MySQL datetime string to WordPress timezone-aware timestamp
     * Uses the same timezone as current_time() for accurate comparison
     *
     * @param string $hook Cron hook name
     * @return int|false Timestamp or false
     */
    private function get_last_job_execution($hook)
    {
        // For anar_sync_products, use the stored option
        if ($hook === 'anar_sync_products') {
            $last_sync_time = get_option('anar_last_regular_sync_time');
            if ($last_sync_time) {
                // Convert MySQL datetime (stored in WordPress timezone) to UTC timestamp
                // get_gmt_from_date converts WordPress timezone to GMT/UTC
                $gmt_time = get_gmt_from_date($last_sync_time);
                if ($gmt_time) {
                    // Create DateTime object with explicit format and UTC timezone
                    // This ensures correct parsing regardless of server timezone settings
                    $date = \DateTime::createFromFormat('Y-m-d H:i:s', $gmt_time, new \DateTimeZone('UTC'));
                    if ($date) {
                        return $date->getTimestamp();
                    }
                }
                // Fallback: try direct conversion
                return strtotime($last_sync_time);
            }
        }
        
        // For anar_sync_outdated_products, check logs or use a similar approach
        // We can check the last sync time from product meta or logs
        // For now, we'll check if there's a stored option
        if ($hook === 'anar_sync_outdated_products') {
            $last_sync_time = get_option('anar_last_outdated_sync_time');
            if ($last_sync_time) {
                // Convert MySQL datetime to UTC timestamp
                $gmt_time = get_gmt_from_date($last_sync_time);
                if ($gmt_time) {
                    // Create DateTime object with explicit format and UTC timezone
                    $date = \DateTime::createFromFormat('Y-m-d H:i:s', $gmt_time, new \DateTimeZone('UTC'));
                    if ($date) {
                        return $date->getTimestamp();
                    }
                }
                // Fallback: try direct conversion
                return strtotime($last_sync_time);
            }
        }
        
        // Fallback: check cron array for last execution (if function exists)
        if (function_exists('_get_cron_array')) {
            $cron_array = _get_cron_array();
            if ($cron_array) {
                $last_execution = 0;
                foreach ($cron_array as $timestamp => $cron) {
                    if (isset($cron[$hook])) {
                        if ($timestamp > $last_execution) {
                            $last_execution = $timestamp;
                        }
                    }
                }
                return $last_execution > 0 ? $last_execution : false;
            }
        }
        
        return false;
    }

    /**
     * Test if WordPress cron can be spawned via HTTP request
     * This is critical because WordPress cron relies on HTTP requests to trigger scheduled events
     *
     * @return array
     */
    private function test_cron_spawn()
    {
        $results = [
            'success' => false,
            'url' => '',
            'status_code' => null,
            'response_time' => null,
            'error' => null,
            'error_code' => null,
            'status' => 'unknown',
            'message' => ''
        ];

        // Test wp-cron.php endpoint
        $cron_url = site_url('wp-cron.php?doing_wp_cron');
        $results['url'] = $cron_url;

        $start_time = microtime(true);

        try {
            // Make HTTP request to cron endpoint
            // Use blocking mode for testing to get actual response code
            $response = wp_remote_get($cron_url, [
                'timeout' => 10,
                'sslverify' => false,
                'blocking' => true, // Blocking for testing to get response code
                'headers' => [
                    'User-Agent' => 'WordPress/' . get_bloginfo('version') . '; ' . home_url()
                ]
            ]);

            $end_time = microtime(true);
            $response_time = round(($end_time - $start_time) * 1000, 2); // Convert to milliseconds
            $results['response_time'] = $response_time;

            if (is_wp_error($response)) {
                $results['error'] = $response->get_error_message();
                $results['error_code'] = $response->get_error_code();
                $results['status'] = 'error';
                
                // Check for specific error types
                if (strpos($response->get_error_message(), 'timeout') !== false || 
                    strpos($response->get_error_message(), 'timed out') !== false) {
                    $results['message'] = 'Timeout: درخواست HTTP به wp-cron.php زمان‌بر شد';
                } elseif (strpos($response->get_error_message(), 'curl') !== false) {
                    $results['message'] = 'خطای cURL: ' . $response->get_error_message();
                } else {
                    $results['message'] = 'خطا در اتصال: ' . $response->get_error_message();
                }
            } else {
                $status_code = wp_remote_retrieve_response_code($response);
                $results['status_code'] = $status_code;

                // Check response code
                if ($status_code >= 200 && $status_code < 300) {
                    $results['success'] = true;
                    $results['status'] = 'success';
                    $results['message'] = sprintf('موفق - کد پاسخ: %d (زمان پاسخ: %s ms)', $status_code, $response_time);
                } elseif ($status_code === 403) {
                    $results['status'] = 'error';
                    $results['message'] = 'خطای 403: دسترسی به wp-cron.php مسدود است (احتمالاً توسط .htaccess یا فایروال)';
                } elseif ($status_code === 401) {
                    $results['status'] = 'error';
                    $results['message'] = 'خطای 401: نیاز به احراز هویت برای دسترسی به wp-cron.php';
                } elseif ($status_code === 404) {
                    $results['status'] = 'error';
                    $results['message'] = 'خطای 404: فایل wp-cron.php یافت نشد';
                } elseif ($status_code >= 500) {
                    $results['status'] = 'error';
                    $results['message'] = sprintf('خطای %d: خطای سرور در wp-cron.php', $status_code);
                } else {
                    $results['status'] = 'warning';
                    $results['message'] = sprintf('کد پاسخ غیرمنتظره: %d', $status_code);
                }
            }
        } catch (\Exception $e) {
            $end_time = microtime(true);
            $results['response_time'] = round(($end_time - $start_time) * 1000, 2);
            $results['error'] = $e->getMessage();
            $results['error_code'] = 'exception';
            $results['status'] = 'error';
            $results['message'] = 'خطا در تست: ' . $e->getMessage();
        }

        return $results;
    }

    /**
     * Determine if cron is working (simple yes/no)
     * Checks important jobs and cron spawn test
     *
     * @param array $results
     * @return bool
     */
    private function is_cron_working($results)
    {
        // Check cron spawn test first - if HTTP request fails, cron can't work
        if (isset($results['cron_spawn_test']) && $results['cron_spawn_test']['status'] === 'error') {
            return false;
        }
        
        // Check important jobs - this is the primary indicator
        foreach ($results['important_jobs'] as $job) {
            // If job is not scheduled, it's not working
            if (!$job['scheduled']) {
                return false;
            }
            
            // If job has error status (hasn't run recently), it's not working
            if ($job['status'] === 'error') {
                return false;
            }
        }
        
        // If we get here, all important jobs are scheduled and running
        return true;
    }

    /**
     * Format time difference in human-readable format
     *
     * @param int $seconds
     * @return string
     */
    private function format_time_diff($seconds)
    {
        if ($seconds < 60) {
            return sprintf('%d ثانیه', $seconds);
        } elseif ($seconds < 3600) {
            $minutes = floor($seconds / 60);
            return sprintf('%d دقیقه', $minutes);
        } elseif ($seconds < 86400) {
            $hours = floor($seconds / 3600);
            return sprintf('%d ساعت', $hours);
        } else {
            $days = floor($seconds / 86400);
            return sprintf('%d روز', $days);
        }
    }
}


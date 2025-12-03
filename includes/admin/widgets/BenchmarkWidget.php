<?php

namespace Anar\Admin\Widgets;

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

/**
 * Benchmark Report Widget
 * Tests host performance (disk read/write, network speed)
 */
class BenchmarkWidget extends AbstractReportWidget
{
    /**
     * Benchmark configuration
     */
    private const DISK_TEST_SIZE_MB = 5;            // Size of each disk test file
    private const DISK_TEST_ITERATIONS = 3;         // Number of reads/writes to average
    private const NETWORK_TEST_ITERATIONS = 3;      // Number of network requests per URL
    private const NETWORK_TIMEOUT = 8;              // Timeout per network request (seconds)

    protected function init()
    {
        $this->widget_id = 'anar-benchmark-widget';
        $this->title = 'بنچمارک هاست';
        $this->description = 'سنجش عملکرد هاست (خواندن/نوشتن دیسک و سرعت شبکه)';
        $this->icon = '<span class="dashicons dashicons-performance"></span>';
        $this->ajax_action = 'anar_get_benchmark';
        $this->button_text = 'اجرای بنچمارک';
        $this->button_class = 'button-primary';

        // Register AJAX handler
        add_action('wp_ajax_' . $this->ajax_action, [$this, 'handle_ajax']);
    }

    protected function get_report_data()
    {
        $results = [
            'disk_read' => $this->benchmark_disk_read(),
            'disk_write' => $this->benchmark_disk_write(),
            'network' => $this->benchmark_network(),
            'database' => $this->benchmark_database(),
            'overall_score' => 0,
            'timestamp' => current_time('Y-m-d H:i:s')
        ];

        // Calculate overall score (0-10)
        $scores = [];
        if ($results['disk_read']['success']) {
            $scores[] = $results['disk_read']['score'];
        }
        if ($results['disk_write']['success']) {
            $scores[] = $results['disk_write']['score'];
        }
        if ($results['network']['success']) {
            $scores[] = $results['network']['score'];
        }
        if ($results['database']['success']) {
            $scores[] = $results['database']['score'];
        }

        if (!empty($scores)) {
            $results['overall_score'] = round(array_sum($scores) / count($scores), 2);
        }

        return $results;
    }

    /**
     * Benchmark disk read speed
     */
    private function benchmark_disk_read()
    {
        $test_size = self::DISK_TEST_SIZE_MB * 1024 * 1024;
        $test_file = sys_get_temp_dir() . '/anar_benchmark_read_' . uniqid() . '.tmp';
        $iterations = self::DISK_TEST_ITERATIONS;

        try {
            // Create test file once
            $data = str_repeat('A', $test_size);
            if (file_put_contents($test_file, $data, LOCK_EX) !== $test_size) {
                throw new \RuntimeException('Failed to create test file');
            }

            $durations = [];
            for ($i = 0; $i < $iterations; $i++) {
                $start_time = microtime(true);
                $read_data = file_get_contents($test_file);
                $end_time = microtime(true);

                if ($read_data === false || strlen($read_data) !== $test_size) {
                    throw new \RuntimeException('Failed to read test file');
                }

                $durations[] = $end_time - $start_time;
            }

            @unlink($test_file);

            if (empty($durations)) {
                throw new \RuntimeException('No valid disk read samples');
            }

            $avg_duration = array_sum($durations) / count($durations);
            $best_duration = min($durations);
            $speed_mbps = ($test_size / 1024 / 1024) / $avg_duration;
            $peak_speed_mbps = ($test_size / 1024 / 1024) / $best_duration;

            // Score calculation: 0-10 scale
            // Excellent: >50 MB/s = 10
            // Good: 20-50 MB/s = 7-9
            // Average: 10-20 MB/s = 5-7
            // Poor: 5-10 MB/s = 3-5
            // Very Poor: <5 MB/s = 0-3
            $score = min(10, max(0, ($speed_mbps / 5) * 2));

            return [
                'success' => true,
                'speed_mbps' => round($speed_mbps, 2),
                'peak_speed_mbps' => round($peak_speed_mbps, 2),
                'duration' => round($avg_duration, 4),
                'size_mb' => round($test_size / 1024 / 1024, 2),
                'samples' => count($durations),
                'score' => round($score, 2)
            ];
        } catch (\Exception $e) {
            @unlink($test_file);
            return [
                'success' => false,
                'error' => $e->getMessage(),
                'speed_mbps' => 0,
                'score' => 0,
                'samples' => 0
            ];
        }
    }

    /**
     * Benchmark disk write speed
     */
    private function benchmark_disk_write()
    {
        $test_size = self::DISK_TEST_SIZE_MB * 1024 * 1024;
        $iterations = self::DISK_TEST_ITERATIONS;

        try {
            $data = str_repeat('B', $test_size);
            $durations = [];

            for ($i = 0; $i < $iterations; $i++) {
                $test_file = sys_get_temp_dir() . '/anar_benchmark_write_' . uniqid('', true) . '.tmp';

                $start_time = microtime(true);
                $bytes_written = file_put_contents($test_file, $data, LOCK_EX);
                $end_time = microtime(true);

                @unlink($test_file);

                if ($bytes_written === false || $bytes_written !== $test_size) {
                    throw new \RuntimeException('Failed to write test file');
                }

                $durations[] = $end_time - $start_time;

                // Short pause between writes to reduce cache effects
                usleep(50000); // 50ms
            }

            if (empty($durations)) {
                throw new \RuntimeException('No valid disk write samples');
            }

            $avg_duration = array_sum($durations) / count($durations);
            $best_duration = min($durations);
            $speed_mbps = ($test_size / 1024 / 1024) / $avg_duration;
            $peak_speed_mbps = ($test_size / 1024 / 1024) / $best_duration;

            // Score calculation: 0-10 scale
            // Excellent: >30 MB/s = 10
            // Good: 15-30 MB/s = 7-9
            // Average: 8-15 MB/s = 5-7
            // Poor: 3-8 MB/s = 3-5
            // Very Poor: <3 MB/s = 0-3
            $score = min(10, max(0, ($speed_mbps / 3) * 2));

            return [
                'success' => true,
                'speed_mbps' => round($speed_mbps, 2),
                'peak_speed_mbps' => round($peak_speed_mbps, 2),
                'duration' => round($avg_duration, 4),
                'size_mb' => round($test_size / 1024 / 1024, 2),
                'samples' => count($durations),
                'score' => round($score, 2)
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
                'speed_mbps' => 0,
                'score' => 0,
                'samples' => 0
            ];
        }
    }

    /**
     * Benchmark network speed
     * Uses large files (~4MB) to get accurate download speed measurements
     * Small files don't reach actual download speed, so we need larger files
     */
    private function benchmark_network()
    {
        // Test network with large files (~4MB each) for accurate speed measurement
        // Using 2 reliable direct download URLs from public sources
        // Large files are needed to reach actual download speed (small files don't reach steady state)
        $test_urls = [
            // Wikipedia Commons - Large high-resolution image (~4-5MB)
            // This is a well-known large image file that's reliably available
            'https://wordpress.org/latest.zip',
//            'https://upload.wikimedia.org/wikipedia/commons/7/77/Big_Buck_Bunny_poster_big.jpg',
            // Wikipedia Commons - Another large image file (~4MB)
            // Using a different large image for variety
//            'https://upload.wikimedia.org/wikipedia/commons/thumb/f/ff/Pizigani_1367_Chart_10MB.jpg/4096px-Pizigani_1367_Chart_10MB.jpg',
        ];
        
        // Note: If these URLs don't work or aren't the right size, you can:
        // 1. Find large files on Wikipedia Commons (search for files >3MB)
        // 2. Use GitHub releases with large binary files
        // 3. Use test file services like speedtest.net or similar
        // 4. Host your own test files on a CDN
        
        $samples = [];
        $url_results = []; // Track results per URL

        foreach ($test_urls as $url) {
            $url_samples = [];
            
            // For large files, we only need 1-2 iterations per URL since they take longer
            // This gives us enough samples while keeping test time reasonable
            $iterations_per_url = 2;
            
            for ($i = 0; $i < $iterations_per_url; $i++) {
                try {
                    $start_time = microtime(true);

                    // Use WordPress HTTP API with longer timeout for large files
                    // Large files need more time to reach actual download speed
                    $response = wp_remote_get($url, [
                        'timeout' => 30, // Longer timeout for 4MB files
                        'sslverify' => false,
                        'user-agent' => 'WordPress/' . get_bloginfo('version') . '; ' . home_url(),
                        'stream' => false, // Download full file
                        'redirection' => 5 // Allow redirects
                    ]);

                    $end_time = microtime(true);

                    if (is_wp_error($response)) {
                        continue;
                    }

                    $body = wp_remote_retrieve_body($response);
                    $size = strlen($body);

                    // For large files, we expect at least 1MB to be valid
                    // Some URLs might redirect or return small files, skip those
                    if ($size < 1024 * 1024) { // Less than 1MB
                        continue;
                    }

                    $duration = $end_time - $start_time;
                    if ($duration <= 0) {
                        continue;
                    }

                    $speed_mbps = ($size / 1024 / 1024) / $duration;
                    $sample = [
                        'speed_mbps' => $speed_mbps,
                        'duration' => $duration,
                        'size_kb' => $size / 1024,
                        'url' => $url
                    ];
                    
                    $samples[] = $sample;
                    $url_samples[] = $sample;

                    // Longer pause between large file downloads to avoid server throttling
                    usleep(200000); // 200ms
                } catch (\Exception $e) {
                    continue;
                }
            }
            
            // Calculate average for this URL
            if (!empty($url_samples)) {
                $url_speeds = array_column($url_samples, 'speed_mbps');
                $url_results[] = [
                    'url' => $url,
                    'avg_speed_mbps' => array_sum($url_speeds) / count($url_speeds),
                    'samples' => count($url_samples),
                    'avg_duration' => array_sum(array_column($url_samples, 'duration')) / count($url_samples),
                    'avg_size_kb' => array_sum(array_column($url_samples, 'size_kb')) / count($url_samples)
                ];
            }
        }

        if (!empty($samples)) {
            // Calculate overall average across all samples from all URLs
            $speeds = array_column($samples, 'speed_mbps');
            $avg_speed = array_sum($speeds) / count($speeds);
            $best_sample = $samples[array_search(max($speeds), $speeds, true)];
            $worst_sample = $samples[array_search(min($speeds), $speeds, true)];

            // Score calculation: 0-10 scale based on average
            // Excellent: >5 MB/s = 10
            // Good: 2-5 MB/s = 7-9
            // Average: 1-2 MB/s = 5-7
            // Poor: 0.5-1 MB/s = 3-5
            // Very Poor: <0.5 MB/s = 0-3
            $score = min(10, max(0, ($avg_speed / 0.5) * 2));

            return [
                'success' => true,
                'speed_mbps' => round($avg_speed, 2),
                'peak_speed_mbps' => round($best_sample['speed_mbps'], 2),
                'min_speed_mbps' => round($worst_sample['speed_mbps'], 2),
                'duration' => round(array_sum(array_column($samples, 'duration')) / count($samples), 4),
                'size_kb' => round(array_sum(array_column($samples, 'size_kb')) / count($samples), 2),
                'url' => $best_sample['url'], // Best performing URL
                'samples' => count($samples),
                'urls_tested' => count($url_results),
                'url_results' => $url_results, // Individual URL results
                'score' => round($score, 2)
            ];
        }

        // Fallback: test localhost connection
        try {
            $start_time = microtime(true);
            $response = wp_remote_get(home_url(), ['timeout' => 5]);
            $end_time = microtime(true);
            
            if (!is_wp_error($response)) {
                $body = wp_remote_retrieve_body($response);
                $size = strlen($body);
                $duration = $end_time - $start_time;
                $speed_mbps = ($size / 1024 / 1024) / $duration;
                
                // Score calculation for localhost (usually very fast)
                $score = min(10, max(8, ($speed_mbps / 10) * 2));
                
                return [
                    'success' => true,
                    'speed_mbps' => round($speed_mbps, 2),
                    'peak_speed_mbps' => round($speed_mbps, 2),
                    'duration' => round($duration, 4),
                    'size_kb' => round($size / 1024, 2),
                    'url' => 'localhost',
                    'samples' => 1,
                    'score' => round($score, 2)
                ];
            }
        } catch (\Exception $e) {
            // Ignore
        }
        
        return [
            'success' => false,
            'error' => 'Unable to test network speed',
            'speed_mbps' => 0,
            'score' => 0,
            'samples' => 0
        ];
    }

    /**
     * Benchmark database performance (read/write and complex queries)
     */
    private function benchmark_database()
    {
        global $wpdb;
        
        $iterations = 3;
        $samples = [
            'simple_read' => [],
            'write' => [],
            'complex_query' => []
        ];

        try {
            // Test 1: Simple SELECT queries (read performance)
            for ($i = 0; $i < $iterations; $i++) {
                $start_time = microtime(true);
                
                // Simple query: get post count
                $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = 'post'");
                
                $end_time = microtime(true);
                $duration = $end_time - $start_time;
                
                if ($duration > 0) {
                    $samples['simple_read'][] = $duration;
                }
                
                usleep(10000); // 10ms pause
            }

            // Test 2: Write performance (INSERT/UPDATE)
            $test_table = $wpdb->prefix . 'anar_benchmark_test';
            
            // Create temporary test table
            $wpdb->query("CREATE TEMPORARY TABLE IF NOT EXISTS {$test_table} (
                id INT AUTO_INCREMENT PRIMARY KEY,
                test_data VARCHAR(255),
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            )");
            
            for ($i = 0; $i < $iterations; $i++) {
                $start_time = microtime(true);
                
                // Insert test data
                $wpdb->insert($test_table, [
                    'test_data' => str_repeat('B', 100) // 100 bytes
                ]);
                
                // Update test data
                $wpdb->update($test_table, [
                    'test_data' => str_repeat('C', 100)
                ], ['id' => $wpdb->insert_id]);
                
                $end_time = microtime(true);
                $duration = $end_time - $start_time;
                
                if ($duration > 0) {
                    $samples['write'][] = $duration;
                }
                
                usleep(10000); // 10ms pause
            }
            
            // Clean up test table
            $wpdb->query("DROP TEMPORARY TABLE IF EXISTS {$test_table}");

            // Test 3: Complex JOIN query (realistic workload)
            for ($i = 0; $i < $iterations; $i++) {
                $start_time = microtime(true);
                
                // Complex query: JOIN posts with postmeta and count
                $wpdb->get_results("
                    SELECT p.ID, p.post_title, COUNT(pm.meta_id) as meta_count
                    FROM {$wpdb->posts} p
                    LEFT JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
                    WHERE p.post_type = 'product'
                      AND p.post_status = 'publish'
                    GROUP BY p.ID
                    LIMIT 100
                ");
                
                $end_time = microtime(true);
                $duration = $end_time - $start_time;
                
                if ($duration > 0) {
                    $samples['complex_query'][] = $duration;
                }
                
                usleep(10000); // 10ms pause
            }

            // Calculate averages
            $avg_simple_read = !empty($samples['simple_read']) 
                ? array_sum($samples['simple_read']) / count($samples['simple_read']) 
                : 0;
            $avg_write = !empty($samples['write']) 
                ? array_sum($samples['write']) / count($samples['write']) 
                : 0;
            $avg_complex = !empty($samples['complex_query']) 
                ? array_sum($samples['complex_query']) / count($samples['complex_query']) 
                : 0;

            // Calculate overall performance score
            // Lower times = better performance
            // Simple read: <10ms = 10, 10-50ms = 7-9, 50-100ms = 5-7, 100-200ms = 3-5, >200ms = 0-3
            $read_score = $avg_simple_read > 0 
                ? min(10, max(0, 10 - ($avg_simple_read * 1000 / 20))) 
                : 0;
            
            // Write: <20ms = 10, 20-100ms = 7-9, 100-200ms = 5-7, 200-500ms = 3-5, >500ms = 0-3
            $write_score = $avg_write > 0 
                ? min(10, max(0, 10 - ($avg_write * 1000 / 50))) 
                : 0;
            
            // Complex query: <100ms = 10, 100-300ms = 7-9, 300-500ms = 5-7, 500-1000ms = 3-5, >1000ms = 0-3
            $complex_score = $avg_complex > 0 
                ? min(10, max(0, 10 - ($avg_complex * 1000 / 100))) 
                : 0;

            // Overall score is average of all three
            $valid_scores = array_filter([$read_score, $write_score, $complex_score]);
            $overall_score = !empty($valid_scores) 
                ? array_sum($valid_scores) / count($valid_scores) 
                : 0;

            return [
                'success' => true,
                'simple_read_ms' => round($avg_simple_read * 1000, 2),
                'write_ms' => round($avg_write * 1000, 2),
                'complex_query_ms' => round($avg_complex * 1000, 2),
                'read_score' => round($read_score, 2),
                'write_score' => round($write_score, 2),
                'complex_score' => round($complex_score, 2),
                'samples' => [
                    'simple_read' => count($samples['simple_read']),
                    'write' => count($samples['write']),
                    'complex_query' => count($samples['complex_query'])
                ],
                'score' => round($overall_score, 2)
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
                'score' => 0
            ];
        }
    }
}


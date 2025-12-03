<?php

namespace Anar\Admin\Widgets;

use Anar\Core\Activation;
use Anar\Init\Checks;

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

/**
 * System Diagnostics
 * Runs multiple health checks sequentially to diagnose system issues
 */
class SystemDiagnostics
{
    private static $instance = null;
    private $tests = [];

    public static function get_instance()
    {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct()
    {
        $this->register_tests();
        add_action('wp_ajax_anar_run_system_diagnostics', [$this, 'handle_ajax']);
    }

    /**
     * Register all available tests
     */
    private function register_tests()
    {
        $this->tests = [
            'plugin_activation' => [
                'name' => 'فعال‌سازی افزونه',
                'method' => 'test_plugin_activation'
            ],
            'api_health' => [
                'name' => 'سلامت API',
                'method' => 'test_api_health'
            ],
            'cron_health' => [
                'name' => 'سلامت Cron',
                'method' => 'test_cron_health'
            ],
            'shipping_rates' => [
                'name' => 'روش‌های حمل و نقل',
                'method' => 'test_shipping_rates'
            ],
            'benchmark' => [
                'name' => 'بنچمارک هاست',
                'method' => 'test_benchmark'
            ],
        ];
    }

    /**
     * Get all registered tests
     */
    public function get_tests()
    {
        return $this->tests;
    }

    /**
     * Handle AJAX request
     */
    public function handle_ajax()
    {
        // Verify nonce
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'awca_ajax_nonce')) {
            wp_send_json_error(['message' => 'Security check failed']);
            return;
        }

        // Check permissions
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Insufficient permissions']);
            return;
        }

        // Get test ID from request
        $test_id = isset($_POST['test_id']) ? sanitize_text_field($_POST['test_id']) : null;

        if ($test_id && isset($this->tests[$test_id])) {
            // Run single test
            $result = $this->run_test($test_id);
            wp_send_json_success($result);
        } else {
            // Return list of all tests
            $test_list = [];
            foreach ($this->tests as $id => $test) {
                $test_list[] = [
                    'test_id' => $id,
                    'name' => $test['name']
                ];
            }
            wp_send_json_success(['tests' => $test_list]);
        }
    }

    /**
     * Run a specific test
     *
     * @param string $test_id Test identifier
     * @return array Test result
     */
    private function run_test($test_id)
    {
        if (!isset($this->tests[$test_id])) {
            return [
                'test_id' => $test_id,
                'name' => 'Unknown Test',
                'passed' => false,
                'message' => 'Test not found',
                'details' => []
            ];
        }

        $test = $this->tests[$test_id];
        $method = $test['method'];

        try {
            if (method_exists($this, $method)) {
                return $this->$method();
            } else {
                return [
                    'test_id' => $test_id,
                    'name' => $test['name'],
                    'passed' => false,
                    'message' => 'Test method not found',
                    'details' => []
                ];
            }
        } catch (\Exception $e) {
            return [
                'test_id' => $test_id,
                'name' => $test['name'],
                'passed' => false,
                'message' => 'خطا در اجرای تست: ' . $e->getMessage(),
                'details' => []
            ];
        }
    }

    /**
     * Test 1: Plugin Activation
     * Checks if Activation::validate_token() returns true
     */
    private function test_plugin_activation()
    {
        $is_valid = Activation::validate_token();

        return [
            'test_id' => 'plugin_activation',
            'name' => 'فعال‌سازی افزونه',
            'passed' => $is_valid,
            'message' => $is_valid 
                ? 'افزونه با موفقیت فعال است' 
                : 'افزونه فعال نیست. لطفاً توکن را بررسی کنید.',
            'details' => [
                'token_validation' => get_option('_anar_token_validation', 'unknown')
            ]
        ];
    }

    /**
     * Test 2: API Health
     * Checks if all API endpoints are working
     */
    private function test_api_health()
    {
        try {
            $api_widget = new ApiHealthWidget();
            $data = $api_widget->get_data();
        } catch (\Exception $e) {
            return [
                'test_id' => 'api_health',
                'name' => 'سلامت API',
                'passed' => false,
                'message' => 'خطا در اجرای تست API: ' . $e->getMessage(),
                'details' => []
            ];
        }

        $passed = $data['total_failed'] === 0 && $data['total_success'] > 0;

        $message = $passed
            ? sprintf('همه %d API ها با موفقیت کار می‌کنند', $data['total_success'])
            : sprintf('%d از %d API ها با خطا مواجه شدند', $data['total_failed'], $data['total_tested']);

        return [
            'test_id' => 'api_health',
            'name' => 'سلامت API',
            'passed' => $passed,
            'message' => $message,
            'details' => [
                'total_tested' => $data['total_tested'],
                'total_success' => $data['total_success'],
                'total_failed' => $data['total_failed'],
                'health_score' => $data['health_score'] ?? 0
            ]
        ];
    }

    /**
     * Test 3: Benchmark
     * Checks if overall score >= 6/10 and each sub-test >= 4/10
     */
    private function test_benchmark()
    {
        try {
            $benchmark_widget = new BenchmarkWidget();
            $data = $benchmark_widget->get_data();
        } catch (\Exception $e) {
            return [
                'test_id' => 'benchmark',
                'name' => 'بنچمارک هاست',
                'passed' => false,
                'message' => 'خطا در اجرای تست بنچمارک: ' . $e->getMessage(),
                'details' => []
            ];
        }

        $overall_score = $data['overall_score'] ?? 0;
        $sub_tests = [];

        // Check each sub-test
        $sub_test_scores = [];
        if (isset($data['disk_read']) && $data['disk_read']['success']) {
            $score = $data['disk_read']['score'] ?? 0;
            $sub_test_scores['disk_read'] = $score;
            $sub_tests[] = [
                'name' => 'خواندن دیسک',
                'score' => $score,
                'passed' => $score >= 4
            ];
        }

        if (isset($data['disk_write']) && $data['disk_write']['success']) {
            $score = $data['disk_write']['score'] ?? 0;
            $sub_test_scores['disk_write'] = $score;
            $sub_tests[] = [
                'name' => 'نوشتن دیسک',
                'score' => $score,
                'passed' => $score >= 4
            ];
        }

        if (isset($data['network']) && $data['network']['success']) {
            $score = $data['network']['score'] ?? 0;
            $sub_test_scores['network'] = $score;
            $sub_tests[] = [
                'name' => 'شبکه',
                'score' => $score,
                'passed' => $score >= 4
            ];
        }

        if (isset($data['database']) && $data['database']['success']) {
            $score = $data['database']['score'] ?? 0;
            $sub_test_scores['database'] = $score;
            $sub_tests[] = [
                'name' => 'پایگاه داده',
                'score' => $score,
                'passed' => $score >= 4
            ];
        }

        // Check if overall score >= 6
        $overall_passed = $overall_score >= 6;

        // Check if all sub-tests >= 4
        $all_sub_tests_passed = true;
        foreach ($sub_test_scores as $score) {
            if ($score < 4) {
                $all_sub_tests_passed = false;
                break;
            }
        }

        $passed = $overall_passed && $all_sub_tests_passed;

        $message = $passed
            ? sprintf('امتیاز کلی: %s/10 - همه تست‌ها موفق بودند', $overall_score)
            : sprintf('امتیاز کلی: %s/10 - برخی تست‌ها زیر حد انتظار هستند', $overall_score);

        return [
            'test_id' => 'benchmark',
            'name' => 'بنچمارک هاست',
            'passed' => $passed,
            'message' => $message,
            'details' => [
                'overall_score' => $overall_score,
                'sub_tests' => $sub_tests,
                'overall_passed' => $overall_passed,
                'all_sub_tests_passed' => $all_sub_tests_passed
            ]
        ];
    }

    /**
     * Test 4: Cron Health
     * Checks if cron is working (is_working === true)
     */
    private function test_cron_health()
    {
        try {
            $cron_widget = new CronHealthWidget();
            $data = $cron_widget->get_data();
        } catch (\Exception $e) {
            return [
                'test_id' => 'cron_health',
                'name' => 'سلامت Cron',
                'passed' => false,
                'message' => 'خطا در اجرای تست Cron: ' . $e->getMessage(),
                'details' => []
            ];
        }

        $is_working = isset($data['is_working']) && $data['is_working'] === true;

        $message = $is_working
            ? 'Cron به درستی کار می‌کند'
            : 'Cron با مشکل مواجه است. لطفاً وضعیت Cron را بررسی کنید.';

        return [
            'test_id' => 'cron_health',
            'name' => 'سلامت Cron',
            'passed' => $is_working,
            'message' => $message,
            'details' => [
                'is_working' => $is_working,
                'wp_cron_disabled' => $data['wp_cron_disabled'] ?? null,
                'cron_spawn_test' => $data['cron_spawn_test'] ?? null
            ]
        ];
    }

    /**
     * Test 5: Shipping Rates
     * Checks if at least one shipping method exists
     */
    private function test_shipping_rates()
    {
        // Skip if ship-to-stock is enabled
        if (function_exists('anar_is_ship_to_stock_enabled') && anar_is_ship_to_stock_enabled()) {
            return [
                'test_id' => 'shipping_rates',
                'name' => 'روش‌های حمل و نقل',
                'passed' => true,
                'message' => 'این تست به دلیل فعال بودن Ship-to-Stock رد شده است',
                'details' => [
                    'skipped' => true,
                    'reason' => 'ship_to_stock_enabled'
                ]
            ];
        }

        // Check shipping zones (reusing logic from Checks::admin_notice_missing_shipping_rates)
        $zones = \WC_Shipping_Zones::get_zones();
        $has_shipping_method = false;

        // Check default zone if no zones are set
        if (empty($zones)) {
            $default_zone = new \WC_Shipping_Zone(0);
            $has_shipping_method = !empty($default_zone->get_shipping_methods(true));
        } else {
            $has_shipping_method = true; // If we have zones, we have shipping methods
        }

        $message = $has_shipping_method
            ? 'حداقل یک روش حمل و نقل تنظیم شده است'
            : 'هیچ روش حمل و نقلی تنظیم نشده است. لطفاً روش حمل و نقل اضافه کنید.';

        return [
            'test_id' => 'shipping_rates',
            'name' => 'روش‌های حمل و نقل',
            'passed' => $has_shipping_method,
            'message' => $message,
            'details' => [
                'has_shipping_method' => $has_shipping_method,
                'zones_count' => count($zones)
            ]
        ];
    }
}


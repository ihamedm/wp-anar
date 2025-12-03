<?php

namespace Anar\Admin;

use Anar\Admin\Tools\DatabaseTools;
use Anar\Admin\Tools\SqlProductsTools;
use Anar\Admin\Tools\SyncTools;
use Anar\Core\Logger;
use Anar\Admin\Widgets\WidgetManager;
use Anar\Admin\Widgets\SystemDiagnostics;

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

/**
 * Class Tools
 * Handles various admin tools and utilities
 */
class Tools
{
    private static $instance;

    public static function get_instance()
    {
        if (!isset(self::$instance)) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function __construct()
    {
        
        // Initialize widget manager to register AJAX handlers
        $this->init_widgets();
        $this->init_tools();
        
        // Enqueue status tools scripts
        add_action('admin_enqueue_scripts', [$this, 'enqueue_status_tools_assets']);
        
        // Register AJAX handler for legacy single product creation
        add_action('wp_ajax_awca_create_single_product_legacy', [$this, 'create_single_product_legacy']);
        
        // Register admin_init handler for run-cron action
        add_action('admin_init', [$this, 'handle_run_cron_action']);

    }

    /**
     * Initialize widgets to register their AJAX handlers
     */
    private function init_widgets()
    {
        // Initialize widget manager to register AJAX handlers
        WidgetManager::get_instance();
        
        // Initialize system diagnostics to register AJAX handlers
        SystemDiagnostics::get_instance();
    }

    private function init_tools(){
        SqlProductsTools::get_instance();
        DatabaseTools::get_instance();
        SyncTools::get_instance();
    }

    /**
     * Enqueue status tools assets on the status page
     */
    public function enqueue_status_tools_assets($hook)
    {
        // Check if we're on the tools page using $_GET parameter (more reliable than hook name)
        if (!isset($_GET['page']) || $_GET['page'] !== 'tools') {
            return;
        }

        // Check if we're on the status tab
        $active_tab = $_GET['tab'] ?? 'tools';
        if ($active_tab !== 'status') {
            return;
        }

        // Enqueue status-tools script
        wp_enqueue_script(
            'anar-status-tools',
            ANAR_PLUGIN_URL . '/assets/dist/status-tools.min.js',
            ['jquery'],
            ANAR_PLUGIN_VERSION,
            true
        );

        wp_enqueue_style(
          'anar-status-tools',
          ANAR_PLUGIN_URL . '/assets/css/status-tools.css',
          [],
          ANAR_PLUGIN_VERSION
        );

        // Localize script with AJAX data
        wp_localize_script('anar-status-tools', 'awca_ajax_object', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('awca_ajax_nonce'),
        ));

        // Enqueue import.css for modal styling
        wp_enqueue_style(
            'anar-import',
            ANAR_PLUGIN_URL . '/assets/css/import.css',
            [],
            ANAR_PLUGIN_VERSION
        );
    }

    /**
     * AJAX handler for creating a single product using legacy import system
     */
    public function create_single_product_legacy()
    {
        // Verify nonce
        check_ajax_referer('awca_ajax_nonce', 'security');

        // Check user capabilities
        if (!current_user_can('manage_woocommerce')) {
            wp_send_json_error([
                'message' => __('شما مجوز انجام این عملیات را ندارید.', 'wp-anar'),
            ], 403);
        }

        // Sanitize and get SKU from POST
        $anar_sku = isset($_POST['anar_sku']) ? sanitize_text_field(wp_unslash($_POST['anar_sku'])) : '';

        if (empty($anar_sku)) {
            wp_send_json_error([
                'message' => __('لطفاً شناسه SKU انار را وارد کنید.', 'wp-anar'),
            ]);
        }

        // Call legacy function
        $result = anar_create_single_product_legacy($anar_sku);

        if (is_wp_error($result)) {
            wp_send_json_error([
                'message' => $result->get_error_message(),
            ]);
        }

        wp_send_json_success([
            'message' => $result['created'] 
                ? __('محصول با موفقیت ساخته شد.', 'wp-anar')
                : __('محصول با موفقیت به‌روزرسانی شد.', 'wp-anar'),
            'product_id' => $result['product_id'] ?? 0,
            'created' => $result['created'] ?? false,
            'product_sku' => $result['product_sku'] ?? '',
        ]);
    }

    /**
     * Handle run-cron action from URL (similar to Crontrol plugin)
     * Generic handler that works with any Anar cron hook
     * 
     * URL format: ?page=tools&tab=status&anar_action=run-cron&anar_id=hook_name&_wpnonce=...
     */
    public function handle_run_cron_action() {
        // Check if this is our run-cron action
        if (!isset($_GET['anar_action']) || $_GET['anar_action'] !== 'run-cron') {
            return;
        }

        // Check if it's for an Anar hook
        if (!isset($_GET['anar_id']) || empty($_GET['anar_id'])) {
            return;
        }

        $hook = sanitize_text_field($_GET['anar_id']);

        // Verify it's an Anar hook (security check)
        if (strpos($hook, 'anar_') !== 0) {
            wp_die('Invalid hook name', 'Security Error', ['response' => 403]);
        }

        // Verify nonce
        if (!isset($_GET['_wpnonce']) || !wp_verify_nonce($_GET['_wpnonce'], 'anar_run_cron_' . $hook)) {
            wp_die('Security check failed', 'Security Error', ['response' => 403]);
        }

        // Check user capabilities
        if (!current_user_can('manage_options')) {
            wp_die('You do not have permission to run this cron job.', 'Permission Denied', ['response' => 403]);
        }

        // Trigger the hook immediately
        do_action($hook);

        // Redirect back with success message
        $redirect_url = add_query_arg([
            'page' => 'tools',
            'tab' => 'status',
            'anar_cron_run' => 'success',
            'anar_cron_hook' => $hook
        ], admin_url('admin.php'));

        wp_redirect($redirect_url);
        exit;
    }

    /**
     * Get cron job data for a specific hook
     * Uses WordPress's cron array to get accurate schedule information
     * 
     * @param string $hook Cron hook name
     * @param string $display_name Display name for the cron job
     * @return array Cron job data
     */
    public static function get_cron_job_data($hook, $display_name = null) {
        $next_run = wp_next_scheduled($hook);
        $actual_interval = 'Not scheduled';
        $actual_interval_seconds = 0;

        // Get actual schedule from WordPress cron array
        // Search through all timestamps to find the hook (more reliable than using wp_next_scheduled timestamp)
        if (function_exists('_get_cron_array')) {
            $cron_array = _get_cron_array();
            if ($cron_array && is_array($cron_array)) {
                foreach ($cron_array as $timestamp => $cron_events) {
                    if (isset($cron_events[$hook])) {
                        $event = $cron_events[$hook];
                        
                        // Get actual schedule name (hourly, daily, etc.)
                        if (isset($event['schedule'])) {
                            $schedule_name = $event['schedule'];
                            $schedules = wp_get_schedules();
                            if (isset($schedules[$schedule_name])) {
                                $actual_interval = $schedules[$schedule_name]['display'];
                                $actual_interval_seconds = $schedules[$schedule_name]['interval'];
                            } else {
                                // Custom schedule not found in wp_get_schedules, use schedule name
                                $actual_interval = $schedule_name;
                            }
                        }
                        
                        // If no schedule but has interval, it's a single event
                        if (!isset($event['schedule']) && isset($event['interval'])) {
                            $actual_interval_seconds = $event['interval'];
                            // Format interval nicely
                            if ($actual_interval_seconds < 60) {
                                $actual_interval = $actual_interval_seconds . ' seconds';
                            } elseif ($actual_interval_seconds < 3600) {
                                $actual_interval = round($actual_interval_seconds / 60) . ' minutes';
                            } elseif ($actual_interval_seconds < 86400) {
                                $actual_interval = round($actual_interval_seconds / 3600) . ' hours';
                            } else {
                                $actual_interval = round($actual_interval_seconds / 86400) . ' days';
                            }
                        }
                        
                        break; // Found the hook, no need to continue
                    }
                }
            }
        }

        // Calculate status (simplified - only check if scheduled)
        $status = $next_run !== false ? 'success' : 'error';

        return [
            'hook' => $hook,
            'name' => $display_name ?: $hook,
            'is_scheduled' => $next_run !== false,
            'next_run' => $next_run ? $next_run : null,
            'next_run_formatted' => $next_run ? wp_date('Y-m-d H:i:s', $next_run) : null,
            'interval' => $actual_interval,
            'interval_seconds' => $actual_interval_seconds,
            'status' => $status,
            'run_now_link' => self::get_run_cron_link($hook)
        ];
    }


    /**
     * Generate "Run Now" link for any cron hook (similar to Crontrol plugin)
     * 
     * @param string $hook Cron hook name
     * @return string URL to run the cron hook immediately
     */
    public static function get_run_cron_link($hook) {
        $next_run = wp_next_scheduled($hook);
        
        // Generate signature based on hook name and next run time
        $sig = md5($hook . ($next_run ? $next_run : 'none'));
        
        $url = add_query_arg([
            'page' => 'tools',
            'tab' => 'status',
            'anar_action' => 'run-cron',
            'anar_id' => $hook,
            'anar_sig' => $sig,
            'anar_next_run_utc' => $next_run ? $next_run : 0,
            '_wpnonce' => wp_create_nonce('anar_run_cron_' . $hook)
        ], admin_url('admin.php'));

        return $url;
    }

}

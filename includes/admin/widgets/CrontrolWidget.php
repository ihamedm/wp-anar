<?php

namespace Anar\Admin\Widgets;

use Anar\Admin\Tools;

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

/**
 * Crontrol Widget
 * Lists Anar cron hooks with their data and "Run Now" links (similar to Crontrol plugin)
 */
class CrontrolWidget extends AbstractReportWidget
{
    protected function init()
    {
        $this->widget_id = 'anar-crontrol-widget';
        $this->title = 'Cron Jobs Manager';
        $this->description = 'List and manage Anar cron hooks';
        $this->icon = '<span class="dashicons dashicons-clock"></span>';
        $this->ajax_action = 'anar_get_crontrol_data';
        $this->button_text = 'Load Cron Jobs';
        $this->button_class = 'button-primary';

        // Register AJAX handler
        add_action('wp_ajax_' . $this->ajax_action, [$this, 'handle_ajax']);
    }

    protected function get_report_data()
    {
        // Define all Anar cron hooks with their display names
        // All schedule data will be read from WordPress cron array
        $anar_cron_hooks = [
            [
                'hook' => 'anar_import_products',
                'display_name' => 'Import Products'
            ],
            [
                'hook' => 'anar_fetch_updates',
                'display_name' => 'Fetch Updates'
            ],
            [
                'hook' => 'anar_daily_maintenance',
                'display_name' => 'Daily Maintenance'
            ],
            [
                'hook' => 'anar_sync_products',
                'display_name' => 'Regular Sync'
            ],
            [
                'hook' => 'anar_sync_outdated_products',
                'display_name' => 'Outdated Sync'
            ],
            [
                'hook' => 'anar_fix_products',
                'display_name' => 'Fix Products'
            ],
            [
                'hook' => 'anar_cleanup_jobs',
                'display_name' => 'Cleanup Jobs'
            ]
        ];

        $cron_jobs = [];

        foreach ($anar_cron_hooks as $hook_config) {
            $hook = $hook_config['hook'];
            $display_name = $hook_config['display_name'];

            // Get cron job data using Tools class (reads actual schedule from WordPress cron array)
            $job_data = Tools::get_cron_job_data($hook, $display_name);
            $cron_jobs[] = $job_data;
        }

        return [
            'cron_jobs' => $cron_jobs,
            'timestamp' => current_time('Y-m-d H:i:s')
        ];
    }
}


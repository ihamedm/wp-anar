<?php
namespace Anar\Init;

use Anar\Sync;
use Anar\SyncOutdated;
use Anar\SyncRealTime;
use Anar\Core\CronJobs;

/**
 * Class Update
 * 
 * Handles version changes and updates for the plugin
 * Manages cron job rescheduling when versions change
 */
class Update {
    private static $instance = null;

    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        add_action('admin_init', array($this, 'check_versions'));
    }

    /**
     * Check all version changes and perform necessary updates
     */
    public function check_versions() {
        $this->check_cron_version();
        // Add other version checks here as needed
    }

    /**
     * Check cron version and reschedule if needed
     */
    private function check_cron_version() {
        $installed_cron_version = get_option('awca_cron_version');

        if ($installed_cron_version !== ANAR_CRON_VERSION) {
            $this->reschedule_cron_jobs();
            update_option('awca_cron_version', ANAR_CRON_VERSION);
            update_option('anar_cron_version', ANAR_CRON_VERSION);
        }
    }

    /**
     * Reschedule all cron jobs
     */
    private function reschedule_cron_jobs() {
        // First unschedule all existing cron jobs
        $this->unschedule_cron_jobs();

        // Then reschedule them
        $this->schedule_cron_jobs();

        CronJobs::get_instance()->reschedule_events();
    }

    /**
     * Unschedule all cron jobs
     */
    private function unschedule_cron_jobs() {
        // Unschedule Sync cron
        $sync = Sync::get_instance();
        $sync->unschedule_cron();

        // Unschedule SyncOutdated cron
        $sync_outdated = SyncOutdated::get_instance();
        $sync_outdated->unscheduled_cron();
    }

    /**
     * Schedule all cron jobs
     */
    private function schedule_cron_jobs() {
        // Schedule Sync cron
        $sync = Sync::get_instance();
        $sync->schedule_cron();

        // Schedule SyncOutdated cron
        $sync_outdated = SyncOutdated::get_instance();
        $sync_outdated->schedule_cron();
    }

    public function deactivate() {
        $sync = Sync::get_instance();
        $sync->unschedule_cron();
    }

    public function activate() {
        $sync = Sync::get_instance();
        $sync->schedule_cron();
    }
} 
<?php
namespace Anar\Core;

class Cleaner{

    private static $instance;

    public static function instance(){
        if( null === self::$instance ){
            self::$instance = new self();
        }
        return self::$instance;
    }


    public function cleanup_action_scheduler(){
        try {
            global $wpdb;

            awca_log('Starting cleanup of action scheduler tables...');

            // Get count of records to be deleted
            $count = $wpdb->get_var(
                $wpdb->prepare(
                    "SELECT COUNT(*) FROM {$wpdb->prefix}actionscheduler_actions 
                    WHERE status = %s AND scheduled_date_gmt < DATE_SUB(NOW(), INTERVAL 1 HOUR)",
                    'complete'
                )
            );

            awca_log("Found {$count} completed actions older than 1 day to clean up");

            // Delete completed actions older than 1 day
            $result = $wpdb->query(
                $wpdb->prepare(
                    "DELETE a, ag, ac, am
                    FROM {$wpdb->prefix}actionscheduler_actions a
                    LEFT JOIN {$wpdb->prefix}actionscheduler_groups ag ON ag.group_id = a.group_id
                    LEFT JOIN {$wpdb->prefix}actionscheduler_claims ac ON ac.claim_id = a.claim_id
                    LEFT JOIN {$wpdb->prefix}actionscheduler_logs am ON am.action_id = a.action_id
                    WHERE a.status = %s 
                    AND a.scheduled_date_gmt < DATE_SUB(NOW(), INTERVAL 1 DAY)",
                    'complete'
                )
            );

            awca_log("Action scheduler cleanup completed. Deleted {$result} rows.");

        } catch (\Exception $e) {
            awca_log('Error in cleanup_action_scheduler: ' . $e->getMessage());
        }
    }
}
<?php
/**
 * Anar Sync Functions
 *
 * General core functions available on both the front-end and admin.
 *
 * @package Anar\Sync
 * @version 0.6.0
 */

use Anar\Sync\OutdatedSync;
use Anar\Sync\RegularSync;
use Anar\Sync\RealTimeSync;
use Anar\Sync\Sync;


/**
 * Schedules or unschedules a sync cron job
 *
 * Factory function to manage cron scheduling for different sync strategies.
 * Supports 'regular' and 'outdated' sync types. Can schedule or unschedule
 * the corresponding WordPress cron job.
 *
 * @param string $sync_name The sync strategy name: 'regular' or 'outdated'
 * @param string $type Action type: 'schedule' to enable cron, 'unschedule' to disable cron
 * @return void
 */
function anar_schedule_sync_factory($sync_name , $type) {
    if($sync_name == 'regular'){
        $regular_sync = RegularSync::get_instance();
        if($type == 'schedule'){
            $regular_sync->scheduleCron();
        }elseif($type == 'unschedule'){
            $regular_sync->unscheduleCron();
        }
    }
    if($sync_name == 'outdated'){
        $outdated_sync = OutdatedSync::get_instance();
        if($type == 'schedule'){
            $outdated_sync->schedule_cron();
        }elseif($type == 'unschedule'){
            $outdated_sync->unscheduled_cron();
        }
    }
}

/**
 * Gets the last regular sync execution time
 *
 * Retrieves the timestamp of when the last regular sync job completed successfully.
 *
 * @return string|false The last sync time in MySQL datetime format (Y-m-d H:i:s), or false if never synced
 */
function anar_get_last_regular_sync_time(){
    $regular_sync = RegularSync::get_instance();
    return $regular_sync->getLastSyncTime();
}

/**
 * Syncs a WooCommerce product with Anar360 API
 *
 * Main wrapper function for syncing a single WooCommerce product. Creates a Sync instance
 * and calls the syncProduct method with the provided parameters.
 *
 * @param int $product_id WooCommerce product ID to sync
 * @param string $sync_strategy The sync strategy identifier (e.g., 'realtime-sync', 'outdated-sync', 'force-sync', 'custom')
 * @param bool $full Whether to perform a full sync (true) or partial sync (false). Default true
 * @param bool $deprecate_on_fault Whether to deprecate the product if API errors occur. Default true
 * @return array{
 *     updated: bool,
 *     status_code: int,
 *     message: string,
 *     logs: string
 * } Sync result array with update status, HTTP status code, message, and logs
 */
function anar_sync_product($product_id, $sync_strategy = 'custom', $full = true, $deprecate_on_fault = true){
    $sync = new Anar\Sync\Sync();
    return $sync->syncProduct($product_id, $sync_strategy, $full, $deprecate_on_fault);
}

/**
 * Syncs a product using Anar SKU
 *
 * Placeholder function for syncing products by Anar SKU instead of WooCommerce product ID.
 * This function is not yet implemented.
 *
 * @return void
 */
function anar_sync_product_with_anar_sku(){}

/**
 * Syncs a product using pre-fetched Anar product data
 *
 * Placeholder function for syncing products using already-retrieved Anar product data
 * to avoid redundant API calls. This function is not yet implemented.
 *
 * @return void
 */
function anar_sync_product_with_anar_product_data(){}

/**
 * Gets the Persian error message for a sync error code
 *
 * Retrieves the human-readable Persian error message corresponding to a sync error code.
 * Used for displaying sync errors to users in the admin interface.
 *
 * @param string $error_code The sync error code (e.g., 'no_wc_variants', 'no_anar_variant', 'unknown')
 * @return string The Persian error message, or a default message if code not found
 */
function anar_get_sync_error_message($error_code) {
    if (empty($error_code)) {
        return 'خطای همگام‌سازی نامشخص است.';
    }

    // Use reflection to access protected static property
    $reflection = new \ReflectionClass(Sync::class);
    $property = $reflection->getProperty('sync_error_codes');
    $property->setAccessible(true);
    $error_codes = $property->getValue();

    if (isset($error_codes[$error_code])) {
        return $error_codes[$error_code];
    }

    // Return default message for unknown error codes
    return isset($error_codes[Sync::ERROR_UNKNOWN]) 
        ? $error_codes[Sync::ERROR_UNKNOWN] 
        : 'خطای ناشناخته در همگام‌سازی رخ داد.';
}

<?php
namespace Anar\Admin\Tools;

defined( 'ABSPATH' ) || exit;

class SyncTools{

    private static $instance;

    public static function get_instance(){
        if( null === self::$instance ){
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function __construct(){

        add_action('wp_ajax_anar_clear_sync_times', array($this, 'clear_sync_times_ajax'));
        add_action('wp_ajax_anar_manual_sync_outdated', array($this, 'manual_sync_outdated_ajax'));
        add_action('wp_ajax_anar_toggle_sleep_mode', array($this, 'toggle_sleep_mode'));
    }



    /**
     * AJAX handler for clearing sync times
     * Updates _anar_last_sync_time to old date instead of deleting for better performance
     */
    public function clear_sync_times_ajax() {
        if (!current_user_can('manage_woocommerce')) {
            wp_send_json_error('شما این مجوز را ندارید!');
            wp_die();
        }

        try {
            global $wpdb;

            // Get total count of products with _anar_sku
            $total_products = $wpdb->get_var("
                SELECT COUNT(DISTINCT post_id) 
                FROM {$wpdb->postmeta} 
                WHERE meta_key = '_anar_sku'
            ");

            if ($total_products === null) {
                wp_send_json_error('خطا در دریافت تعداد محصولات');
                return;
            }

            // Set a very old date (1 year ago) to mark all products as needing sync
            // This is better than deleting because:
            // 1. Maintains index efficiency
            // 2. Keeps data integrity
            // 3. Faster than delete operations
            $old_date = date('Y-m-d H:i:s', strtotime('-1 year'));

            // First, update existing _anar_last_sync_time records
            $updated = $wpdb->update(
                $wpdb->postmeta,
                array('meta_value' => $old_date),
                array('meta_key' => '_anar_last_sync_time')
            );

            if ($updated === false) {
                wp_send_json_error('خطا در بروزرسانی زمان‌های بروزرسانی');
                return;
            }

            // Then, insert _anar_last_sync_time for parent products that don't have it yet
            // Only add to parent products, not variants
            $inserted = $wpdb->query($wpdb->prepare("
                INSERT IGNORE INTO {$wpdb->postmeta} (post_id, meta_key, meta_value)
                SELECT DISTINCT pm.post_id, '_anar_last_sync_time', %s
                FROM {$wpdb->postmeta} pm
                INNER JOIN {$wpdb->posts} p ON pm.post_id = p.ID
                LEFT JOIN {$wpdb->postmeta} sync_time ON pm.post_id = sync_time.post_id AND sync_time.meta_key = '_anar_last_sync_time'
                WHERE pm.meta_key = '_anar_sku'
                AND p.post_type = 'product'
                AND p.post_parent = 0
                AND sync_time.post_id IS NULL
            ", $old_date));

            $total_processed = $updated + ($inserted ? $inserted : 0);

            awca_log("Reset sync times for {$updated} existing products and added for {$inserted} new products", 'info');

            wp_send_json_success([
                'message' => 'زمان‌های بروزرسانی با موفقیت به تاریخ قدیمی تنظیم شدند',
                'total_products' => $total_products,
                'updated_count' => $updated,
                'inserted_count' => $inserted,
                'total_processed' => $total_processed,
                'reset_date' => $old_date
            ]);

        } catch (\Exception $e) {
            wp_send_json_error([
                'message' => $e->getMessage()
            ]);
        }
    }

    /**
     * AJAX handler for manually triggering sync outdated products
     */
    public function manual_sync_outdated_ajax() {
        if (!current_user_can('manage_woocommerce')) {
            wp_send_json_error('شما این مجوز را ندارید!');
            wp_die();
        }

        try {
            do_action('anar_sync_outdated_products');

            // Return the actual results
            wp_send_json_success([
                'message' => 'ایونت کرون جاب sync_outdated خارج از برنامه اجرا شد',
                'processed' => 0,
                'failed' => 0,
                'total_checked' => 0
            ]);

        } catch (\Exception $e) {
            wp_send_json_error([
                'message' => 'خطا در اجرای همگام‌سازی: ' . $e->getMessage()
            ]);
        }
    }


    /**
     * Ajax Handler for toggle the Sleep-Mode enable/disable
     * this method used for a tool that placed on general tools tab
     */
    public function toggle_sleep_mode(){
        anar_log(print_r($_POST, true), "info");

        if(
            !current_user_can('manage_woocommerce') ||
            !DOING_AJAX ||
            !wp_verify_nonce($_POST["sleep_mode_ajax_field"], 'sleep_mode_ajax_nonce')
        ){
            wp_send_json_error(["message" => "شما مجوز انجام این عملیات را ندارید!"]);
            wp_die();
        }

        if(isset($_POST['anar-sleep-mode-toggle'])){
            update_option('anar_sleep_mode', $_POST['anar-sleep-mode-toggle'] == 'on' ? 'enable' : 'disable');
            wp_send_json_success(["message" => "مد اسلیپ تغییر داده شد"]);
        }

        wp_send_json_error(["message" => "مشکلی در ارسال اطلاعات پیش آمده است."]);

    }

}
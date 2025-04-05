<?php

namespace Anar\Init;

class Checks{

    private static $instance;

    public static function get_instance(){
        if( null === self::$instance ){
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function __construct() {
        // Add admin notice for missing shipping rates
        add_action('admin_notices', [$this, 'admin_notice_missing_shipping_rates']);

        // Add a check on admin dashboard
        add_action('admin_init', [$this, 'check_shipping_rates_availability']);
    }




    /**
     * Check if shipping rates are configured
     */
    public function check_shipping_rates_availability() {
        // Check if any shipping methods are enabled and have rates
        $shipping_methods = WC()->shipping()->get_shipping_methods();
        $has_active_methods = false;
        $instance_ids = [];

        foreach ($shipping_methods as $method) {
            // Check if the method is enabled by checking if it has any instances
            if (method_exists($method, 'get_instance_ids')) {
                $instance_ids = $method->get_instance_ids();
                if (!empty($instance_ids)) {
                    $has_active_methods = true;
                    break;
                }
            }
        }

        // Store the check result as an option for the admin notice
        update_option('anar_has_shipping_rates', print_r($instance_ids, true));
    }

    /**
     * Show admin notice for missing shipping rates
     */
    public function admin_notice_missing_shipping_rates() {
        // Check if we're on the WooCommerce settings page
        $screen = get_current_screen();

        // Get the shipping rates status
        $has_shipping_rates = get_option('anar_has_shipping_rates', false);

        if (!$has_shipping_rates) {
            ?>
            <div class="notice notice-error" style="border-left-color: #dc3232; padding: 10px 12px;">
                <p><strong>خطا در تنظیمات حمل و نقل!</strong> هیچ روش حمل و نقلی در فروشگاه شما تنظیم نشده است. این مشکل می‌تواند باعث شود مشتریان نتوانند خرید خود را تکمیل کنند.</p>
                <p><a href="<?php echo admin_url('admin.php?page=wc-settings&tab=shipping'); ?>" class="button button-primary">افزودن روش حمل و نقل</a></p>
            </div>
            <?php
        }
    }

}
<?php

namespace Anar\Init;

use Anar\Core\Activation;

class Checks {
    private static $instance;
    private $notice_dismissible_key = 'anar_persian_wc_notice_dismissed';

    public static function get_instance() {
        if( null === self::$instance ){
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function __construct() {
        // Add admin notice for missing shipping rates
        add_action('admin_notices', [$this, 'admin_notice_missing_shipping_rates']);
        add_action('admin_notices', [$this, 'admin_notice_persian_wc']);
        add_action('admin_notices', [$this, 'admin_notice_max_input_vars']);
        add_action('admin_notices', [$this, 'admin_notice_cron_health']);
        add_action('admin_notices', [$this, 'admin_notice_token_inactive']);
        // Remove AJAX and JS hooks
    }

    /**
     * Check if Persian WooCommerce is active
     */
    private function is_persian_wc_active() {
        return in_array(
            'persian-woocommerce/woocommerce-persian.php',
            apply_filters('active_plugins', get_option('active_plugins'))
        );
    }

    /**
     * Show admin notice for Persian WooCommerce requirement
     */
    public function admin_notice_persian_wc() {
        // Check if notice was dismissed
        if (get_option($this->notice_dismissible_key)) {
            return;
        }

        // Check if Persian WooCommerce is active
        if (!$this->is_persian_wc_active()) {
            ?>
            <div class="notice notice-warning is-dismissible" data-notice-id="persian-wc-notice">
                <p><strong>هشدار افزونه ووکامرس فارسی!</strong> برای نمایش صحیح آدرس خریداران در سفارش های ووکامرس لازم است افزونه ووکامرس فارسی را نصب و فعال کنید.</p>
                <p><a href="<?php echo admin_url('plugin-install.php?s=persian-woocommerce&tab=search&type=term'); ?>" class="button button-primary">نصب ووکامرس فارسی</a></p>
            </div>
            <?php
        }
    }


    /**
     * Check if shipping rates are configured and show notice if missing
     */
    public function admin_notice_missing_shipping_rates() {
        if(anar_is_ship_to_stock_enabled())
            return;
        // Only check on WooCommerce pages to improve performance
        $screen = get_current_screen();
        if (!$screen || !in_array($screen->id, ['woocommerce_page_wc-settings', 'edit-shop_order', 'dashboard'])) {
            return;
        }

        // Get shipping zones which is more reliable than checking methods
        $zones = \WC_Shipping_Zones::get_zones();
        $has_shipping_method = false;

        // Check default zone if no zones are set
        if (empty($zones)) {
            $default_zone = new \WC_Shipping_Zone(0);
            $has_shipping_method = !empty($default_zone->get_shipping_methods(true));
        } else {
            $has_shipping_method = true; // If we have zones, we have shipping methods
        }

        $anar_fruit_url = ANAR_WC_API_PLUGIN_URL.'assets/images/anar-fruit.svg';
        if (!$has_shipping_method) {
            ?>
            <div class="notice notice-error" style="border-left-color: #dc3232; padding: 10px 12px;">
                <p style="display: flex;align-items: center; gap:8px"><img style="width: 24px;" src="<?php echo $anar_fruit_url;?>"><strong>انار۳۶۰ : خطا در تنظیمات حمل و نقل!</strong><span> هیچ روش حمل و نقلی در فروشگاه شما تنظیم نشده است. این مشکل می‌تواند باعث شود مشتریان نتوانند خرید خود را تکمیل کنند.</span></p>
                <p>
                    <a href="<?php echo admin_url('admin.php?page=wc-settings&tab=shipping'); ?>" class="button button-primary">افزودن روش حمل و نقل</a>
                    <a href="https://wp.anar360.com/installation" class="button button-secondary" target="_blank">مشاهده ویدیوی آموزشی</a>
                </p>
            </div>
            <?php
        }
    }

    /**
     * Check if max_input_vars is below recommended value and show notice
     */
    public function admin_notice_max_input_vars() {
        $max_input_vars = ini_get('max_input_vars');
        if ($max_input_vars < 4000) {
            $anar_fruit_url = ANAR_WC_API_PLUGIN_URL.'assets/images/anar-fruit.svg';
            ?>
            <div class="notice notice-warning" style="border-left-color: #ffb900; padding: 10px 12px;">
                <p style="display: flex;align-items: center; gap:8px">
                    <img style="width: 24px;" src="<?php echo $anar_fruit_url;?>">
                    <strong>انار۳۶۰ : تنظیمات PHP نیاز به بهینه‌سازی دارد!</strong>
                    <span>مقدار max_input_vars در PHP شما کمتر از مقدار توصیه شده است. این می‌تواند باعث مشکلاتی در عملکرد افزونه شود.</span>
                </p>
                <p>
                    بهتر است از نسخه های PHP 8.0 به بالا استفاده کنید و مقدار max_input_vars بیشتر از 4000 باشد.
                    <br><span style="color:darkred">مقدار فعلی max_input_vars = <strong><?php echo $max_input_vars;?></strong></span>
                </p>
                <!--p>
                    <a href="https://wp.anar360.com/installation#php-settings" class="button button-secondary" target="_blank">راهنمای تنظیم PHP</a>
                </p-->
            </div>
            <?php
        }
    }

    /**
     * Check WordPress cron health and show notice if there are issues
     */
    public function admin_notice_cron_health() {
        // Handle dismiss via query arg
        if (isset($_GET['anar_dismiss_cron_notice']) && current_user_can('manage_options')) {
            update_user_meta(get_current_user_id(), 'anar_cron_notice_dismissed', 1);
            // Redirect to remove the query arg
            wp_redirect(remove_query_arg('anar_dismiss_cron_notice'));
            exit;
        }

        // Only check on specific pages to improve performance
        $screen = get_current_screen();
        if (!$screen || !in_array($screen->id, ['dashboard', 'woocommerce_page_wc-settings'])) {
            return;
        }

        // Check if notice was dismissed for this user
        if (get_user_meta(get_current_user_id(), 'anar_cron_notice_dismissed', true)) {
            return;
        }

        // Check if WP Cron is disabled
        if (defined('DISABLE_WP_CRON') && DISABLE_WP_CRON) {
            $this->show_cron_notice('disabled');
            return;
        }

        // Check last cron execution time
        $last_cron = get_option('_transient_wp_cron_lock');
        if ($last_cron) {
            $last_cron_time = (int) $last_cron;
            $current_time = time();
            $time_diff = $current_time - $last_cron_time;

            // If cron hasn't run in more than 1 hour, show warning
            if ($time_diff > 3600) {
                $this->show_cron_notice('delayed', $time_diff);
            }
        }
    }

    /**
     * Show cron health notice
     */
    private function show_cron_notice($type, $time_diff = 0) {
        $anar_fruit_url = ANAR_WC_API_PLUGIN_URL.'assets/images/anar-fruit.svg';
        $dismiss_url = esc_url(add_query_arg('anar_dismiss_cron_notice', 1));
        ?>
        <div class="notice notice-warning is-dismissible" style="border-left-color: #ffb900; padding: 10px 12px; position:relative;">
            <p style="display: flex;align-items: center; gap:8px">
                <img style="width: 24px;" src="<?php echo $anar_fruit_url;?>">
                <strong>انار۳۶۰ : هشدار وضعیت کران جاب!</strong>
                <?php if ($type === 'disabled'): ?>
                    <span>WP Cron در سایت شما غیرفعال است. این می‌تواند باعث مشکلاتی در عملکرد افزونه شود.</span>
                <?php else: ?>
                    <span>کران جاب در <?php echo floor($time_diff / 3600); ?> ساعت گذشته اجرا نشده است. این می‌تواند باعث مشکلاتی در عملکرد افزونه شود.</span>
                <?php endif; ?>
            </p>
            <button type="button" class="notice-dismiss" onclick="location.href='<?php echo $dismiss_url; ?>'"></button>
        </div>
        <?php
    }

    /**
     * Show admin notice when Anar token is not active
     */
    public function admin_notice_token_inactive() {
        // Only show on admin pages, not on the activation page itself
        $screen = get_current_screen();
        if (!$screen || $screen->id === 'toplevel_page_configs') {
            return;
        }

        // Check if token is active
        if (!Activation::is_active()) {
            $anar_fruit_url = ANAR_WC_API_PLUGIN_URL.'assets/images/anar-fruit.svg';
            $activation_error_msg = Activation::get_error_msg();
            ?>
            <div class="notice notice-error" style="border-left-color: #dc3232; padding: 10px 12px;">
                <p style="display: flex;align-items: center; gap:8px">
                    <img style="width: 24px;" src="<?php echo $anar_fruit_url;?>">
                    <strong>انار۳۶۰ : توکن فعال نیست! برای استفاده از پلاگین انار باید <span style="background:green; color:#fff; display: inline-block; padding: 2px 6px">اشتراک حرفه ایی فعال</span> داشته باشید.</strong>
                </p>
                <p><span style="color:red">اخطار : </span>تا زمانی که توکن فعال نشود فرآیندهای <strong>همگام سازی قیمت و موجودی</strong> و <strong>ثبت سفارش</strong> غیرفعال خواهند بود.</p>
                <?php if($activation_error_msg){
                 printf('<p style="color:red">خطا: %s</p>', $activation_error_msg);
                }?>
                <p>
                    <a href="<?php echo admin_url('admin.php?page=configs'); ?>" class="button button-primary">فعال‌سازی</a>
                    <a href="https://wp.anar360.com/installation" class="button button-secondary" target="_blank">راهنمای فعال‌سازی</a>
                </p>
            </div>
            <?php
        }
    }
}
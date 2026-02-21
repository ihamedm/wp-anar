<?php
namespace Anar\Core;

use Anar\ApiDataHandler;

class Activation{

    private static $instance;

    public static function get_instance() {
        if ( ! isset( self::$instance ) ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function __construct()
    {
        add_action('wp_ajax_awca_handle_token_activation_ajax', [$this, 'handle_anar_token_activation_ajax']);
    }


    public function handle_anar_token_activation_ajax()
    {
        $response = [
            'success' => false,
            'message' => 'اعتبار اطلاعات فرم به پایان رسیده است',
        ];

        if (!isset($_POST['awca_handle_token_activation_ajax_field']) || !wp_verify_nonce($_POST['awca_handle_token_activation_ajax_field'], 'awca_handle_token_activation_ajax_nonce')) {
            wp_send_json($response);
        }

        $activation = $this->save_activation_key();
        $activation_status = self::validate_token();

        if ($activation && $activation_status) {
            $response = [
                'success' => true,
                'message' => 'توکن شما با موفقیت ثبت و تایید شد',
            ];

        } elseif ($activation_status) {
            $response = [
                'success' => true,
                'message' => 'توکن شما معتبر و پلاگین انار فعال شد.',
            ];
        } else {
            $error_msg = Activation::get_error_msg() ?? 'توکن شما از سمت انار تایید نشد';
            $response['message'] = $error_msg;
        }

        wp_send_json($response);
    }


    public function save_activation_key()
    {
        try {
            if (isset($_POST['activation_code'])) {
                $activation_code = sanitize_text_field($_POST['activation_code']);
                $activation = update_option('_awca_activation_key', $activation_code);
                update_option('_anar_token', $activation_code);
                if ($activation) {
                    return true;
                } else {
                    throw new \Exception('Failed to update activation key.');
                }
            } else {
                throw new \Exception('Activation code not found in POST data.');
            }
        } catch (\Exception $e) {
            awca_log('Error: ' . $e->getMessage());
            return false;
        }
    }


    public static function get_saved_activation_key(){
        return get_option('_awca_activation_key');
    }


    public static function validate_token()
    {

        if(!self::get_saved_activation_key())
            return false;

        $tokenValidationResponse = ApiDataHandler::callAnarApi("https://api.anar360.com/wp/auth/validate");

        if(is_wp_error($tokenValidationResponse))
            return false;


        if ($tokenValidationResponse['response']['code'] == 200) {
            $tokenValidation = json_decode($tokenValidationResponse['body']);


            if(isset($tokenValidation->shopUrl)){
                update_option('_anar_shop_url', $tokenValidation->shopUrl);
            }
            if(isset($tokenValidation->subscriptionPlan)){
                update_option('_anar_subscription_plan', $tokenValidation->subscriptionPlan);
            }
            if(isset($tokenValidation->subscriptionRemaining)){
                update_option('_anar_subscription_remaining', $tokenValidation->subscriptionRemaining);
            }
            if(isset($tokenValidation->domainConnectedAt)){
                update_option('_anar_domain_connected_at', $tokenValidation->domainConnectedAt);
            }

            // Check for forceUpdate
            self::check_force_update($tokenValidation);
            self::handle_feature_flags($tokenValidation);

            if (isset($tokenValidation->success) && $tokenValidation->success === true) {
                update_option('_anar_token_validation', 'valid');
                if (!get_option('_anar_activation_first_time_at')) {
                    update_option('_anar_activation_first_time_at', current_time('mysql'));
                }
                return true;
            }

        }else{
            $tokenValidation = json_decode($tokenValidationResponse['body']);

            // Check for forceUpdate even in error response
            self::check_force_update($tokenValidation);
            self::handle_feature_flags($tokenValidation);

            delete_option('_anar_shop_url');
            delete_option('_anar_subscription_plan');
            delete_option('_anar_subscription_remaining');

            update_option('_anar_token_validation', 'invalid');

            if(isset($tokenValidation->error)){
                update_option('_anar_subscription_error', $tokenValidation->error);
            }

            return false;
        }

        return false;
    }

    public static function is_active(){
        $token_validation = get_option('_anar_token_validation', 'invalid');

        if(!$token_validation){
            return self::validate_token();
        }elseif($token_validation === 'valid'){
            return true;
        }elseif($token_validation === 'invalid'){
            return false;
        }
        awca_log('unknown error, anar token validation not saved, check Activation::is_active() method for more details');
        return false;
    }


    public static function get_error_msg($error_code = ''){
        /**
         * UNKNOWN_ERROR: 10000,
         * SUBSCRIPTION_REQUIRED: 10010,
         * PRO_SUBSCRIPTION_REQUIRED: 10011,
         * SUBSCRIPTION_EXPIRED: 10012,
         * INVALID_DOMAIN: 10020
         */

        if(self::is_active())
            return false;

        if($error_code == ''){
            $error_code = get_option('_anar_subscription_error', 9000);
        }

        switch ($error_code) {
            case 10000:
                return 'خطای نامشخصی رخ داده است.';
            case 10010:
                return 'شما هیچ اشتراکی ندارید.';
            case 10011:
                return 'اشتراک حرفه ایی ندارید.';
            case 10012:
                return 'اعتبار اشتراک شما به پایان رسیده است.';
            case 10020:
                return 'توکن با آدرس وب سایت مطابقت ندارد.';
            case 9000:
                return 'توکن وارد نشده است.';
            default:
                return 'کد خطا نامعتبر است.';
        }

    }

    /**
     * Check for forceUpdate in API response and show alert if version mismatch
     * 
     * @param object|null $tokenValidation The decoded JSON response from API
     * @return void
     */
    private static function check_force_update($tokenValidation) {
        // Check if tokenValidation is a valid object
        if (!is_object($tokenValidation)) {
            return;
        }

        // Check if forceUpdate exists
        if (!isset($tokenValidation->forceUpdate)) {
            return;
        }

        $force_update_version = $tokenValidation->forceUpdate;

        // If forceUpdate is false, clear any existing alert
        if ($force_update_version === false) {
            $existing_alert = get_option('anar_dashboard_alert');
            if ($existing_alert && isset($existing_alert['notificationId']) && $existing_alert['notificationId'] === 'force_update_alert') {
                delete_option('anar_dashboard_alert');
            }
            return;
        }

        // Get current plugin version
        $current_version = defined('ANAR_PLUGIN_VERSION') ? ANAR_PLUGIN_VERSION : '0.0.0';

        // Check if forceUpdate is a version number and doesn't match current version
        if (is_string($force_update_version) && version_compare($force_update_version, $current_version, 'gt')) {
            // Set dashboard alert
            $alert_data = [
                'type' => 'error',
                'title' => 'بروزرسانی اجباری پلاگین انار',
                'message' => sprintf(
                    'نسخه فعلی پلاگین انار شما (%s) منسوخ شده است. لطفا فورا به نسخه %s بروزرسانی کنید.',
                    esc_html($current_version),
                    esc_html($force_update_version)
                ),
                'buttonText' => 'برو به صفحه افزونه‌ها',
                'buttonLink' => '',
                'buttonAdminLink' => admin_url('plugins.php'),
                'notificationId' => 'force_update_alert',
            ];

            update_option('anar_dashboard_alert', $alert_data);
        } else {
            // Clear alert if versions match
            $existing_alert = get_option('anar_dashboard_alert');
            if ($existing_alert && isset($existing_alert['notificationId']) && $existing_alert['notificationId'] === 'force_update_alert') {
                delete_option('anar_dashboard_alert');
            }
        }
    }

    private static function handle_feature_flags($tokenValidation){
        // Check if tokenValidation is a valid object
        if (!is_object($tokenValidation)) {
            return;
        }

        // Check if for feature flags
        if (!isset($tokenValidation->flags) || !is_object($tokenValidation->flags)) {
            return;
        }

        $defined_flags = [
            'sleep_mode' => 'anar_sleep_mode'
        ];

        foreach ($defined_flags as $flag_key => $flag_opt) {
            if (isset($tokenValidation->flags->{$flag_key})) {
                $value = get_option($flag_opt);
                // compare only if values changed update the DB
                if($value !== $tokenValidation->flags->{$flag_key}){
                    update_option($flag_opt, $tokenValidation->flags->{$flag_key});
                }
            }
        }




    }

}


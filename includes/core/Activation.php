<?php
namespace Anar\Core;


use Anar\ApiDataHandler;
use Anar\Payments;

class Activation{

    public function __construct()
    {
        add_action('wp_ajax_awca_handle_token_activation_ajax', [$this, 'handle_anar_token_activation_ajax']);
        add_action('wp_ajax_nopriv_awca_handle_token_activation_ajax', [$this, 'handle_anar_token_activation_ajax']);
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
            $response['message'] = 'توکن شما از سمت انار تایید نشد';
        }

        wp_send_json($response);
    }


    public function save_activation_key()
    {
        try {
            if (isset($_POST['activation_code'])) {
                $activation_code = sanitize_text_field($_POST['activation_code']);
                $activation = update_option('_awca_activation_key', $activation_code);
//                @todo change token key
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

        $tokenValidation = ApiDataHandler::tryGetAnarApiResponse("https://api.anar360.com/wp/auth/validate");

        if ($tokenValidation !== null) {
            if(isset($tokenValidation->shopUrl)){
                update_option('_anar_shop_url', $tokenValidation->shopUrl);
            }
            if(isset($tokenValidation->subscriptionPlan)){
                update_option('_anar_subscription_plan', $tokenValidation->subscriptionPlan);
            }
            if(isset($tokenValidation->subscriptionRemaining)){
                update_option('_anar_subscription_remaining', $tokenValidation->subscriptionRemaining);
            }

            if (isset($tokenValidation->success) && $tokenValidation->success === true) {
                update_option('_anar_token_validation', 'valid');
                return true;
            }else{
                update_option('_anar_token_validation', 'invalid');
                return false;
            }
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


}


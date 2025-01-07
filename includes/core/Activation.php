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
        $activation_status = self::validate_saved_activation_key_from_anar();

        if ($activation && $activation_status) {
            $response = [
                'success' => true,
                'message' => 'توکن شما با موفقیت ثبت و تایید شد',
            ];

            // update anar unpaid orders on wpdb to show alert
            (new Payments())->count_unpaid_orders_count();

        } elseif ($activation_status) {
            $response = [
                'success' => true,
                'message' => 'توکن شما معتبر و سمت انار مورد تایید است ',
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


    public static function validate_saved_activation_key_from_anar()
    {

        if(!self::get_saved_activation_key())
            return false;

        $tokenValidation = ApiDataHandler::tryGetAnarApiResponse("https://api.anar360.com/wp/auth/validate");
        if ($tokenValidation !== null) {
            if (isset($tokenValidation->success) && $tokenValidation->success === true) {
                return true;
            }
        }
        return false;
    }


}


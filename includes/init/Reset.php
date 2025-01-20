<?php
namespace Anar\Init;

class Reset{
    private static $instance;
    public static function get_instance(){
        if( null === self::$instance ){
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function __construct() {
        add_action('wp_ajax_awca_reset_all_settings_ajax', [$this, 'reset_all_options']);
    }

    public function reset_all_options(){
        try {
            Uninstall::reset_options();

            if($_POST['delete_maps'] == 'true'){
                Uninstall::remove_map_data();
            }
            wp_send_json_success(
                ['message' => 'تنظیمات انار به حالت پیش فرض برگشت.' ]
            );
        }catch (\Exception $exception){
            wp_send_json_error($exception->getMessage());
        }
    }
}
<?php
namespace Anar\Init;
defined( 'ABSPATH' ) || exit;

class Plugins{
    private static $instance;
    public static function get_instance(){
        if( null === self::$instance ){
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function __construct(){
        require_once ANAR_PLUGIN_PATH . 'includes/lib/class-tgm-plugin-activation.php';

        add_action( 'tgmpa_register', [$this, 'anar_register_required_plugins'] );
    }

    public function anar_register_required_plugins() {
        $plugins = array(
            array(
                'name'      => 'WooCommerce',
                'slug'      => 'woocommerce',
                'required'  => true,
                'force_activation'  => true,
            ),
            array(
                'name'      => 'ووکامرس فارسی',
                'slug'      => 'persian-woocommerce',
                'required'  => true,
                'force_activation'  => false,
            ),
        );

        $config = array(
            'id'           => 'anar',
            'default_path' => '',
            'menu'         => 'anar-required-plugins',
            'capability'   => 'manage_options',
            'has_notices'  => true,
            'dismissable'  => false,
            'dismiss_msg'  => 'برای استفاده از تمام امکانات انار، نصب افزونه‌های ضروری الزامی است.',
            'is_automatic' => false,
            'message'      => '<p class="anar-alert anar-alert-warning">انار برای عملکرد صحیح نیاز به افزونه‌های زیر دارد. لطفاً آنها را نصب و فعال کنید:</p>',
            'parent_slug'  => 'anar',
        );


        $config['strings'] = array(
            'page_title'                      => 'نصب افزونه‌های ضروری',
            'menu_title'                      => 'نصب افزونه‌ها',
            'installing'                      => 'در حال نصب افزونه: %s',
            'updating'                        => 'در حال بروزرسانی افزونه: %s',
            'oops'                            => 'مشکلی در API افزونه رخ داده است.',
            'notice_can_install_required'     => _n_noop(
                'این پلاگین نیاز به افزونه زیر دارد: %1$s.',
                'این پلاگین نیاز به افزونه‌های زیر دارد: %1$s.',
                'required-plugins'
            ),
            'notice_can_install_recommended'  => _n_noop(
                'این پلاگین افزونه زیر را پیشنهاد می‌دهد: %1$s.',
                'این پلاگین افزونه‌های زیر را پیشنهاد می‌دهد: %1$s.',
                'required-plugins'
            ),
            'notice_ask_to_update'            => _n_noop(
                'افزونه زیر برای حداکثر سازگاری با این پلاگین نیاز به بروزرسانی دارد: %1$s.',
                'افزونه‌های زیر برای حداکثر سازگاری با این پلاگین نیاز به بروزرسانی دارند: %1$s.',
                'required-plugins'
            ),
            'notice_ask_to_update_maybe'      => _n_noop(
                'بروزرسانی برای موارد زیر موجود است: %1$s.',
                'بروزرسانی برای افزونه‌های زیر موجود است: %1$s.',
                'required-plugins'
            ),
            'notice_can_activate_required'    => _n_noop(
                'افزونه ضروری زیر در حال حاضر غیرفعال است: %1$s.',
                'افزونه‌های ضروری زیر در حال حاضر غیرفعال هستند: %1$s.',
                'required-plugins'
            ),
            'notice_can_activate_recommended' => _n_noop(
                'افزونه پیشنهادی زیر در حال حاضر غیرفعال است: %1$s.',
                'افزونه‌های پیشنهادی زیر در حال حاضر غیرفعال هستند: %1$s.',
                'required-plugins'
            ),
            'install_link'                    => _n_noop(
                'شروع نصب افزونه',
                'شروع نصب افزونه‌ها',
                'required-plugins'
            ),
            'update_link'                     => _n_noop(
                'شروع بروزرسانی افزونه',
                'شروع بروزرسانی افزونه‌ها',
                'required-plugins'
            ),
            'activate_link'                   => _n_noop(
                'شروع فعال‌سازی افزونه',
                'شروع فعال‌سازی افزونه‌ها',
                'required-plugins'
            ),
            'return'                          => 'بازگشت به نصب‌کننده افزونه‌های ضروری',
            'dashboard'                       => 'بازگشت به داشبورد',
            'plugin_activated'                => 'افزونه با موفقیت فعال شد.',
            'activated_successfully'          => 'افزونه زیر با موفقیت فعال شد:',
            'plugin_already_active'           => 'هیچ اقدامی انجام نشد. افزونه %1$s از قبل فعال بود.',
            'plugin_needs_higher_version'     => 'افزونه فعال نشد. نسخه بالاتری از %s برای این پلاگین مورد نیاز است. لطفاً افزونه را بروزرسانی کنید.',
            'complete'                        => 'تمام افزونه‌ها با موفقیت نصب و فعال شدند. %1$s',
            'dismiss'                         => 'این اعلان را نادیده بگیرید',
            'notice_cannot_install_activate'  => 'یک یا چند افزونه ضروری یا پیشنهادی برای نصب، بروزرسانی یا فعال‌سازی وجود دارد.',
            'contact_admin'                   => 'لطفاً برای راهنمایی با مدیر سایت تماس بگیرید.',
        );

        tgmpa( $plugins, $config );
    }

}
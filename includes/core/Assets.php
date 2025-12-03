<?php
namespace Anar\Core;

class Assets {

    private static $instance;

    private $plugin_version;

    private $plugin_name;

    private $plugin_url;

    public static function get_instance() {
        if ( ! isset( self::$instance ) ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function __construct() {
        $this->plugin_name = ANAR_PLUGIN_TEXTDOMAIN;
        $this->plugin_version = ANAR_PLUGIN_VERSION;
        $this->plugin_url = ANAR_PLUGIN_URL;

        // Hook into WordPress to load assets
        if(!is_admin())
            add_action('wp_enqueue_scripts', [$this, 'load_public_assets']);

        if(is_admin())
            add_action('admin_enqueue_scripts', [$this, 'load_admin_assets']);
    }

    /**
     * Load public-facing assets
     */
    public function load_public_assets() {
        wp_enqueue_style($this->plugin_name . '-public', $this->plugin_url . '/assets/css/awca-public.css', null, $this->plugin_version);
        wp_enqueue_script($this->plugin_name . '-public', $this->plugin_url. '/assets/dist/public.min.js', ['jquery'], $this->plugin_version, true);


        wp_localize_script($this->plugin_name . '-public', 'awca_ajax_object', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('awca_ajax_nonce'),
        ));

    }

    /**
     * Load admin-facing assets
     */
    public function load_admin_assets() {
        wp_enqueue_style($this->plugin_name, $this->plugin_url . '/assets/css/awca-admin.css' , array(), $this->plugin_version, 'all');
        wp_enqueue_style($this->plugin_name . '-toastify-style', $this->plugin_url . '/assets/css/toastify.min.css', array(), $this->plugin_version, 'all');
        wp_enqueue_style($this->plugin_name . '-micromodal-style', $this->plugin_url . '/assets/css/micromodal.css', array(), $this->plugin_version, 'all');


        wp_enqueue_script($this->plugin_name, $this->plugin_url . '/assets/dist/admin.min.js' , array('jquery'), $this->plugin_version, false);

        wp_localize_script($this->plugin_name, 'awca_ajax_object', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'awca_handle_token_activation_ajax_nonce' => wp_create_nonce(),
            'nonce' => wp_create_nonce('awca_ajax_nonce'),
        ));

        if (isset($_GET['page']) && $_GET['page'] === 'wp-anar-import-v2') {
            if (class_exists('\WooCommerce')) {
                if (!wp_script_is('selectWoo', 'registered')) {
                    wp_register_script(
                        'selectWoo',
                        \WC()->plugin_url() . '/assets/js/selectWoo/selectWoo.full.min.js',
                        ['jquery'],
                        \WC()->version,
                        true
                    );
                }

                if (!wp_style_is('select2', 'registered')) {
                    wp_register_style(
                        'select2',
                        \WC()->plugin_url() . '/assets/css/select2.css',
                        [],
                        \WC()->version
                    );
                }
            }

            wp_enqueue_script('selectWoo');
            wp_enqueue_style('select2');

            wp_enqueue_style(
                $this->plugin_name . '-import-v2',
                $this->plugin_url . '/assets/css/import.css',
                [],
                $this->plugin_version
            );

            wp_register_script(
                $this->plugin_name . '-import-v2',
                $this->plugin_url . '/assets/dist/import-wizard.min.js',
                ['jquery', 'selectWoo'],
                $this->plugin_version,
                true
            );

            wp_localize_script($this->plugin_name . '-import-v2', 'awcaImportV2Data', array(
                'ajaxUrl' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('awca_import_v2_nonce'),
                'i18n' => [
                    'unexpected' => __('خطای غیرمنتظره رخ داد.', 'wp-anar'),
                    'failed' => __('درخواست با خطا مواجه شد.', 'wp-anar'),
                ],
            ));

            wp_enqueue_script($this->plugin_name . '-import-v2');
        }

    }
}

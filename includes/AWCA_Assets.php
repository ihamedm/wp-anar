<?php
namespace Anar;

class AWCA_Assets {

    private $plugin_version;

    private $plugin_name;
    
    public function __construct($plugin_name, $plugin_version) {
        
        $this->plugin_name = $plugin_name;
        $this->plugin_version = $plugin_version;
        
        // Hook into WordPress to load assets
        add_action('wp_enqueue_scripts', [$this, 'load_public_assets']);
        add_action('admin_enqueue_scripts', [$this, 'load_admin_assets']);
    }

    /**
     * Load public-facing assets
     */
    public function load_public_assets() {
//        wp_enqueue_style($this->plugin_name . '-public', plugins_url('../assets/css/awca-public.css', __FILE__), null, $this->plugin_version);
//        wp_enqueue_script($this->plugin_name . '-public', plugins_url('../assets/js/awca-public.js', __FILE__), ['jquery'], $this->plugin_version, true);
    }

    /**
     * Load admin-facing assets
     */
    public function load_admin_assets() {
        wp_enqueue_style($this->plugin_name, plugins_url('../assets/css/awca-admin.css', __FILE__) , array(), $this->plugin_version, 'all');
        wp_enqueue_style($this->plugin_name . '-toastify-style', plugins_url('../assets/css/toastify.min.css', __FILE__) , array(), $this->plugin_version, 'all');


        wp_enqueue_script($this->plugin_name, plugins_url('../assets/js/awca-admin.js', __FILE__) , array('jquery'), $this->plugin_version, false);
        wp_enqueue_script($this->plugin_name . '-products', plugins_url('../assets/js/awca-products.js', __FILE__) , array('jquery'), $this->plugin_version, false);
        wp_enqueue_script($this->plugin_name . '-toastify-script', plugins_url('../assets/js/toastify.js', __FILE__) , array('jquery'), $this->plugin_version, false);



        wp_localize_script($this->plugin_name, 'awca_ajax_object', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'awca_handle_token_activation_ajax_nonce' => wp_create_nonce(),
        ));

        wp_localize_script($this->plugin_name . '-products', 'awca_ajax_object', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'awca_ajax_nonce' => wp_create_nonce(),
        ));
        
    }
}

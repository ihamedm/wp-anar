<?php

namespace Anar\Admin;

use Anar\AWCA_Payments;

class AWCA_Menus{
    
    public $is_activated;

    public function __construct(){
        
        $this->is_activated = awca_check_activation_state();
        
        add_action('admin_menu', [$this, 'create_plugin_menus']);
        add_action('admin_bar_menu', [$this, 'awca_add_product_creation_progress_to_toolbar'], 999);

    }

    
    public function create_plugin_menus()
    {

        add_menu_page(
            __('تنظیمات انار', 'awca-360'),
            __('تنظیمات انار', 'awca-360'),
            'manage_options',
            'awca-activation-menu',
            [$this, 'awca_activation_menu_content'],
            ANAR_WC_API_PLUGIN_URL . 'assets/images/icon.png',
            10
        );
        add_submenu_page(
            'awca-activation-menu',
            __('همگام سازی محصولات', 'awca-360'),
            __('همگام سازی محصولات', 'awca-360'),
            'manage_options',
            'awca-sync-menu',
            [$this, 'awca_sync_menu_content']
        );
        

//        add_submenu_page(
//            'awca-activation-menu',
//            __('تسویه حساب با انار', 'awca-360'),
//            __('تسویه حساب با انار', 'awca-360'),
//            'manage_options',
//            'awca-sync-pay',
//            [$this, 'awca_sync_menu_pay']
//        );


        
//        add_submenu_page(
//            'awca-activation-menu',
//            __('مرکز پیام', 'awca-360'),
//            __('مرکز پیام', 'awca-360'),
//            'manage_options',
//            'awca-inbox',
//            [$this, 'awca_inbox']
//        );

        add_submenu_page(
            'awca-activation-menu',
            __('اضافه کردن محصولات', 'awca-360'),
            __('اضافه کردن محصولات', 'awca-360'),
            'manage_options',
            'awca-configuration-menu',
            [$this, 'awca_configuration_menu_content']
        );
        add_submenu_page(
            'tools.php',
            __('ابزار انار', 'awca-360'),
            __('ابزار انار', 'awca-360'),
            'manage_options',
            'awca-tools',
            [$this, 'awca_tools']
        );

    }


    public function awca_activation_menu_content()
    {
        include ANAR_WC_API_ADMIN . 'partials/menu-contents/configuration-menus/activation-form-content.php';
    }

    public function awca_sync_menu_pay()
    {
        if (!$this->is_activated) {
            wp_redirect('?page=awca-configuration-menu');
            exit;
        } else {
            include ANAR_WC_API_ADMIN . 'partials/menu-contents/general-menus/sync-menu-pay.php';
        }
    }

    public function awca_sync_menu_content()
    {
        
        if (!$this->is_activated) {
            wp_redirect('?page=awca-activation-menu');
            exit;
        } else {
            include ANAR_WC_API_ADMIN . 'partials/menu-contents/general-menus/sync-menu-content.php';
        }
    }

    public function awca_dashboard_menu_content()
    {
        include ANAR_WC_API_ADMIN . 'partials/menu-contents/general-menus/dashboard-menu-content.php';
    }

    public function awca_configuration_menu_content()
    {
        include ANAR_WC_API_ADMIN . 'partials/menu-contents/general-menus/configuration-menu-content.php';
    }

    public function awca_settings_menu_content()
    {
        
        if (!$this->is_activated) {
            wp_redirect('?page=awca-activation-menu');
            exit;
        } else {
            include ANAR_WC_API_ADMIN . 'partials/menu-contents/general-menus/settings-menu-content.php';
        }
    }
    public function awca_financial_menu_content()
    {
        

        if (!$this->is_activated) {
            wp_redirect('?page=awca-activation-menu');
            exit;
        } else {
            include ANAR_WC_API_ADMIN . 'partials/menu-contents/general-menus/financial-menu-content.php';
        }
    }

    public function awca_orders_menu_content()
    {
        
        if (!$this->is_activated) {
            wp_redirect('?page=awca-activation-menu');
            exit;
        } else {
            include ANAR_WC_API_ADMIN . 'partials/menu-contents/general-menus/orders-menu-content.php';
        }
    }


    public function awca_inbox()
    {
        
        if (!$this->is_activated) {
            wp_redirect('?page=awca-activation-menu');
            exit;
        } else {
            include ANAR_WC_API_ADMIN . 'partials/menu-contents/general-menus/notifications.php';
        }
    }



    public function awca_tools()
    {
        
        if (!$this->is_activated) {
            wp_redirect('?page=awca-tools');
            exit;
        } else {
            include ANAR_WC_API_ADMIN . 'partials/menu-contents/general-menus/tools.php';
        }
    }


    public function awca_add_product_creation_progress_to_toolbar($wp_admin_bar) {
        // Get the value of the transient
        $proceed_products = get_option('awca_proceed_products');
        $total_products = get_option('awca_total_products');


        // Only add the menu item if the transient exists
        if ($proceed_products && $total_products) {

            $message = sprintf('انار - ساخت محصول %s از %s', $proceed_products, $total_products);

            $args = array(
                'id' => 'awca_product_creation_progress',
                'title' => '<div class="wrap"><span class="ripple-dot"></span><span class="awca-progress-bar"></span><span class="msg">' . esc_html($message) . '</span></div>', // The content of the menu item
                'href' => false, // No link
                'meta' => array(
                    'class' => 'awca-progress-bar-menu-bar-item' // Add a custom class for styling
                )
            );

            $wp_admin_bar->add_node($args);

            // Add the dropdown items
            $dropdown_items = array(
                array('id' => 'awca_progress_detail_1', 'title' => 'Detail 1', 'href' => false),
                array('id' => 'awca_progress_detail_2', 'title' => 'Detail 2', 'href' => false),
                array('id' => 'awca_progress_detail_3', 'title' => 'Detail 3', 'href' => false)
            );

            foreach ($dropdown_items as $item) {
                $item_args = array(
                    'id' => $item['id'],
                    'title' => $item['title'],
                    'href' => $item['href'],
                    'parent' => 'awca_product_creation_progress',
                    'meta' => array(
                        'class' => 'awca-progress-bar-dropdown-item'
                    )
                );

                //$wp_admin_bar->add_node($item_args);
            }
        }
    }

}
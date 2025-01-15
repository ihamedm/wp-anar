<?php

namespace Anar\Admin;

use Anar\Background_Process_Products;
use Anar\Background_Process_Thumbnails;
use Anar\Core\Activation;
use Anar\CronJob_Process_Products;
use Anar\ProductData;

class Menus{
    
    public $is_activated;

    public function __construct(){
        
        $this->is_activated = Activation::validate_saved_activation_key_from_anar();
        
        add_action('admin_menu', [$this, 'add_menu_pages']);
        //add_action('admin_bar_menu', [$this, 'product_creation_bg_progress_to_toolbar'], 999);
        add_action('admin_bar_menu', [$this, 'product_creation_cron_progress_to_toolbar'], 999);
        //add_action('admin_bar_menu', [$this, 'thumbnail_download_progress_to_toolbar'], 999);

    }


    public function force_activation(){
        if (!$this->is_activated) {
            wp_redirect('?page=configs');
            exit;
        }
    }

    
    public function add_menu_pages()
    {

        add_menu_page(
            'انار۳۶۰',
            'انار۳۶۰',
            'manage_options',
            'wp-anar',
            [$this, 'create_products_page_content'],
            ANAR_PLUGIN_URL . '/assets/images/icon.png',
            10
        );

        add_submenu_page(
            'wp-anar',
            'اضافه کردن محصولات',
            'اضافه کردن محصولات',
            'manage_options',
            'wp-anar',
            [$this, 'create_products_page_content']
        );

        if( ANAR_IS_ENABLE_PAYMENTS_PAGE )
            add_submenu_page(
                'wp-anar',
                'تسویه حساب',
                'تسویه حساب',
                'manage_options',
                'payments',
                [$this, 'payments_page_content']
            );


        
        add_submenu_page(
            'wp-anar',
            'مرکز پیام',
            'مرکز پیام',
            'manage_options',
            'notifications',
            [$this, 'notifications_page_content']
        );

        add_submenu_page(
            'wp-anar',
            'ابزارها',
            'ابزارها',
            'manage_options',
            'tools',
            [$this, 'tools_page_content']
        );

        add_submenu_page(
            'wp-anar', // Parent slug
            'تنظیمات', // Submenu page title (this will be displayed in the page title)
            'تنظیمات', // Submenu title (this will be displayed in the menu)
            'manage_options', // Capability
            'configs', // Submenu slug (same as main menu)
            [$this, 'activation_page_content'] // Callback function
        );

    }


    public function activation_page_content()
    {
        include_once ANAR_PLUGIN_PATH . 'includes/admin/menu/activation.php';
    }

    public function create_products_page_content()
    {
        $this->force_activation();
        include_once ANAR_PLUGIN_PATH . 'includes/admin/menu/products-wizard.php';
    }

    public function payments_page_content()
    {
        $this->force_activation();
        include_once ANAR_PLUGIN_PATH . 'includes/admin/menu/payments.php';
    }

    public function notifications_page_content()
    {
        $this->force_activation();
        include_once ANAR_PLUGIN_PATH . 'includes/admin/menu/notifications.php';
    }

    public function tools_page_content()
    {
        $this->force_activation();
        include_once ANAR_PLUGIN_PATH . 'includes/admin/menu/tools.php';
    }



    public function product_creation_bg_progress_to_toolbar($wp_admin_bar) {
        $productData = new ProductData();
        $productProcessing = Background_Process_Products::get_instance();



        // Only add the menu item if the transient exists
        if (in_array($productProcessing->get_status(), ['processing', 'queued']) ) {
            $total_products = get_option('awca_total_products');
            $proceed_products = $productData->count_anar_products();
            if($total_products == 0 || $proceed_products == 0)
                return;

            $message = sprintf('انار - ساخت محصول %s از %s', $proceed_products, $total_products);
            $progress_width_percent = $proceed_products * 100 / $total_products;
            $message2 = sprintf('انار - ساخت محصول %s', round($progress_width_percent).'%');
            $args = array(
                'id' => 'awca_product_creation_progress',
                'title' => '<div class="wrap"><span class="bgprogress" style="width:'.$progress_width_percent.'%"></span> <span class="ripple-dot"></span><span class="awca-progress-bar"></span><span class="msg">' . esc_html($message2) . '</span></div>', // The content of the menu item
                'href' => false, // No link
                'meta' => array(
                    'class' => 'awca-progress-bar-menu-bar-item' // Add a custom class for styling
                )
            );

            $wp_admin_bar->add_node($args);

            // Add the dropdown items
            $dropdown_items = array(
                array('id' => 'awca_progress_detail_1', 'title' => 'هر دقیقه ۳۰ محصول ساخته می شود', 'href' => false),
                array('id' => 'awca_progress_detail_2', 'title' => 'شروع پردازش: سه شنبه ۱۸ دی  ساعت ۱۲:۰۱', 'href' => false),
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

                $wp_admin_bar->add_node($item_args);
            }
        }





    }

    public function product_creation_cron_progress_to_toolbar($wp_admin_bar)
    {
        $productData = new ProductData();

        // Only add the menu item if the transient exists
        if (!CronJob_Process_Products::is_create_products_cron_locked()) {
            $total_products = get_option('awca_total_products');
            $proceed_products = $productData->count_anar_products();
            $proceed_products_increment = get_option('awca_proceed_products');
            if ($total_products == 0 || $proceed_products == 0)
                return;

            $progress_width_percent = $proceed_products * 100 / $total_products;
            $progress_width_percent = 100;
//            $message2 = sprintf('انار - پردازش محصولات انار %s', round($progress_width_percent) . '%');
            $message2 = 'پردازش محصولات انار';
            $args = array(
                'id' => 'awca_product_creation_progress',
                'title' => '<div class="wrap"><span class="bgprogress" style="width:' . $progress_width_percent . '%"></span> <span class="ripple-dot"></span><span class="awca-progress-bar"></span><span class="msg">' . esc_html($message2) . '</span></div>', // The content of the menu item
                'href' => false,
                'meta' => array(
                    'class' => 'awca-progress-bar-menu-bar-item'
                )
            );

            $wp_admin_bar->add_node($args);

            $start_time = wp_date('j F Y ساعت H:i', get_option('awca_cron_create_products_start_time'));
            $estimate_finish = round(($total_products - $proceed_products_increment) / 30) + 2;

            $dropdown_items = array(
                array('id' => 'awca_progress_detail_1', 'title' => 'هر دقیقه ۳۰ محصول پردازش می شود', 'href' => false),
                array('id' => 'awca_progress_detail_2',
                    'title' => "{$proceed_products_increment} محصول از {$total_products} پردازش شده",
                    'href' => false
                ),
            );

            if($estimate_finish > 0){
                $dropdown_items[] = array('id' => 'awca_progress_detail_3',
                    'title' => "حدودا {$estimate_finish} دقیقه تا پایان پردازش",
                    'href' => false
                );
            }

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

                $wp_admin_bar->add_node($item_args);
            }

        }
    }
    public function thumbnail_download_progress_to_toolbar($wp_admin_bar){
        $ThumbnailProcessing = Background_Process_Thumbnails::get_instance();
        if (in_array($ThumbnailProcessing->get_status(), ['processing']) ) {

            $thumbnail_processing_data = $ThumbnailProcessing->get_process_data();
            if($thumbnail_processing_data['processed_products'] == 0 || $thumbnail_processing_data['total_products'] == 0)
                return;

            $progress_width_percent = $thumbnail_processing_data['processed_products'] * 100 / $thumbnail_processing_data['total_products'];
            $message2 = sprintf('انار - دریافت تصاویر %s', round($progress_width_percent).'%');
            $args = array(
                'id' => 'awca_product_thumbnail_progress',
                'title' => '<div class="wrap"><span class="bgprogress" style="width:'.$progress_width_percent.'%"></span> <span class="ripple-dot"></span><span class="awca-progress-bar"></span><span class="msg">' . esc_html($message2) . '</span></div>', // The content of the menu item
                'href' => false, // No link
                'meta' => array(
                    'class' => 'awca-progress-bar-menu-bar-item' // Add a custom class for styling
                )
            );

            $wp_admin_bar->add_node($args);

        }
    }

}
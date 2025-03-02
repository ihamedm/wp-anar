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
        add_action('admin_bar_menu', [$this, 'product_creation_cron_progress_to_toolbar'], 999);
        add_filter('plugin_action_links', [$this, 'add_settings_link'], 10, 2);

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
            ANAR_PLUGIN_URL . '/assets/images/anar360-icon.svg',
            10
        );


        if (get_option(OPT_KEY__COUNT_ANAR_PRODUCT_ON_DB, 0) == 0){
            $menu_import_label = 'درون ریزی محصولات';
        }else{
            $menu_import_label = 'همگام سازی محصولات';
        }
        add_submenu_page(
            'wp-anar',
            $menu_import_label,
            $menu_import_label,
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


        if(ANAR_IS_ENABLE_NOTIF_PAGE)
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

        add_submenu_page(
            'wp-anar', // Parent slug
            'راهنما و مستندات انار۳۶۰', // Submenu title (this will be displayed in the menu)
            'راهنما', // Submenu page title (this will be displayed in the page title)
            'manage_options', // Capability
            'docs', // Submenu slug (same as main menu)
            [$this, 'docs_page_content'] // Callback function
        );

    }


    public function add_settings_link($links, $plugin_file){
        if (ANAR_PLUGIN_BASENAME == $plugin_file) {
            $settings_link = '<a href="admin.php?page=wp-anar">همگام سازی محصولات</a>';
            $links[ANAR_PLUGIN_TEXTDOMAIN] = $settings_link;
        }

        return $links;
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
        $active_tab = $_GET['tab'] ?? 'tools';

        ?>
        <div class="wrap awca-wrap">
            <h2>ابزارهای انار</h2>

            <h2 class="nav-tab-wrapper">
                <a href="?page=tools&tab=tools" class="nav-tab <?php echo $active_tab === 'tools' ? 'nav-tab-active' : ''; ?>">ابزارها</a>
                <a href="?page=tools&tab=features" class="nav-tab <?php echo $active_tab === 'features' ? 'nav-tab-active' : ''; ?>">امکانات</a>
                <a href="?page=tools&tab=status" class="nav-tab <?php echo $active_tab === 'status' ? 'nav-tab-active' : ''; ?>">وضعیت سیستم</a>
            </h2>

            <?php include_once ANAR_PLUGIN_PATH . 'includes/admin/menu/tools/'.$active_tab.'.php';?>


        </div>
        <?php
    }

    public function docs_page_content()
    {
        $active_tab = $_GET['tab'] ?? 'help';
        ?>
        <div class="wrap awca-wrap">
            <h2>راهنما و مستندات انار۳۶۰</h2>

            <h2 class="nav-tab-wrapper">
                <a href="?page=docs&tab=help" class="nav-tab <?php echo $active_tab === 'help' ? 'nav-tab-active' : ''; ?>">آموزش افزودن محصولات</a>
                <a href="?page=docs&tab=faq" class="nav-tab <?php echo $active_tab === 'faq' ? 'nav-tab-active' : ''; ?>">سوالات متداول</a>
                <a href="?page=docs&tab=changelogs" class="nav-tab <?php echo $active_tab === 'changelogs' ? 'nav-tab-active' : ''; ?>">تاریخچه تغییرات</a>
            </h2>

            <?php include_once ANAR_PLUGIN_PATH . 'includes/admin/menu/doc/'.$active_tab.'.php';?>


        </div>
        <?php
    }


    public function product_creation_cron_progress_to_toolbar($wp_admin_bar)
    {
        $productData = new ProductData();

        // Only add the menu item if the transient exists
        if (awca_is_import_products_running()) {
            $total_products = get_option('awca_total_products');
            $count_db_products = $productData->count_anar_products();
            $proceed_products_increment = get_option('awca_proceed_products');
            if ($total_products == 0 || $count_db_products == 0)
                return;

            // sometimes we have bug on increment , so prevent show processed products number grater than total products
            // @todo better counting processed products
            $proceed_products_increment = $proceed_products_increment > $total_products ? $total_products : $proceed_products_increment;
            $progress_width_percent = $count_db_products * 100 / $total_products;
            $progress_width_percent = 100;
//            $message2 = sprintf('انار - پردازش محصولات انار %s', round($progress_width_percent) . '%');
            $message2 = 'همگام سازی محصولات انار';
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
                array('id' => 'awca_progress_detail_1', 'title' => 'هر دقیقه ۳۰ محصول همگام سازی می شود', 'href' => false),
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

}
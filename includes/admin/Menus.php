<?php

namespace Anar\Admin;

use Anar\Core\Activation;
use Anar\Import;
use Anar\ProductData;

class Menus{
    
    public $is_activated;

    public function __construct(){
        
        $this->is_activated = Activation::is_active();
        
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
            'wp-anar',
            'فعالسازی',
            'فعالسازی',
            'manage_options',
            'configs',
            [$this, 'activation_page_content']
        );

        add_submenu_page(
            'wp-anar',
            'راهنما و مستندات انار۳۶۰',
            'راهنما',
            'manage_options',
            'docs',
            [$this, 'docs_page_content']
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
                <a href="?page=tools&tab=features" class="nav-tab <?php echo $active_tab === 'features' ? 'nav-tab-active' : ''; ?>">تنظیمات</a>
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
            $cronJob = Import::get_instance();
            $progress = $cronJob->get_progress_data();
            
            if ($progress['total'] == 0 || $productData->count_anar_products() == 0) {
                return;
            }

            $progress_width_percent = 100;
            $message2 = 'همگام سازی محصولات انار';
            $args = array(
                'id' => 'awca_product_creation_progress',
                'title' => '<div class="wrap"><span class="bgprogress" style="width:' . $progress_width_percent . '%"></span> <span class="ripple-dot"></span><span class="awca-progress-bar"></span><span class="msg">' . esc_html($message2) . '</span></div>',
                'href' => false,
                'meta' => array(
                    'class' => 'awca-progress-bar-menu-bar-item'
                )
            );

            $wp_admin_bar->add_node($args);

            $dropdown_items = array(
                array('id' => 'awca_progress_detail_1',
                    'title' => "{$progress['processed']} محصول از {$progress['total']} پردازش شده",
                    'href' => false
                ),
            );

            if($progress['estimated_minutes'] > 0){
                $dropdown_items[] = array('id' => 'awca_progress_detail_2',
                    'title' => "حدودا {$progress['estimated_minutes']} دقیقه تا پایان پردازش",
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
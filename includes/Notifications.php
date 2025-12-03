<?php

namespace Anar;

class Notifications {

    private static $instance;

    public $star_icon = '<svg  xmlns="http://www.w3.org/2000/svg"  width="16"  height="16"  viewBox="0 0 24 24"  fill="#ff6600"  class="icon icon-tabler icons-tabler-filled icon-tabler-star"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><path d="M8.243 7.34l-6.38 .925l-.113 .023a1 1 0 0 0 -.44 1.684l4.622 4.499l-1.09 6.355l-.013 .11a1 1 0 0 0 1.464 .944l5.706 -3l5.693 3l.1 .046a1 1 0 0 0 1.352 -1.1l-1.091 -6.355l4.624 -4.5l.078 -.085a1 1 0 0 0 -.633 -1.62l-6.38 -.926l-2.852 -5.78a1 1 0 0 0 -1.794 0l-2.853 5.78z" /></svg>';

    public static function get_instance(){
        if ( ! isset( self::$instance ) && ! ( self::$instance instanceof Notifications ) ) {
            self::$instance = new Notifications();
        }
        return self::$instance;
    }

    public function __construct() {

        //@fetch all notifications and count unread 1 time in a day via cron
        add_action('admin_menu', [$this, 'add_menu_badges']);
        add_action('admin_notices', [$this, 'display_dashboard_alert']);

        add_action('admin_enqueue_scripts', [$this, 'enqueue_notification_assets']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_alert_assets']);

        add_action('wp_ajax_anar_fetch_notifications_ajax', [$this, 'fetch_page']);
        add_action('wp_ajax_anar_mark_as_read_notifications_ajax', [$this, 'mark_as_read_page']);
        add_action('wp_ajax_anar_mark_single_notification_read_ajax', [$this, 'mark_single_as_read']);
        add_action('wp_ajax_anar_mark_alert_notification_read_ajax', [$this, 'mark_alert_notification_read']);

    }


    public function add_menu_badges() {
        // Get the notification count
        $unread_notifications = get_option('anar_unread_notifications', 0);

        // If the count is zero, don't display the badge
        if (!$unread_notifications || $unread_notifications == 0) {
            return;
        }

        global $menu, $submenu;

        // Find the menu item and add the badge
        foreach ($menu as $index => $menu_item) {
            if ($menu_item[2] == 'wp-anar') {
                $menu[$index][0] .= " <span class='awaiting-mod update-plugins count-$unread_notifications'><span class='plugin-count'>$unread_notifications</span></span>";
            }
        }

        // Find the submenu item and add the badge
        if (isset($submenu['wp-anar'])) {
            foreach ($submenu['wp-anar'] as $index => $submenu_item) {
                if ($submenu_item[2] == 'notifications') {
                    $submenu['wp-anar'][$index][0] .= " <span class='awaiting-mod update-plugins count-$unread_notifications'><span class='plugin-count'>$unread_notifications</span></span>";
                }
            }
        }
    }

    /**
     * Enqueue status tools assets on the status page
     */
    public function enqueue_notification_assets($hook)
    {
        // Check if we're on the tools page using $_GET parameter (more reliable than hook name)
        if (!isset($_GET['page']) || $_GET['page'] !== 'notifications') {
            return;
        }

        // Enqueue status-tools script
        wp_enqueue_script(
            'anar-notifications',
            ANAR_PLUGIN_URL . '/assets/dist/notifications.min.js',
            ['jquery'],
            ANAR_PLUGIN_VERSION,
            true
        );

        wp_enqueue_style(
            'anar-notifications',
            ANAR_PLUGIN_URL . '/assets/css/notifications.css',
            [],
            ANAR_PLUGIN_VERSION
        );

        // Localize script with AJAX data
        wp_localize_script('anar-notifications', 'anar_ajax_object', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('anar_ajax_nonce'),
        ));
    }

    public function fetch_page(){

        $page = isset($_POST['page']) ? intval($_POST['page']) : 1;
        $limit = isset($_POST['limit']) ? intval($_POST['limit']) : 100;

        $query_args = [
            'page' =>           $page,
            'limit' =>          $limit,
            'application' =>    $_POST['application'] ?? 'wordpress'
        ];

        $api_url = ApiDataHandler::getApiUrl('notifications', $query_args);

        $response = ApiDataHandler::callAnarApi($api_url);

        if (is_wp_error($response)) {
            $message = $response->get_error_message();
            wp_send_json_error(["message" => $message]);
        }

        $response_body = json_decode(wp_remote_retrieve_body($response), true);

        if ($response_body['success']) {
            update_option('anar_unread_notifications', $response_body['unReads'] ?? 0);
            $notifications = $response_body['result'];
            $output = '';

            if(is_array($notifications) ) {
                foreach ($notifications as $i => $notification) {

                    if( $i == 0 ) {
                        $this->check_and_store_alert_data($notification);
                    }

                    $notification_id = isset($notification['_id']) ? esc_attr($notification['_id']) : '';
                    $is_wordpress = $notification["application"] == 'wordpress';
                    $is_unread = !$notification['read'];
                    $mark_read_btn = '';
                    
                    // Add mark as read button for all unread notifications
                    if ($is_unread && $notification_id) {
                        $mark_read_btn = sprintf(
                            '<span class="anar-mark-read-btn" data-notification-id="%s" title="علامت گذاری به عنوان خوانده شده">
                                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.25" stroke-linecap="round" stroke-linejoin="round" class="icon icon-tabler icons-tabler-outline icon-tabler-mail-opened"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><path d="M3 9l9 6l9 -6l-9 -6l-9 6" /><path d="M21 9v10a2 2 0 0 1 -2 2h-14a2 2 0 0 1 -2 -2v-10" /><path d="M3 19l6 -6" /><path d="M15 13l6 6" /></svg>
                            </span>',
                            $notification_id
                        );
                    }
                    
                    // Get full description
                    $description = $notification['description'] ?? '';
                    $description_preview = mb_strlen($description) > 100 ? mb_substr($description, 0, 100) . '...' : $description;
                    
                    $date_formatted = wp_date('j F Y ساعت H:i', strtotime($notification["updatedAt"]));
                    
                    $output .= sprintf('<div class="item %s" data-notification-id="%s" data-full-description="%s" data-is-unread="%s">
                        <div class="notification-row">
                            <div class="notification-content">
                                <span class="notification-title">%s</span>
                                <span class="notification-preview">%s</span>
                            </div>
                            <div class="notification-meta">
                                <date>%s</date>
                            </div>
                           
                        </div>
                        %s
                        <div class="notification-full-content" style="display: none;">
                            <div class="notification-full-message">%s</div>
                        </div>
                    </div>',
                        $notification["application"] .' '. $notification["type"] .' '. $notification["reason"]
                        . (!$notification['read'] ? ' unread' : ' read'),
                        $notification_id,
                        esc_attr($description),
                        $is_unread ? '1' : '0',
                        esc_html($notification['title']),
                        esc_html($description_preview),
                        $date_formatted,
                        $mark_read_btn,
                        wp_kses_post($description)
                    );


                }
                $message = $response_body['message'] ?? 'اعلان ها با موفقیت دریافت شد.';
                wp_send_json_success(["message" => $message, 'output' => $output, 'total' => $response_body['total'], 'page' => $page, 'limit' => $limit]);
            }else{
                $output .= sprintf('<div class="item">%s</div>',
                    'هیچ اعلانی وجود ندارد',
                
                );

                $message = $response_body['message'] ?? 'هیچ اعلانی وجود ندارد';
                wp_send_json_success(["message" => $message, 'output' => $output]);
            }
        } else {
            $message =  $response_body['message'] ?? 'مشکلی در دریافت اعلان ها بوجود آمد.';
            wp_send_json_error(["message" => $message]);
        }
    }


    public function mark_as_read_page(){
        $page = isset($_POST['page']) ? intval($_POST['page']) : 1;
        $limit = isset($_POST['limit']) ? intval($_POST['limit']) : 100;
        $application = isset($_POST['application']) ? sanitize_text_field($_POST['application']) : 'wordpress';

        $query_args = [
            'page' => $page,
            'limit' => $limit,
            'application' => $application
        ];

        $api_url = ApiDataHandler::getApiUrl('notifications', $query_args);
        $response = ApiDataHandler::callAnarApi($api_url);

        if (is_wp_error($response)) {
            wp_send_json_error(["message" => 'مشکلی در نشانه گذاری پیام ها به عنوان خوانده شده پیش آمد']);
        }

        $response_body = json_decode(wp_remote_retrieve_body($response), true);

        if ($response_body['success']) {

            $notifications = $response_body['result'];

            if(is_array($notifications) ) {
                $i = 0;
                foreach ($notifications as $notification) {
                    if(!$notification['read']){
                        $this->mark_as_read_notification($notification['_id']);
                        $i++;
                    }
                }
                wp_send_json_success(["message" => "$i پیام به عنوان خوانده شده علامت گذاری شد."]);
            }else{
                wp_send_json_success(["message" => 'مشکلی در نشانه گذاری پیام ها به عنوان خوانده شده پیش آمد']);
            }
        } else {
            $message =  $response_body['message'] ?? 'مشکلی در دریافت اعلان ها بوجود آمد.';
            wp_send_json_error(["message" => $message]);
        }
    }

    private function mark_as_read_notification($id){
        ApiDataHandler::postAnarApi("https://api.anar360.com/wp/notifications/$id/read", ['id' => $id]);
    }

    public function mark_single_as_read(){
        $notification_id = isset($_POST['notification_id']) ? sanitize_text_field($_POST['notification_id']) : '';
        
        if (empty($notification_id)) {
            wp_send_json_error(["message" => 'شناسه اعلان معتبر نیست']);
            return;
        }

        $response = ApiDataHandler::postAnarApi("https://api.anar360.com/wp/notifications/$notification_id/read", ['id' => $notification_id]);

        if (is_wp_error($response)) {
            wp_send_json_error(["message" => 'مشکلی در نشانه گذاری اعلان به عنوان خوانده شده پیش آمد']);
            return;
        }

        // Update unread count
        $this->count_unread_notifications();

        wp_send_json_success(["message" => 'اعلان به عنوان خوانده شده علامت گذاری شد.']);
    }

    public function count_unread_notifications()
    {
        $response = ApiDataHandler::callAnarApi("https://api.anar360.com/wp/notifications?page=1&limit=1&application=wordpress");

        if (is_wp_error($response)) {
            return;
        }

        $response_body = json_decode(wp_remote_retrieve_body($response), true);

        if ($response_body['success'] && isset($response_body['unReads'])) {
            update_option('anar_unread_notifications', $response_body['unReads']);
        }

        // Check the last notification for alert data
        if ($response_body['success'] && isset($response_body['result']) && is_array($response_body['result']) && !empty($response_body['result'])) {
            $last_notification = $response_body['result'][0];
            $this->check_and_store_alert_data($last_notification);
        }
    }

    /**
     * Check notification data for alert information and store it
     * 
     * @param array $notification The notification data from API
     */
    private function check_and_store_alert_data($notification) {
        // Check if notification is read - if so, clear alert
        if (isset($notification['read']) && $notification['read']) {
            delete_option('anar_dashboard_alert');
            return;
        }

        // Check if notification has data field
        if (!isset($notification['data']) || empty($notification['data'])) {
            // Clear alert if no data
            delete_option('anar_dashboard_alert');
            return;
        }

        $data = $notification['data'];
        
        // If data is a JSON string, decode it
        if (is_string($data)) {
            $data = json_decode($data, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                return;
            }
        }

        // Check if showAlert is set to "true" (string) or true (boolean)
        $show_alert = isset($data['showAlert']) && (
            $data['showAlert'] === 'true' || 
            $data['showAlert'] === true || 
            $data['showAlert'] === '1' || 
            $data['showAlert'] === 1
        );

        if (!$show_alert) {
            // Clear alert if showAlert is not true
            delete_option('anar_dashboard_alert');
            return;
        }

        // Prepare alert data
        $alert_data = [
            'type' => isset($data['alertType']) ? sanitize_text_field($data['alertType']) : 'info',
            'title' => isset($data['alertTitle']) ? sanitize_text_field($data['alertTitle']) : ($notification['title'] ?? ''),
            'message' => isset($data['alertMessage']) ? sanitize_textarea_field($data['alertMessage']) : ($notification['description'] ?? ''),
            'buttonText' => isset($data['alertBtnTxt']) ? sanitize_text_field($data['alertBtnTxt']) : '',
            'buttonLink' => isset($data['alertBtnLink']) ? esc_url_raw($data['alertBtnLink']) : '',
            'buttonAdminLink' => isset($data['alertBtnAdminLink']) ? esc_url_raw($data['alertBtnAdminLink']) : '',
            'notificationId' => isset($notification['_id']) ? sanitize_text_field($notification['_id']) : '',
        ];

        // Validate alert type
        $allowed_types = ['info', 'warning', 'success', 'error'];
        if (!in_array($alert_data['type'], $allowed_types)) {
            $alert_data['type'] = 'info';
        }

        // Store alert data
        update_option('anar_dashboard_alert', $alert_data);
    }

    /**
     * Display dashboard alert on all admin pages
     */
    public function display_dashboard_alert() {
        $alert_data = get_option('anar_dashboard_alert');
        
        if (!$alert_data || !is_array($alert_data)) {
            return;
        }

        $type = isset($alert_data['type']) ? esc_attr($alert_data['type']) : 'info';
        $title = isset($alert_data['title']) ? esc_html($alert_data['title']) : '';
        $message = isset($alert_data['message']) ? wp_kses_post($alert_data['message']) : '';
        $button_text = isset($alert_data['buttonText']) ? esc_html($alert_data['buttonText']) : '';
        $button_link = isset($alert_data['buttonLink']) ? esc_url($alert_data['buttonLink']) : '';
        $button_admin_link = isset($alert_data['buttonAdminLink']) ? esc_url($alert_data['buttonAdminLink']) : '';
        $notification_id = isset($alert_data['notificationId']) ? esc_attr($alert_data['notificationId']) : '';

        if (empty($message) && empty($title)) {
            return;
        }

        // Determine which link to use (admin link takes priority if both exist)
        $final_button_link = !empty($button_admin_link) ? $button_admin_link : $button_link;

        $action_buttons = '';
        if (!empty($button_text) && !empty($final_button_link)) {
            $dismiss_link = sprintf(
                ' <a href="#" class="anar-alert-dismiss-link" data-notification-id="%s">دیگر نمایش نده</a>',
                $notification_id
            );
            
            $action_buttons = sprintf(
                '<p>
                    <a href="%s" class="button button-primary">%s</a>%s
                </p>',
                $final_button_link,
                $button_text,
                $dismiss_link
            );
        } else {
            $action_buttons = sprintf(
                '<p>
                    <a href="#" class="anar-alert-dismiss-link" data-notification-id="%s">دیگر نمایش نده</a>
                </p>',
                $notification_id
            );
        }

        $title_html = '';
        if (!empty($title)) {
            $title_html = sprintf('<strong>%s</strong>', $title);
            if (!empty($message)) {
                $title_html .= '<br>';
            }
        }

        $anar_fruit_url = ANAR_WC_API_PLUGIN_URL.'assets/images/anar-fruit.svg';
        $anar_icon  = '<img style="width: 24px; position:absolute;right:10px;top:22px;" src="'. $anar_fruit_url .'">';

        printf(
            '<div class="notice notice-%s anar-dashboard-alert" data-notification-id="%s">
                <p>%s%s</p>
                %s
                %s
            </div>',
            $type,
            $notification_id,
            $title_html,
            $message,
            $action_buttons,
            $anar_icon
        );
    }

    /**
     * Enqueue alert assets on all admin pages
     * Note: alert.js is now imported in admin.js, so we only need to localize script
     */
    public function enqueue_alert_assets($hook) {
        $alert_data = get_option('anar_dashboard_alert');
        
        if (!$alert_data || !is_array($alert_data)) {
            return;
        }

        // Localize script for admin.js (which imports alert.js)
        // Use the same handle as the admin script (ANAR_PLUGIN_TEXTDOMAIN)
        $admin_script_handle = ANAR_PLUGIN_TEXTDOMAIN;
        wp_localize_script($admin_script_handle, 'anar_alert_object', [
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('anar_alert_nonce'),
        ]);
    }

    /**
     * Handle alert notification mark as read via AJAX
     */
    public function mark_alert_notification_read() {
        check_ajax_referer('anar_alert_nonce', 'nonce');

        $notification_id = isset($_POST['notification_id']) ? sanitize_text_field($_POST['notification_id']) : '';
        
        if (empty($notification_id)) {
            wp_send_json_error(['message' => 'شناسه اعلان معتبر نیست']);
            return;
        }

        // Mark notification as read via API
        $response = ApiDataHandler::postAnarApi("https://api.anar360.com/wp/notifications/$notification_id/read", ['id' => $notification_id]);

        if (is_wp_error($response)) {
            wp_send_json_error(['message' => 'مشکلی در علامت گذاری اعلان به عنوان خوانده شده پیش آمد']);
            return;
        }

        // Clear the alert since notification is now read
        delete_option('anar_dashboard_alert');

        // Update unread count
        $this->count_unread_notifications();

        wp_send_json_success(['message' => 'اعلان به عنوان خوانده شده علامت گذاری شد']);
    }

}
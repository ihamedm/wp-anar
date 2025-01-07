<?php

namespace Anar;

class Notifications {
    public function __construct() {

        //@fetch all notif and count unread 1 time in a day via cron
        add_action('admin_menu', [$this, 'awca_add_menu_badges']);

        add_action('wp_ajax_awca_fetch_notifications_ajax', [$this, 'awca_fetch_notifications_ajax']);
        add_action('wp_ajax_nopriv_awca_fetch_notifications_ajax', [$this, 'awca_fetch_notifications_ajax']);

    }


    public function awca_add_menu_badges() {
        // Get the notification count
        $unread_notifications = get_option('awca_unread_notifications', 0);

        // If the count is zero, don't display the badge
        if (!$unread_notifications || $unread_notifications == 0) {
            return;
        }

        global $menu, $submenu;

        // Find the menu item and add the badge
        foreach ($menu as $index => $menu_item) {
            if ($menu_item[2] == 'awca-activation-menu') {
                $menu[$index][0] .= " <span class='awaiting-mod update-plugins count-$unread_notifications'><span class='plugin-count'>$unread_notifications</span></span>";
            }
        }

        // Find the submenu item and add the badge
        if (isset($submenu['awca-activation-menu'])) {
            foreach ($submenu['awca-activation-menu'] as $index => $submenu_item) {
                if ($submenu_item[2] == 'awca-inbox') {
                    $submenu['awca-activation-menu'][$index][0] .= " <span class='awaiting-mod update-plugins count-$unread_notifications'><span class='plugin-count'>$unread_notifications</span></span>";
                }
            }
        }
    }


    public function awca_fetch_notifications_ajax(){
        $page = isset($_POST['page']) ? intval($_POST['page']) : 1;
        $limit = isset($_POST['limit']) ? intval($_POST['limit']) : 30;

        $response = ApiDataHandler::callAnarApi("https://api.anar360.com/wp/notifications?page=$page&limit=$limit");

        if (is_wp_error($response)) {
            $message = $response->get_error_message();
            wp_send_json_error(["message" => $message]);
        }

        $response_body = json_decode(wp_remote_retrieve_body($response), true);

        if ($response_body['success']) {

            $notifications = $response_body['data']['result'];
            $output = '';

            if(is_array($notifications) ) {
                foreach ($notifications as $index => $notification) {

                    $output .= sprintf('<tr class="item">
                            <td>%s</td>
                            <td>%s</td>
                            <td>%s</td>
                        </tr>',
                        $index + 1,
                        $notification['read'] ? $notification['title'] : '<b>' . $notification['title'] . '</b>',
                        $notification['read'] ? $notification['description'] : '<b>' . $notification['description'] . '</b>',
                    );

                    if(isset($notification['_id']))
                        $this->mark_as_read_notification($notification['_id']);

                    $message = $response_body['message'] ?? 'اعلان ها با موفقیت دریافت شد.';
                    wp_send_json_success(["message" => $message, 'output' => $output]);

                }
            }else{
                $output .= sprintf('<tr class="item">
                            <td></td>
                            <td>%s</td>
                            <td></td>
                        </tr>',
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


    private function mark_as_read_notification($id){

        ApiDataHandler::callAnarApi("https://api.anar360.com/wp/notifications/$id/read");

    }


    public function count_unread_notifications()
    {
        $response = ApiDataHandler::callAnarApi("https://api.anar360.com/wp/notifications?page=1&limit=1");

        if (is_wp_error($response)) {
            awca_log("count unread notifications error: " . print_r($response->get_error_message(), true));
            return;
        }

        $response_body = json_decode(wp_remote_retrieve_body($response), true);

        if ($response_body['success']) {
            update_option('awca_unread_notifications', $response_body['data']['total']);
        } else {
            awca_log("count unread notifications error: " . print_r($response_body, true));
        }
    }

}
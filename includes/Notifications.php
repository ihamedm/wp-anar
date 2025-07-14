<?php

namespace Anar;

use Anar\Core\Mock;

class Notifications {

    public $star_icon = '<svg  xmlns="http://www.w3.org/2000/svg"  width="16"  height="16"  viewBox="0 0 24 24"  fill="#ff6600"  class="icon icon-tabler icons-tabler-filled icon-tabler-star"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><path d="M8.243 7.34l-6.38 .925l-.113 .023a1 1 0 0 0 -.44 1.684l4.622 4.499l-1.09 6.355l-.013 .11a1 1 0 0 0 1.464 .944l5.706 -3l5.693 3l.1 .046a1 1 0 0 0 1.352 -1.1l-1.091 -6.355l4.624 -4.5l.078 -.085a1 1 0 0 0 -.633 -1.62l-6.38 -.926l-2.852 -5.78a1 1 0 0 0 -1.794 0l-2.853 5.78z" /></svg>';

    public function __construct() {

        //@fetch all notif and count unread 1 time in a day via cron
        add_action('admin_menu', [$this, 'awca_add_menu_badges']);

        add_action('wp_ajax_awca_fetch_notifications_ajax', [$this, 'fetch_page']);
        add_action('wp_ajax_awca_mark_as_read_notifications_ajax', [$this, 'mark_as_read_page']);

    }


    public function awca_add_menu_badges() {
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


    public function fetch_page(){
        $page = isset($_POST['page']) ? intval($_POST['page']) : 1;
        $limit = isset($_POST['limit']) ? intval($_POST['limit']) : 100;

        $response = ApiDataHandler::callAnarApi("https://api.anar360.com/wp/notifications?page=$page&limit=$limit");

        if (is_wp_error($response)) {
            $message = $response->get_error_message();
            wp_send_json_error(["message" => $message]);
        }

        $response_body = json_decode(wp_remote_retrieve_body($response), true);
//        $response_body = json_decode(Mock::$notifications, true);

        if ($response_body['success']) {

            $notifications = $response_body['result'];
            $output = '';

            if(is_array($notifications) ) {
                foreach ($notifications as $notification) {
                    $output .= sprintf('<div class="item %s">%s</div>',
                        $notification["application"] .' '. $notification["type"] .' '. $notification["reason"]
                        . (!$notification['read'] ? ' unread' : ' read'),
                        sprintf('<header><span class="label">%s</span><div class="meta"><date>%s</date>%s</div></header><p class="content">%s</p>',
                            $notification['title'] ,
                            wp_date('j F Y ساعت H:i', strtotime($notification["updatedAt"])),
                            ($notification['application'] == 'wordpress' ? $this->star_icon : ''),
                            $notification['description']
                        ),
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
        wp_send_json_error(['message' => 'temporary disable mark as read!']);
        $page = isset($_POST['page']) ? intval($_POST['page']) : 1;
        $limit = isset($_POST['limit']) ? intval($_POST['limit']) : 100;

        $response = ApiDataHandler::callAnarApi("https://api.anar360.com/wp/notifications?page=$page&limit=$limit");

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


    public function count_unread_notifications()
    {
        $response = ApiDataHandler::callAnarApi("https://api.anar360.com/wp/notifications?page=1&limit=1");

        if (is_wp_error($response)) {
            return;
        }

        $response_body = json_decode(wp_remote_retrieve_body($response), true);

        if ($response_body['success'] && isset($response_body['total'])) {
            update_option('anar_unread_notifications', $response_body['total']);
        }
    }

}
<?php

namespace Anar;

class AWCA_Notifications {
    public function __construct() {

        $this->update_new_notifications();

        add_action('admin_menu', [$this, 'awca_add_menu_badges']);

    }



    public function update_new_notifications() {


        update_option('awca_new_notifications', 2);

    }


    public function awca_add_menu_badges() {
        // Get the notification count
        $notification_count = get_option('awca_new_notifications', 0);

        // If the count is zero, don't display the badge
        if ($notification_count == 0) {
            return;
        }

        global $menu, $submenu;

        // Find the menu item and add the badge
        foreach ($menu as $index => $menu_item) {
            if ($menu_item[2] == 'awca-activation-menu') {
                $menu[$index][0] .= " <span class='awaiting-mod update-plugins count-$notification_count'><span class='plugin-count'>$notification_count</span></span>";
            }
        }

        // Find the submenu item and add the badge
        if (isset($submenu['awca-activation-menu'])) {
            foreach ($submenu['awca-activation-menu'] as $index => $submenu_item) {
                if ($submenu_item[2] == 'awca-inbox') {
                    $submenu['awca-activation-menu'][$index][0] .= " <span class='awaiting-mod update-plugins count-$notification_count'><span class='plugin-count'>$notification_count</span></span>";
                }
            }
        }
    }

}
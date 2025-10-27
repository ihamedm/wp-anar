<?php
namespace Anar;

class OrderReports{

    public static function count_anar_orders() {
        global $wpdb;

        try {
            // Count for HPOS enabled stores
            $orders_table = $wpdb->prefix . 'wc_orders';
            $meta_table = $wpdb->prefix . 'wc_orders_meta';

            // Validate database tables existence for HPOS
            if (awca_is_hpos_enable()) {
                // Check if HPOS tables exist
                $table_exists = $wpdb->get_var($wpdb->prepare(
                    "SHOW TABLES LIKE %s",
                    $orders_table
                ));

                if (!$table_exists) {
                    throw new \Exception('HPOS tables not found. Please check WooCommerce installation.');
                }

                $query = $wpdb->prepare("
                SELECT COUNT(DISTINCT o.id)
                FROM {$orders_table} o
                INNER JOIN {$meta_table} om ON o.id = om.order_id
                WHERE om.meta_key = %s
                AND om.meta_value = %s
            ", '_is_anar_order', 'anar');
            } else {
                // Traditional post meta query
                $query = $wpdb->prepare("
                SELECT COUNT(DISTINCT p.ID)
                FROM {$wpdb->posts} AS p
                INNER JOIN {$wpdb->postmeta} AS pm ON p.ID = pm.post_id
                WHERE p.post_type = %s
                AND pm.meta_key = %s
                AND pm.meta_value = %s
            ", 'shop_order', '_is_anar_order', 'anar');
            }

            $count = $wpdb->get_var($query);

            // Check for SQL errors
            if ($wpdb->last_error) {
                throw new \Exception('Database query error: ' . $wpdb->last_error);
            }

            // Ensure count is numeric and not null
            return is_null($count) ? 0 : (int)$count;

        } catch (\Exception $e) {
            // Log the error with detailed information
            error_log(sprintf(
                '[%s] Error in count_anar_orders: %s | Query: %s',
                current_time('mysql'),
                $e->getMessage(),
                isset($query) ? $query : 'Query not set'
            ));
            return 0; // Return 0 as a safe fallback
        }
    }

    public static function count_anar_orders_submited() {
        global $wpdb;

        try {
            // Count for HPOS enabled stores
            $orders_table = $wpdb->prefix . 'wc_orders';
            $meta_table = $wpdb->prefix . 'wc_orders_meta';

            if (awca_is_hpos_enable()) {
                $query = $wpdb->prepare("
                SELECT COUNT(DISTINCT o.id)
                FROM {$orders_table} o
                INNER JOIN {$meta_table} om ON o.id = om.order_id
                WHERE om.meta_key = %s
            ", '_anar_order_group_id');

                $count = $wpdb->get_var($query);
            } else {
                $query = $wpdb->prepare("
                SELECT COUNT(DISTINCT p.ID)
                FROM {$wpdb->posts} AS p
                INNER JOIN {$wpdb->postmeta} AS pm ON p.ID = pm.post_id
                WHERE p.post_type = %s
                AND pm.meta_key = %s
            ", 'shop_order', '_anar_order_group_id');

                $count = $wpdb->get_var($query);
            }

            // Check for SQL errors
            if ($wpdb->last_error) {
                throw new \Exception('Database query error: ' . $wpdb->last_error);
            }

            // Ensure count is numeric and not null
            return is_null($count) ? 0 : (int)$count;

        } catch (\Exception $e) {
            // Log the error
            error_log('Error in count_anar_group_orders: ' . $e->getMessage());
            return 0; // Return 0 as a safe fallback
        }
    }

}
<?php

namespace Anar\Admin;

use Anar\Core\Logger;

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

/**
 * Class Tools
 * Handles various admin tools and utilities
 */
class Tools
{
    private static $instance;
    private $logger;

    public static function get_instance()
    {
        if (!isset(self::$instance)) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function __construct()
    {
        $this->logger = new Logger();
        
        // Register AJAX handlers
        add_action('wp_ajax_anar_get_zero_profit_products', [$this, 'get_zero_profit_products_ajax']);

        add_action('pre_get_posts', [$this, 'filter_zero_profit_products']);
    }

    public function filter_zero_profit_products($query) {
        if (!is_admin() || !$query->is_main_query()) {
            return;
        }

        // Only apply to product post type
        if (!isset($_GET['post_type']) || $_GET['post_type'] !== 'product') {
            return;
        }

        // Apply zero profit filter
        if (isset($_GET['zero_profit']) && $_GET['zero_profit'] === '1') {
            // Clear any existing meta queries to avoid conflicts
            $query->set('meta_query', array());
            
            // Include all post statuses (not just published)
            $query->set('post_status', array('publish', 'draft', 'private', 'pending', 'future', 'trash'));
            $query->set('post_type', 'product');
            
            // Use a custom SQL approach since meta queries with LIKE on serialized data can be tricky
            add_filter('posts_join', [$this, 'filter_zero_profit_join_clause']);
            add_filter('posts_where', [$this, 'filter_zero_profit_where_clause']);
        }
    }

    public function filter_zero_profit_join_clause($join) {
        global $wpdb;
        
        // Remove this filter to avoid affecting other queries
        remove_filter('posts_join', [$this, 'filter_zero_profit_join_clause']);
        
        // Add our custom JOIN clause
        $join .= " INNER JOIN {$wpdb->postmeta} p ON {$wpdb->posts}.ID = p.post_id AND p.meta_key = '_anar_prices'";
        $join .= " INNER JOIN {$wpdb->postmeta} st ON {$wpdb->posts}.ID = st.post_id AND st.meta_key = '_stock_status' AND st.meta_value = 'instock'";
        
        return $join;
    }

    public function filter_zero_profit_where_clause($where) {
        global $wpdb;
        
        // Remove this filter to avoid affecting other queries
        remove_filter('posts_where', [$this, 'filter_zero_profit_where_clause']);
        
        // Add our custom WHERE clause
        $where .= " AND p.meta_value LIKE '%s:14:\"resellerProfit\";i:0;%'";
        
        return $where;
    }

    /**
     * AJAX handler for getting products with zero reseller profit
     */
    public function get_zero_profit_products_ajax()
    {
        // Verify nonce for security
        if (!wp_verify_nonce($_POST['nonce'], 'awca_ajax_nonce')) {
            wp_die('Security check failed');
        }

        // Check user capabilities
        if (!current_user_can('manage_options')) {
            wp_die('Insufficient permissions');
        }

        try {
            global $wpdb;
            
            $sql = "SELECT p.post_id,
                           s.meta_value AS anar_sku,
                           sync.meta_value AS last_sync_time
                    FROM {$wpdb->postmeta} p
                    LEFT JOIN {$wpdb->postmeta} s 
                           ON s.post_id = p.post_id 
                          AND s.meta_key = '_anar_sku'
                    LEFT JOIN {$wpdb->postmeta} sync 
                           ON sync.post_id = p.post_id 
                          AND sync.meta_key = '_anar_last_sync_time'
                    JOIN {$wpdb->postmeta} st 
                           ON st.post_id = p.post_id 
                          AND st.meta_key = '_stock_status'
                          AND st.meta_value = 'instock'
                    WHERE p.meta_key = '_anar_prices'
                      AND p.meta_value LIKE '%s:14:\"resellerProfit\";i:0;%'";
            
            $results = $wpdb->get_results($sql);
            
            // Generate HTML for products
            $products_html = '';
            $count = 0;
            
            foreach ($results as $result) {
                $product = get_post($result->post_id);
                $last_sync_time = $result->last_sync_time;
                $sync_time_formatted = '';
                $sync_time_ago = '';
                
                if ($last_sync_time) {
                    $sync_time_formatted = mysql2date('j F Y' . ' در ' . 'H:i', $last_sync_time);
                    $sync_time_ago = awca_time_ago($last_sync_time);
                }
                
                $product_title = $product ? $product->post_title : 'Post not found (ID: ' . $result->post_id . ')';
                $edit_link = $product ? get_edit_post_link($result->post_id) : '#';
                $view_link = $product ? get_permalink($result->post_id) : '#';
                $sku = $result->anar_sku ?: 'ندارد';
                
                // Generate sync time HTML
                if ($sync_time_formatted && $sync_time_ago) {
                    $sync_time_html = sprintf(
                        '<div class="anar-product-sync-time">
                            <strong>آخرین همگام‌سازی:</strong> %s<br>
                            <small style="color: #666;">%s</small>
                        </div>',
                        $sync_time_formatted,
                        $sync_time_ago
                    );
                } else {
                    $sync_time_html = '<div class="anar-product-sync-time" style="color: #999;">همگام‌سازی نشده</div>';
                }
                
                // Generate product item HTML
                $products_html .= sprintf(
                    '<div class="anar-product-item">
                        <div class="anar-product-title">%s</div>
                        <div class="anar-product-sku">SKU: %s</div>
                        %s
                        <div class="anar-product-actions">
                            <a href="%s" class="edit-link" target="_blank">ویرایش</a>
                            <a href="%s" class="view-link" target="_blank">مشاهده</a>
                        </div>
                    </div>',
                    esc_html($product_title),
                    esc_html($sku),
                    $sync_time_html,
                    esc_url($edit_link),
                    esc_url($view_link)
                );
                
                $count++;
            }
            
            if (empty($products_html)) {
                $products_html = '<p style="text-align: center; color: #666; padding: 20px;">محصولی با سود صفر یافت نشد.</p>';
            }
            
            wp_send_json_success([
                'html' => $products_html,
                'count' => $count
            ]);
            
        } catch (Exception $e) {
            $this->logger->log('Error fetching zero profit products: ' . $e->getMessage(), 'tools', 'error');
            wp_send_json_error('Error fetching products: ' . $e->getMessage());
        }
    }

    /**
     * Log a message
     */
    private function log($message, $level = 'info')
    {
        $this->logger->log($message, 'tools', $level);
    }
}

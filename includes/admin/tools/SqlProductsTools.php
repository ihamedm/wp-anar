<?php
namespace Anar\Admin\Tools;

use Anar\Core\Logger;

defined( 'ABSPATH' ) || exit;

class SqlProductsTools{
    private static $instance;

    private $logger;


    public static function get_instance()
    {
        if (!isset(self::$instance)) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function __construct(){
        $this->logger = new Logger();
        // Register AJAX handlers using the generic system
        add_action('wp_ajax_anar_get_zero_profit_products', [$this, 'get_zero_profit_products']);
        add_action('wp_ajax_anar_get_deprecated_products', [$this, 'get_deprecated_products']);
        add_action('wp_ajax_anar_change_deprecated_status', [$this, 'change_deprecated_status']);
        add_action('wp_ajax_anar_get_duplicate_products', [$this, 'get_duplicate_products']);
        add_action('wp_ajax_anar_change_duplicate_status', [$this, 'change_duplicate_status']);
        add_action('wp_ajax_anar_get_need_fix_products', [$this, 'get_need_fix_products']);
        add_action('wp_ajax_anar_manual_fix_products', [$this, 'manual_fix_products']);
    }


    /**
     * Get zero profit products
     */
    public function get_zero_profit_products()
    {
        $this->verify_request();

        try {
            global $wpdb;

            $sql = "SELECT DISTINCT post.ID,
                           post.post_title,
                           post.post_status,
                           s.meta_value AS anar_sku,
                           sync.meta_value AS last_sync_time
                    FROM {$wpdb->posts} post
                    JOIN {$wpdb->postmeta} p 
                           ON post.ID = p.post_id 
                          AND p.meta_key = '_anar_prices'
                          AND p.meta_value LIKE '%s:14:\"resellerProfit\";i:0;%'
                    LEFT JOIN {$wpdb->postmeta} s 
                           ON post.ID = s.post_id 
                          AND s.meta_key = '_anar_sku'
                    LEFT JOIN {$wpdb->postmeta} sync 
                           ON post.ID = sync.post_id 
                          AND sync.meta_key = '_anar_last_sync_time'
                    JOIN {$wpdb->postmeta} st 
                           ON post.ID = st.post_id 
                          AND st.meta_key = '_stock_status'
                          AND st.meta_value = 'instock'
                    WHERE post.post_type = 'product'";

            $results = $wpdb->get_results($sql);

            $products_html = $this->generate_product_list_html($results, 'zero_profit');

            wp_send_json_success([
                'html' => $products_html,
                'count' => count($results)
            ]);

        } catch (\Exception $e) {
            $this->logger->log('Error fetching zero profit products: ' . $e->getMessage(), 'tools', 'error');
            wp_send_json_error('Error fetching products: ' . $e->getMessage());
        }
    }

    /**
     * Get deprecated products
     */
    public function get_deprecated_products()
    {
        $this->verify_request();

        try {
            global $wpdb;

            $sql = "SELECT DISTINCT p.ID, p.post_title, p.post_status,
                           sku.meta_value AS anar_sku,
                           deprecated.meta_value AS deprecated_value,
                           sync.meta_value AS last_sync_time
                    FROM {$wpdb->posts} p
                    INNER JOIN {$wpdb->postmeta} deprecated 
                           ON p.ID = deprecated.post_id 
                          AND deprecated.meta_key = '_anar_deprecated'
                    LEFT JOIN {$wpdb->postmeta} sku 
                           ON p.ID = sku.post_id 
                          AND sku.meta_key = '_anar_sku'
                    LEFT JOIN {$wpdb->postmeta} sync 
                           ON p.ID = sync.post_id 
                          AND sync.meta_key = '_anar_last_sync_time'
                    WHERE p.post_type = 'product'
                    ORDER BY p.post_modified DESC";

            $results = $wpdb->get_results($sql);

            if (empty($results)) {
                wp_send_json_success([
                    'html' => '<p style="text-align: center; color: #666; padding: 20px;">محصول منسوخ‌شده‌ای یافت نشد.</p>',
                    'count' => 0
                ]);
                return;
            }

            $products_html = $this->generate_product_list_html($results, 'deprecated');

            wp_send_json_success([
                'html' => $products_html,
                'count' => count($results)
            ]);

        } catch (\Exception $e) {
            $this->logger->log('Error fetching deprecated products: ' . $e->getMessage(), 'tools', 'error');
            wp_send_json_error('Error fetching products: ' . $e->getMessage());
        }
    }

    /**
     * Get duplicate products
     */
    public function get_duplicate_products()
    {
        $this->verify_request();

        try {
            global $wpdb;

            $sql = "SELECT 
                      pm.meta_value AS anar_sku,
                      GROUP_CONCAT(p.ID ORDER BY p.ID SEPARATOR ',') AS post_ids,
                      GROUP_CONCAT(p.post_title ORDER BY p.ID SEPARATOR '|||') AS post_titles,
                      GROUP_CONCAT(p.post_status ORDER BY p.ID SEPARATOR ',') AS post_statuses,
                      COUNT(*) AS count
                    FROM {$wpdb->posts} p
                    JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
                    WHERE p.post_type = 'product'
                      AND p.post_status IN ('draft', 'publish')
                      AND pm.meta_key = '_anar_sku'
                    GROUP BY pm.meta_value
                    HAVING COUNT(*) > 1
                    ORDER BY count DESC";

            $results = $wpdb->get_results($sql);

            if (empty($results)) {
                wp_send_json_success([
                    'html' => '<p style="text-align: center; color: #666; padding: 20px;">محصول تکراری یافت نشد.</p>',
                    'count' => 0,
                    'total_products' => 0
                ]);
                return;
            }

            $products_html = $this->generate_duplicate_groups_html($results);
            $total_products = array_sum(array_column($results, 'count'));

            wp_send_json_success([
                'html' => $products_html,
                'count' => count($results),
                'total_products' => $total_products
            ]);

        } catch (\Exception $e) {
            $this->logger->log('Error fetching duplicate products: ' . $e->getMessage(), 'tools', 'error');
            wp_send_json_error('Error fetching products: ' . $e->getMessage());
        }
    }

    /**
     * Change deprecated products status
     */
    public function change_deprecated_status()
    {
        $this->verify_request();

        try {
            global $wpdb;

            $sql = "SELECT DISTINCT p.ID
                    FROM {$wpdb->posts} p
                    INNER JOIN {$wpdb->postmeta} deprecated 
                           ON p.ID = deprecated.post_id 
                          AND deprecated.meta_key = '_anar_deprecated'
                    WHERE p.post_type = 'product'
                      AND p.post_status != 'pending'";

            $product_ids = $wpdb->get_col($sql);

            if (empty($product_ids)) {
                wp_send_json_success([
                    'message' => 'هیچ محصولی برای تغییر وضعیت یافت نشد.',
                    'updated_count' => 0
                ]);
                return;
            }

            $updated_count = $this->update_products_status($product_ids, 'pending');

            $this->logger->log(sprintf('Changed %d deprecated products to pending status', $updated_count), 'tools', 'info');

            wp_send_json_success([
                'message' => sprintf('وضعیت %d محصول به "در انتظار بررسی" تغییر یافت.', $updated_count),
                'updated_count' => $updated_count
            ]);

        } catch (\Exception $e) {
            $this->logger->log('Error changing deprecated products status: ' . $e->getMessage(), 'tools', 'error');
            wp_send_json_error(['message' => 'خطا در تغییر وضعیت: ' . $e->getMessage()]);
        }
    }

    /**
     * Change duplicate products status
     */
    public function change_duplicate_status()
    {
        $this->verify_request();

        try {
            global $wpdb;

            $sql = "UPDATE {$wpdb->posts} p
                    JOIN {$wpdb->postmeta} sku ON p.ID = sku.post_id AND sku.meta_key = '_anar_sku'
                    JOIN (
                      SELECT MIN(p2.ID) AS keep_id, sku.meta_value AS anar_sku
                      FROM {$wpdb->posts} p2
                      JOIN {$wpdb->postmeta} sku ON p2.ID = sku.post_id AND sku.meta_key = '_anar_sku'
                      WHERE p2.post_type = 'product'
                        AND p2.post_status IN ('draft', 'publish')
                      GROUP BY sku.meta_value
                      HAVING COUNT(*) > 1
                    ) AS dups ON sku.meta_value = dups.anar_sku AND p.ID != dups.keep_id
                    SET p.post_status = 'pending'
                    WHERE p.post_type = 'product'
                      AND p.post_status IN ('draft', 'publish')";

            $updated_count = $wpdb->query($sql);

            if ($updated_count === false) {
                throw new \Exception('خطا در اجرای کوئری');
            }

            $this->logger->log(sprintf('Changed %d duplicate products to pending status', $updated_count), 'tools', 'info');

            wp_send_json_success([
                'message' => sprintf('وضعیت %d محصول تکراری به "در انتظار بررسی" تغییر یافت.', $updated_count),
                'updated_count' => $updated_count
            ]);

        } catch (\Exception $e) {
            $this->logger->log('Error changing duplicate products status: ' . $e->getMessage(), 'tools', 'error');
            wp_send_json_error(['message' => 'خطا در تغییر وضعیت: ' . $e->getMessage()]);
        }
    }

    /**
     * Generate HTML for product list
     */
    private function generate_product_list_html($results, $type = 'default')
    {
        if (empty($results)) {
            return '<p style="text-align: center; color: #666; padding: 20px;">محصولی یافت نشد.</p>';
        }

        $products_html = '';

        foreach ($results as $result) {
            $product_id = $result->ID ?? $result->post_id;
            $product_title = $result->post_title ?? 'Unknown';
            $anar_sku = $result->anar_sku ?? 'ندارد';
            $post_status = $result->post_status ?? 'unknown';
            $last_sync_time = $result->last_sync_time ?? null;

            $edit_link = get_edit_post_link($product_id);
            $view_link = get_permalink($product_id);

            // Generate sync time HTML
            $sync_time_html = $this->generate_sync_time_html($last_sync_time);

            // Status badge
            $status_info = $this->get_status_info($post_status);

            $products_html .= sprintf(
                '<div class="anar-product-item">
                    <div class="anar-product-title">%s</div>
                    <div class="anar-product-meta">
                        <span class="anar-product-sku">SKU: %s</span>
                        <span class="anar-product-status" style="background: %s; color: white; padding: 2px 8px; border-radius: 3px; font-size: 11px; margin-right: 10px;">%s</span>
                    </div>
                    %s
                    <div class="anar-product-actions">
                        <a href="%s" class="edit-link" target="_blank">ویرایش</a>
                        <a href="%s" class="view-link" target="_blank">مشاهده</a>
                    </div>
                </div>',
                esc_html($product_title),
                esc_html($anar_sku),
                esc_attr($status_info['color']),
                esc_html($status_info['label']),
                $sync_time_html,
                esc_url($edit_link),
                esc_url($view_link)
            );
        }

        return $products_html;
    }

    /**
     * Generate HTML for duplicate groups
     */
    private function generate_duplicate_groups_html($results)
    {
        $products_html = '';

        foreach ($results as $result) {
            $post_ids = explode(',', $result->post_ids);
            $post_titles = explode('|||', $result->post_titles);
            $post_statuses = explode(',', $result->post_statuses);
            $sku = $result->anar_sku;
            $count = $result->count;

            // Create group header
            $products_html .= sprintf(
                '<div class="anar-duplicate-group" style="margin-bottom: 20px; padding: 15px; background: #f8f9fa; border: 1px solid #ddd; border-radius: 4px;">
                    <div class="anar-duplicate-group-header" style="margin-bottom: 10px; padding-bottom: 10px; border-bottom: 2px solid #dc3232;">
                        <strong style="color: #dc3232;">SKU: %s</strong>
                        <span style="margin-right: 10px; color: #666;">(%d محصول تکراری)</span>
                    </div>
                    <div class="anar-duplicate-group-products">',
                esc_html($sku),
                $count
            );

            // List all products in this duplicate group
            foreach ($post_ids as $index => $post_id) {
                $product_title = isset($post_titles[$index]) ? $post_titles[$index] : 'Unknown';
                $post_status = isset($post_statuses[$index]) ? $post_statuses[$index] : 'unknown';
                $edit_link = get_edit_post_link($post_id);
                $view_link = get_permalink($post_id);

                // Mark the first (oldest) product
                $is_kept = ($index == 0);
                $badge_style = $is_kept ? 'background: #46b450; color: white;' : 'background: #ffb900; color: white;';
                $badge_text = $is_kept ? '✓ حفظ می‌شود' : 'تکراری';

                $status_info = $this->get_status_info($post_status);

                $products_html .= sprintf(
                    '<div class="anar-product-item" style="margin-bottom: 8px; padding: 8px; background: white; border-right: 3px solid %s;">
                        <div style="display: flex; justify-content: space-between; align-items: center;">
                            <div>
                                <strong>%s</strong>
                                <span style="margin-right: 10px; padding: 2px 6px; border-radius: 3px; font-size: 11px; %s">%s</span>
                                <span style="margin-right: 5px; color: #666; font-size: 12px;">(%s)</span>
                            </div>
                            <div>
                                <a href="%s" class="button button-small" target="_blank">ویرایش</a>
                                <a href="%s" class="button button-small" target="_blank">مشاهده</a>
                            </div>
                        </div>
                    </div>',
                    $is_kept ? '#46b450' : '#dc3232',
                    esc_html($product_title),
                    $badge_style,
                    $badge_text,
                    esc_html($status_info['label']),
                    esc_url($edit_link),
                    esc_url($view_link)
                );
            }

            $products_html .= '</div></div>';
        }

        return $products_html;
    }

    /**
     * Generate sync time HTML
     */
    private function generate_sync_time_html($last_sync_time)
    {
        if (!$last_sync_time) {
            return '<div class="anar-product-sync-time" style="color: #999;">همگام‌سازی نشده</div>';
        }

        $sync_time_formatted = mysql2date('j F Y' . ' در ' . 'H:i', $last_sync_time);
        $sync_time_ago = awca_time_ago($last_sync_time);

        return sprintf(
            '<div class="anar-product-sync-time">
                <strong>آخرین همگام‌سازی:</strong> %s<br>
                <small style="color: #666;">%s</small>
            </div>',
            $sync_time_formatted,
            $sync_time_ago
        );
    }

    /**
     * Get status information
     */
    private function get_status_info($post_status)
    {
        $status_labels = [
            'publish' => 'منتشر شده',
            'draft' => 'پیش‌نویس',
            'pending' => 'در انتظار بررسی',
            'private' => 'خصوصی',
            'trash' => 'سطل زباله'
        ];

        $status_colors = [
            'publish' => '#46b450',
            'draft' => '#666',
            'pending' => '#ffb900',
            'private' => '#0073aa',
            'trash' => '#dc3232'
        ];

        return [
            'label' => $status_labels[$post_status] ?? $post_status,
            'color' => $status_colors[$post_status] ?? '#666'
        ];
    }

    /**
     * Update products status
     */
    private function update_products_status($product_ids, $new_status)
    {
        $updated_count = 0;

        foreach ($product_ids as $product_id) {
            $result = wp_update_post([
                'ID' => $product_id,
                'post_status' => $new_status
            ], true);

            if (!is_wp_error($result)) {
                $updated_count++;
            }
        }

        return $updated_count;
    }

    /**
     * Get products that need fix
     */
    public function get_need_fix_products()
    {
        $this->verify_request();

        try {
            global $wpdb;

            // Get fixable error keys from Sync class
            $reflection = new \ReflectionClass(\Anar\Sync\Sync::class);
            $property = $reflection->getProperty('fix_error_keys');
            $property->setAccessible(true);
            $fix_error_keys = $property->getValue();

            if (empty($fix_error_keys)) {
                wp_send_json_success([
                    'html' => '<p style="text-align: center; color: #666; padding: 20px;">هیچ کلید خطای قابل تعمیری تعریف نشده است.</p>',
                    'count' => 0
                ]);
                return;
            }

            // Prepare placeholders for IN clause
            $placeholders = implode(',', array_fill(0, count($fix_error_keys), '%s'));

            $sql = $wpdb->prepare("
                SELECT DISTINCT p.ID, 
                       p.post_title, 
                       p.post_status,
                       sku.meta_value AS anar_sku,
                       need_fix.meta_value AS fix_key,
                       sync_error.meta_value AS sync_error,
                       last_try.meta_value AS last_try_sync_time,
                       sync.meta_value AS last_sync_time
                FROM {$wpdb->posts} p
                INNER JOIN {$wpdb->postmeta} need_fix 
                       ON p.ID = need_fix.post_id 
                      AND need_fix.meta_key = %s
                      AND need_fix.meta_value IN ($placeholders)
                LEFT JOIN {$wpdb->postmeta} sku 
                       ON p.ID = sku.post_id 
                      AND sku.meta_key = '_anar_sku'
                LEFT JOIN {$wpdb->postmeta} sync_error 
                       ON p.ID = sync_error.post_id 
                      AND sync_error.meta_key = '_anar_sync_error'
                LEFT JOIN {$wpdb->postmeta} last_try 
                       ON p.ID = last_try.post_id 
                      AND last_try.meta_key = '_anar_last_try_sync_time'
                LEFT JOIN {$wpdb->postmeta} sync 
                       ON p.ID = sync.post_id 
                      AND sync.meta_key = '_anar_last_sync_time'
                WHERE p.post_type = 'product'
                  AND p.post_status IN ('publish', 'draft')
                ORDER BY p.post_modified DESC
            ", \Anar\Sync\Sync::NEED_FIX_META_KEY, ...$fix_error_keys);

            $results = $wpdb->get_results($sql);

            if (empty($results)) {
                wp_send_json_success([
                    'html' => '<p style="text-align: center; color: #666; padding: 20px;">محصولی نیازمند تعمیر یافت نشد.</p>',
                    'count' => 0
                ]);
                return;
            }

            $products_html = $this->generate_need_fix_products_html($results);

            wp_send_json_success([
                'html' => $products_html,
                'count' => count($results)
            ]);

        } catch (\Exception $e) {
            $this->logger->log('Error fetching need fix products: ' . $e->getMessage(), 'tools', 'error');
            wp_send_json_error('Error fetching products: ' . $e->getMessage());
        }
    }

    /**
     * Manually trigger fix products cron
     */
    public function manual_fix_products()
    {
        $this->verify_request();

        try {

            do_action('anar_fix_products');

            // Return the actual results
            wp_send_json_success([
                'message' => 'ایونت کرون جاب anar_fix_products خارج از برنامه اجرا شد',
            ]);

        } catch (\Exception $e) {
            $this->logger->log('Error scheduling manual fix products cron: ' . $e->getMessage(), 'tools', 'error');
            wp_send_json_error(['message' => 'خطا در برنامه‌ریزی تعمیر محصولات: ' . $e->getMessage()]);
        }
    }


    /**
     * Generate HTML for need fix products list
     */
    private function generate_need_fix_products_html($results)
    {
        if (empty($results)) {
            return '<p style="text-align: center; color: #666; padding: 20px;">محصولی یافت نشد.</p>';
        }

        $products_html = '';

        foreach ($results as $result) {
            $product_id = $result->ID;
            $product_title = $result->post_title ?? 'Unknown';
            $anar_sku = $result->anar_sku ?? 'ندارد';
            $post_status = $result->post_status ?? 'unknown';
            $fix_key = $result->fix_key ?? 'unknown';
            $sync_error = $result->sync_error ?? null;
            $last_try_sync_time = $result->last_try_sync_time ?? null;
            $last_sync_time = $result->last_sync_time ?? null;

            $edit_link = get_edit_post_link($product_id);
            $view_link = get_permalink($product_id);

            // Get error message
            $error_message = '';
            if ($sync_error) {
                $error_message = anar_get_sync_error_message($sync_error);
            }

            // Generate sync time HTML
            $sync_time_html = '';
            if ($last_try_sync_time) {
                $try_time_formatted = mysql2date('j F Y' . ' در ' . 'H:i', $last_try_sync_time);
                $try_time_ago = awca_time_ago($last_try_sync_time);
                $sync_time_html = sprintf(
                    '<div class="anar-product-sync-time" style="margin-top: 5px;">
                        <strong>آخرین تلاش همگام‌سازی:</strong> %s<br>
                        <small style="color: #666;">%s</small>
                    </div>',
                    $try_time_formatted,
                    $try_time_ago
                );
            } else {
                $sync_time_html = '<div class="anar-product-sync-time" style="color: #999; margin-top: 5px;">هنوز همگام‌سازی نشده</div>';
            }

            // Status badge
            $status_info = $this->get_status_info($post_status);

            // Fix key badge
            $fix_key_label = '';
            switch ($fix_key) {
                case 'no_wc_variants':
                    $fix_key_label = 'بدون واریانت ووکامرس';
                    break;
                default:
                    $fix_key_label = $fix_key;
            }

            $products_html .= sprintf(
                '<div class="anar-product-item" style="margin-bottom: 15px; padding: 15px; background: #fff; border: 1px solid #ddd; border-radius: 4px; border-right: 4px solid #ffb900;">
                    <div class="anar-product-title" style="font-weight: bold; margin-bottom: 8px;">%s</div>
                    <div class="anar-product-meta" style="margin-bottom: 8px;">
                        <span class="anar-product-sku" style="margin-left: 10px;">SKU: %s</span>
                        <span class="anar-product-status" style="background: %s; color: white; padding: 2px 8px; border-radius: 3px; font-size: 11px; margin-right: 10px;">%s</span>
                        <span style="background: #ffb900; color: white; padding: 2px 8px; border-radius: 3px; font-size: 11px;">خطا: %s</span>
                    </div>
                    %s
                    %s
                    <div class="anar-product-actions" style="margin-top: 10px;">
                        <a href="%s" class="button button-small" target="_blank">ویرایش</a>
                        <a href="%s" class="button button-small" target="_blank">مشاهده</a>
                    </div>
                </div>',
                esc_html($product_title),
                esc_html($anar_sku),
                esc_attr($status_info['color']),
                esc_html($status_info['label']),
                esc_html($fix_key_label),
                $error_message ? sprintf('<div style="color: #dc3232; margin-top: 5px;"><strong>پیام خطا:</strong> %s</div>', esc_html($error_message)) : '',
                $sync_time_html,
                esc_url($edit_link),
                esc_url($view_link)
            );
        }

        return $products_html;
    }

    /**
     * Verify AJAX request
     */
    private function verify_request()
    {
        if (!wp_verify_nonce($_POST['nonce'], 'awca_ajax_nonce')) {
            wp_die('Security check failed');
        }

        if (!current_user_can('manage_options')) {
            wp_die('Insufficient permissions');
        }
    }
}
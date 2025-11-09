<?php

namespace Anar\Sync;

use Anar\ApiDataHandler;
use Anar\Wizard\ProductManager;

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

/**
 * Class RealTimeSync
 *
 * Real-time sync strategy that handles on-demand product synchronization via AJAX.
 * This strategy provides:
 * - AJAX endpoints for single product sync (admin and frontend)
 * - AJAX endpoint for cart products sync (frontend)
 * - Admin meta box sync button for manual product updates
 * - Product ID meta tag injection for frontend JavaScript
 *
 * Used for immediate product updates when users interact with products (viewing, cart updates).
 *
 * @package Anar\Sync
 * @since 0.6.0
 */
class RealTimeSync extends Sync{

    /**
     * Singleton instance
     *
     * @var RealTimeSync|null
     */
    private static $instance;

    /**
     * AJAX nonce action name for security
     *
     * @var string
     */
    const AJAX_NONCE_ACTION = 'awca_ajax_nonce';

    /**
     * Meta tag name for product ID (used by frontend JavaScript)
     *
     * @var string
     */
    const PRODUCT_ID_META_NAME = 'anar-product-id';

    /**
     * Get singleton instance
     *
     * @return RealTimeSync
     */
    public static function get_instance()
    {
        if (!isset(self::$instance)) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * RealTimeSync constructor
     *
     * Registers AJAX handlers and WordPress hooks for real-time sync functionality.
     */
    public function __construct(){
        parent::__construct();

        // Add product ID meta tag to product pages (for frontend JavaScript)
        add_action('wp_head', [$this, 'add_product_id_meta_tag']);

        // Register AJAX handlers for authenticated users
        add_action('wp_ajax_anar_update_product_async', [$this, 'handle_async_product_update_ajax']);
        add_action('wp_ajax_anar_update_cart_products_async', [$this, 'handle_async_cart_update_ajax']);

        // Register AJAX handlers for non-authenticated users (frontend)
        add_action('wp_ajax_nopriv_anar_update_product_async', [$this, 'handle_async_product_update_ajax']);
        add_action('wp_ajax_nopriv_anar_update_cart_products_async', [$this, 'handle_async_cart_update_ajax']);

        // Add sync button to product edit meta box
        add_action('anar_edit_product_meta_box', [$this, 'add_sync_button_product_meta_box']);
    }

    /**
     * Adds sync button to product edit meta box
     *
     * Renders a button that triggers AJAX sync for the current product.
     * Button includes loading spinner and reloads page on success.
     *
     * @return void
     */
    public function add_sync_button_product_meta_box()
    {
        global $post;

        ?>
        <a href="#" class="anar-ajax-action awca-primary-btn"
           id="anar-sync-product-form"
           style="margin: 10px 0; display: inline-block; text-decoration: none;"
           data-action="anar_update_product_async"
           data-product_id="<?php echo $post->ID; ?>"
           data-nonce="<?php echo wp_create_nonce(self::AJAX_NONCE_ACTION); ?>"
           data-reload="success"
           data-reload_timeout="2000">
            همگام سازی محصول با انار
            <svg class="spinner-loading" width="24px" height="24px" viewBox="0 0 66 66"
                 xmlns="http://www.w3.org/2000/svg">
                <circle class="path" fill="none" stroke-width="6" stroke-linecap="round" cx="33" cy="33"
                        r="30"></circle>
            </svg>
        </a>
        <?php

    }

    /**
     * Adds product ID meta tag to product pages
     *
     * Injects a meta tag with product ID for frontend JavaScript to identify
     * the current product page. Used by frontend sync scripts.
     *
     * @return void
     */
    public function add_product_id_meta_tag()
    {
        if (is_product()) {
            global $post;
            if ($post && isset($post->ID)) {
                echo '<meta name="' . esc_attr(self::PRODUCT_ID_META_NAME) . '" content="' . esc_attr($post->ID) . '" />' . "\n";
            }
        }
    }

    /**
     * Handles AJAX request for single product updates
     *
     * Validates request, syncs the specified product, and returns JSON response.
     * Used by admin sync button and frontend product page syncs.
     *
     * @return void Sends JSON response and exits
     */
    public function handle_async_product_update_ajax()
    {
        // Verify AJAX nonce for security
        check_ajax_referer(self::AJAX_NONCE_ACTION, 'nonce');

        // Validate product ID parameter
        if (!isset($_POST['product_id']) || !is_numeric($_POST['product_id'])) {
            wp_send_json_error(['message' => 'Invalid or missing product ID.'], 400, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }
        $wc_product_id = intval($_POST['product_id']);

        // Sync product with realtime-sync strategy
        $sync_result = $this->syncProduct($wc_product_id, [
            'sync_strategy' => 'realtime-sync',
            'full_sync' => true,
            'deprecate_on_faults' => false
        ]);

        // Return JSON response
        wp_send_json(
            [
                'success' => $sync_result['updated'],
                'data' => $sync_result
            ],
            $sync_result['status_code'],
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
        );
    }

    /**
     * Handles AJAX request for cart product updates
     *
     * Syncs all products in the current user's cart. Used by frontend
     * to ensure cart products are up-to-date before checkout.
     *
     * @return void Sends JSON response and exits
     */
    public function handle_async_cart_update_ajax()
    {
        $sync_result = [];

        // Verify AJAX nonce for security
        check_ajax_referer(self::AJAX_NONCE_ACTION, 'nonce');

        // Validate WooCommerce cart is available
        if (!function_exists('WC') || !WC()->cart) {
            wp_send_json_error(['message' => 'Anar: WooCommerce Cart not available.'], 500, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }

        // Get cart items
        $cart = WC()->cart->get_cart();
        if (empty($cart)) {
            wp_send_json_success(['message' => 'Anar: Cart is empty, no products to update.', 'needs_refresh' => false], 200, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }

        // Sync each product in cart
        foreach ($cart as $cart_item) {
            $sync_result['items'][] = $this->syncProduct($cart_item['product_id'], [
                'sync_strategy' => 'realtime-sync',
                'full_sync' => true,
                'deprecate_on_faults' => false
            ]);
        }

        $sync_result['needs_refresh'] = false;

        wp_send_json_success($sync_result, 200, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }


}
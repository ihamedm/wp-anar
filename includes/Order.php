<?php
namespace Anar;

/**
 * Order Class
 * 
 * Manages Anar order display and interaction in WooCommerce admin interface.
 * Provides admin meta box, pre-order creation modal, and AJAX endpoints for
 * order management, address handling, and Anar order data retrieval.
 * 
 * Key Responsibilities:
 * - Display Anar order meta box on WooCommerce order edit screen
 * - Show Anar order details (status, tracking, pricing)
 * - Provide pre-order modal for creating orders in Anar system
 * - Handle order type selection (retail vs wholesale/ship-to-stock)
 * - Manage stock address CRUD operations
 * - Display shipping options in admin modal
 * - Show package/shipment information
 * - Fetch live order status from Anar API
 * - Display payment links for unpaid orders
 * 
 * Modal Features:
 * - Customer address display
 * - Stock address management (add/edit/load)
 * - Shipping method selection for retail orders
 * - Shipping fee calculation for wholesale orders
 * - Order type toggle (retail/wholesale)
 * - Tehran-only validation for ship-to-stock
 * 
 * @package Anar
 * @since 1.0.0
 */
class Order {

    /**
     * Singleton instance
     * @var Order|null
     */
    protected static $instance = null;

    /**
     * Get singleton instance
     * 
     * @return Order The singleton instance
     * @since 1.0.0
     */
    public static function get_instance() {
        if ( is_null( self::$instance ) ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Constructor - Register hooks for order management UI
     * 
     * Registers WordPress/WooCommerce hooks for:
     * - Meta box display on order edit screen
     * - Pre-order modal injection
     * - AJAX endpoints for order operations
     * - Address management
     * - Shipping calculations
     * 
     * @since 1.0.0
     */
    public function __construct() {
        add_action('add_meta_boxes', [$this, 'anar_order_meta_box'] );
        add_action('admin_footer', [$this, 'preorder_modal']);
        add_action('wp_ajax_awca_fetch_order_details_ajax', [$this, 'fetch_order_details_ajax']);
        add_action('wp_ajax_awca_get_order_address_ajax', [$this, 'get_order_address_ajax']);
        add_action('wp_ajax_awca_save_stock_address_ajax', [$this, 'save_stock_address_ajax']);
        add_action('wp_ajax_awca_load_stock_address_ajax', [$this, 'load_stock_address_ajax']);
        add_action('wp_ajax_awca_get_shipping_fee_ajax', [$this, 'get_shipping_fee_ajax']);
        add_action('wp_ajax_awca_force_check_anar_products', [$this, 'force_check_anar_products_ajax']);
    }



    /**
     * Check if order contains Anar products
     * 
     * Validates if a WooCommerce order contains Anar products by checking
     * for '_is_anar_order' meta. Handles multiple input types (order object,
     * post object, or order ID) and is HPOS compatible.
     * 
     * Used throughout admin to determine if Anar UI elements should be shown.
     * 
     * @param \WC_Order|\WP_Post|int $order Order object, post object, or numeric order ID
     * @return int|false Order ID if it's an Anar order, false otherwise
     * @since 1.0.0
     * @access public
     */
    public function is_anar_order($order) {
        // If we got an ID, get the order object
        if (is_numeric($order)) {
            $order = wc_get_order($order);
        }
        // If we got a post object, convert it to order object
        elseif ($order instanceof \WP_Post) {
            $order = wc_get_order($order->ID);
        }

        // If we couldn't get a valid order object, return false
        if (!($order instanceof \WC_Order)) {
            return false;
        }

        // Now we have a WC_Order object, proceed with the check
        if (awca_is_hpos_enable()) {
            $_is_anar_order = $order->get_meta('_is_anar_order', true);
            $order_ID = $order->get_id();
        } else {
            $_is_anar_order = get_post_meta($order->get_id(), '_is_anar_order', true);
            $order_ID = $order->get_id();
        }

        return $_is_anar_order ? $order_ID : false;
    }

    /**
     * Get order meta data (HPOS compatible)
     * 
     * Retrieves order meta with HPOS compatibility check.
     * Uses $order->get_meta() for HPOS, get_post_meta() for legacy.
     * 
     * @param int $order_id Order ID
     * @param string $key Meta key to retrieve
     * @return mixed Meta value
     * @since 1.0.0
     * @access private
     */
    private function get_order_meta($order_id, $key) {
        if(awca_is_hpos_enable()){
            $order = wc_get_order($order_id);
            $order_meta = $order->get_meta($key, true);
        }else{
            $order_meta = get_post_meta($order_id, $key, true);
        }
        return $order_meta;
    }

    /**
     * Get Anar order data from order meta
     * 
     * Retrieves '_anar_order_data' meta containing Anar order IDs,
     * group IDs, and order numbers saved after order creation.
     * 
     * @param int $order_id Order ID
     * @return array|false Anar order data or false if not set
     * @since 1.0.0
     * @access public
     */
    public function get_order_anar_data($order_id) {
        return $this->get_order_meta($order_id, '_anar_order_data');
    }

    /**
     * Register Anar meta box on order edit screen
     * 
     * Adds Anar meta box to WooCommerce order edit screen (HPOS compatible).
     * Only displays for orders containing Anar products.
     * 
     * Meta box shows:
     * - Order creation button (if not created in Anar)
     * - Order details from Anar API (if created)
     * - Package information
     * - Ship-to-stock eligibility
     * - Raw API data (if ANAR_SUPPORT_MODE enabled)
     * 
     * Hooked to: add_meta_boxes
     * 
     * @return \WC_Order|false Order object if Anar order, false otherwise
     * @since 1.0.0
     * @access public
     */
    public function anar_order_meta_box() {

        if(isset($_GET['post'])){
            $order_id = $_GET['post'];
        }elseif(isset($_GET['id'])){
            $order_id = $_GET['id'];
        }else{
            $order_id = false;
        }

        // don't show meta box for new orders
        if(!$order_id) {
            return false;
        }

        $order = wc_get_order($order_id);
        
        // Only show meta box if is anar order OR if order exists (to allow force check)
        if(!$order){
            return false;
        }

        // check for anar order
        $screen = awca_is_hpos_enable() ? wc_get_page_screen_id( 'shop-order' ) : 'shop_order';

        add_meta_box(
            'anar_order_meta_box',
            'انار',
            [$this, 'anar_order_meta_box_html'],
            $screen,
            'side',
            'high'
        );
        return $order;
    }

    /**
     * Inject pre-order creation modal into admin footer
     * 
     * Displays modal dialog for creating Anar orders from admin interface.
     * Only injects on WooCommerce order edit screens for Anar orders.
     * 
     * Modal includes:
     * - Order type selector (retail/wholesale)
     * - Customer address display
     * - Stock address management form
     * - Shipping options display (for retail)
     * - Shipping fee display (for wholesale)
     * - Create order button
     * 
     * Hooked to: admin_footer
     * 
     * @return void Outputs HTML modal markup
     * @since 1.0.0
     * @access public
     */
    public function preorder_modal(){
        $screen = get_current_screen();
        if(isset($_GET['post'])){
            $order_id = $_GET['post'];
        }elseif(isset($_GET['id'])){
            $order_id = $_GET['id'];
        }else{
            $order_id = false;
        }

        // don't show meta box for new orders
        if(!$order_id) {
            return;
        }

        if (
            !$screen ||
            !in_array($screen->id, ['shop_order', 'woocommerce_page_wc-orders'], true) ||
            ! $this->is_anar_order($order_id)
        ) {
            return;
        }
        $order_id = $this->get_order_ID($GLOBALS['post'] ?? $_GET['post'] ?? $_GET['id'] ?? 0);
        $can_ship_to_stock = $this->canShipToResellerStock($order_id);
        ?>
        <div class="modal micromodal-slide" id="preorder-modal" aria-hidden="true">
            <div class="modal__overlay" tabindex="-1" data-micromodal-close1>
                <div class="modal__container" role="dialog" aria-modal="true" aria-labelledby="preorder-modal-title" style="max-width: 50vw; width: 600px;">
                    <header class="modal__header">
                        <strong class="modal__title" id="preorder-modal-title">
                            ثبت سفارش در پنل انار
                        </strong>
                        <button class="modal__close" aria-label="Close modal" data-micromodal-close></button>
                    </header>
                    <main class="modal__content">

                        
                        <?php if (!anar_order_can_ship_to_customer($order_id)): ?>
                        <div class="anar-order-type-section" style="">
                            <?php if (anar_is_ship_to_stock_enabled()): ?>
                                <?php if ($can_ship_to_stock): ?>
                                    <h4 style="margin: 0 0 15px 0; color: #333;">شیوه ارسال سفارش:</h4>
                                    <div class="order-type-options">
                                        <label>
                                            <input type="radio" name="order_type" value="retail" checked>
                                            <span>ارسال به مشتری</span>
                                        </label>
                                        <label>
                                            <input type="radio" name="order_type" value="wholesale">
                                            <span>ارسال به انبار شما</span>
                                        </label>
                                    </div>
                                <?php else: ?>
                                    <div class="anar-alert anar-alert-warning" style="margin-bottom: 15px;">
                                        <strong>توجه:</strong> اقلام این سفارش قابلیت ارسال به انبار را ندارند. این سفارش فقط امکان ارسال مستقیم برای مشتری را دارد.
                                    </div>
                                <?php endif; ?>
                            <?php else: ?>

                                <input type="hidden" name="order_type" value="retail">
                            <?php endif; ?>
                            
                            <!-- Customer Address (Default) -->
                            <div id="customer-address-section" class="address-section">
                                <strong style="margin: 0 0 10px 0; color: #666;">آدرس مشتری:</strong>
                                <div id="customer-address-display">
                                    <!-- Customer address will be populated by JavaScript -->
                                </div>
                            </div>
                            
                            <!-- Shipping Options Section (for retail orders) -->
                            <div id="shipping-options-section" class="address-section">
                                <p style="margin: 0 0 10px 0; color: #666;">روش ارسال:</p>
                                <div id="shipping-options-display" style="font-size: 12px;">
                                    <!-- Shipping options will be populated by JavaScript -->
                                </div>
                            </div>
                            
                            <!-- Stock Address Display -->
                            <div id="stock-address-display-section" class="address-section">
                                <div style="display: flex; gap: 8px; align-items: center;justify-content: space-between; margin-bottom: 8px">
                                    <strong>آدرس انبار شما:</strong>
                                    <span href="#" id="edit-stock-address-btn" class="awca-btn-link" style="display: flex; align-items: center; gap:4px; color: blue; cursor:pointer">
                                        <svg  xmlns="http://www.w3.org/2000/svg"  width="16"  height="16"  viewBox="0 0 24 24"  fill="none"  stroke="currentColor"  stroke-width="1.25"  stroke-linecap="round"  stroke-linejoin="round"  class="icon icon-tabler icons-tabler-outline icon-tabler-edit"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><path d="M7 7h-1a2 2 0 0 0 -2 2v9a2 2 0 0 0 2 2h9a2 2 0 0 0 2 -2v-1" /><path d="M20.385 6.585a2.1 2.1 0 0 0 -2.97 -2.97l-8.415 8.385v3h3l8.385 -8.415z" /><path d="M16 5l3 3" /></svg>
                                        <span>ویرایش آدرس</span>
                                    </span>
                                </div>
                                <div id="stock-address-display">
                                    <!-- Stock address will be populated by JavaScript -->
                                </div>

                            </div>

                            <!-- No Stock Address State -->
                            <div id="no-stock-address-section" class="address-section">
                                <div class="no-address-message">
                                    <p>آدرس انبار شما هنوز ثبت نشده است.</p>
                                    <a href="#" id="add-stock-address-btn" class="awca-btn awca-primary-btn" style="text-decoration: none; display: inline-block;">
                                        افزودن آدرس انبار
                                    </a>
                                </div>
                            </div>

                            <!-- Stock Address Form -->
                            <div id="stock-address-section" class="address-section">
                                <h5>آدرس انبار شما:</h5>
                                <div class="stock-address-form">
                                    <div>
                                        <label>نام:</label>
                                        <input type="text" id="stock_first_name" name="stock_first_name" required>
                                    </div>
                                    <div>
                                        <label>نام خانوادگی:</label>
                                        <input type="text" id="stock_last_name" name="stock_last_name" required>
                                    </div>
                                    <div style="display: none;">
                                        <label>استان:</label>
                                        <input type="text" id="stock_state" name="stock_state" value="تهران" readonly>
                                    </div>
                                    <div style="display: none;">
                                        <label>شهر:</label>
                                        <input type="text" id="stock_city" name="stock_city" value="تهران" readonly>
                                    </div>
                                    <div>
                                        <label>آدرس کامل در تهران:</label>
                                        <textarea id="stock_address" name="stock_address" required placeholder="آدرس کامل در تهران"></textarea>
                                    </div>
                                    <div>
                                        <label>کد پستی:</label>
                                        <input type="text" id="stock_postcode" name="stock_postcode" required pattern="[0-9]{10}">
                                    </div>
                                    <div>
                                        <label>شماره تماس:</label>
                                        <input type="tel" id="stock_phone" name="stock_phone" required>
                                    </div>
                                </div>
                                
                                <!-- Tehran Only Notice -->
                                <div class="anar-alert anar-alert-info" style="margin-top: 15px; padding: 12px; background-color: #e7f3ff; border: 1px solid #b3d9ff; border-radius: 4px;">
                                    <div style="display: flex; align-items: center; gap: 8px;">
                                        <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="color: #0073aa; flex-shrink: 0;">
                                            <path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"></path>
                                            <circle cx="12" cy="10" r="3"></circle>
                                        </svg>
                                        <div>
                                            <strong style="color: #0073aa;">ارسال به انبار فقط در تهران امکان‌پذیر است</strong>
                                            <div style="font-size: 12px; color: #666; margin-top: 4px;">
                                                آدرس انبار شما باید در محدوده شهر تهران باشد
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Shipping Fee (for wholesale orders) -->
                            <div class="stock-shipping-fee">
                                <!-- Shipping fee will be loaded via AJAX -->
                            </div>

                        </div>
                        <?php else: ?>
                            <div class="anar-alert anar-alert-warning" style="margin-bottom: 15px;">
                                <strong>توجه:</strong> قبل از ثبت سفارش در پنل انار، لطفاً موارد زیر را بررسی کنید:
                            </div>
                            <ul style="margin: 15px 0; padding-right: 20px; line-height: 1.8;">
                                <li>✅ اطلاعات آدرس و شماره تماس مشتری کامل و صحیح باشد</li>
                                <li>✅ کد پستی ۱۰ رقمی و معتبر وارد شده باشد</li>
                            </ul>
                            <div class="anar-alert anar-alert-info">
                                پس از ثبت سفارش، امکان تغییر اطلاعات محدود خواهد بود.
                            </div>
                        <?php endif; ?>


                    </main>
                    <footer class="modal__footer" style="display: flex; justify-content: end">

                        <button class="awca-btn awca-link-btn" style="color: #333 !important;" data-micromodal-close>انصراف</button>
                        <button id="awca-create-anar-order" class="awca-btn awca-info-btn " style="width: fit-content" data-order-id="<?php echo $order_id; ?>">
                            تایید و ثبت سفارش
                            <svg class="spinner-loading" width="24px" height="24px" viewBox="0 0 66 66" xmlns="http://www.w3.org/2000/svg">
                                <circle class="path" fill="none" stroke-width="6" stroke-linecap="round" cx="33" cy="33" r="30"></circle>
                            </svg>
                        </button>

                    </footer>
                </div>
            </div>
        </div>
        <?php
    }

    /**
     * Extract order ID from various input types
     * 
     * Normalizes different order representations (WC_Order object,
     * WP_Post object, or numeric ID) to a simple order ID integer.
     * 
     * @param \WC_Order|\WP_Post|int $order Order in various formats
     * @return int|false Order ID or false if invalid
     * @since 1.0.0
     * @access public
     */
    public function get_order_ID($order)
    {
        if($order instanceof \WC_Order){
            return $order->get_id();
        }elseif($order instanceof \WP_Post){
            return $order->ID;
        }elseif (is_numeric($order)) {
            return $order;
        }else{
            return false;
        }
    }

    /**
     * Render Anar meta box HTML content
     * 
     * Displays meta box content showing:
     * - HPOS status indicator
     * - Anar order details (if created) via AJAX
     * - Package/shipment information
     * - Order creation button (if not created)
     * - Ship-to-stock eligibility notice
     * - Raw API data (debug mode)
     * 
     * @param \WC_Order|\WP_Post|int $order Order object or ID
     * @return void Outputs HTML content
     * @since 1.0.0
     * @access public
     */
    public function anar_order_meta_box_html($order) {
        $order_id = $this->get_order_ID($order);


        printf( '<span class="awca-hpos-enabled-sign %s">HPOS</span>',
            awca_is_hpos_enable() ? "on" : "off"
        );

        // Check if this is an Anar order
        $is_anar_order = $this->is_anar_order($order);

        // If not an Anar order, show force check button
        if( !$is_anar_order ):?>
        <div id="awca-force-check-container">
            <p class="anar-alert anar-alert-info">این سفارش هنوز بررسی نشده است.</p>
            <p style="font-size: 12px; color: #666; margin: 10px 0;">
                اگر این سفارش شامل محصولات انار است اما لیبل سفارش انار ندارد، روی دکمه زیر کلیک کنید.
            </p>
            <button type="button" id="awca-force-check-btn" class="awca-primary-btn meta-box-btn" data-order-id="<?php echo $order_id;?>" style="width: 100%;">
                <span class="dashicons dashicons-update" style="margin-top: 3px;"></span>
                بررسی و شناسایی محصولات انار
            </button>
            <?php wp_nonce_field( 'awca_force_check_nonce', 'awca_force_check_nonce_field' ); ?>
        </div>
        <?php 
        return; // Exit early if not an Anar order
        endif;

        // If it is an Anar order, show the regular content
        if( $this->get_order_anar_data($order_id) ):?>
        <div class="anar-text" id="anar-order-details" data-order-id="<?php echo $order_id?>">
            <div class="awca-loading-message-spinner">
                در حال دریافت اطلاعات...
                <svg class="spinner-loading" width="24px" height="24px" viewBox="0 0 66 66" xmlns="http://www.w3.org/2000/svg">
                    <circle class="path" fill="none" stroke-width="6" stroke-linecap="round" cx="33" cy="33" r="30"></circle>
                </svg>
            </div>
        </div>


        <?php else:
            $this->get_packages_shipping_data($order_id);
        ?>
        <div id="awca-custom-meta-box-container">
            <p class="anar-alert anar-alert-warning">این سفارش هنوز در پنل انار شما ثبت نشده است.</p>
            <button id="awca-open-preorder-modal" class="awca-primary-btn meta-box-btn" data-order-id="<?php echo $order_id;?>">
                ثبت این سفارش در پنل انار
            </button>

            <?php wp_nonce_field( 'awca_nonce', 'awca_nonce_field' ); ?>
        </div>
        <?php
        endif;

        if( anar_is_ship_to_stock_enabled() && $this->canShipToResellerStock($order_id) )
            echo '<p class="anar-alert anar-alert-info">امکان ارسال به انبار دارد</p>';

        $raw_create_data = $this->get_order_meta($order_id, '_anar_raw_create_data');
        if($raw_create_data)
            printf('<pre class="awca-json-display" style="%s"><code>%s</code></pre>',
                ANAR_SUPPORT_MODE ? "" : "display:none;",
                json_encode($raw_create_data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)
            );

        do_action('anar_order_meta_box_end');
    }

    public function get_packages_shipping_data($order_id) {
        $wc_order = wc_get_order($order_id);
        if (!$wc_order) {
            return;
        }

        // Get the saved shipping data from order meta
        $order_shipping_data = $wc_order->get_meta('_anar_shipping_data');
        if (empty($order_shipping_data) || !is_array($order_shipping_data)) {
            return;
        }

        $all_packages_data = [];

        // prepare order items data
        foreach ($wc_order->get_items() as $item) {
            $product = $item->get_product();
            $product_id = $item->get_product_id();

            if(!$product)
                continue;

            // Store product information
            $products_info = [
                'name' => $product->get_name(),
                'image' => wp_get_attachment_image_url($product->get_image_id(), 'thumbnail'),
                'link' => get_permalink($product_id),
                'quantity' => $item->get_quantity()
            ];

            $product_shipments = ProductData::get_anar_product_shipments($product_id);

            if (empty($product_shipments)) {
                return;
            }

            // Ensure shipmentsReferenceId is a scalar value for use as array key
            $reference_id = $product_shipments['shipmentsReferenceId'] ?? '';
            
            // Skip if reference ID is not scalar (string/integer)
            if (!is_scalar($reference_id) || empty($reference_id)) {
                error_log('Invalid shipmentsReferenceId for product ' . $product_id . ': ' . print_r($reference_id, true));
                continue;
            }

            $user_selected_shipments = '';
            foreach ($order_shipping_data as $selected){
                if($selected['shipmentsReferenceId'] == $reference_id){
                    $user_selected_shipments = $selected;
                }
            }

            $all_packages_data[$reference_id][] = [
                'products_info' => $products_info,
                'product_shipments' => $product_shipments,
                'selected_shipments' => $user_selected_shipments
            ];

        }

        // show packages data
        $output = '';
        $package_number = 0;
        foreach ($all_packages_data as $i => $package_data) {
            $package_number++;

            // prepare package delivery data
            $deliveryInfo = [];
            try {
                // Check if package_data exists and has the expected structure
                if (!empty($package_data) &&
                    isset($package_data[0]['product_shipments']['delivery']) &&
                    is_array($package_data[0]['product_shipments']['delivery']) &&
                    isset($package_data[0]['selected_shipments']['deliveryId'])
                ) {
                    foreach ($package_data[0]['product_shipments']['delivery'] as $deliveryID => $deliveryData) {
                        if ($deliveryID == $package_data[0]['selected_shipments']['deliveryId']) {
                            $deliveryInfo = $deliveryData;
                            break;
                        }
                    }
                } else {
                    // Log the error or handle the case where data is not in expected format
                    error_log('Package data is not in the expected format');
                    echo '<div class="anar-alert anar-alert-warning">اطلاعات حمل و نقل به درستی ذخیره نشده است.</div>';
                }
            } catch (\Exception $e) {
                // Log the error
                error_log('Error processing delivery info: ' . $e->getMessage());
                echo '<div class="anar-alert anar-alert-warning">مشکلی در دریافت اطلاعات حمل و نقل برخی مرسوله ها وجود دارد!</div>';

            }

            $order_items_markup = '';
            // prepare package products
            foreach ($package_data as $package) {
                $order_items_markup .= sprintf('
                    <div class="product-info">
                        <div class="product-image"><a href="%s"><img src="%s"></a></div>
                        <div class="product-details">
                            <a href="%s" target="_blank">%s</a>
                            <p>تعداد: %s</p>
                        </div>
                    </div>
                    ',
                    $package['products_info']['link'] ,
                    $package['products_info']['image'],
                    $package['products_info']['link'],
                    $package['products_info']['name'],
                    $package['products_info']['quantity'],
                );
            }


            $output .= sprintf(
                '
                        <div class="anar-package-data close">
                        <header>
                            <strong>%s</strong>
                            <spn class="toggle-handler"><span class="close">+</span><span class="open">-</span></span>
                        </header>
                        
                        <ul>
                          
                            <li><b>آیتم ها(%s): </b><div class="order-items">%s</div></li>
                            
                            <li>
                                <ul style="background:#eee; border-radius:5px; padding:8px;">
                                    <li><b>شیوه ارسال: </b>%s</li>
                                    <li><b>هزینه: </b>%s</li>
                                    <li><b>مدت زمان تخمینی: </b>%s</li>
                                </ul>
                           
                            </li>
                        </ul>
                        </div>
                        ',
                sprintf('<b style="color:red">مرسوله %d</b>', $package_number),
                count($package_data),
                $order_items_markup,
                $deliveryInfo['name'] ?? '--',
                $deliveryInfo['price'] ?? '--',
                $deliveryInfo['estimatedTime'] ?? '--'
            );
        }

        echo $output;

    }

    /**
     * AJAX handler: Fetch order details from Anar API
     * 
     * Retrieves live order data from Anar API and formats it for display
     * in the admin meta box. Fetches data for all packages in an order group.
     * 
     * Process:
     * 1. Validate order_id from POST
     * 2. Get '_anar_order_data' from order meta
     * 3. Loop through each Anar order/package
     * 4. Make API call to fetch current status
     * 5. Format package data with items, pricing, status
     * 6. Add payment link if order is unpaid
     * 7. Return formatted HTML output
     * 
     * Displays:
     * - Order number and package number
     * - Product items with links
     * - Price breakdown (items, reseller share, delivery, total)
     * - Order status and delivery method
     * - Tracking number and creation date
     * - Payment link for unpaid orders
     * 
     * Hooked to: wp_ajax_awca_fetch_order_details_ajax
     * 
     * @return void Sends JSON response with formatted HTML
     * @since 1.0.0
     * @access public
     */
    public function fetch_order_details_ajax() {

        if ( !isset( $_POST['order_id'] ) ) {
            wp_send_json_error( array( 'message' => 'order_id required') );
        }
        $order_id = $_POST['order_id'];
        $order = wc_get_order($_POST['order_id']);
        $total_payable = 0;
        $total_reseller_share = 0;

        $anar_order_data = $order->get_meta('_anar_order_data', true);
        if(isset($anar_order_data) && count($anar_order_data) > 0){
            $output = '';
            foreach($anar_order_data as $order_index => $order){

                $url = "https://api.anar360.com/wp/orders/" . $order['_id'];
                $response = wp_remote_get($url, [
                    'headers' => [
                        'Authorization' => anar_get_saved_token()
                    ],
                    'timeout' => 300,
                ]);

                if (is_wp_error($response)) {
                    $message = $response->get_error_message();
                    wp_send_json_error(["message" => $message]);
                }

                $response_body = json_decode(wp_remote_retrieve_body($response), true);

                if ($response_body['success']) {
                    $package = $response_body['result'];
                    $package_number = $order_index + 1 ; // Start numbering from 1
                    $total_payable += $package['price']['payable'];
                    $total_reseller_share += $package['price']['resellerShare'];
                    $all_item_titles = [];
                    // Collect item titles
                    foreach ($package['items'] as $item) {

                        $paymentStatus = $package['status'];
                        $groupId = $package['groupId'];

                        $item_wc_id = ProductData::get_product_variation_by_anar_variation($item['product']);
                        // Check if the product ID is valid and get the product link
                        if ($item_wc_id && !is_wp_error($item_wc_id)) {
                            $product_link = get_permalink($item_wc_id); // Get the product link
                            $all_item_titles[] = sprintf('<a href="%s">%s</a>', esc_url($product_link), esc_html($item['title']));
                        } else {
                            // If the product ID is not valid, just use the title without a link
                            $all_item_titles[] = esc_html($item['title']);
                        }

                    }
                    $currency = get_woocommerce_currency_symbol(get_woocommerce_currency());

                    $output .= sprintf(
                        '
                        <div class="anar-package-data close">
                        <header>
                            <strong>%s</strong>
                            <span>%s</span>
                            <spn class="toggle-handler"><span class="close">+</span><span class="open">-</span></span>
                        </header>
                        
                        <ul>
                          
                            <li><b>شماره مرسوله: </b>%s</li>
                            <li><b>آیتم ها(%s): </b>%s</li>
                            
                            <li>
                                <ul style="background:#eee; border-radius:5px; padding:8px;">
                                    <li><b>جمع کالاها: </b>%s</li>
                                    <li><b>سهم شما: </b>%s</li>
                                    <li><b>هزینه ارسال: </b>%s</li>
                                    <li><b>قابل پرداخت: </b>%s</li>    
                                </ul>
                           
                            </li>
                            <li><b>وضعیت سفارش: </b>%s</li>
                            <li><b>شیوه ارسال: </b>%s</li>
                            <li><b>زمان تقریبی ارسال: </b>%s</li>
                            <li><b>کد رهگیری: </b>%s</li>
                            <li><b>تاریخ ثبت: </b>%s</li>
                            <li><b>شناسه یکتای سفارش: </b>%s</li>
                            <li><b>شناسه انار: </b>%s</li>
                        </ul>
                        </div>
                        ',
                        sprintf('<b style="color:red">مرسوله %d</b>', $package_number),
                        $package['orderNumber'],
                        $package['orderNumber'],
                        count($all_item_titles),
                        implode(', ', $all_item_titles),
                        anar_get_formatted_price($package['price']['items']),
                        anar_get_formatted_price($package['price']['resellerShare']),
                        anar_get_formatted_price($package['price']['delivery']),
                        anar_get_formatted_price($package['price']['payable']),
                        anar_translator($package['status']),
                        anar_translator($package['delivery']['deliveryType']),
                        $package['delivery']['estimatedTime'],
                        $package['trackingNumber'],
                        date_i18n('j F Y ساعت H:i', strtotime($package['createdAt'])),
                        $order_id,
                        $package['_id'],

                    );

                } else {
                    $message =  $response_body['message'] ?? 'مشکلی در دریافت اطلاعات سفارش از انار بوجود آمد.';
                    wp_send_json_error(["message" => $message]);
                }
            }// end loop orders


            // if order unpaid we need to create payment modal and button
            if(isset($groupId) && isset($paymentStatus) && $paymentStatus == 'unpaid'){

                $output .= sprintf('<p><strong>قابل پرداخت : %s</strong></p>', anar_get_formatted_price($total_payable - $total_reseller_share));

                $output .= sprintf(
                    '<div style="display: flex;flex-direction: column; ">
                                         <p class="anar-alert anar-alert-warning">این سفارش هنوز به انار پرداخت نشده است. انار موجودی این کالا را رزرو نمی کند</p>
                                         <a id="pay-order-btn" class="awca-primary-btn" target="_blank" href="https://anar360.com/payment/order/%s/pay?type=retail&callback=%s">پرداخت آنلاین</a>
                                    </div>',
                    $groupId,
                    rawurlencode(admin_url('post.php?post='.$order_id.'action=edit'))
                );
                $output .= '
                     <div class="modal micromodal-slide" id="order-payment-modal"  aria-hidden="true">
            <div class="modal__overlay" tabindex="-1" data-micromodal-close>
                <div class="modal__container" role="dialog" aria-modal="true" aria-labelledby="modal-1-title">
                    <header class="modal__header">
                        <strong class="modal__title">
                            تسویه حساب
                        </strong>
                        <button class="modal__close" aria-label="Close modal" data-micromodal-close></button>
                    </header>
                    <main class="modal__content" >
                        <p>
                        این سفارش در وضعیت <strong style="color:#e11c47">پرداخت نشده</strong> می باشد. با توجه به حجم سفارشات در انار لازم است هرچه سریعتر نسبت به پرداخت اقدام فرمایید تا موجودی اقلام سفارش برای شما رزرو بماند.
                        </p>
                        <p><strong>قابل پرداخت : '. anar_get_formatted_price($total_payable - $total_reseller_share) .'</strong></p>
                    </main>
                    <footer class="modal__footer">
                        <a id="pay-order-btn" class="awca-primary-btn" target="_blank" href="https://anar360.com/payment/order/'.$groupId.'/pay?type=retail&callback='.rawurlencode(admin_url('post.php?post='.$order_id.'action=edit')).'">پرداخت آنلاین</a>
                    </footer>
                </div>
            </div>
        </div>';

            }else{
                $output .= '<p class="anar-alert anar-alert-warning">وضعیت سفارش را از پنل انار دنبال کنید</p>';
                $output .= '<a class="awca-btn awca-success-btn" target="_blank" href="https://anar360.com/o/'.$groupId.'">وضعیت سفارش در پنل انار</a>';
            }


            $message = "اطلاعات سفارش از انار دریافت شد.";
            wp_send_json_success(['message' => $message, "output" => $output, 'paymentStatus'=> $package['paymentStatus'] ?? '']);
        }else{
            wp_send_json_error(["message" => "اطلاعات سفارش انار معتبر نیست!"]);
        }

    }

    /**
     * Check if order can be shipped to reseller's stock
     * 
     * Determines if order contains products eligible for ship-to-stock
     * by checking 'anar_can_ship_stock' meta.
     * 
     * @param int $order_id Order ID
     * @return bool True if eligible for ship-to-stock
     * @since 1.0.0
     * @access public
     */
    public function canShipToResellerStock($order_id){
        $anar_order_ship_stock = $this->get_order_meta($order_id, 'anar_can_ship_stock');
        return (bool)$anar_order_ship_stock;
    }

    /**
     * Check if order can be shipped to customer
     * 
     * Determines if order has shipping data for customer delivery
     * by checking '_anar_shipping_data' meta.
     * 
     * @param int $order_id Order ID
     * @return bool True if has customer shipping data
     * @since 1.0.0
     * @access public
     */
    public function canShipToCustomer($order_id){
        $anar_shipping_data = $this->get_order_meta($order_id, '_anar_shipping_data');
        return (bool)$anar_shipping_data;
    }

    /**
     * AJAX handler: Get customer address for modal display
     * 
     * Retrieves and formats customer's billing address from order
     * for display in pre-order creation modal.
     * 
     * Formatting:
     * - Replaces <br> tags with commas
     * - Strips remaining HTML tags
     * - Removes line breaks
     * - Trims whitespace
     * 
     * Hooked to: wp_ajax_awca_get_order_address_ajax
     * 
     * @return void Sends JSON response with formatted address
     * @since 1.0.0
     * @access public
     */
    public function get_order_address_ajax() {
        if (!isset($_POST['order_id']) || !$_POST['order_id']) {
            wp_send_json_error(array('message' => 'order_id required'));
        }

        $order_id = $_POST['order_id'];
        $order = wc_get_order($order_id);

        if (!$order) {
            wp_send_json_error(array('message' => 'Order not found'));
        }

        $address = $order->get_formatted_billing_address();

        if($address == '')
            wp_send_json_success(array('address' => 'آدرس مشتری ثبت نشده است. بدون آدرس امکان ثبت سفارش در انار نیست!'));

        // Format address for single line display (replace HTML tags with commas first, then strip remaining tags)
        $formatted_address = str_replace(['<br>', '<br/>', '<br />'], ' ، ', $address);
        $formatted_address = strip_tags($formatted_address);
        $formatted_address = str_replace(["\n", "\r"], ' ، ', $formatted_address);
        $formatted_address = preg_replace('/\s+/', ' ', $formatted_address);
        $formatted_address = trim($formatted_address);
        
        wp_send_json_success(array('address' => $formatted_address));
    }

    /**
     * AJAX handler to save stock address to wp_options
     */
    public function save_stock_address_ajax() {
        // Verify nonce
        if (!wp_verify_nonce($_POST['awca_nonce_field'], 'awca_nonce')) {
            wp_send_json_error(array('message' => 'Security check failed'));
        }

        // Get and sanitize stock address data
        $stock_address = array(
            'first_name' => sanitize_text_field($_POST['stock_first_name'] ?? ''),
            'last_name' => sanitize_text_field($_POST['stock_last_name'] ?? ''),
            'state' => sanitize_text_field($_POST['stock_state'] ?? ''),
            'city' => sanitize_text_field($_POST['stock_city'] ?? ''),
            'address' => sanitize_textarea_field($_POST['stock_address'] ?? ''),
            'postcode' => sanitize_text_field($_POST['stock_postcode'] ?? ''),
            'phone' => sanitize_text_field($_POST['stock_phone'] ?? ''),
        );

        // Validate required fields
        $required_fields = ['first_name', 'last_name', 'state', 'city', 'address', 'postcode', 'phone'];
        foreach ($required_fields as $field) {
            if (empty($stock_address[$field])) {
                wp_send_json_error(array('message' => "فیلد {$field} اجباری است"));
            }
        }

        // Validate postcode format
        if (!preg_match('/^\d{10}$/', $stock_address['postcode'])) {
            wp_send_json_error(array('message' => 'کد پستی باید ۱۰ رقم باشد'));
        }

        // Validate phone format
        if (!preg_match('/^09\d{9}$/', $stock_address['phone'])) {
            wp_send_json_error(array('message' => 'شماره موبایل باید با ۰۹ شروع شده و ۱۱ رقم باشد'));
        }

        // Save to wp_options
        $saved = update_option('_anar_user_stock_address', $stock_address);

        if ($saved) {
            wp_send_json_success(array('message' => 'آدرس انبار با موفقیت ذخیره شد'));
        } else {
            wp_send_json_error(array('message' => 'خطا در ذخیره آدرس انبار'));
        }
    }

    /**
     * AJAX handler to load stock address from wp_options
     */
    public function load_stock_address_ajax() {
        // Verify nonce
        if (!wp_verify_nonce($_POST['awca_nonce_field'], 'awca_nonce')) {
            wp_send_json_error(array('message' => 'Security check failed'));
        }

        $stock_address = get_option('_anar_user_stock_address', array());

        if (empty($stock_address)) {
            wp_send_json_error(array('message' => 'آدرس انبار ذخیره نشده است'));
        }

        // Format address for single line display
        $formatted_address = sprintf(
            '%s %s، %s، %s، %s، کد پستی: %s، تلفن: %s',
            $stock_address['first_name'],
            $stock_address['last_name'],
            $stock_address['state'],
            $stock_address['city'],
            $stock_address['address'],
            $stock_address['postcode'],
            $stock_address['phone']
        );

        wp_send_json_success(array(
            'address' => $formatted_address,
            'data' => $stock_address
        ));
    }

    /**
     * AJAX handler to get shipping fee information
     */
    public function get_shipping_fee_ajax() {
        // Verify nonce
        if (!wp_verify_nonce($_POST['awca_nonce_field'], 'awca_nonce')) {
            wp_send_json_error(['message' => 'Security check failed']);
            return;
        }

        // For now, return a static message
        // In the future, this can be replaced with an API call
        $shipping_fee = '۲۰۰ هزار تومان'; // This can be fetched from an API
        $date = 'شنبه ۱۲ مهر'; // This can be calculated dynamically
        
        // Create pretty formatted message
        $message = sprintf(
            '<div class="shipping-fee-display" style="">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px;">
                    <div style="text-align: right;">
                        <span style="color: #333; font-weight: 600;">هزینه ارسال به انبار شما</span>
                    </div>
                    <div style="text-align: left;">
                        <span style="color: #0073aa; font-weight: 600; ">%s</span>
                    </div>
                  
                </div>
                <div style="color: #666; font-size: 12px; line-height: 1.4; text-align: right;">
                    📌 امروز %s می توانید <strong>۴ مرسوله دیگر</strong> ثبت سفارش کنید تا با مرسوله فعلی ارسال شود و هزینه ی اضافی پرداخت نکنید.
                </div>
            </div>',
            $shipping_fee,
            $date,
        );

        wp_send_json_success(array(
            'message' => $message,
            'fee' => $shipping_fee,
            'date' => $date
        ));
    }

    /**
     * AJAX handler: Force check for Anar products in order
     * 
     * Manually triggers Anar product detection for orders created by plugins
     * that bypass WooCommerce standard order creation hooks. Useful for orders
     * created directly via database or custom importers.
     * 
     * Expected POST parameters:
     * - order_id: WooCommerce order ID
     * - awca_force_check_nonce_field: Security nonce
     * 
     * Response JSON:
     * - success: bool
     * - data.message: string - User-friendly message
     * 
     * @return void Outputs JSON response
     * @since 1.0.0
     * @access public
     */
    public function force_check_anar_products_ajax() {
        // Verify nonce
        if (!isset($_POST['awca_force_check_nonce_field']) || 
            !wp_verify_nonce($_POST['awca_force_check_nonce_field'], 'awca_force_check_nonce')) {
            wp_send_json_error(['message' => 'خطای امنیتی. لطفا صفحه را رفرش کنید.']);
            return;
        }

        // Verify order ID
        $order_id = isset($_POST['order_id']) ? intval($_POST['order_id']) : 0;
        
        if (!$order_id) {
            wp_send_json_error(['message' => 'شناسه سفارش معتبر نیست.']);
            return;
        }

        $order = wc_get_order($order_id);
        
        if (!$order) {
            wp_send_json_error(['message' => 'سفارش یافت نشد.']);
            return;
        }

        // Get Checkout instance and run detection
        $checkout = \Anar\Checkout::get_instance();
        $detection = $checkout->detect_anar_products_in_order($order);

        // Update order meta
        $checkout->update_anar_order_meta(
            $order_id,
            $detection['has_anar_product'],
            $detection['can_ship_to_stock']
        );

        // Return simple success/error message
        if ($detection['has_anar_product']) {
            wp_send_json_success(['message' => 'بررسی موفق! محصولات انار شناسایی شد.']);
        } else {
            wp_send_json_error(['message' => 'هیچ محصول انار در این سفارش یافت نشد.']);
        }
    }

}
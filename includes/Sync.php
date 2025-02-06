<?php

namespace Anar;

use Anar\Core\Activation;
use Anar\Core\Logger;

class Sync {
    private static $instance;
    private $baseApiUrl;
    private $limit;
    private $page;
    private $syncedCounter;
    private $startTime;

    private $jobID;

    private $triggerDuration = 5 * 60000; // we run sync every (n) ms with cronjob

    private $logger;

    public $triggerBy;

    public $fullSync = false;

    /**
     * time in milliseconds
     * @var
     */
    private $updatedSince;

    public static function get_instance() {
        if ( ! isset( self::$instance ) ) {
            self::$instance = new self();
        }
        return self::$instance;
    }


    public function __construct() {
        $this->baseApiUrl = 'https://api.anar360.com/wp/products';
        $this->limit = 10;
        $this->page = 1;
        $this->syncedCounter = 0;
        $this->triggerBy = 'Cronjob';

        $this->logger = new Logger();

        // query for products that updated since 10 min ago
        $this->updatedSince = 10 * 60000;

        // Hooking the method into AJAX actions
        add_action('wp_ajax_awca_sync_products_price_and_stocks', array($this, 'syncProductsPriceAndStocksAjax'));
        add_action('wp_ajax_nopriv_awca_sync_products_price_and_stocks', array($this, 'syncProductsPriceAndStocksAjax'));

        // show total products changed notice
        // this is accrued when user add/remove some product from anar panel
        add_action('admin_notices', [$this, 'show_total_products_changed_notice']);
    }

    public function syncProducts() {

        if(!Activation::validate_saved_activation_key_from_anar())
            return;

        if ($this->isSyncInProgress()) {
            $this->log('Sync already in progress, exiting to prevent overlap.');
            return;
        }


        // check for last sync time, it must be same with duration that we run sync
        // we do this check to prevent lost some product updates from server
        $lastSyncTime = get_option('awca_last_sync_time');
        if ($lastSyncTime) {
            $timeSinceLastSync = strtotime('now') - strtotime($lastSyncTime);
            $timeSinceLastSyncMs = $timeSinceLastSync * 1000; // Convert to milliseconds

            $this->updatedSince = 10 * 60000; // get updates of products since 10 min ago
            // If it's been longer than trigger duration, increase the time window
            // we look back for updates proportionally
            if ($timeSinceLastSyncMs > $this->triggerDuration) {
                $this->updatedSince = $timeSinceLastSyncMs;
                $this->log('we lost some updates from Anar, so increase updatedSince value to ' . $this->updatedSince / 60000 . ' minutes ago.');
            }

        }

        $this->jobID = uniqid('anar_sync_job_', true);

        $this->lockSync();

        // Capture the start time before beginning the sync process
        $this->startTime = microtime(true);

        try {
            $this->log('-------------------------------- Start Sync '.$this->jobID.' --------------------------------');
            $this->log('## Sync products that changes since '.($this->updatedSince / 60000).' minutes ago ...');
            $this->log('## Trigger by [ '.$this->triggerBy.' ] ' . ($this->fullSync ? ' [ full sync ]' : '') );
            $this->processPages();
            $this->updateLastSyncTime();
        } finally {
            $this->unlockSync();
            $this->logTotalTime(); // Log the total time at the end
        }
    }

    private function isSyncInProgress() {
        return get_transient('awca_sync_all_products_lock');
    }

    private function lockSync() {
        set_transient('awca_sync_all_products_lock', true, 3600); // Lock for 1 hour
    }

    private function unlockSync() {
        delete_transient('awca_sync_all_products_lock');
    }

    private function processPages() {
        while (true) {
            $api_args = array('page' => $this->page, 'limit' => $this->limit, 'since' => $this->updatedSince * -1);

            if($this->fullSync)
                unset($api_args['since']);

            $apiUrl = add_query_arg($api_args, $this->baseApiUrl);
            $awcaProducts = $this->callAnarApi($apiUrl);

            if (is_wp_error($awcaProducts)) {
                $this->log('Failed to fetch products from API: ' . $awcaProducts->get_error_message());
                return;
            }

            if (empty($awcaProducts->items)) {
                break;
            }

            if($this->page == 1){
                $this->log(sprintf('## Find %s products for update.' , $awcaProducts->total));
            }


            $this->processProducts($awcaProducts->items);

            $this->syncedCounter += count($awcaProducts->items);

            if (count($awcaProducts->items) < $this->limit) {
                break;
            }

            $this->page++;
        }
    }

    private function processProducts($products) {
        foreach ($products as $updateProduct) {
            if (count($updateProduct->variants) == 1) {
                $this->processSimpleProduct($updateProduct);
            } else {
                $this->processVariableProduct($updateProduct);
            }

            if(!$this->fullSync)
                $this->log("Sync Product [{$updateProduct->title}].");

        }
    }

    private function processSimpleProduct($updateProduct) {
        $variant = $updateProduct->variants[0];
        $sku = $updateProduct->id;
        $productId = ProductData::get_simple_product_by_anar_sku($sku);

        if ($productId) {
            $variantStock = ($updateProduct->resellStatus == 'editing-pending') ? 0 : $variant->stock;
            $product = wc_get_product($productId);
            $this->updateProductStockAndPrice($product, $variantStock, $variant->price);
            $this->updateProductMetadata($productId, $variant);
        }
    }

    private function processVariableProduct($updateProduct) {

        // update variants
        foreach ($updateProduct->variants as $variant) {
            $sku = $variant->_id;
            $productId = ProductData::get_product_variation_by_anar_sku($sku);
            if ($productId) {
                $variantStock = ($updateProduct->status == 'editing-pending') ? 0 : $variant->stock;
                $product = wc_get_product($productId);
                $this->updateProductStockAndPrice($product, $variantStock, $variant->price);
                $this->updateProductMetadata($product->get_parent_id(), $variant);
            }
        }
    }

    private function updateProductStockAndPrice($product, $stock, $price) {
        if ($product) {
            $convertedPrice = awca_convert_price_to_woocommerce_currency($price);
            $product->set_stock_quantity($stock);

            if(get_option('anar_conf_feat__optional_price_sync', 'no') == 'no'){
                $product->set_price($convertedPrice);
                $product->set_regular_price($convertedPrice);
            }

            $product->save();
        }
    }

    private function updateProductMetadata($productId, $variant) {
        update_post_meta($productId, '_anar_last_sync_time', current_time('mysql'));
        update_post_meta($productId, '_anar_prices',
            [
                'price' => $variant->price,
                'priceForResell' => $variant->priceForResell,
                'resellerProfit' => $variant->resellerProfit,
                'sellerDiscount' => $variant->sellerDiscount,
            ]
        );
    }

    private function callAnarApi($apiUrl) {
        return ApiDataHandler::tryGetAnarApiResponse($apiUrl);
    }

    private function log($message) {
        $this->logger->log($message, 'sync');
    }

    private function updateLastSyncTime() {
        update_option('awca_last_sync_time', current_time('mysql'));
    }

    // Calculate and log total time taken for sync process
    private function logTotalTime() {
        $endTime = microtime(true);
        $elapsedTime = $endTime - $this->startTime;
        $minutes = round($elapsedTime / 60);
        $this->log("## Sync done. Total sync time: {$minutes} minute(s).");
        $this->log('-------------------------------- End Sync '.$this->jobID.' --------------------------------');
    }

    // AJAX handler for syncing products price and stocks
    public function syncProductsPriceAndStocksAjax() {
        $this->triggerBy = 'Manual';
        if(isset($_POST['full_sync']) && $_POST['full_sync'] == 'on') {
            $this->fullSync = true;
        }
        $this->syncProducts();

        $response = array(
            'success' => true,
            'message' => sprintf('همگام سازی %s محصول با موفقیت انجام شد', $this->syncedCounter),
        );
        wp_send_json($response);
    }


    /**
     * periodically call products api, check total
     * and compare with saved total_products on last time get products
     *
     * @return void
     */
    public function get_api_total_products_number() {
        $apiUrl = add_query_arg(
            array('page' => 1, 'limit' => 1),
            $this->baseApiUrl
        );
        $awcaProducts = $this->callAnarApi($apiUrl);

        if (is_wp_error($awcaProducts)) {
            return;
        }

        update_option('awca_api_total_products', $awcaProducts->total);
    }

    public function show_total_products_changed_notice() {
        $awca_api_total_products = get_option('awca_api_total_products', 0);
        $awca_last_sync_total_products = get_option('awca_count_anar_products_on_db', 0);

        if($awca_api_total_products == 0 || $awca_last_sync_total_products == 0
            || awca_is_import_products_running()) {
            return;
        }

        // Calculate the absolute difference between the two values
        $difference = abs($awca_api_total_products - $awca_last_sync_total_products);

        // If difference is more than 10 products, show the notice
        if ($difference >= 10) {
            // Get the direction of change (increase or decrease)
            $change_direction = ($awca_api_total_products > $awca_last_sync_total_products)
                ? 'افزایش'
                : 'کاهش';

            // Create the notice message
            $notice = sprintf(
                '<div class="notice notice-warning is-dismissible">
                <p>
                    <strong>تغییر در تعداد محصولات انار:</strong> 
                    تعداد %d محصول %s یافته است. 
                    (تعداد فعلی: %d، تعداد قبلی: %d)
                    
                    <a href="%s">همگام‌سازی محصولات</a>
                </p>
            </div>',
                $difference,
                $change_direction,
                $awca_api_total_products,
                $awca_last_sync_total_products,
                admin_url('admin.php?page=wp-anar')
            );

            // Echo the notice
            echo wp_kses_post($notice);
        }
    }
}


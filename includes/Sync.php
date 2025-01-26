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

    private $logger;

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

        $this->logger = new Logger();

        // query for products that updated since 10 min ago
        $this->updatedSince = 10 * 60000;

        // Hooking the method into AJAX actions
        add_action('wp_ajax_awca_sync_products_price_and_stocks', array($this, 'syncProductsPriceAndStocksAjax'));
        add_action('wp_ajax_nopriv_awca_sync_products_price_and_stocks', array($this, 'syncProductsPriceAndStocksAjax'));
    }

    public function syncAllProducts() {

        if(!Activation::validate_saved_activation_key_from_anar())
            return;

        if ($this->isSyncInProgress()) {
            $this->log('Sync already in progress, exiting to prevent overlap.');
            return;
        }

        $this->lockSync();

        // Capture the start time before beginning the sync process
        $this->startTime = microtime(true);

        try {
            $this->log('## Sync products that changes since '.($this->updatedSince / 60000).' minutes ago ...');
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
            $apiUrl = add_query_arg(
                array('page' => $this->page, 'limit' => $this->limit, 'since' => $this->updatedSince * -1),
                $this->baseApiUrl
            );
            $awcaProducts = $this->callAnarApi($apiUrl);

            if (is_wp_error($awcaProducts)) {
                $this->log('Failed to fetch products from API: ' . $awcaProducts->get_error_message());
                return;
            }

            if (empty($awcaProducts->items)) {
                break;
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
        }
    }

    private function processVariableProduct($updateProduct) {
        foreach ($updateProduct->variants as $variant) {
            $sku = $variant->_id;
            $productId = ProductData::get_product_variation_by_anar_sku($sku);

            if ($productId) {
                $variantStock = ($updateProduct->status == 'editing-pending') ? 0 : $variant->stock;
                $product = wc_get_product($productId);
                $this->updateProductStockAndPrice($product, $variantStock, $variant->price);
            }
        }
    }

    private function updateProductStockAndPrice($product, $stock, $price) {
        if ($product) {
            $convertedPrice = awca_convert_price_to_woocommerce_currency($price);
            $product->set_stock_quantity($stock);
            $product->set_price($convertedPrice);
            $product->set_regular_price($convertedPrice);
            $product->save();
        }
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
    }

    // AJAX handler for syncing products price and stocks
    public function syncProductsPriceAndStocksAjax() {
        $this->syncAllProducts();

        $response = array(
            'success' => true,
            'message' => 'همگام سازی با موفقیت انجام شد'
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
}


<?php

use Anar\AnarDataRepository;
use Anar\Import\AttributeManager;
use Anar\Import\CategoryManager;
use Anar\Import\ProductCreatorV2;
use Anar\Wizard\ProductManager;

if (!function_exists('anar_create_single_product')) {
    /**
     * Helper to create a single WooCommerce product from Anar product data.
     *
     * @param object $anar_product_data Raw product payload returned by Anar API
     * @return array|\WP_Error Result data or WP_Error on failure
     */
    function anar_create_single_product($anar_product_data)
    {
        if (empty($anar_product_data)) {
            return new \WP_Error('anar_single_product_empty', __('داده محصول انار یافت نشد.', 'wp-anar'));
        }

        // Normalize payload into the same structure used by ProductCreatorV2 batches
        $prepared_product = ProductManager::product_serializer($anar_product_data);

        if (empty($prepared_product) || empty($prepared_product['sku'])) {
            return new \WP_Error('anar_single_product_invalid', __('ساختار داده محصول نامعتبر است.', 'wp-anar'));
        }

        try {
            $attribute_manager = new AttributeManager();
            $category_manager  = new CategoryManager();

            // Ensure mapping storage exists (mirrors BackgroundImporter behaviour)
            $repository = AnarDataRepository::get_instance();
            if (!$repository->exists('categoryMap')) {
                $repository->save('categoryMap', []);
            }
            if (!$repository->exists('attributeMap')) {
                $repository->save('attributeMap', []);
            }

            $attribute_manager->clear_cache();

            $category_map = [];
            foreach ($category_manager->get_mappings() as $mapping) {
                if (!is_array($mapping)) {
                    continue;
                }
                $anar_name = $mapping['anar_name'] ?? '';
                $woo_name  = $mapping['wc_term_name'] ?? '';
                if ($anar_name && $woo_name) {
                    $category_map[$anar_name] = $woo_name;
                }
            }

            $attribute_map = [];
            foreach ($attribute_manager->get_mappings() as $key => $mapping) {
                if (!is_array($mapping)) {
                    continue;
                }

                $wc_slug  = $mapping['wc_attribute_name'] ?? sanitize_title($mapping['anar_name'] ?? $key);
                $wc_label = $mapping['wc_attribute_label']
                    ?? $mapping['wc_attribute_name']
                    ?? ($mapping['anar_name'] ?? $key);

                $attribute_map[$key] = [
                    'name' => $wc_label,
                    'map'  => $wc_slug,
                ];
            }

            $creator = new ProductCreatorV2($attribute_map, $category_map);
            $result  = $creator->create($prepared_product);

            return [
                'success'     => true,
                'product_id'  => $result['product_id'] ?? 0,
                'created'     => (bool) ($result['created'] ?? false),
                'logs'        => $result['logs'] ?? [],
                'product_sku' => $prepared_product['sku'],
            ];
        } catch (\Throwable $exception) {
            return new \WP_Error(
                'anar_single_product_failed',
                sprintf(
                    __('ساخت محصول تکی با خطا مواجه شد: %s', 'wp-anar'),
                    $exception->getMessage()
                )
            );
        }
    }
}

if (!function_exists('anar_create_single_product_legacy')) {
    /**
     * Legacy helper to create a single WooCommerce product from Anar SKU using the old import system.
     * This function uses the legacy import system from Import.php instead of Import v2 methods.
     *
     * @param string $anar_sku The Anar product SKU
     * @return array|\WP_Error Result data or WP_Error on failure
     */
    function anar_create_single_product_legacy($anar_sku)
    {
        if (empty($anar_sku)) {
            return new \WP_Error('anar_single_product_legacy_empty_sku', __('شناسه SKU انار وارد نشده است.', 'wp-anar'));
        }

        // Fetch product data from API
        $anar_product_data = anar_fetch_product_data_by($anar_sku, 'sku');
        if (is_wp_error($anar_product_data)) {
            return $anar_product_data;
        }

        if (empty($anar_product_data)) {
            return new \WP_Error('anar_single_product_legacy_empty', __('داده محصول انار یافت نشد.', 'wp-anar'));
        }

        // Use ProductManager::product_serializer() to prepare product data (same as legacy)
        $prepared_product = ProductManager::product_serializer($anar_product_data);

        if (empty($prepared_product) || empty($prepared_product['sku'])) {
            return new \WP_Error('anar_single_product_legacy_invalid', __('ساختار داده محصول نامعتبر است.', 'wp-anar'));
        }

        try {
            global $wpdb;

            // Use legacy mapping system (get_option instead of AttributeManager/CategoryManager)
            $attributeMap = get_option('attributeMap', []);
            $categoryMap = get_option('categoryMap', []);

            // Prepare product creation data array (same structure as Import::create_products())
            $product_creation_data = array(
                'name' => $prepared_product['name'],
                'price' => $prepared_product['label_price'],
                'regular_price' => $prepared_product['regular_price'],
                'description' => $prepared_product['description'],
                'image' => $prepared_product['image'],
                'categories' => $prepared_product['categories'],
                'category' => $prepared_product['category'],
                'stock_quantity' => $prepared_product['stock_quantity'],
                'gallery_images' => $prepared_product['gallery_images'],
                'attributes' => $prepared_product['attributes'],
                'variants' => $prepared_product['variants'],
                'sku' => $prepared_product['sku'],
                'shipments' => $prepared_product['shipments'],
                'shipments_ref' => $prepared_product['shipments_ref'],
            );

            // Use legacy ProductManager::create_wc_product() method (NOT ProductCreatorV2)
            $product_creation_result = ProductManager::create_wc_product($product_creation_data, $attributeMap, $categoryMap, [
                'use_custom_table' => true
            ]);

            if ($product_creation_result === false) {
                return new \WP_Error(
                    'anar_single_product_legacy_failed',
                    __('خطا در ساخت محصول رخ داد.', 'wp-anar')
                );
            }

            $product_id = $product_creation_result['product_id'];
            $product_created = $product_creation_result['created'];

            // Use ImageDownloader to set product thumbnail (same as legacy Import::create_products())
            if ($product_created && !empty($prepared_product['image'])) {
                $image_downloader = \Anar\Core\ImageDownloader::get_instance();
                $image_downloader->set_product_thumbnail($product_id, $prepared_product['image']);
            }

            return [
                'success'     => true,
                'product_id'  => $product_id,
                'created'     => (bool) $product_created,
                'product_sku' => $prepared_product['sku'],
            ];
        } catch (\Throwable $exception) {
            return new \WP_Error(
                'anar_single_product_legacy_failed',
                sprintf(
                    __('ساخت محصول تکی با خطا مواجه شد: %s', 'wp-anar'),
                    $exception->getMessage()
                )
            );
        }
    }
}


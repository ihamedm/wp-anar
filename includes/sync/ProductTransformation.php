<?php

namespace Anar\Sync;

use Anar\Wizard\ProductManager;

/**
 * Class ProductTransformation
 *
 * Handles product type transformations (simple ↔ variable) during sync.
 * This class is responsible for converting WooCommerce products between
 * simple and variable types based on Anar product data.
 *
 * @package Anar\Sync
 * @since 0.6.0
 */
class ProductTransformation {

    /**
     * Checks if product needs transformation between simple and variable types
     *
     * Compares current WooCommerce product type with Anar product data to determine
     * if a type conversion is needed.
     *
     * @param \WC_Product $product Current WooCommerce product
     * @param object $anar_product Anar product data
     * @return string|false 'simple_to_variable', 'variable_to_simple', or false if no transformation needed
     */
    public static function checkNeeded($product, $anar_product) {
        if (!$product || !$anar_product) {
            return false;
        }

        $current_type = $product->get_type();
        $has_attributes = !empty($anar_product->attributes);
        $variant_count = isset($anar_product->variants) ? count($anar_product->variants) : 0;

        // Determine what the product should be based on Anar data
        $should_be_variable = $has_attributes && $variant_count > 1;
        $should_be_simple = !$has_attributes || $variant_count <= 1;

        // Check if transformation is needed
        if ($current_type === 'simple' && $should_be_variable) {
            return 'simple_to_variable';
        } elseif ($current_type === 'variable' && $should_be_simple) {
            return 'variable_to_simple';
        }

        return false; // No transformation needed
    }

    /**
     * Applies product transformation if needed
     *
     * Converts product type (simple to variable or vice versa) based on Anar product data.
     * Returns result array with success status and log messages.
     *
     * @param \WC_Product $product Current WooCommerce product
     * @param object $anar_product Anar product data
     * @param int $wc_product_id WooCommerce product ID
     * @param callable|null $logCallback Callback function to add logs (function($message))
     * @return array{
     *     success: bool,
     *     message?: string
     * } Transformation result
     */
    public static function apply($product, $anar_product, $wc_product_id, $logCallback = null) {
        $transformation_type = self::checkNeeded($product, $anar_product);
        
        if (!$transformation_type) {
            // No transformation needed
            return [
                'success' => true
            ];
        }

        if ($logCallback && is_callable($logCallback)) {
            call_user_func($logCallback, "Transformation needed: {$transformation_type} for product #{$wc_product_id}");
        }

        try {
            switch ($transformation_type) {
                case 'simple_to_variable':
                    $result = self::convertSimpleToVariable($wc_product_id, $anar_product, $logCallback);
                    break;
                    
                case 'variable_to_simple':
                    $result = self::convertVariableToSimple($wc_product_id, $anar_product, $logCallback);
                    break;
                    
                default:
                    return [
                        'success' => true
                    ];
            }

            if (!$result['success']) {
                return [
                    'success' => false,
                    'message' => $result['message'] ?? 'خطا در تبدیل نوع محصول'
                ];
            }

            return [
                'success' => true
            ];

        } catch (\Exception $e) {
            if ($logCallback && is_callable($logCallback)) {
                call_user_func($logCallback, "Transformation exception: " . $e->getMessage());
            }
            return [
                'success' => false,
                'message' => 'خطا در تبدیل نوع محصول: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Converts a simple product to variable product
     *
     * Used when Anar product changes from simple to variable (multiple variants with attributes).
     *
     * @param int $product_id WooCommerce product ID
     * @param object $anar_product Anar product data
     * @param callable|null $logCallback Callback function to add logs (function($message))
     * @return array{
     *     success: bool,
     *     message?: string
     * } Conversion result
     */
    public static function convertSimpleToVariable($product_id, $anar_product, $logCallback = null) {
        if (empty($anar_product->attributes)) {
            if ($logCallback && is_callable($logCallback)) {
                call_user_func($logCallback, "Cannot convert: no attributes in Anar product for product #{$product_id}");
            }
            return [
                'success' => false,
                'message' => 'محصول انار دارای ویژگی نیست'
            ];
        }

        global $wpdb;

        try {
            // Change post type to variable (WooCommerce handles variable products the same way)
            $updated = $wpdb->update(
                $wpdb->posts,
                ['post_type' => 'product'],
                ['ID' => $product_id],
                ['%s'],
                ['%d']
            );

            if ($updated === false) {
                throw new \Exception("Failed to update post type for product #{$product_id}");
            }

            // Get product object and reconfigure
            $product = new \WC_Product_Variable($product_id);

            // Remove simple product prices/stock (variations will have their own)
            $product->set_price('');
            $product->set_regular_price('');
            $product->set_sale_price('');
            $product->set_stock_quantity('');
            $product->set_manage_stock(false);
            $product->set_stock_status('instock');
            $product->save();

            // Remove any existing variations (shouldn't be any, but cleanup)
            $children = $product->get_children();
            foreach ($children as $variation_id) {
                wp_delete_post($variation_id, true);
            }

            // Serialize Anar product and create attributes/variations
            $serialized_product = ProductManager::product_serializer($anar_product);
            if (!$serialized_product) {
                throw new \Exception("Failed to serialize Anar product data");
            }

            $attributeMap = [];
            ProductManager::setup_attributes_and_variations($product, [
                'attributes' => $serialized_product['attributes'],
                'variants' => $serialized_product['variants']
            ], $attributeMap);

            if ($logCallback && is_callable($logCallback)) {
                call_user_func($logCallback, "Converted simple to variable for product #{$product_id}");
            }
            
            return [
                'success' => true
            ];

        } catch (\Exception $e) {
            if ($logCallback && is_callable($logCallback)) {
                call_user_func($logCallback, "Conversion error: " . $e->getMessage());
            }
            return [
                'success' => false,
                'message' => 'خطا در تبدیل محصول ساده به متغیر: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Converts a variable product to simple product
     *
     * Used when Anar product changes from variable to simple (only 1 variant).
     *
     * @param int $product_id WooCommerce product ID
     * @param object $anar_product Anar product data
     * @param callable|null $logCallback Callback function to add logs (function($message))
     * @return array{
     *     success: bool,
     *     message?: string
     * } Conversion result
     */
    public static function convertVariableToSimple($product_id, $anar_product, $logCallback = null) {
        if (empty($anar_product->variants) || count($anar_product->variants) !== 1) {
            $variant_count = isset($anar_product->variants) ? count($anar_product->variants) : 0;
            if ($logCallback && is_callable($logCallback)) {
                call_user_func($logCallback, "Cannot convert: Anar product has {$variant_count} variants for product #{$product_id}");
            }
            return [
                'success' => false,
                'message' => 'محصول انار باید فقط یک variant داشته باشد'
            ];
        }

        global $wpdb;

        try {
            // Get the single variant data
            $variant = $anar_product->variants[0];
            
            // Get variable product to delete variations
            $product = wc_get_product($product_id);
            if (!$product || $product->get_type() !== 'variable') {
                throw new \Exception("Product #{$product_id} is not a variable product");
            }

            // Delete all existing variations
            $children = $product->get_children();
            foreach ($children as $variation_id) {
                wp_delete_post($variation_id, true);
            }

            // Change post type to simple product
            $updated = $wpdb->update(
                $wpdb->posts,
                ['post_type' => 'product'],
                ['ID' => $product_id],
                ['%s'],
                ['%d']
            );

            if ($updated === false) {
                throw new \Exception("Failed to update post type for product #{$product_id}");
            }

            // Clear all attributes
            $product->set_attributes([]);
            $product->save();

            // Re-instantiate as simple product
            $simple_product = new \WC_Product_Simple($product_id);

            // Set simple product data from the single variant
            $simple_product->set_price(awca_convert_price_to_woocommerce_currency($variant->price));
            $simple_product->set_regular_price(awca_convert_price_to_woocommerce_currency($variant->price));
            $simple_product->set_stock_quantity($variant->stock ?? 0);
            $simple_product->set_manage_stock(true);
            $simple_product->set_stock_status(($variant->stock ?? 0) > 0 ? 'instock' : 'outofstock');

            // Update Anar meta data
            if (isset($variant->_id)) {
                update_post_meta($product_id, '_anar_variant_id', $variant->_id);
            }
            
            $simple_product->save();

            if ($logCallback && is_callable($logCallback)) {
                call_user_func($logCallback, "Converted variable to simple for product #{$product_id}");
            }
            
            return [
                'success' => true
            ];

        } catch (\Exception $e) {
            if ($logCallback && is_callable($logCallback)) {
                call_user_func($logCallback, "Conversion error: " . $e->getMessage());
            }
            return [
                'success' => false,
                'message' => 'خطا در تبدیل محصول متغیر به ساده: ' . $e->getMessage()
            ];
        }
    }
}


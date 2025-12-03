<?php

namespace Anar\Import;

use Anar\Core\ImageDownloader;
use Anar\ProductData;
use WC_Product_Simple;
use WC_Product_Variable;
use WC_Product_Variation;

class ProductCreatorV2
{
    private array $attribute_map;
    private array $category_map;
    private bool $use_custom_table;
    private CategoryManager $category_manager;
    private AttributeManager $attribute_manager;
    private array $logs = [];

    public function __construct(array $attribute_map, array $category_map, bool $use_custom_table = false)
    {
        $this->attribute_map = $attribute_map;
        $this->category_map = $category_map;
        $this->use_custom_table = $use_custom_table;
        $this->category_manager = new CategoryManager();
        $this->attribute_manager = new AttributeManager();
    }

    /**
     * Convenience wrapper for staged table records (`wp_anar_products`).
     * @throws \Throwable
     */
    public function create_from_staged_product(array $record): array
    {
        $payload = maybe_unserialize($record['product_data'] ?? '');

        if (!is_array($payload)) {
            throw new \RuntimeException(__('داده محصول نامعتبر است.', 'wp-anar'));
        }

        return $this->create($payload);
    }

    public function create(array $product_data, array $options = []): array
    {
        $this->logs = []; // Reset logs for each product
        set_time_limit(300);

        $use_custom_table = $options['use_custom_table'] ?? $this->use_custom_table;
        $sku = $product_data['sku'] ?? null;

        if (!$sku) {
            throw new \InvalidArgumentException(__('شناسه SKU یافت نشد.', 'wp-anar'));
        }

        $this->add_log("=== PRODUCT CREATION START (V2) ===");
        $this->add_log("Processing SKU: {$sku}");

        $existing_product_id = ProductData::check_for_existence_product($sku);
        $product_created = true;

        try {
            if ($existing_product_id) {
                $product = wc_get_product($existing_product_id);
                if (!$product) {
                    throw new \RuntimeException(__('محصول موجود یافت نشد.', 'wp-anar'));
                }
                $this->update_existing_product($product, $product_data);
                $product_created = false;

                if ($use_custom_table) {
                    $this->update_custom_table_status($sku, 'updated', $existing_product_id);
                }
            } else {
                $product = $this->initialize_new_product($product_data);
                $this->setup_new_product($product, $product_data);

                if ($use_custom_table) {
                    $this->update_custom_table_status($sku, 'created', $product->get_id());
                }
            }

            $product_id = $product->get_id();
            $this->update_common_meta_data($product, $product_data);

            // Download and set product thumbnail
            // TODO: Remove this debug condition before production - skip image download in debug mode for faster testing
            $is_debug_mode = (defined('ANAR_DEBUG') && ANAR_DEBUG) || get_option('anar_log_level', 'info') === 'debug';
            if (!empty($product_data['image']) && !$is_debug_mode) {
                $this->download_product_image($product_id, $product_data['image']);
            } elseif (!empty($product_data['image']) && $is_debug_mode) {
                $this->add_log("Skipping image download (debug mode): {$product_data['image']}", 'debug');
            }

            $this->add_log("=== PRODUCT CREATION COMPLETE (V2) - ID: {$product_id}, Created: " . ($product_created ? 'YES' : 'NO') . " ===");

            // Log final result with all collected logs
            $log_message = "Product " . ($product_created ? 'CREATED' : 'UPDATED') . " - SKU: {$sku}, ID: {$product_id}\n" . 
                          implode("\n", $this->logs);
            anar_log($log_message, $product_created ? 'info' : 'debug');

            return [
                'product_id' => $product_id,
                'created' => $product_created,
                'logs' => $this->logs,
            ];
        } catch (\Throwable $e) {
            $error_message = "Product creation FAILED - SKU: {$sku}\n" . 
                           "Error: {$e->getMessage()}\n" . 
                           implode("\n", $this->logs);
            anar_log($error_message, 'error');
            throw $e;
        }
    }

    /**
     * Get collected logs for this product creation.
     */
    public function get_logs(): array
    {
        return $this->logs;
    }

    /**
     * Add a log message to the collection.
     */
    private function add_log(string $message, string $level = 'debug'): void
    {
        $this->logs[] = "[{$level}] {$message}";
    }

    private function initialize_new_product(array $product_data)
    {
        return !empty($product_data['attributes'])
            ? new WC_Product_Variable()
            : new WC_Product_Simple();
    }

    private function setup_new_product($product, array $product_data): void
    {
        $this->add_log("Setting up new product: {$product_data['name']}");

        $product->set_name($product_data['name']);
        $product->set_status('draft');
        $product->set_description($product_data['description'] ?? '');

        // Map categories using CategoryManager
        $category_ids = $this->category_manager->map_anar_product_cats_with_saved_cats(
            $product_data['categories'] ?? [],
            $this->category_map
        );
        $product->set_category_ids($category_ids);
        $this->add_log("Categories mapped: " . count($category_ids) . " categories assigned");

        $product_id = $product->save();

        update_post_meta($product_id, '_anar_sku', $product_data['sku']);
        update_post_meta($product_id, '_anar_sku_backup', $product_data['sku']);
        update_post_meta($product_id, '_anar_variant_id', $product_data['variants'][0]->_id ?? '');

        if (!empty($product_data['attributes'])) {
            $this->setup_attributes_and_variations($product, $product_data);
        } else {
            $this->setup_simple_product_data($product, $product_data);
        }
    }

    private function update_existing_product($product, array $product_data): void
    {
        $this->add_log("Updating existing product: {$product->get_name()} (ID: {$product->get_id()})");

        if ($product->get_type() === 'simple') {
            $this->setup_simple_product_data($product, $product_data);
        } elseif ($product->get_type() === 'variable') {
            $this->update_variable_product($product, $product_data);
        }

        if (!empty($product_data['image'])) {
            update_post_meta($product->get_id(), '_product_image_url', $product_data['image']);
        }

        update_post_meta($product->get_id(), '_anar_variant_id', $product_data['variants'][0]->_id ?? '');
        delete_post_meta($product->get_id(), '_anar_pending');

        $product->save();
        $this->add_log("Product updated successfully");
    }

    private function setup_simple_product_data($product, array $product_data): void
    {
        // Use label_price for price, regular_price for regular_price
        $price = $product_data['label_price'] ?? $product_data['price'] ?? 0;
        $regular_price = $product_data['regular_price'] ?? $price;

        $product->set_price(awca_convert_price_to_woocommerce_currency($price));
        $product->set_regular_price(awca_convert_price_to_woocommerce_currency($regular_price));
        $product->set_stock_quantity($product_data['stock_quantity'] ?? 0);
        $product->set_manage_stock(true);
        $product->save();
        $this->add_log("Simple product data set - Price: {$price}, Stock: " . ($product_data['stock_quantity'] ?? 0));
    }


    // @todo instead of removing all variation and setup again check only for changes
    private function update_variable_product($product, array $product_data): void
    {
        $this->add_log("Updating variable product - removing old variations");
        $variations = $product->get_children();
        foreach ($variations as $variation_id) {
            wp_delete_post($variation_id, true);
        }

        $product->set_attributes([]);
        $product->save();

        $this->setup_attributes_and_variations($product, $product_data);
    }

    private function setup_attributes_and_variations($product, array $product_data): void
    {
        $this->add_log("Setting up attributes and variations");
        
        // Use AttributeManager to create attributes
        // Returns both attribute objects and key->taxonomy lookup map
        $result = $this->attribute_manager->create_attributes(
            $product_data['attributes'] ?? []
        );
        
        $attrsObject = $result['attributes'] ?? [];
        $key_lookup = $result['key_lookup'] ?? [];
        
        $product->set_props(['attributes' => $attrsObject]);
        $product->save();
        
        // Use the key_lookup directly (Anar key -> WooCommerce taxonomy name)
        // This is the most reliable since variants reference attributes by key
        $this->create_product_variations($product->get_id(), $product_data, $key_lookup);
    }

    private function create_product_variations(int $parent_id, array $product_data, array $taxonomy_lookup = []): void
    {
        $variations = $product_data['variants'] ?? [];
        $attributes = $product_data['attributes'] ?? [];

        $this->add_log("Creating " . count($variations) . " variations");

        // TODO: perf review - variation creation can spike memory usage for large attribute sets.
        foreach ($variations as $index => $variation_data) {
            $variation = new WC_Product_Variation();
            $variation->set_parent_id($parent_id);
            $variation->set_price(awca_convert_price_to_woocommerce_currency($variation_data->price ?? 0));
            $variation->set_regular_price(awca_convert_price_to_woocommerce_currency($variation_data->regular_price ?? $variation_data->price ?? 0));
            $variation->set_stock_quantity($variation_data->stock ?? 0);
            $variation->set_manage_stock(true);

            $variation_attributes = [];

            if (!empty($attributes)) {
                foreach ($variation_data->attributes ?? [] as $attr_key => $attr_value) {
                    // Validate that we have a proper value (not empty, not the attribute name itself)
                    if (empty($attr_value)) {
                        $this->add_log("Skipping empty attribute value for key '{$attr_key}'", 'warning');
                        continue;
                    }
                    
                    // Get the Anar attribute name from the product attributes (for logging)
                    $attr_name = $attributes[$attr_key]['name'] ?? $attr_key;
                    
                    // Safety check: ensure value is not accidentally the attribute name
                    if ($attr_value === $attr_name || $attr_value === $attr_key) {
                        $this->add_log("Warning: Attribute value '{$attr_value}' matches attribute name/key for '{$attr_name}'. This might indicate a data structure issue.", 'warning');
                    }
                    
                    // Use the key-based taxonomy lookup (most reliable - variants use keys)
                    // The lookup is built from create_attributes() which uses Anar key as slug
                    if (!empty($taxonomy_lookup) && isset($taxonomy_lookup[$attr_key])) {
                        $taxonomy_name = $taxonomy_lookup[$attr_key];
                    } else {
                        // Fallback: try to find by slug (Anar key sanitized)
                        $expected_slug = sanitize_title($attr_key);
                        $attribute_id = wc_attribute_taxonomy_id_by_name($expected_slug);
                        
                        if ($attribute_id > 0) {
                            $taxonomy_name = 'pa_' . $expected_slug;
                            // Update lookup for future variations
                            $taxonomy_lookup[$attr_key] = $taxonomy_name;
                        } else {
                            // Last resort: try to get/create attribute by key
                            try {
                                $taxonomy_name = $this->attribute_manager->get_or_create_attribute_by_key(
                                    $attr_key,
                                    $attr_name,
                                    []
                                );
                                // Update lookup for future variations
                                $taxonomy_lookup[$attr_key] = $taxonomy_name;
                            } catch (\Throwable $e) {
                                $this->add_log("Failed to get/create attribute for key '{$attr_key}': " . $e->getMessage(), 'error');
                                continue;
                            }
                        }
                    }
                    
                    // Final validation: ensure we have a valid taxonomy name
                    if (empty($taxonomy_name) || strpos($taxonomy_name, 'pa_') !== 0) {
                        $this->add_log("Invalid taxonomy name '{$taxonomy_name}' for attribute key '{$attr_key}', skipping", 'error');
                        continue;
                    }

                    // Set the variation attribute using the WooCommerce taxonomy format (pa_*)
                    // For mapped attributes (especially with Persian characters), we need to find the actual term
                    // because terms might already exist with different slugs than sanitize_title() would create
                    // For unmapped attributes, sanitize_title() works because terms are created with that slug
                    $term_slug = $this->get_term_slug_for_variation($taxonomy_name, $attr_value);
                    
                    if ($term_slug) {
                        $taxonomy_sanitized_name = sanitize_title($taxonomy_name);
                        $variation_attributes[$taxonomy_sanitized_name] = $term_slug;
                    } else {
                        $this->add_log("Cannot set variation attribute: term not found for '{$attr_value}' in {$taxonomy_name}", 'error');
                    }
                }
            }

            // Set attributes on variation (like old stable system)
            if (!empty($variation_attributes)) {
                $variation->set_attributes($variation_attributes);
            }
            
            $variation->save();

            $variation_id = $variation->get_id();
            if ($variation_id) {
                update_post_meta($variation_id, '_anar_sku', $variation_data->_id ?? '');
                update_post_meta($variation_id, '_anar_variant_id', $variation_data->_id ?? '');
            }

            unset($variation_attributes, $variation);
        }

        $this->add_log("Variations created successfully");
    }

    /**
     * Get term slug for variation attribute value.
     * For mapped attributes (especially with Persian characters), finds the actual term in the mapped taxonomy.
     * For unmapped attributes, uses sanitize_title() (terms are created with that slug).
     * 
     * @param string $taxonomy_name The taxonomy name (e.g., 'pa_color' or 'pa_سایظ')
     * @param string $value The term value/name
     * @return string|null The term slug, or null if not found
     */
    private function get_term_slug_for_variation(string $taxonomy_name, string $value): ?string
    {
        if (empty($value) || empty($taxonomy_name)) {
            return null;
        }

        // Method 1: Try to find term by exact name (most reliable for mapped attributes with Persian characters)
        $term = get_term_by('name', $value, $taxonomy_name);
        if ($term && !is_wp_error($term)) {
            return $term->slug;
        }

        // Method 2: Try to find by slug (sanitized value)
        // This works for unmapped attributes where terms are created with sanitize_title()
        $expected_slug = sanitize_title($value);
        $term = get_term_by('slug', $expected_slug, $taxonomy_name);
        if ($term && !is_wp_error($term)) {
            return $term->slug;
        }

        // Method 3: Search all terms and find by case-insensitive name match
        // This handles cases where terms might have been created with different casing
        $all_terms = get_terms([
            'taxonomy' => $taxonomy_name,
            'hide_empty' => false,
        ]);

        if (!is_wp_error($all_terms) && !empty($all_terms)) {
            foreach ($all_terms as $term) {
                if (strcasecmp($term->name, $value) === 0) {
                    return $term->slug;
                }
            }
        }

        // Term not found - log warning and return sanitized slug as fallback
        // This might work if the term exists but wasn't found by the above methods
        $this->add_log("WARNING: Term '{$value}' not found in {$taxonomy_name}, using sanitized slug '{$expected_slug}' as fallback", 'warning');
        return $expected_slug;
    }

    /**
     * Build attribute map in simplified format (for backward compatibility).
     * Note: AttributeManager now handles mapping internally, this is mainly for variation attribute lookup.
     */
    private function build_attribute_map_for_creation(): array
    {
        return $this->attribute_manager->build_attribute_map_for_creation();
    }

    private function update_common_meta_data($product, array $product_data): void
    {
        $product_id = $product->get_id();

        update_post_meta($product_id, '_anar_products', 'true');
        update_post_meta($product_id, '_anar_last_sync_time', current_time('mysql'));
        
        if (isset($product_data['shipments'])) {
            update_post_meta($product_id, '_anar_shipments', $product_data['shipments']);
        }
        
        if (isset($product_data['shipments_ref'])) {
            update_post_meta($product_id, '_anar_shipments_ref', $product_data['shipments_ref']);
        }

        $author_id = awca_get_first_admin_user_id();
        if ($author_id) {
            wp_update_post([
                'ID'          => $product_id,
                'post_author' => $author_id,
            ]);
        }

        if (!empty($product_data['image'])) {
            update_post_meta($product_id, '_product_image_url', $product_data['image']);
        }

        if (!empty($product_data['gallery_images']) && is_array($product_data['gallery_images'])) {
            update_post_meta($product_id, '_anar_gallery_images', $product_data['gallery_images']);
        }

        $product->save();
    }

    /**
     * Download and set product thumbnail image.
     */
    private function download_product_image(int $product_id, string $image_url): void
    {
        if (empty($image_url)) {
            return;
        }

        try {
            $image_downloader = ImageDownloader::get_instance();
            $result = $image_downloader->set_product_thumbnail($product_id, $image_url);

            if (is_wp_error($result)) {
                $this->add_log("Image download failed: " . $result->get_error_message(), 'error');
            } else {
                $this->add_log("Product thumbnail downloaded and set - Attachment ID: {$result}");
            }
        } catch (\Throwable $e) {
            $this->add_log("Image download exception: " . $e->getMessage(), 'error');
            // Don't fail product creation if image download fails
        }
    }

    private function update_custom_table_status(string $sku, string $status, ?int $wc_product_id = null): void
    {
        global $wpdb;

        $table_name = $wpdb->prefix . ANAR_DB_PRODUCTS_NAME;
        $data = [
            'status' => $status,
            'updated_at' => current_time('mysql'),
        ];

        if ($wc_product_id) {
            $data['wc_product_id'] = $wc_product_id;
        }

        $wpdb->update(
            $table_name,
            $data,
            ['anar_sku' => $sku]
        );
    }
}

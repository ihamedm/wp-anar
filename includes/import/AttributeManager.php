<?php

namespace Anar\Import;

use Anar\AnarDataRepository;
use Anar\AnarDataItemRepository;

class AttributeManager
{
    private AnarDataRepository $repository;
    private AnarDataItemRepository $item_repository;
    
    /**
     * Cache for created attributes during batch processing to avoid redundant lookups.
     * Structure: ['attribute_name' => 'pa_taxonomy_slug']
     */
    private array $created_attributes_cache = [];

    public function __construct()
    {
        $this->repository = AnarDataRepository::get_instance();
        $this->item_repository = AnarDataItemRepository::get_instance();
    }

    /**
     * Get all Anar attributes from API response.
     */
    public function get_attributes(): array
    {
        // Try new table first (paginated storage)
        $items_from_new_table = $this->item_repository->get_items_by_key('attribute');
        
        if (!empty($items_from_new_table)) {
            // Convert from new format to old format for backward compatibility
            return array_map(function($row) {
                return $row['data'];
            }, $items_from_new_table);
        }
        
        // Fallback to old repository for backward compatibility
        $record = $this->repository->get('attributes');
        if (!$record) {
            return [];
        }

        return is_array($record['response']) ? $record['response'] : [];
    }

    /**
     * Get an attribute by Anar _id directly from the new table.
     * 
     * @param string $anar_id The Anar API _id field
     * @return array|null Attribute data or null if not found
     */
    public function get_attribute_by_id(string $anar_id): ?array
    {
        $item = $this->item_repository->get_item('attribute', $anar_id);
        
        if ($item && isset($item['data'])) {
            return is_array($item['data']) ? $item['data'] : (array) $item['data'];
        }
        
        return null;
    }

    /**
     * Get attributes grouped by name (merges duplicates with same name but different keys).
     * 
     * @return array Structure: ['attribute_name' => ['name' => ..., 'keys' => [...], 'values' => [...]]]
     */
    public function get_attributes_grouped_by_name(): array
    {
        $attributes = $this->get_attributes();
        $grouped = [];

        foreach ($attributes as $attribute) {
            $attribute_array = (array) $attribute;
            $name = $attribute_array['name'] ?? '';
            $key = $attribute_array['key'] ?? '';
            $values = $attribute_array['values'] ?? [];

            if (empty($name)) {
                continue;
            }

            if (!isset($grouped[$name])) {
                $grouped[$name] = [
                    'name' => $name,
                    'keys' => [],
                    'values' => [],
                ];
            }

            // Add key if not already present
            if (!empty($key) && !in_array($key, $grouped[$name]['keys'], true)) {
                $grouped[$name]['keys'][] = $key;
            }

            // Merge values (remove duplicates)
            $grouped[$name]['values'] = array_unique(array_merge($grouped[$name]['values'], $values));
        }

        return $grouped;
    }

    /**
     * Get attribute lookup by key or name (for backward compatibility).
     */
    public function get_attribute_lookup(): array
    {
        $lookup = [];
        $attributes = $this->get_attributes();

        foreach ($attributes as $attribute) {
            $attribute_array = (array) $attribute;
            $key = $attribute_array['key'] ?? '';
            if ($key) {
                $lookup[$key] = $attribute_array;
            }
            $name = $attribute_array['name'] ?? '';
            if ($name) {
                $lookup[$name] = $attribute_array;
            }
        }

        return $lookup;
    }

    /**
     * Get mappings indexed by Anar attribute key (primary identifier).
     * Structure: ['anar_key' => ['wc_attribute_id' => ..., 'wc_attribute_name' => ..., 'wc_attribute_label' => ..., 'anar_name' => ...]]
     * Automatically validates and removes mappings to non-existent WooCommerce attributes.
     * 
     * @param bool $validate Whether to validate mappings (default: true)
     * @return array Key-based mappings
     */
    public function get_mappings(bool $validate = true): array
    {
        $record = $this->repository->get('attributeMap');
        if (!$record) {
            return [];
        }

        $raw_mappings = is_array($record['response']) ? $record['response'] : [];
        
        // Return key-based mappings directly (keys are stable identifiers from API)
        $key_based = [];
        $has_invalid_mappings = false;
        
        foreach ($raw_mappings as $key => $mapping) {
            if (!is_array($mapping)) {
                continue;
            }

            $anar_key = $mapping['anar_key'] ?? $key;
            $wc_attribute_id = $mapping['wc_attribute_id'] ?? 0;
            $wc_attribute_name = $mapping['wc_attribute_name'] ?? '';
            $wc_attribute_slug = $mapping['wc_attribute_slug'] ?? '';
            if (empty($wc_attribute_slug) && !empty($wc_attribute_name)) {
                // Backward compatibility: get the actual registered taxonomy name and extract slug
                $registered_name = $this->get_registered_taxonomy_name($wc_attribute_name);
                $wc_attribute_slug = str_replace('pa_', '', $registered_name);
            } elseif (empty($wc_attribute_slug)) {
                // Fallback: use normalize method if no attribute name available
                $wc_attribute_slug = $this->normalize_wc_attribute_slug($mapping['anar_name'] ?? $anar_key);
            }
            
            // Validate mapping if requested - only check by ID
            if ($validate && $wc_attribute_id > 0) {
                // Check if the WooCommerce attribute still exists by ID
                if (!$this->validate_wc_attribute_exists($wc_attribute_id)) {
                    // Attribute doesn't exist - skip this mapping and mark for cleanup
                    anar_log("Removing invalid mapping for Anar key '{$anar_key}': WooCommerce attribute ID {$wc_attribute_id} no longer exists", 'warning');
                    $has_invalid_mappings = true;
                    continue; // Skip this mapping
                }
            }
            
            $key_based[$anar_key] = [
                'wc_attribute_id' => $wc_attribute_id,
                'wc_attribute_name' => $wc_attribute_name,
                'wc_attribute_label' => $mapping['wc_attribute_label'] ?? '',
                'wc_attribute_slug' => $wc_attribute_slug,
                'anar_name' => $mapping['anar_name'] ?? $key,
            ];
        }
        
        // If invalid mappings were found, save the cleaned mappings back to storage
        if ($has_invalid_mappings && $validate) {
            $this->repository->save('attributeMap', $key_based);
            anar_log("Cleaned up invalid attribute mappings - removed mappings to non-existent WooCommerce attributes", 'info');
        }

        return $key_based;
    }
    
    /**
     * Validate that a WooCommerce attribute exists by ID.
     * 
     * @param int $attribute_id The WooCommerce attribute ID
     * @return bool True if attribute exists, false otherwise
     */
    private function validate_wc_attribute_exists(int $attribute_id): bool
    {
        if ($attribute_id <= 0) {
            return false;
        }
        
        // Check if attribute exists by ID
        $taxonomy = $this->find_woocommerce_attribute($attribute_id);
        return $taxonomy !== null;
    }
    
    /**
     * Get mappings indexed by attribute name (for grouping/merging by name).
     * Structure: ['attribute_name' => ['wc_attribute_id' => ..., 'wc_attribute_name' => ..., 'anar_keys' => [...]]]
     * 
     * @return array Name-based mappings (grouped)
     */
    public function get_mappings_by_name(): array
    {
        $key_based = $this->get_mappings();
        $name_based = [];
        
        foreach ($key_based as $key => $mapping) {
            $anar_name = $mapping['anar_name'] ?? $key;
            
            if (!isset($name_based[$anar_name])) {
                $name_based[$anar_name] = [
                    'wc_attribute_id' => $mapping['wc_attribute_id'],
                    'wc_attribute_name' => $mapping['wc_attribute_name'],
                    'wc_attribute_label' => $mapping['wc_attribute_label'],
                    'anar_keys' => [],
                ];
            }
            
            $name_based[$anar_name]['anar_keys'][] = $key;
        }
        
        return $name_based;
    }

    /**
     * Save mapping using Anar attribute key as primary identifier.
     * If multiple keys share the same name, all keys with that name will be mapped to the same WC attribute.
     * 
     * @param string $anar_attribute_key The Anar attribute key (primary identifier, stable from API)
     * @param int $wc_attribute_id The WooCommerce attribute ID to map to
     * @param string|null $anar_name Optional Anar attribute name (for display and grouping)
     * @return array The saved mapping
     */
    public function save_mapping(string $anar_attribute_key, int $wc_attribute_id, ?string $anar_name = null): array
    {
        if (empty($anar_attribute_key) || $wc_attribute_id <= 0) {
            throw new \InvalidArgumentException(__('اطلاعات ارسال شده نامعتبر است.', 'wp-anar'));
        }

        $taxonomy = $this->find_woocommerce_attribute($wc_attribute_id);

        if (!$taxonomy) {
            throw new \RuntimeException(__('ویژگی انتخاب شده در ووکامرس یافت نشد.', 'wp-anar'));
        }

        $mappings = $this->get_mappings();
        
        // Store mapping by key (primary identifier)
        // Get the ACTUAL registered taxonomy name (critical for Persian characters)
        $registered_taxonomy_name = $this->get_registered_taxonomy_name($taxonomy->attribute_name);
        // Store just the slug part (without 'pa_' prefix) for backward compatibility
        $normalized_slug = str_replace('pa_', '', $registered_taxonomy_name);

        $mapping_data = [
            'wc_attribute_id' => $taxonomy->attribute_id,
            'wc_attribute_name' => $taxonomy->attribute_name,
            'wc_attribute_label' => $taxonomy->attribute_label,
            'wc_attribute_slug' => $normalized_slug,
            'anar_name' => $anar_name ?? $anar_attribute_key,
        ];
        
        $mappings[$anar_attribute_key] = $mapping_data;
        
        // If name is provided and different from key, also map all other keys with the same name
        if ($anar_name && $anar_name !== $anar_attribute_key) {
            $attributes = $this->get_attributes();
            foreach ($attributes as $attribute) {
                $attr_array = (array) $attribute;
                $attr_key = $attr_array['key'] ?? '';
                $attr_name = $attr_array['name'] ?? '';
                
                // Map all keys with the same name to the same WC attribute
                if ($attr_name === $anar_name && !empty($attr_key) && $attr_key !== $anar_attribute_key) {
                    $mappings[$attr_key] = $mapping_data;
                }
            }
        }

        // Save in key-based format
        $this->repository->save('attributeMap', $mappings);

        return $mapping_data;
    }

    /**
     * Remove mapping by Anar attribute key.
     * Also removes all other keys with the same anar_name (to match auto-mapping behavior).
     * 
     * @param string $anar_attribute_key The Anar attribute key
     */
    public function remove_mapping(string $anar_attribute_key): void
    {
        $mappings = $this->get_mappings();

        if (!isset($mappings[$anar_attribute_key])) {
            return; // Key doesn't exist, nothing to remove
        }

        // Get the anar_name from the mapping being removed
        $mapping_to_remove = $mappings[$anar_attribute_key];
        $anar_name = $mapping_to_remove['anar_name'] ?? '';

        // Remove the requested key
        unset($mappings[$anar_attribute_key]);

        // If we have an anar_name, also remove all other keys with the same name
        // This matches the auto-mapping behavior in save_mapping()
        // Iterate through mappings to find all keys with the same anar_name
        if (!empty($anar_name)) {
            foreach ($mappings as $key => $mapping) {
                if (!is_array($mapping)) {
                    continue;
                }
                
                $mapping_anar_name = $mapping['anar_name'] ?? '';
                
                // Remove all keys with the same name (except the one we already removed)
                if ($mapping_anar_name === $anar_name && $key !== $anar_attribute_key) {
                    unset($mappings[$key]);
                }
            }
        }

        // Save the updated mappings
        $this->repository->save('attributeMap', $mappings);
    }
    
    /**
     * Get mapping by Anar attribute key.
     * 
     * @param string $anar_key The Anar attribute key
     * @return array|null The mapping or null if not found
     */
    public function get_mapping_by_key(string $anar_key): ?array
    {
        $mappings = $this->get_mappings();
        return $mappings[$anar_key] ?? null;
    }

    /**
     * Retrieve WooCommerce attribute taxonomy list.
     */
    public function get_woocommerce_attributes(string $search = ''): array
    {
        $taxonomies = wc_get_attribute_taxonomies();

        if (!empty($search)) {
            $taxonomies = array_filter($taxonomies, static function ($taxonomy) use ($search) {
                return stripos($taxonomy->attribute_label, $search) !== false
                    || stripos($taxonomy->attribute_name, $search) !== false;
            });
        }

        return $taxonomies;
    }

    private function find_woocommerce_attribute(int $attribute_id): ?object
    {
        $taxonomies = wc_get_attribute_taxonomies();

        foreach ($taxonomies as $taxonomy) {
            if ((int) $taxonomy->attribute_id === $attribute_id) {
                return $taxonomy;
            }
        }

        return null;
    }

    /**
     * Normalize WooCommerce attribute slug to match how WooCommerce stores taxonomy names.
     * Uses WooCommerce's wc_attribute_taxonomy_name() helper to ensure consistency.
     * 
     * @param string $raw_slug The raw attribute slug (e.g., 'سایز-ووکامرس' or 'sizzzz')
     * @return string The normalized slug that matches WooCommerce's taxonomy registration
     */
    private function normalize_wc_attribute_slug(string $raw_slug): string
    {
        if (empty($raw_slug)) {
            return '';
        }

        // Use WooCommerce's helper to get the normalized taxonomy name
        // This ensures we match exactly how WooCommerce registers taxonomies
        $taxonomy_name = wc_attribute_taxonomy_name($raw_slug);
        
        // Remove 'pa_' prefix to get just the slug
        return str_replace('pa_', '', $taxonomy_name);
    }

    /**
     * Get the actual registered taxonomy name from WordPress.
     * This is critical for Persian characters - WordPress may register taxonomies with URL-encoded names.
     * 
     * @param string $attribute_slug The attribute slug (without 'pa_' prefix)
     * @return string The actual registered taxonomy name (with 'pa_' prefix) as WordPress registered it
     */
    private function get_registered_taxonomy_name(string $attribute_slug): string
    {
        if (empty($attribute_slug)) {
            return '';
        }

        // First, try the literal format: 'pa_' + attribute_slug (as stored in database)
        // This is how WooCommerce stores it for Persian characters (may be URL-encoded)
        $literal_name = 'pa_' . $attribute_slug;
        
        // Check both get_taxonomy() and taxonomy_exists() as they may behave differently
        $taxonomy = get_taxonomy($literal_name);
        $taxonomy_exists = taxonomy_exists($literal_name);
        
        if (($taxonomy && $taxonomy->name === $literal_name) || $taxonomy_exists) {
            return $literal_name;
        }
        
        // Second, try WooCommerce's helper
        $expected_name = wc_attribute_taxonomy_name($attribute_slug);
        
        // Check if this taxonomy is actually registered with this exact name
        $taxonomy = get_taxonomy($expected_name);
        if ($taxonomy && $taxonomy->name === $expected_name) {
            return $expected_name;
        }

        // Taxonomy might be registered with a different encoding
        // Search all registered product attribute taxonomies to find the exact match
        $all_taxonomies = get_taxonomies(['object_type' => ['product']], 'objects');
        
        // Normalize the input slug for comparison
        $normalized_attr_slug = mb_strtolower(trim($attribute_slug));
        
        foreach ($all_taxonomies as $tax) {
            if (strpos($tax->name, 'pa_') !== 0) {
                continue; // Not a product attribute taxonomy
            }
            
            // Get the slug part (without 'pa_' prefix)
            $tax_slug = str_replace('pa_', '', $tax->name);
            $normalized_tax_slug = mb_strtolower(trim($tax_slug));
            
            // Try multiple comparison methods to handle URL encoding differences
            // Method 1: Direct comparison (works for Latin characters and exact URL-encoded matches)
            if ($tax_slug === $attribute_slug) {
                return $tax->name;
            }
            
            if ($normalized_tax_slug === $normalized_attr_slug && $normalized_tax_slug !== $tax_slug) {
                return $tax->name;
            }
            
            // Method 2: URL decode both and compare (handles encoding differences)
            $decoded_tax_slug = urldecode($tax_slug);
            $decoded_attr_slug = urldecode($attribute_slug);
            if ($decoded_tax_slug === $decoded_attr_slug) {
                return $tax->name;
            }
            
            $normalized_decoded_tax = mb_strtolower(trim($decoded_tax_slug));
            $normalized_decoded_attr = mb_strtolower(trim($decoded_attr_slug));
            if ($normalized_decoded_tax === $normalized_decoded_attr && !empty($normalized_decoded_tax)) {
                return $tax->name;
            }
            
            // Method 3: Compare sanitized versions (handles different encoding methods)
            $sanitized_tax = sanitize_title($decoded_tax_slug);
            $sanitized_attr = sanitize_title($decoded_attr_slug);
            if ($sanitized_tax === $sanitized_attr && !empty($sanitized_tax)) {
                return $tax->name;
            }
        }

        // Fallback: try to register using literal format first (for Persian characters)
        // This matches how WooCommerce stores attribute names in the database
        if (!taxonomy_exists($literal_name)) {
            register_taxonomy($literal_name, ['product'], []);
            return $literal_name;
        }
        
        // If literal doesn't work, try WooCommerce helper format
        if (!taxonomy_exists($expected_name)) {
            register_taxonomy($expected_name, ['product'], []);
        }
        
        return $expected_name;
    }

    /**
     * Check if a WooCommerce attribute already exists by Anar key.
     * Uses the same logic as product creation to determine if attribute exists.
     * 
     * @param string $anar_key The Anar attribute key (primary identifier)
     * @return array|null Returns array with 'id', 'label', 'slug' if exists, null otherwise
     */
    public function check_attribute_exists_by_key(string $anar_key): ?array
    {
        if (empty($anar_key)) {
            return null;
        }

        // Use Anar key (sanitized) as the WC attribute slug directly
        // This is the same logic used in get_or_create_attribute_by_key() Step 2
        $tax_slug = sanitize_title($anar_key);

        // Check if attribute already exists with this slug (Anar key)
        // This matches exactly how get_or_create_attribute_by_key() checks
        $existing_id = wc_attribute_taxonomy_id_by_name($tax_slug);
        
        if ($existing_id > 0) {
            // Attribute exists - get its details
            $taxonomy = $this->find_woocommerce_attribute($existing_id);
            
            if ($taxonomy) {
                return [
                    'id' => $taxonomy->attribute_id,
                    'label' => $taxonomy->attribute_label,
                    'slug' => $taxonomy->attribute_name,
                ];
            }
        }
        
        return null;
    }

    /**
     * Get or create WooCommerce attribute by Anar attribute key.
     * This is the main method for on-demand attribute creation during product creation.
     * Uses key as primary identifier (since variants reference attributes by key).
     * 
     * @param string $anar_key The Anar attribute key (primary identifier, stable from API)
     * @param string|null $anar_name Optional Anar attribute name (for display/fallback)
     * @param array $values Array of attribute values to create/update
     * @return string The WooCommerce taxonomy name (e.g., 'pa_size')
     */
    public function get_or_create_attribute_by_key(string $anar_key, ?string $anar_name = null, array $values = []): string
    {
        if (empty($anar_key)) {
            throw new \InvalidArgumentException(__('کلید ویژگی نمی‌تواند خالی باشد.', 'wp-anar'));
        }

        // Check cache first (by key)
        if (isset($this->created_attributes_cache[$anar_key])) {
            $taxonomy_name = $this->created_attributes_cache[$anar_key];
            // Still need to ensure values are created
            $this->ensure_attribute_values($taxonomy_name, $values);
            return $taxonomy_name;
        }

        // Step 1: Use Anar key (sanitized) as the WC attribute slug directly
        // This ensures direct lookup by key - simple and reliable
        $tax_slug = sanitize_title($anar_key);
        $display_name = $anar_name ?? $anar_key;
        
        // Step 2: Check if attribute already exists with this slug (Anar key)
        $existing_id = wc_attribute_taxonomy_id_by_name($tax_slug);
        
        if ($existing_id > 0) {
            // Attribute exists - use it
            $taxonomy = $this->find_woocommerce_attribute($existing_id);
            if ($taxonomy) {
                $tax_slug = $taxonomy->attribute_name;
                $tax_label = $taxonomy->attribute_label;
            }
            // Get the ACTUAL registered taxonomy name (critical for Persian characters)
            $taxonomy_name = $this->get_registered_taxonomy_name($tax_slug);
        } else {
            // Step 3: Check if mapping exists by key (user mapped to existing WC attribute)
            $mappings = $this->get_mappings();
            $mapping = $mappings[$anar_key] ?? null;
            
            if ($mapping && (!empty($mapping['wc_attribute_slug']) || !empty($mapping['wc_attribute_name']))) {
                // Use mapped WooCommerce attribute slug
                $tax_slug = $mapping['wc_attribute_slug'] ?? $mapping['wc_attribute_name'];
                $tax_label = $mapping['wc_attribute_label'] ?? $display_name;
                
                // Verify the mapped attribute exists
                $mapped_id = wc_attribute_taxonomy_id_by_name($tax_slug);
                if ($mapped_id > 0) {
                    // Get the WooCommerce attribute object to access the actual taxonomy name
                    $wc_attribute = $this->find_woocommerce_attribute($mapped_id);
                    if ($wc_attribute) {
                        // Build taxonomy name directly from attribute_name (this is how WooCommerce stores it)
                        // For Persian characters, WordPress stores it as-is in the database
                        $taxonomy_name = 'pa_' . $wc_attribute->attribute_name;
                        
                        // CRITICAL: Ensure taxonomy is registered before inserting terms
                        // WooCommerce should have registered it when the attribute was created, but verify
                        if (!taxonomy_exists($taxonomy_name)) {
                            // Register the taxonomy if it doesn't exist
                            // Use the exact format: 'pa_' + attribute_name (as stored in database)
                            register_taxonomy($taxonomy_name, ['product'], []);
                        }
                        
                        // Verify the taxonomy is actually registered and accessible
                        $taxonomy_obj = get_taxonomy($taxonomy_name);
                        if (!$taxonomy_obj) {
                            // If still not found, try to find the actual registered name
                            $taxonomy_name = $this->get_registered_taxonomy_name($wc_attribute->attribute_name);
                        }
                    } else {
                        // Fallback: use the registered taxonomy name lookup
                        $taxonomy_name = $this->get_registered_taxonomy_name($tax_slug);
                    }
                    
                    // CRITICAL: Ensure all values are created as terms in the mapped taxonomy
                    // This is essential for variation attributes to work correctly
                    $this->ensure_attribute_values($taxonomy_name, $values);
                    
                    $this->created_attributes_cache[$anar_key] = $taxonomy_name;
                    return $taxonomy_name;
                }
                // If mapped attribute doesn't exist, fall through to create new one with Anar key as slug
                anar_log("Mapped attribute '{$tax_slug}' not found for Anar key '{$anar_key}', creating new attribute with Anar key as slug", 'warning');
            }
            
            // Step 4: Create new attribute using Anar key as slug
            $args = [
                'name' => $display_name,
                'slug' => $tax_slug, // Anar key (sanitized) as slug
                'type' => 'select',
                'order_by' => 'menu_order',
                'has_archives' => false,
            ];

            $attribute_id = wc_create_attribute($args);
            
            if (is_wp_error($attribute_id)) {
                anar_log("Failed to create WooCommerce attribute '{$display_name}': " . $attribute_id->get_error_message(), 'error');
                throw new \RuntimeException(__('خطا در ایجاد ویژگی ووکامرس.', 'wp-anar'));
            }

            // Register taxonomy using WooCommerce's helper to ensure proper normalization
            $taxonomy_name = wc_attribute_taxonomy_name($tax_slug);
            register_taxonomy($taxonomy_name, ['product'], []);
            
            anar_log("Created new WooCommerce attribute: {$display_name} ({$taxonomy_name}) for Anar key: {$anar_key}", 'info');
        }
        
        // Ensure all values are created as terms
        $this->ensure_attribute_values($taxonomy_name, $values);
        
        // Cache the result by key (primary identifier)
        $this->created_attributes_cache[$anar_key] = $taxonomy_name;

        return $taxonomy_name;
    }
    
    /**
     * Get or create WooCommerce attribute by Anar attribute name (for backward compatibility).
     * This method groups attributes by name and uses the first key found.
     * 
     * @param string $anar_name The Anar attribute name
     * @param string|null $anar_key Optional Anar attribute key
     * @param array $values Array of attribute values
     * @return string The WooCommerce taxonomy name
     */
    public function get_or_create_attribute_by_name(string $anar_name, ?string $anar_key = null, array $values = []): string
    {
        // If key is provided, use key-based method
        if ($anar_key) {
            return $this->get_or_create_attribute_by_key($anar_key, $anar_name, $values);
        }
        
        // Otherwise, try to find a key for this name from attributes
        $attributes = $this->get_attributes();
        foreach ($attributes as $attribute) {
            $attr_array = (array) $attribute;
            $attr_name = $attr_array['name'] ?? '';
            $attr_key = $attr_array['key'] ?? '';
            
            if ($attr_name === $anar_name && !empty($attr_key)) {
                return $this->get_or_create_attribute_by_key($attr_key, $anar_name, $values);
            }
        }
        
        // Fallback: use name as key (not ideal, but works)
        return $this->get_or_create_attribute_by_key($anar_name, $anar_name, $values);
    }

    /**
     * Ensure all attribute values exist as terms in the taxonomy.
     * 
     * @param string $taxonomy_name The taxonomy name (e.g., 'pa_size')
     * @param array $values Array of values to create
     */
    private function ensure_attribute_values(string $taxonomy_name, array $values): void
    {
        if (empty($values)) {
            return;
        }

        foreach ($values as $value) {
            if (empty($value)) {
                continue;
            }

            // Check if term exists by name first (more reliable)
            $existing_terms = get_terms([
                'taxonomy' => $taxonomy_name,
                'name' => $value,
                'hide_empty' => false,
                'number' => 1,
            ]);
            
            if (!is_wp_error($existing_terms) && !empty($existing_terms)) {
                // Term already exists
                continue;
            }
            
            // Also check by slug (sanitized value)
            $expected_slug = sanitize_title($value);
            $term_by_slug = term_exists($expected_slug, $taxonomy_name);
            
            if ($term_by_slug) {
                // Term exists by slug
                continue;
            }
            
            // Term doesn't exist, create it
            $result = wp_insert_term($value, $taxonomy_name);
            
            if (is_wp_error($result)) {
                // If term already exists (duplicate), that's okay
                if ($result->get_error_code() !== 'term_exists') {
                    anar_log("Failed to create term '{$value}' in taxonomy '{$taxonomy_name}': " . $result->get_error_message(), 'error');
                }
            }
        }
    }

    /**
     * Merge attribute values from multiple attributes with the same name.
     * 
     * @param array $attributes Array of attribute arrays with 'name' and 'values' keys
     * @return array Merged values (unique)
     */
    public function merge_attribute_values(array $attributes): array
    {
        $merged = [];
        
        foreach ($attributes as $attribute) {
            $values = $attribute['values'] ?? [];
            $merged = array_merge($merged, $values);
        }
        
        return array_unique($merged);
    }

    /**
     * Create WooCommerce product attributes from Anar attribute data.
     * Simple approach: Each Anar key gets its own WC attribute (no merging).
     * Returns both attribute objects and a key->taxonomy lookup map.
     * 
     * @param array $attributes Array of attribute data with keys: key, name, values
     * @return array Array with 'attributes' (WC_Product_Attribute objects) and 'key_lookup' (key->taxonomy map)
     */
    public function create_attributes(array $attributes): array
    {
        if (empty($attributes)) {
            return [
                'attributes' => [],
                'key_lookup' => [],
            ];
        }

        // Process each attribute individually (no merging by name)
        $attributeObjects = [];
        $key_lookup = []; // Anar key -> WooCommerce taxonomy name
        
        foreach ($attributes as $product_attribute) {
            $name = $product_attribute['name'] ?? '';
            $key = $product_attribute['key'] ?? '';
            $values = $product_attribute['values'] ?? [];

            if (empty($key)) {
                anar_log("Skipping attribute with empty key (name: {$name})", 'warning');
                continue;
            }

            try {
                // Get or create attribute using key (each key gets its own attribute)
                // Pass values array so terms are created in the correct taxonomy (mapped or default)
                $taxonomy_name = $this->get_or_create_attribute_by_key(
                    $key,
                    $name,
                    $values
                );

                // Map key to taxonomy
                $key_lookup[$key] = $taxonomy_name;

                // Get attribute ID
                $taxonomy_slug = str_replace('pa_', '', $taxonomy_name);
                $attributeId = wc_attribute_taxonomy_id_by_name($taxonomy_slug);

                if ($attributeId > 0) {
                    // Create WC_Product_Attribute object
                    $attribute = new \WC_Product_Attribute();
                    $attribute->set_id($attributeId);
                    $attribute->set_position(0);
                    $attribute->set_visible(true);
                    $attribute->set_variation(true);
                    $attribute->set_name($taxonomy_name);
                    
                    // CRITICAL: Use term names in set_options() (like old stable system)
                    // WooCommerce can match term names to terms, and variations will use sanitize_title() 
                    // which matches the slug WordPress creates from the term name
                    // This matches the old stable system behavior exactly
                    $attribute->set_options($values);
                    $attributeObjects[] = $attribute;
                } else {
                    anar_log("Failed to get attribute ID for taxonomy: {$taxonomy_name}", 'error');
                }
            } catch (\Throwable $e) {
                anar_log("Error creating attribute for key '{$key}' (name: '{$name}'): " . $e->getMessage(), 'error');
                // Continue with other attributes
            }
        }

        return [
            'attributes' => $attributeObjects,
            'key_lookup' => $key_lookup,
        ];
    }

    /**
     * Clear the created attributes cache.
     * Should be called at the start of each batch to ensure fresh lookups.
     */
    public function clear_cache(): void
    {
        $this->created_attributes_cache = [];
    }

    /**
     * Build attribute map in simplified format for backward compatibility.
     * Returns name-based map: ['attribute_name' => ['name' => ..., 'map' => ...]]
     * 
     * @return array Simplified attribute map
     */
    public function build_attribute_map_for_creation(): array
    {
        $map = [];
        $mappings = $this->get_mappings();

        foreach ($mappings as $name => $mapping) {
            if (!is_array($mapping)) {
                continue;
            }

            $wc_name = $mapping['wc_attribute_name'] ?? $name;
            $wc_slug = $mapping['wc_attribute_slug'] ?? '';
            if (empty($wc_slug) && !empty($mapping['wc_attribute_name'])) {
                // Get the actual registered taxonomy name and extract slug
                $registered_name = $this->get_registered_taxonomy_name($mapping['wc_attribute_name']);
                $wc_slug = str_replace('pa_', '', $registered_name);
            } elseif (empty($wc_slug)) {
                // Fallback: use normalize method
                $wc_slug = $this->normalize_wc_attribute_slug($name);
            }

            $map[$name] = [
                'name' => $wc_name,
                'map' => $wc_slug,
            ];
        }

        return $map;
    }
}

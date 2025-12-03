<?php

namespace Anar\Import;

use Anar\AnarDataRepository;
use Anar\AnarDataItemRepository;

class CategoryManager
{
    private AnarDataRepository $repository;
    private AnarDataItemRepository $item_repository;
    private array $cached_items = [];
    private array $cached_index = [];

    public function __construct()
    {
        $this->repository = AnarDataRepository::get_instance();
        $this->item_repository = AnarDataItemRepository::get_instance();
    }

    /**
     * Get the normalized category tree.
     */
    public function get_tree(): array
    {
        $items = $this->get_items();
        return $this->build_tree($items);
    }

    /**
     * Retrieve current mappings from storage.
     */
    public function get_mappings(): array
    {
        $record = $this->repository->get('categoryMap');

        if (!$record) {
            return [];
        }

        $response = $record['response'];

        return is_array($response) ? $response : [];
    }

    /**
     * Save or update a category mapping.
     */
    public function save_mapping(string $anar_category_id, int $wc_category_id, ?string $anar_name = null): array
    {
        if (empty($anar_category_id) || $wc_category_id <= 0) {
            throw new \InvalidArgumentException(__('اطلاعات ارسال شده نامعتبر است.', 'wp-anar'));
        }

        $term = get_term($wc_category_id, 'product_cat');

        if (!$term || $term instanceof \WP_Error) {
            throw new \RuntimeException(__('دسته‌بندی انتخاب شده در ووکامرس یافت نشد.', 'wp-anar'));
        }

        if (!$anar_name) {
            $category = $this->get_category($anar_category_id);
            $anar_name = $category['name'] ?? '';
        }

        $mapping = $this->get_mappings();

        $mapping[$anar_category_id] = [
            'anar_id'      => $anar_category_id,
            'anar_name'    => $anar_name ?? '',
            'wc_term_id'   => $term->term_id,
            'wc_term_name' => $term->name,
        ];

        $this->repository->save('categoryMap', $mapping);

        return $mapping[$anar_category_id];
    }

    public function remove_mapping(string $anar_category_id): void
    {
        $mapping = $this->get_mappings();

        if (isset($mapping[$anar_category_id])) {
            unset($mapping[$anar_category_id]);
            $this->repository->save('categoryMap', $mapping);
        }
    }

    /**
     * Find a category by ID.
     */
    public function get_category(string $category_id): ?array
    {
        $index = $this->get_index();

        return $index[$category_id] ?? null;
    }

    /**
     * Convert flat list to tree.
     */
    private function build_tree(array $items): array
    {
        $index = $this->get_index($items);
        $tree = [];

        foreach ($index as $id => &$node) {
            if (!empty($node['parent']) && isset($index[$node['parent']])) {
                $index[$node['parent']]['children'][] =& $node;
            } else {
                $tree[] =& $node;
            }
        }

        return $tree;
    }

    /**
     * Get only parent (top-level) categories.
     */
    public function get_parent_categories(): array
    {
        $index = $this->get_index();
        $parents = [];

        foreach ($index as $node) {
            if (empty($node['parent'])) {
                $parents[] = $node;
            }
        }

        return $parents;
    }

    public function get_root_category_name(string $category_id): ?string
    {
        $root = $this->get_root_category($category_id);
        return $root['name'] ?? null;
    }

    /**
     * Extract unique root category names from product categories array.
     *
     * @param array $product_categories
     * @return array
     */
    public function extract_root_names_from_product(array $product_categories): array
    {
        $names = [];

        foreach ($product_categories as $category) {
            if (is_object($category)) {
                $category_id = $category->_id ?? null;
            } else {
                $category = (array) $category;
                $category_id = $category['_id'] ?? ($category['id'] ?? null);
            }

            if (!$category_id) {
                continue;
            }

            $root_name = $this->get_root_category_name($category_id);

            if ($root_name && !in_array($root_name, $names, true)) {
                $names[] = $root_name;
            }
        }

        return $names;
    }

    private function get_items(): array
    {
        if (!empty($this->cached_items)) {
            return $this->cached_items;
        }

        // Try new table first (paginated storage)
        $items_from_new_table = $this->item_repository->get_items_by_key('category');
        
        if (!empty($items_from_new_table)) {
            // Convert from new format to old format for backward compatibility
            $this->cached_items = array_map(function($row) {
                return $row['data'];
            }, $items_from_new_table);
        } else {
            // Fallback to old repository for backward compatibility
            $record = $this->repository->get('categories');
            $this->cached_items = $record ? (array) ($record['response'] ?? []) : [];
        }

        return $this->cached_items;
    }

    /**
     * Get a category by Anar _id directly from the new table.
     * 
     * @param string $anar_id The Anar API _id field
     * @return array|null Category data or null if not found
     */
    public function get_category_by_id(string $anar_id): ?array
    {
        $item = $this->item_repository->get_item('category', $anar_id);
        
        if ($item && isset($item['data'])) {
            return is_array($item['data']) ? $item['data'] : (array) $item['data'];
        }
        
        return null;
    }

    private function get_index(array $items = []): array
    {
        if (!empty($this->cached_index)) {
            return $this->cached_index;
        }

        $items = $items ?: $this->get_items();
        $index = [];

        foreach ($items as $item) {
            if (!is_object($item) && !is_array($item)) {
                continue;
            }

            $item = (array) $item;
            $id = $item['_id'] ?? ($item['id'] ?? null);

            if (!$id) {
                continue;
            }

            $index[$id] = [
                'id'       => $id,
                'name'     => $item['name'] ?? '',
                'parent'   => $item['parent'] ?? null,
                'level'    => $item['level'] ?? '',
                'route'    => $item['route'] ?? [],
                'children' => [],
            ];
        }

        $this->cached_index = $index;

        return $this->cached_index;
    }

    private function get_root_category(string $category_id): ?array
    {
        $index = $this->get_index();

        if (!isset($index[$category_id])) {
            return null;
        }

        $current = $index[$category_id];

        while (!empty($current['parent']) && isset($index[$current['parent']])) {
            $current = $index[$current['parent']];
        }

        return $current;
    }

    /**
     * Create a WooCommerce product category if it doesn't exist.
     * 
     * @param string $category_name The name of the category to create
     * @return int The term ID of the category (0 on failure)
     */
    public function create_woocommerce_category(string $category_name): int
    {
        try {
            $existing_category = get_term_by('name', $category_name, 'product_cat');

            if (!$existing_category) {
                $term = wp_insert_term(
                    $category_name,
                    'product_cat'
                );

                if (!is_wp_error($term)) {
                    return $term['term_id'];
                } else {
                    throw new \Exception('Category creation failed: ' . $term->get_error_message());
                }
            } else {
                return $existing_category->term_id;
            }
        } catch (\Exception $e) {
            anar_log('Error creating category: ' . $e->getMessage(), 'error');
            return 0;
        }
    }

    /**
     * Map Anar product categories to WooCommerce categories using saved mappings.
     * Creates categories if mapping is 'select'.
     * 
     * @param array $product_categories Array of category names from product data
     * @param array $category_map Category mapping array in format: ['anar_name' => 'woo_name' or 'select']
     * @return array Array of WooCommerce category term IDs
     */
    public function map_anar_product_cats_with_saved_cats(array $product_categories, array $category_map): array
    {
        $IDs = [];
        foreach ($product_categories as $categoryName) {
            if (empty($categoryName)) {
                continue;
            }

            $newCategoryName = $category_map[$categoryName] ?? null;
            
            if ($newCategoryName === 'select' || $newCategoryName === null) {
                // Create new category
                $category_id = $this->create_woocommerce_category($categoryName);
                if ($category_id > 0) {
                    $IDs[] = $category_id;
                }
            } else {
                // Use mapped category
                $product_cat = get_term_by('name', $newCategoryName, 'product_cat');
                if ($product_cat && !is_wp_error($product_cat)) {
                    $IDs[] = $product_cat->term_id;
                }
            }
        }

        return $IDs;
    }
}


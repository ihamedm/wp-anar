<?php

use Anar\Import\CategoryManager;

if (!defined('ABSPATH')) {
    exit;
}

$category_manager = new CategoryManager();
$category_tree = $category_manager->get_tree();
$category_map = $category_manager->get_mappings();
$has_data = !empty($category_tree);

$next_step_url = add_query_arg(
    [
        'page' => 'wp-anar-import-v2',
        'step' => 'attributes',
    ],
    admin_url('admin.php')
);

/**
 * Count all subcategories recursively (including nested children).
 * 
 * @param array $node Category node with children
 * @return int Total count of subcategories
 */
function awca_count_subcategories(array $node): int
{
    $count = 0;
    
    if (!empty($node['children'])) {
        foreach ($node['children'] as $child) {
            $count++; // Count this child
            $count += awca_count_subcategories($child); // Count its descendants
        }
    }
    
    return $count;
}

function awca_render_import_category_tree(array $nodes, array $category_map, bool $is_root = false): void
{
    if (empty($nodes)) {
        return;
    }

    echo '<ul class="awca-category-tree' . ($is_root ? ' awca-category-tree--root' : '') . '">';

    foreach ($nodes as $node) {
        $has_children = !empty($node['children']);
        $is_root_node = empty($node['parent']);
        $mapping = $category_map[$node['id']] ?? null;
        $mapped_name = $mapping['wc_term_name'] ?? '';
        $mapped_id = $mapping['wc_term_id'] ?? '';
        
        // Count subcategories for root nodes
        $subcategory_count = 0;
        if ($is_root_node && $has_children) {
            $subcategory_count = awca_count_subcategories($node);
        }

        echo '<li class="awca-category-node" data-node-id="' . esc_attr($node['id']) . '">';

        if ($has_children) {
            echo '<button type="button" class="awca-category-toggle" aria-label="' . esc_attr__('نمایش زیرمجموعه', 'wp-anar') . '">+</button>';
        } else {
            echo '<span class="awca-category-toggle awca-category-toggle--placeholder"></span>';
        }

        echo '<div class="awca-category-node__content">';
        echo '<span class="awca-category-node__name">' . esc_html($node['name']);
        if ($is_root_node && $subcategory_count > 0) {
            echo ' <span class="awca-category-node__count">(' . esc_html($subcategory_count) . ' زیرمجموعه)</span>';
        }
        echo '</span>';

        if ($is_root_node) {
            echo '<div class="awca-category-node__actions" data-selected-term="' . esc_attr($mapped_id) . '" data-selected-name="' . esc_attr($mapped_name) . '">';
            echo '<button class="button button-secondary awca-map-category" data-anar-id="' . esc_attr($node['id']) . '" data-anar-name="' . esc_attr($node['name']) . '">';
            echo esc_html__('معادل‌سازی', 'wp-anar');
            echo '</button>';

            if ($mapped_name) {
                echo '<span class="awca-category-node__mapping">';
                echo esc_html(sprintf(__('معادل شده با: %s', 'wp-anar'), $mapped_name));
                echo '</span>';
            } else {
                echo '<span class="awca-category-node__mapping awca-category-node__mapping--pending">';
                echo esc_html__('هنوز معادل‌سازی نشده است', 'wp-anar');
                echo '</span>';
            }

            echo '</div>';
        }

        echo '</div>';

        if ($has_children) {
            awca_render_import_category_tree($node['children'], $category_map);
        }

        echo '</li>';
    }

    echo '</ul>';
}

?>

<div class="awca-card awca-card--categories">
    <h2>معادل‌سازی دسته‌بندی‌ها</h2>

    <?php if (!$has_data): ?>
        <p><?php esc_html_e('هیچ داده‌ای برای دسته‌بندی‌ها پیدا نشد. لطفاً ابتدا مرحله دریافت داده‌ها را کامل کنید.', 'wp-anar'); ?></p>
    <?php else: ?>
        <p>
            درخت زیر ساختار دسته‌بندی‌های انار را نمایش می‌دهد. تنها دسته‌بندی‌های سطح اول نیاز به معادل‌سازی دارند.
            با کلیک روی دکمه معادل‌سازی، دسته‌بندی مرتبط خود را جستجو کرده و انتخاب کنید.
        </p>

        <div class="awca-category-tree-wrapper">
            <?php awca_render_import_category_tree($category_tree, $category_map, true); ?>
        </div>

        <div class="awca-fetch-actions">
            <a class="button button-primary button-large" href="<?php echo esc_url($next_step_url); ?>">
                ادامه به معادل‌سازی ویژگی‌ها
            </a>
        </div>
    <?php endif; ?>
</div>

<div class="awca-import-modal" id="awca-category-map-modal" aria-hidden="true">
    <div class="awca-import-modal__overlay" data-modal-close></div>
    <div class="awca-import-modal__content" role="dialog" aria-modal="true" aria-labelledby="awca-category-modal-title">
        <button class="awca-import-modal__close" data-modal-close>&times;</button>
        <h3 id="awca-category-modal-title"><?php esc_html_e('انتخاب دسته‌بندی ووکامرس', 'wp-anar'); ?></h3>

        <form id="awca-category-map-form">
            <p id="awca-category-modal-description"></p>
            <label for="awca-category-select"><?php esc_html_e('جستجو در دسته‌بندی‌های شما', 'wp-anar'); ?></label>
            <select id="awca-category-select" style="width: 100%;" data-placeholder="<?php esc_attr_e('یک دسته‌بندی را انتخاب کنید', 'wp-anar'); ?>"></select>

            <div class="awca-modal-actions">
                <button type="button" class="button button-secondary" data-modal-close><?php esc_html_e('انصراف', 'wp-anar'); ?></button>
                <button type="button" class="button button-link-delete" id="awca-category-map-remove">
                    <?php esc_html_e('حذف معادل‌سازی', 'wp-anar'); ?>
                </button>
                <button type="submit" class="button button-primary"><?php esc_html_e('ذخیره معادل‌سازی', 'wp-anar'); ?></button>
            </div>

            <input type="hidden" name="anar_category_id" id="awca-modal-anar-id" />
            <input type="hidden" name="anar_category_name" id="awca-modal-anar-name" />
        </form>

        <div class="awca-modal-feedback" id="awca-category-modal-feedback"></div>
    </div>
</div>


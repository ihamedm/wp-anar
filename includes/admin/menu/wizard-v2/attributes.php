<?php

use Anar\Import\AttributeManager;

if (!defined('ABSPATH')) {
    exit;
}

$attribute_manager = new AttributeManager();
$attributes = $attribute_manager->get_attributes();
$attributes_grouped = $attribute_manager->get_attributes_grouped_by_name();
$attribute_map = $attribute_manager->get_mappings();
$attribute_lookup = $attribute_manager->get_attribute_lookup();
$has_attributes = !empty($attributes);

$next_step_url = add_query_arg(
    [
        'page' => 'wp-anar-import-v2',
        'step' => 'create-products',
    ],
    admin_url('admin.php')
);

?>

<div class="awca-card awca-card--attributes">
    <h2>معادل‌سازی ویژگی‌ها</h2>

    <?php if (!$has_attributes): ?>
        <p><?php esc_html_e('هیچ ویژگی‌ای یافت نشد. ابتدا باید مرحله دریافت داده‌ها را کامل کنید.', 'wp-anar'); ?></p>
    <?php else: ?>
        <p>
            ویژگی‌های انار را با ویژگی‌های موجود در فروشگاه خود معادل‌سازی کنید تا هنگام ساخت محصولات، مقادیر در جای مناسب قرار گیرند.
            برای هر ویژگی، روی دکمه معادل‌سازی کلیک کرده و ویژگی متناظر ووکامرس را انتخاب کنید.
        </p>

        <div class="awca-attribute-grid">
            <?php foreach ($attributes_grouped as $anar_name => $grouped_attr): ?>
                <?php
                $keys = $grouped_attr['keys'] ?? [];
                $all_values = $grouped_attr['values'] ?? [];
                
                // Check each key to see if it already exists in WooCommerce
                $created_keys = [];
                $new_keys = [];
                
                foreach ($keys as $key) {
                    $exists = $attribute_manager->check_attribute_exists_by_key($key);
                    
                    if ($exists) {
                        $created_keys[] = [
                            'key' => $key,
                            'wc_attr' => $exists,
                            'values' => $attribute_lookup[$key]['values'] ?? [],
                        ];
                    } else {
                        $new_keys[] = [
                            'key' => $key,
                            'values' => $attribute_lookup[$key]['values'] ?? [],
                        ];
                    }
                }
                
                // Helper function to render an attribute card
                $render_card = function($card_keys, $is_created = false, $wc_attr = null) use ($anar_name, $attribute_map, $attribute_lookup) {
                    $first_key = !empty($card_keys) ? $card_keys[0]['key'] : '';
                    $all_card_values = [];
                    foreach ($card_keys as $key_data) {
                        $all_card_values = array_merge($all_card_values, $key_data['values'] ?? []);
                    }
                    $all_card_values = array_unique($all_card_values);
                    $preview_values = array_slice($all_card_values, 0, 4);
                    
                    $mapping = $attribute_map[$first_key] ?? null;
                    $mapped_label = $mapping['wc_attribute_label'] ?? '';
                    $mapped_id = $mapping['wc_attribute_id'] ?? '';
                    
                    // Get all keys for info modal
                    $keys_list = array_map(function($k) { return $k['key']; }, $card_keys);
                    $keys_json = esc_attr(wp_json_encode($keys_list));
                    $values_json = esc_attr(wp_json_encode(array_map(function($k) {
                        return ['key' => $k['key'], 'values' => $k['values'] ?? []];
                    }, $card_keys)));
                    ?>
                    <div class="awca-attribute-card <?php echo $is_created ? 'awca-attribute-card--created' : ''; ?> <?php echo $mapped_id ? 'awca-attribute-card--mapped' : ''; ?>" 
                         data-attribute-name="<?php echo esc_attr($anar_name); ?>" 
                         data-attribute-key="<?php echo esc_attr($first_key); ?>"
                         data-attribute-keys="<?php echo $keys_json; ?>"
                         data-attribute-values="<?php echo $values_json; ?>">
                        <div class="awca-attribute-card__header">
                            <button class="awca-attribute-card__info" 
                                    data-attribute-name="<?php echo esc_attr($anar_name); ?>"
                                    data-attribute-keys="<?php echo $keys_json; ?>"
                                    data-attribute-values="<?php echo $values_json; ?>"
                                    aria-label="<?php esc_attr_e('اطلاعات بیشتر', 'wp-anar'); ?>">
                                <span class="dashicons dashicons-info"></span>
                            </button>
                            <h3><?php echo esc_html($anar_name); ?></h3>
                            <p class="awca-attribute-card__keys" style="display: none;">
                                <small><?php echo esc_html(sprintf(__('(%d کلید: %s)', 'wp-anar'), count($card_keys), implode(', ', array_slice($keys_list, 0, 3)) . (count($keys_list) > 3 ? '...' : ''))); ?></small>
                            </p>

                            <?php if (!empty($preview_values)): ?>
                                <p class="awca-attribute-card__values">
                                    <?php echo esc_html(implode('، ', $preview_values)); ?>
                                    <?php if (count($all_card_values) > 4): ?>
                                        <span class="awca-attribute-card__more">+<?php echo count($all_card_values) - 4; ?></span>
                                    <?php endif; ?>
                                </p>
                            <?php endif; ?>
                            <?php if ($is_created && $wc_attr): ?>
                                <span class="awca-attribute-card__badge awca-attribute-card__badge--created">ساخته شده</span>
                            <?php endif; ?>
                        </div>
                        <div class="awca-attribute-card__actions" data-selected-id="<?php echo esc_attr($mapped_id); ?>" data-selected-label="<?php echo esc_attr($mapped_label); ?>">
                            <?php if ($is_created && $wc_attr): ?>
                                <button class="button button-secondary awca-map-attribute" disabled style="display: none;">
                                    <?php esc_html_e('معادل‌سازی', 'wp-anar'); ?>
                                </button>
                                <span class="awca-attribute-card__mapping" style="display: none;">
                                    <?php echo esc_html(sprintf(__('ویژگی ووکامرس: %s', 'wp-anar'), $wc_attr['label'] ?? '')); ?>
                                </span>
                            <?php else: ?>
                                <button class="button button-secondary awca-map-attribute" data-anar-key="<?php echo esc_attr($first_key); ?>" data-anar-name="<?php echo esc_attr($anar_name); ?>">
                                    <?php esc_html_e('معادل‌سازی', 'wp-anar'); ?>
                                </button>
                                <span class="awca-attribute-card__mapping <?php echo $mapped_label ? '' : 'awca-attribute-card__mapping--pending'; ?>">
                                    <?php
                                    echo $mapped_label
                                        ? esc_html(sprintf(__('معادل شده با: %s', 'wp-anar'), $mapped_label))
                                        : esc_html__('هنوز معادل‌سازی نشده است', 'wp-anar');
                                    ?>
                                </span>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php
                };
                
                // Render created attributes card if any exist
                if (!empty($created_keys)) {
                    // Use the first created key's WC attribute for display
                    $wc_attr = $created_keys[0]['wc_attr'];
                    $render_card($created_keys, true, $wc_attr);
                }
                
                // Render new attributes card if any exist
                if (!empty($new_keys)) {
                    $render_card($new_keys, false);
                }
                ?>
            <?php endforeach; ?>
        </div>

        <div class="awca-fetch-actions">
            <a class="button button-primary button-large" href="<?php echo esc_url($next_step_url); ?>">
                ادامه به ساخت محصولات
            </a>
        </div>
    <?php endif; ?>
</div>

<div class="awca-import-modal" id="awca-attribute-map-modal" aria-hidden="true">
    <div class="awca-import-modal__overlay" data-modal-close></div>
    <div class="awca-import-modal__content" role="dialog" aria-modal="true" aria-labelledby="awca-attribute-modal-title">
        <button class="awca-import-modal__close" data-modal-close>&times;</button>
        <h3 id="awca-attribute-modal-title"><?php esc_html_e('انتخاب ویژگی ووکامرس', 'wp-anar'); ?></h3>

        <form id="awca-attribute-map-form">
            <p id="awca-attribute-modal-description"></p>
            <label for="awca-attribute-select"><?php esc_html_e('یک ویژگی از ووکامرس انتخاب کنید', 'wp-anar'); ?></label>
            <select id="awca-attribute-select" style="width: 100%;" data-placeholder="<?php esc_attr_e('ویژگی مورد نظر را جستجو کنید', 'wp-anar'); ?>"></select>

            <div class="awca-modal-actions">
                <button type="button" class="button button-secondary" data-modal-close><?php esc_html_e('انصراف', 'wp-anar'); ?></button>
                <button type="button" class="button button-link-delete" id="awca-attribute-map-remove"><?php esc_html_e('حذف معادل‌سازی', 'wp-anar'); ?></button>
                <button type="submit" class="button button-primary"><?php esc_html_e('ذخیره معادل‌سازی', 'wp-anar'); ?></button>
            </div>

            <input type="hidden" name="anar_attribute_key" id="awca-modal-attribute-key" />
            <input type="hidden" name="anar_attribute_name" id="awca-modal-attribute-name" />
        </form>

        <div class="awca-modal-feedback" id="awca-attribute-modal-feedback"></div>
    </div>
</div>

<div class="awca-import-modal" id="awca-attribute-info-modal" aria-hidden="true">
    <div class="awca-import-modal__overlay" data-modal-close></div>
    <div class="awca-import-modal__content" role="dialog" aria-modal="true" aria-labelledby="awca-attribute-info-modal-title">
        <button class="awca-import-modal__close" data-modal-close>&times;</button>
        <h3 id="awca-attribute-info-modal-title"><?php esc_html_e('اطلاعات ویژگی', 'wp-anar'); ?></h3>

        <div class="awca-attribute-info">
            <div class="awca-attribute-info__name">
                <strong><?php esc_html_e('نام ویژگی:', 'wp-anar'); ?></strong>
                <span id="awca-attribute-info-name"></span>
            </div>
            
            <div class="awca-attribute-info__keys">
                <strong><?php esc_html_e('کلیدها و مقادیر:', 'wp-anar'); ?></strong>
                <div id="awca-attribute-info-keys-list"></div>
            </div>
            
            <div class="awca-attribute-info__mapping">
                <strong><?php esc_html_e('معادل‌سازی:', 'wp-anar'); ?></strong>
                <span id="awca-attribute-info-mapping"></span>
            </div>
        </div>

        <div class="awca-modal-actions">
            <button type="button" class="button button-secondary" data-modal-close><?php esc_html_e('بستن', 'wp-anar'); ?></button>
        </div>
    </div>
</div>


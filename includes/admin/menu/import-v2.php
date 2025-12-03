<?php

if (!defined('ABSPATH')) {
    exit;
}

$steps = [
    'intro'           => 'شروع',
    'fetch-data'      => 'دریافت داده‌ها',
    'categories'      => 'معادل‌سازی دسته‌بندی‌ها',
    'attributes'      => 'معادل‌سازی ویژگی‌ها',
    'create-products' => 'ساخت محصولات',
];

$current_step = sanitize_text_field($_GET['step'] ?? 'intro');

if (!array_key_exists($current_step, $steps)) {
    $current_step = 'intro';
}

$step_keys = array_keys($steps);
$current_index = array_search($current_step, $step_keys, true);

$step_view = ANAR_PLUGIN_PATH . 'includes/admin/menu/wizard-v2/' . $current_step . '.php';

?>
<div class="wrap awca-wrap awca-import-v2">
    <div class="awca-import-v2__header">
        <h1>درون‌ریزی محصولات - نسخه ۲</h1>
        <div class="awca-import-v2__actions">
            <button class="button" id="awca-open-single-product-modal">
                ساخت محصول تکی
            </button>
        </div>
    </div>

    <div class="awca-stepper">
        <?php foreach ($steps as $slug => $label): ?>
            <?php
            $index = array_search($slug, $step_keys, true);
            $status_class = $index < $current_index ? 'completed' : ($index === $current_index ? 'current' : '');
            ?>
            <div class="awca-stepper__item <?php echo esc_attr($status_class); ?>">
                <div class="awca-stepper__indicator"><?php echo esc_html($index + 1); ?></div>
                <div class="awca-stepper__label"><?php echo esc_html($label); ?></div>
            </div>
        <?php endforeach; ?>
    </div>

    <div class="awca-step-content">
        <?php
        if (file_exists($step_view)) {
            include $step_view;
        } else {
            echo '<p>این مرحله هنوز در دسترس نیست.</p>';
        }
        ?>
    </div>
</div>

<div class="awca-import-modal" id="awca-single-product-modal" aria-hidden="true">
    <div class="awca-import-modal__overlay" data-modal-close></div>
    <div class="awca-import-modal__content" role="dialog" aria-modal="true" aria-labelledby="awca-single-product-modal-title">
        <button class="awca-import-modal__close" data-modal-close>&times;</button>

        <h3 id="awca-single-product-modal-title">ساخت محصول تکی</h3>
        <p>شناسه SKU انار مورد نظر را وارد کنید تا محصول مستقیماً ساخته شود.</p>

        <form id="awca-single-product-form">
            <label for="awca-single-product-sku">شناسه SKU انار</label>
            <input type="text" id="awca-single-product-sku" name="anar_sku" class="regular-text" placeholder="مثال: 667558ab7f5816f7f58f3fb5" required />

            <div class="awca-modal-actions">
                <button type="button" class="button button-secondary" data-modal-close>انصراف</button>
                <button type="submit" class="button button-primary">ساخت محصول</button>
            </div>
        </form>

        <div class="awca-modal-feedback" id="awca-single-product-feedback"></div>
        
        <div class="awca-single-product-logs" id="awca-single-product-logs" style="display: none;">
            <h4>گزارش ساخت محصول:</h4>
            <div class="awca-single-product-logs__container" id="awca-single-product-logs-container"></div>
        </div>
    </div>
</div>



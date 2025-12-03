<?php

if (!defined('ABSPATH')) {
    exit;
}

$next_step_url = add_query_arg(
    [
        'page' => 'wp-anar-import-v2',
        'step' => 'categories',
    ],
    admin_url('admin.php')
);

?>
<div class="awca-card awca-card--progress" id="awca-import-fetch-data" data-next-step="<?php echo esc_url($next_step_url); ?>">
    <h2>دریافت داده‌های مورد نیاز</h2>
    <p>
        در این مرحله اطلاعات دسته‌بندی‌ها، ویژگی‌ها و فهرست کامل محصولات از سرور انار دریافت و برای مراحل بعدی آماده می‌شود.
        لطفاً تا تکمیل همه مراحل این صفحه را ترک نکنید.
    </p>

    <div class="awca-fetch-progress">
        <div class="awca-fetch-item" data-entity="categories">
            <div class="awca-fetch-item__header">
                <span>دسته‌بندی‌ها</span>
                <span class="awca-fetch-item__status">در انتظار</span>
            </div>
            <div class="awca-progress-bar">
                <div class="awca-progress-bar__inner" style="width:0%;"></div>
            </div>
        </div>

        <div class="awca-fetch-item" data-entity="attributes">
            <div class="awca-fetch-item__header">
                <span>ویژگی‌ها</span>
                <span class="awca-fetch-item__status">در انتظار</span>
            </div>
            <div class="awca-progress-bar">
                <div class="awca-progress-bar__inner" style="width:0%;"></div>
            </div>
        </div>

        <div class="awca-fetch-item" data-entity="products">
            <div class="awca-fetch-item__header">
                <span>محصولات</span>
                <span class="awca-fetch-item__status">در انتظار</span>
            </div>
            <div class="awca-progress-bar">
                <div class="awca-progress-bar__inner" style="width:0%;"></div>
            </div>
        </div>
    </div>

    <div class="awca-fetch-actions">
        <button class="button button-primary button-large" id="awca-import-fetch-start">
            آغاز دریافت داده‌ها
        </button>
        <a class="button button-primary button-large" id="awca-import-fetch-next" href="<?php echo esc_url($next_step_url); ?>" disabled>
            ادامه به معادل‌سازی دسته‌بندی
        </a>
    </div>

    <div class="awca-fetch-log">
        <h3>گزارش لحظه‌ای</h3>
        <div class="awca-fetch-log__entries" id="awca-import-fetch-log"></div>
    </div>
</div>


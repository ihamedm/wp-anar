<?php

if (!defined('ABSPATH')) {
    exit;
}

?>

<div class="awca-card awca-card--creation" id="awca-import-create-products">
    <h2>ساخت محصولات ووکامرس</h2>
    <p>
        در این مرحله محصولات به‌صورت خودکار و در پس‌زمینه ساخته یا به‌روزرسانی می‌شوند.
        می‌توانید از صفحه خارج شوید؛ فرآیند به کار خود ادامه می‌دهد و در هر زمان می‌توانید وضعیت را بررسی کنید.
    </p>

    <div class="awca-creation-stats">
        <div class="awca-creation-stat">
            <span>کل محصولات</span>
            <strong data-stat="total">0</strong>
        </div>
        <div class="awca-creation-stat">
            <span>پردازش شده</span>
            <strong data-stat="processed">0</strong>
        </div>
        <div class="awca-creation-stat">
            <span>ساخته شد</span>
            <strong data-stat="created">0</strong>
        </div>
        <div class="awca-creation-stat">
            <span>به‌روزرسانی شد</span>
            <strong data-stat="skipped">0</strong>
        </div>
        <div class="awca-creation-stat">
            <span>با خطا</span>
            <strong data-stat="failed">0</strong>
        </div>
        <div class="awca-creation-stat">
            <span>زمان باقی‌مانده(دقیقه)</span>
            <strong data-stat="estimated_minutes">-</strong>
        </div>
    </div>

    <div class="awca-progress-bar awca-progress-bar--large">
        <div class="awca-progress-bar__inner" data-stat="progress-bar" style="width:0%;"></div>
    </div>

    <div class="awca-creation-actions">
        <button class="button button-primary button-large" id="awca-start-creation">
            شروع ساخت محصولات
        </button>
        <button class="button" id="awca-refresh-creation" style="display: none">
            بروزرسانی وضعیت
        </button>
        <button class="button" id="awca-trigger-batch" style="display:none;">
            پردازش دستی بسته
        </button>
        <button class="button button-secondary" id="awca-cancel-creation" disabled>
            توقف فرآیند
        </button>
    </div>

    <div class="awca-fetch-log">
        <h3>گزارش ساخت</h3>
        <div class="awca-fetch-log__entries" id="awca-creation-log"></div>
    </div>
</div>

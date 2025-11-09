<br class="clear">
<div class="anar-tool">
    <div class="access-menu">
        <span class="access-menu-toggle"><?php echo get_anar_icon('dots-vertical', 24);  ?></span>
        <ul>
            <li>
                <a href="<?php echo esc_url(admin_url('edit.php?post_type=product&anar_deprecated=true'));?>" target="_blank">محصولات منسوخ شده</a>
                <small>محصولات منسوخ شده - حق فروش در انار حذف شده</small>
            </li>

            <li id="anar-zero-profit-products">
                <span style="cursor: pointer">محصولات با سود صفر</span>
                <small>محصولاتی که سود فروشنده صفر است</small>
            </li>
            <li id="anar-deprecated-products">
                <span style="cursor: pointer">محصولات منسوخ شده</span>
                <small>محصولاتی که حق فروش آنها حذف شده است</small>
            </li>
            <li id="anar-duplicate-products">
                <span style="cursor: pointer">محصولات تکراری</span>
                <small>محصولاتی که SKU یکسان دارند</small>
            </li>
        </ul>
    </div>
</div>

<h2>وضعیت سیستم</h2>

<p class="anar-alert anar-alert-warning">این بخش فقط برای پشتیبانی فنی می باشد، لطفا فقط در صورت اعلام و دریافت راهنمایی از پشتیبان فنی استفاده کنید.</p>

<?php
// Initialize widget manager
use Anar\Admin\Widgets\WidgetManager;
$widget_manager = WidgetManager::get_instance();
$widget_manager->render_widgets_grid(); ?>

<div class="system-report-buttons">
    <button class="button button-primary" id="anar-download-system-report" disabled>
        <span class="dashicons dashicons-download"></span>
        دانلود فایل گزارش
    </button>
    
    <button class="button button-secondary" id="anar-create-indexes">
        <span class="dashicons dashicons-performance"></span>
        ساخت ایندکس
    </button>

    <button class="button button-secondary" id="anar-clear-sync-times">
        <span class="dashicons dashicons-performance"></span>
        ریست زمان‌ آپدیت محصولات
    </button>
    
    <button class="button button-secondary" id="anar-test-performance">
        <span class="dashicons dashicons-chart-line"></span>
        تست عملکرد
    </button>
    
    <button class="button button-secondary" id="anar-check-index-status">
        <span class="dashicons dashicons-list-view"></span>
        بررسی وضعیت ایندکس‌ها
    </button>
    
    <button class="button button-secondary" id="anar-manual-sync-outdated">
        <span class="dashicons dashicons-update"></span>
         Manually OutdatedSync
    </button>
</div>

<div id="anar-performance-status" style="margin-top: 20px; padding: 15px; background: #f8f9fa; border: 1px solid #dee2e6; border-radius: 4px; display: none;">
    <h3 style="margin-top: 0;">وضعیت بهینه‌سازی</h3>
    <div id="anar-performance-message"></div>
</div>

<div id="anar-system-reports-table" class="anar-system-reports-table"></div>

<textarea
        id="anar-system-reports"
        rows="20"
        data-action="anar_get_system_reports"
        readonly
        style="width: 100%; direction: ltr; text-align: left; font-family: monospace; resize: vertical; display: none; margin-top: 10px;"
>در حال دریافت اطلاعات...</textarea>

<?php
/**
 * Generic modal template for reports
 */
function render_report_modal($config) {
    $modal_id = $config['modal_id'];
    $title = $config['title'];
    $has_change_status = isset($config['change_status_action']) && $config['change_status_action'];
    $change_status_text = $config['change_status_text'] ?? 'تغییر وضعیت';
    $warning_text = $config['warning_text'] ?? '';
    $modal_size = $config['modal_size'] ?? 'normal';
    $show_total = isset($config['show_total']) && $config['show_total'];
    ?>
    <div class="modal micromodal-slide" id="<?php echo esc_attr($modal_id); ?>" aria-hidden="true">
        <div class="modal__overlay" tabindex="-1" data-micromodal-close>
            <div class="modal__container <?php echo $modal_size === 'large' ? 'modal__container--large' : ''; ?>" role="dialog" aria-modal="true" aria-labelledby="<?php echo esc_attr($modal_id); ?>-title">
                <header class="modal__header">
                    <h2 class="modal__title" id="<?php echo esc_attr($modal_id); ?>-title"><?php echo esc_html($title); ?></h2>
                    <div class="modal__header-actions">
                        <?php if ($has_change_status): ?>
                            <button class="button button-primary" id="<?php echo esc_attr($modal_id); ?>-change-status" style="margin-left: 10px; display: none;">
                                <span class="dashicons dashicons-update-alt" style="font-size: 16px; width: 16px; height: 16px;"></span>
                                <?php echo esc_html($change_status_text); ?>
                            </button>
                        <?php endif; ?>
                        <a class="modal__close" aria-label="Close modal" data-micromodal-close></a>
                    </div>
                </header>
                <main class="modal__content">
                    <div id="<?php echo esc_attr($modal_id); ?>-loading" style="text-align: center; padding: 20px;">
                        <span class="spinner is-active"></span>
                        <p>در حال بارگذاری...</p>
                    </div>
                    <div id="<?php echo esc_attr($modal_id); ?>-content" style="display: none;">
                        <p>
                            <strong>تعداد محصولات: <span id="<?php echo esc_attr($modal_id); ?>-count">0</span></strong>
                            <?php if ($show_total): ?>
                                | <strong>کل محصولات: <span id="<?php echo esc_attr($modal_id); ?>-total">0</span></strong>
                            <?php endif; ?>
                        </p>
                        <?php if ($warning_text): ?>
                            <div style="padding: 10px; background: #fff3cd; border: 1px solid #ffc107; border-radius: 4px; margin-bottom: 15px;">
                                <strong>⚠️ توجه:</strong> <?php echo esc_html($warning_text); ?>
                            </div>
                        <?php endif; ?>
                        <div id="<?php echo esc_attr($modal_id); ?>-list">
                            <!-- Products will be loaded here via AJAX -->
                        </div>
                    </div>
                </main>
            </div>
        </div>
    </div>
    <?php
}

// Define modal configurations
$modal_configs = [
    [
        'modal_id' => 'anar-zero-profit-modal',
        'title' => 'محصولات با سود صفر',
        'change_status_action' => null,
        'change_status_text' => null,
        'warning_text' => '',
        'modal_size' => 'normal',
        'show_total' => false
    ],
    [
        'modal_id' => 'anar-deprecated-modal',
        'title' => 'محصولات منسوخ شده',
        'change_status_action' => 'anar_change_deprecated_status',
        'change_status_text' => 'تغییر وضعیت به "در انتظار بررسی"',
        'warning_text' => '',
        'modal_size' => 'normal',
        'show_total' => false
    ],
    [
        'modal_id' => 'anar-duplicate-modal',
        'title' => 'محصولات تکراری',
        'change_status_action' => 'anar_change_duplicate_status',
        'change_status_text' => 'تغییر وضعیت تکراری‌ها به "در انتظار بررسی"',
        'warning_text' => 'قدیمی‌ترین محصول (با کمترین ID) از هر گروه حفظ می‌شود و بقیه به "در انتظار بررسی" تغییر وضعیت می‌یابند.',
        'modal_size' => 'large',
        'show_total' => true
    ]
];

// Render all modals
foreach ($modal_configs as $config) {
    render_report_modal($config);
}
?>

<style>
    .system-report-buttons {
        display: flex;
        gap: 10px;
        align-items: center;
    }

    .system-report-buttons .button {
        display: flex;
        align-items: center;
        gap: 5px;
    }

    .system-report-buttons .dashicons {
        font-size: 18px;
        width: 18px;
        height: 18px;
        margin-top: 0;
    }

    #anar-system-reports {
        background: #f8f9fa;
        border: 1px solid #dee2e6;
        padding: 15px;
        border-radius: 4px;
        box-shadow: inset 0 1px 2px rgba(0,0,0,.05);
    }

    .anar-system-reports-table {
        margin-top: 20px;
    }
    
    .anar-report-group {
        margin-bottom: 30px;
        background: #fff;
        border: 1px solid #ccd0d4;
        box-shadow: 0 1px 1px rgba(0,0,0,.04);
    }
    
    .anar-report-group h3 {
        margin: 0;
        padding: 12px 15px;
        border-bottom: 1px solid #ccd0d4;
        background: #f8f9fa;
    }
    
    .anar-report-table {
        width: 100%;
        border-collapse: collapse;
    }
    
    .anar-report-table tr {
        border-bottom: 1px solid #f0f0f1;
    }
    
    .anar-report-table tr:last-child {
        border-bottom: none;
    }
    
    .anar-report-table td {
        padding: 12px 15px;
    }
    
    .anar-report-table td:first-child {
        width: 25%;
        font-weight: 600;
    }
    
    .status-icon {
        margin-right: 5px;
    }
    
    .status-good {
        color: #46b450;
    }
    
    .status-warning {
        color: #ffb900;
    }
    
    .status-critical {
        color: #dc3232;
    }
    
    .report-link {
        color: #0073aa;
        text-decoration: none;
        display: inline-flex;
        align-items: center;
    }
    
    .report-link .dashicons {
        font-size: 14px;
        width: 14px;
        height: 14px;
        margin-left: 4px;
    }
    
    /* Zero Profit Products Modal Styles */
    .modal__header-actions {
        display: flex;
        align-items: center;
        gap: 10px;
    }
    
    .modal__content {
        max-height: 500px;
        overflow-y: auto;
    }
    
    .modal__container--large {
        max-width: 900px !important;
    }
    
    .modal__close {
        background: none;
        border: none;
        font-size: 20px;
        cursor: pointer;
        padding: 5px;
        color: #666;
    }
    
    .modal__close:hover {
        color: #000;
    }
    
    .anar-product-item {
        padding: 10px;
        border: 1px solid #e0e0e0;
        margin-bottom: 10px;
        border-radius: 4px;
        background: #fff;
    }
    
    .anar-product-item:hover {
        background: #f8f9fa;
    }
    
    .anar-product-title {
        font-weight: bold;
        margin-bottom: 5px;
    }
    
    .anar-product-sku {
        color: #666;
        font-size: 12px;
        margin-bottom: 5px;
    }
    
    .anar-product-meta {
        display: flex;
        align-items: center;
        margin-bottom: 5px;
    }
    
    .anar-product-status {
        display: inline-block;
        font-weight: 600;
    }
    
    .anar-product-sync-time {
        color: #555;
        font-size: 11px;
        margin-bottom: 8px;
        padding: 5px 8px;
        background: #f8f9fa;
        border-radius: 3px;
        border-left: 3px solid #0073aa;
    }
    
    .anar-product-actions {
        margin-top: 8px;
    }
    
    .anar-product-actions a {
        margin-left: 10px;
        text-decoration: none;
        padding: 4px 8px;
        border-radius: 3px;
        font-size: 12px;
    }
    
    .anar-product-actions .edit-link {
        background: #0073aa;
        color: white;
    }
    
    .anar-product-actions .view-link {
        background: #46b450;
        color: white;
    }
    
    .anar-product-actions a:hover {
        opacity: 0.8;
    }

    /* Report Widgets Styles */
    .anar-widgets-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(350px, 1fr));
        gap: 20px;
        margin: 20px 0;
    }

    .anar-widget-grid-item {
        background: #fff;
        border: 1px solid #ccd0d4;
        border-radius: 4px;
        box-shadow: 0 1px 1px rgba(0,0,0,.04);
        overflow: hidden;
    }

    .anar-report-widget {
        padding: 0;
    }

    .anar-widget-header {
        display: flex;
        align-items: center;
        padding: 15px;
        background: #f8f9fa;
        border-bottom: 1px solid #ccd0d4;
    }

    .anar-widget-icon {
        margin-left: 10px;
        font-size: 24px;
        color: #0073aa;
    }

    .anar-widget-info {
        flex: 1;
    }

    .anar-widget-title {
        margin: 0 0 5px 0;
        font-size: 16px;
        font-weight: 600;
    }

    .anar-widget-description {
        margin: 0;
        color: #666;
        font-size: 13px;
        line-height: 1.4;
    }

    .anar-widget-content {
        padding: 15px;
        min-height: 100px;
    }

    .anar-widget-loading {
        text-align: center;
        padding: 20px;
        color: #666;
    }

    .anar-widget-loading .spinner {
        margin-left: 10px;
    }

    .anar-widget-results {
        max-height: 400px;
        overflow-y: auto;
    }

    .anar-widget-error {
        color: #dc3232;
    }

    .anar-widget-actions {
        padding: 15px;
        background: #f8f9fa;
        border-top: 1px solid #ccd0d4;
        text-align: center;
    }

    .anar-widget-actions .button {
        display: inline-flex;
        align-items: center;
        gap: 5px;
    }

    .anar-widget-section {
        margin-bottom: 20px;
        padding-bottom: 15px;
        border-bottom: 1px solid #f0f0f1;
    }

    .anar-widget-section:last-child {
        border-bottom: none;
        margin-bottom: 0;
    }

    .anar-widget-section h4 {
        margin: 0 0 10px 0;
        font-size: 14px;
        color: #0073aa;
    }

    .anar-info-grid {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 8px;
    }

    .anar-info-item {
        display: flex;
        justify-content: space-between;
        padding: 5px 0;
        border-bottom: 1px solid #f0f0f1;
    }

    .anar-info-item:last-child {
        border-bottom: none;
    }

    .anar-info-label {
        font-weight: 600;
        color: #555;
    }

    .anar-info-value {
        color: #333;
        font-family: monospace;
        font-size: 12px;
    }

    .anar-stats-grid {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 10px;
    }

    .anar-stat-item {
        display: flex;
        justify-content: space-between;
        padding: 8px 12px;
        background: #f8f9fa;
        border-radius: 4px;
        border-right: 3px solid #0073aa;
    }

    .anar-stat-label {
        font-weight: 600;
        color: #555;
    }

    .anar-stat-value {
        font-weight: bold;
        color: #0073aa;
        font-size: 16px;
    }

    .anar-table-health {
        max-height: 200px;
        overflow-y: auto;
    }

    .anar-table-item {
        padding: 8px 0;
        border-bottom: 1px solid #f0f0f1;
    }

    .anar-table-item:last-child {
        border-bottom: none;
    }

    .anar-table-name {
        font-weight: 600;
        color: #333;
        margin-bottom: 4px;
    }

    .anar-table-details {
        display: flex;
        gap: 15px;
        font-size: 12px;
        color: #666;
    }

    .anar-indexes-health {
        display: flex;
        flex-direction: column;
        gap: 5px;
    }

    .anar-index-item {
        display: flex;
        justify-content: space-between;
        padding: 5px 8px;
        background: #f8f9fa;
        border-radius: 3px;
    }

    .anar-index-name {
        font-family: monospace;
        font-size: 12px;
        color: #555;
    }

    .anar-error-summary {
        background: #fff3cd;
        border: 1px solid #ffc107;
        border-radius: 4px;
        padding: 10px;
        margin-bottom: 15px;
    }

    .anar-error-total {
        font-weight: bold;
        margin-bottom: 8px;
        color: #856404;
    }

    .anar-error-level {
        display: inline-block;
        margin-left: 10px;
        padding: 2px 6px;
        border-radius: 3px;
        font-size: 11px;
        font-weight: 600;
    }

    .anar-error-logs {
        max-height: 300px;
        overflow-y: auto;
    }

    .anar-error-log-item {
        padding: 8px;
        margin-bottom: 8px;
        background: #f8f9fa;
        border-radius: 4px;
        border-right: 3px solid #dc3232;
    }

    .anar-error-log-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 5px;
    }

    .anar-error-level {
        padding: 2px 6px;
        border-radius: 3px;
        font-size: 11px;
        font-weight: 600;
    }

    .anar-error-time {
        font-size: 11px;
        color: #666;
    }

    .anar-error-message {
        font-size: 12px;
        color: #333;
        line-height: 1.4;
    }

    .status-good {
        color: #46b450;
    }

    .status-warning {
        color: #ffb900;
    }

    .status-critical {
        color: #dc3232;
    }
</style>

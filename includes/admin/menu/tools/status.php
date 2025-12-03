<br class="clear">
<div class="anar-tool">
    <div class="access-menu">
        <span class="access-menu-toggle"><?php echo get_anar_icon('dots-vertical', 24);  ?></span>
        <ul>
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
            <li id="anar-need-fix-products">
                <span style="cursor: pointer">محصولات نیازمند تعمیر</span>
                <small>محصولاتی که نیاز به بازسازی دارند</small>
            </li>
        </ul>
    </div>
</div>

<?php
// Show success notice if cron was run
if (isset($_GET['anar_cron_run']) && $_GET['anar_cron_run'] === 'success' && isset($_GET['anar_cron_hook'])) {
    $hook_name = esc_html($_GET['anar_cron_hook']);
    echo '<div class="notice notice-success is-dismissible"><p>';
    echo sprintf('کرون جاب <strong>%s</strong> با موفقیت اجرا شد.', $hook_name);
    echo '</p></div>';
}
?>

<h2>وضعیت سیستم</h2>

<p class="anar-alert anar-alert-warning">این بخش فقط برای پشتیبانی فنی می باشد، لطفا فقط در صورت اعلام و دریافت راهنمایی از پشتیبان فنی استفاده کنید.</p>

<div class="system-report-buttons">

    
    <button class="button button-secondary" id="anar-create-indexes">
        <span class="dashicons dashicons-performance"></span>
        ساخت ایندکس
    </button>

    <button class="button button-secondary" id="anar-check-index-status">
        <span class="dashicons dashicons-list-view"></span>
        بررسی وضعیت ایندکس‌ها
    </button>

    <button class="button button-secondary" id="anar-test-performance">
        <span class="dashicons dashicons-chart-line"></span>
        تست عملکرد کوئری
    </button>

    <span>|</span>

    <button class="button button-secondary" id="anar-clear-sync-times">
        <span class="dashicons dashicons-performance"></span>
        ریست زمان‌ آپدیت محصولات
    </button>

    <button class="button button-secondary" id="anar-manual-sync-outdated">
        <span class="dashicons dashicons-update"></span>
         Manually OutdatedSync
    </button>
    
    <button class="button button-primary" id="anar-system-diagnostics">
        <span class="dashicons dashicons-admin-tools"></span>
        all tests
    </button>

    <span>|</span>

    <button class="button button-primary" id="awca-open-single-product-modal-legacy">
        <span class="dashicons dashicons-plus-alt"></span>
        ساخت محصول تکی (Legacy)
    </button>
</div>

<div id="anar-performance-status" style="margin-top: 20px; padding: 15px; background: #f8f9fa; border: 1px solid #dee2e6; border-radius: 4px; display: none;">
    <h3 style="margin-top: 0;">وضعیت بهینه‌سازی</h3>
    <div id="anar-performance-message"></div>
</div>


<?php
// Initialize widget manager
use Anar\Admin\Widgets\WidgetManager;
$widget_manager = WidgetManager::get_instance();
$widget_manager->render_widgets_grid(); ?>



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
    ],
    [
        'modal_id' => 'anar-need-fix-modal',
        'title' => 'محصولات نیازمند تعمیر',
        'change_status_action' => 'anar_manual_fix_products',
        'change_status_text' => 'اجرای دستی تعمیر محصولات',
        'warning_text' => 'این محصولات دارای خطای همگام‌سازی هستند و نیاز به بازسازی دارند. می‌توانید با دکمه "اجرای دستی تعمیر محصولات" آنها را تعمیر کنید.',
        'modal_size' => 'normal',
        'show_total' => false
    ]
];

// Render all modals
foreach ($modal_configs as $config) {
    render_report_modal($config);
}

// Render log preview modal
?>
<div class="modal micromodal-slide" id="anar-log-preview-modal" aria-hidden="true">
    <div class="modal__overlay" tabindex="-1" data-micromodal-close>
        <div class="modal__container modal__container--log-preview" role="dialog" aria-modal="true" aria-labelledby="anar-log-preview-modal-title">
            <header class="modal__header">
                <h2 class="modal__title" id="anar-log-preview-modal-title">پیش‌نمایش فایل لاگ</h2>
                <div class="modal__header-actions">
                    <label class="anar-auto-refresh-label" title="به‌روزرسانی خودکار هر 5 ثانیه (tail -f)">
                        <input type="checkbox" id="anar-log-preview-auto-refresh" />
                        <span>به‌روزرسانی خودکار</span>
                    </label>
                    <button class="button button-small" id="anar-log-preview-refresh" title="به‌روزرسانی">
                        <span class="dashicons dashicons-update"></span>
                        به‌روزرسانی
                    </button>
                    <a class="modal__close" aria-label="Close modal" data-micromodal-close></a>
                </div>
            </header>
            <main class="modal__content">
                <div id="anar-log-preview-loading" style="text-align: center; padding: 20px;">
                    <span class="spinner is-active"></span>
                    <p>در حال بارگذاری...</p>
                </div>
                <div id="anar-log-preview-content" style="display: none; flex: 1; flex-direction: column; min-height: 0; overflow: hidden;">
                    <div class="anar-log-preview-info" style="margin-bottom: 15px; padding: 10px; background: #f8f9fa; border-radius: 4px; flex-shrink: 0;">
                        <p style="margin: 0;">
                            <strong>فایل:</strong> <span id="anar-log-preview-filename"></span> | 
                            <strong>اندازه:</strong> <span id="anar-log-preview-size"></span> | 
                            <strong>تعداد خطوط:</strong> <span id="anar-log-preview-lines"></span>
                            <span id="anar-log-preview-truncated" style="display: none; color: #ffb900; margin-right: 10px;">⚠️ فقط آخرین 10MB نمایش داده می‌شود</span>
                        </p>
                    </div>
                    <div id="anar-log-preview-text" style="background: #1e1e1e; color: #d4d4d4; padding: 15px; border-radius: 4px; font-family: 'Courier New', monospace; font-size: 13px; line-height: 1.5; overflow: auto; white-space: pre-wrap; word-wrap: break-word; direction: ltr; text-align: left; flex: 1; min-height: 0;">
                        <!-- Log content will be loaded here -->
                    </div>
                </div>
                <div id="anar-log-preview-error" style="display: none; padding: 15px; background: #fff3cd; border: 1px solid #ffc107; border-radius: 4px; color: #856404;">
                    <!-- Error messages will be shown here -->
                </div>
            </main>
        </div>
    </div>
</div>

<!-- System Diagnostics Modal -->
<div class="modal micromodal-slide" id="anar-system-diagnostics-modal" aria-hidden="true">
    <div class="modal__overlay" tabindex="-1" data-micromodal-close>
        <div class="modal__container modal__container--large" role="dialog" aria-modal="true" aria-labelledby="anar-system-diagnostics-modal-title">
            <header class="modal__header">
                <h2 class="modal__title" id="anar-system-diagnostics-modal-title">تست سیستم</h2>
                <a class="modal__close" aria-label="Close modal" data-micromodal-close></a>
            </header>
            <main class="modal__content">
                <div id="anar-diagnostics-content">
                    <div id="anar-diagnostics-summary" style="margin-bottom: 20px; padding: 15px; background: #f8f9fa; border-radius: 4px; display: none;">
                        <h3 style="margin-top: 0;">خلاصه نتایج</h3>
                        <div id="anar-diagnostics-summary-content"></div>
                    </div>
                    <div id="anar-diagnostics-tests-list">
                        <!-- Test items will be added here -->
                    </div>
                    <div id="anar-diagnostics-loading" style="text-align: center; padding: 20px; display: none;">
                        <span class="spinner is-active" style="float: none; margin: 0 auto;"></span>
                        <p>در حال اجرای تست‌ها...</p>
                    </div>
                    <div id="anar-diagnostics-error" style="display: none; padding: 15px; background: #fff3cd; border: 1px solid #ffc107; border-radius: 4px; color: #856404;">
                        <!-- Error messages will be shown here -->
                    </div>
                </div>
            </main>
            <footer class="modal__footer">
                <button class="button button-secondary" id="anar-diagnostics-close" data-micromodal-close>بستن</button>
                <button class="button button-primary" id="anar-diagnostics-rerun" style="display: none;">اجرای مجدد</button>
            </footer>
        </div>
    </div>
</div>

<?php
?>

<!-- Legacy Single Product Creation Modal -->
<div class="awca-import-modal" id="awca-single-product-modal-legacy" aria-hidden="true">
    <div class="awca-import-modal__overlay" data-modal-close></div>
    <div class="awca-import-modal__content" role="dialog" aria-modal="true" aria-labelledby="awca-single-product-modal-legacy-title">
        <button class="awca-import-modal__close" data-modal-close>&times;</button>

        <h3 id="awca-single-product-modal-legacy-title">ساخت محصول تکی (Legacy)</h3>
        <p>شناسه SKU انار مورد نظر را وارد کنید تا محصول مستقیماً با سیستم قدیمی ساخته شود.</p>

        <form id="awca-single-product-form-legacy">
            <label for="awca-single-product-sku-legacy">شناسه SKU انار</label>
            <input type="text" id="awca-single-product-sku-legacy" name="anar_sku" class="regular-text" placeholder="مثال: 667558ab7f5816f7f58f3fb5" required />

            <div class="awca-modal-actions">
                <button type="button" class="button button-secondary" data-modal-close>انصراف</button>
                <button type="submit" class="button button-primary">ساخت محصول</button>
            </div>
        </form>

        <div class="awca-modal-feedback" id="awca-single-product-feedback-legacy"></div>
        
        <div class="awca-single-product-logs" id="awca-single-product-logs-legacy" style="display: none;">
            <h4>گزارش ساخت محصول:</h4>
            <div class="awca-single-product-logs__container" id="awca-single-product-logs-container-legacy"></div>
        </div>
    </div>
</div>


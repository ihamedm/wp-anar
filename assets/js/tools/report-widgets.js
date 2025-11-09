import $ from 'jquery';
import { awca_toast } from "../functions";

/**
 * Report Widgets Manager
 */
export class ReportWidgetsManager {
    constructor() {
        this.widgets = {};
        this.init();
    }

    init() {
        $(document).ready(() => {
            this.bindEvents();
        });
    }

    bindEvents() {
        // Handle widget button clicks
        $(document).on('click', '.anar-widget-actions button[data-action]', (e) => {
            e.preventDefault();
            const $button = $(e.currentTarget);
            const action = $button.data('action');
            const widgetId = $button.data('widget');
            
            this.loadWidgetData(widgetId, action, $button);
        });
    }

    loadWidgetData(widgetId, action, $button) {
        const $widget = $(`#${widgetId}`);
        const $loading = $widget.find('.anar-widget-loading');
        const $results = $widget.find('.anar-widget-results');
        const $error = $widget.find('.anar-widget-error');
        const originalText = $button.html();

        console.log('Loading widget data:', { widgetId, action, nonce: awca_ajax_object.nonce });

        // Show loading state
        $loading.show();
        $results.hide();
        $error.hide();
        $button.prop('disabled', true).html('<span class="spinner is-active"></span> در حال بارگذاری...');

        // Make AJAX request
        $.ajax({
            url: awca_ajax_object.ajax_url,
            type: 'POST',
            data: {
                action: action,
                nonce: awca_ajax_object.nonce
            },
            success: (response) => {
                console.log('AJAX Success:', response);
                if (response.success) {
                    this.displayWidgetData($widget, response.data);
                    awca_toast('گزارش با موفقیت دریافت شد', 'success');
                } else {
                    this.displayWidgetError($widget, response.data.message || 'خطا در دریافت گزارش');
                    awca_toast(response.data.message || 'خطا در دریافت گزارش', 'error');
                }
            },
            error: (xhr, status, err) => {
                console.error('AJAX Error:', { xhr, status, err, responseText: xhr.responseText });
                this.displayWidgetError($widget, `خطا در ارتباط با سرور: ${xhr.status} - ${xhr.statusText}`);
                awca_toast('خطا در ارتباط با سرور', 'error');
            },
            complete: () => {
                $loading.hide();
                $button.prop('disabled', false).html(originalText);
            }
        });
    }

    displayWidgetData($widget, data) {
        const $results = $widget.find('.anar-widget-results');
        
        // Generate HTML based on widget type
        const html = this.generateWidgetHTML(data);
        $results.html(html).show();
    }

    displayWidgetError($widget, message) {
        const $error = $widget.find('.anar-widget-error');
        $error.html(`<div class="notice notice-error"><p>${message}</p></div>`).show();
    }

    generateWidgetHTML(data) {
        let html = '';

        // System Info Widget
        if (data.wp_info) {
            html += this.generateSystemInfoHTML(data);
        }

        // Database Health Widget
        if (data.tables) {
            html += this.generateDatabaseHealthHTML(data);
        }

        // Product Stats Widget
        if (data.total_products) {
            html += this.generateProductStatsHTML(data);
        }

        // Error Log Widget
        if (data.anar_logs) {
            html += this.generateErrorLogHTML(data);
        }

        return html || '<p>هیچ داده‌ای یافت نشد.</p>';
    }

    generateSystemInfoHTML(data) {
        let html = '<div class="anar-widget-section">';
        html += '<h4>اطلاعات وردپرس</h4>';
        html += '<div class="anar-info-grid">';
        
        Object.entries(data.wp_info).forEach(([key, value]) => {
            html += `<div class="anar-info-item">
                <span class="anar-info-label">${this.getLabel(key)}:</span>
                <span class="anar-info-value">${value}</span>
            </div>`;
        });
        
        html += '</div></div>';

        if (data.wc_info && Object.keys(data.wc_info).length > 0) {
            html += '<div class="anar-widget-section">';
            html += '<h4>اطلاعات ووکامرس</h4>';
            html += '<div class="anar-info-grid">';
            
            Object.entries(data.wc_info).forEach(([key, value]) => {
                html += `<div class="anar-info-item">
                    <span class="anar-info-label">${this.getLabel(key)}:</span>
                    <span class="anar-info-value">${value}</span>
                </div>`;
            });
            
            html += '</div></div>';
        }

        if (data.anar_info) {
            html += '<div class="anar-widget-section">';
            html += '<h4>اطلاعات انار</h4>';
            html += '<div class="anar-info-grid">';
            
            Object.entries(data.anar_info).forEach(([key, value]) => {
                html += `<div class="anar-info-item">
                    <span class="anar-info-label">${this.getLabel(key)}:</span>
                    <span class="anar-info-value">${value}</span>
                </div>`;
            });
            
            html += '</div></div>';
        }

        return html;
    }

    generateDatabaseHealthHTML(data) {
        let html = '<div class="anar-widget-section">';
        html += '<h4>وضعیت جداول</h4>';
        html += '<div class="anar-table-health">';
        
        Object.entries(data.tables).forEach(([key, table]) => {
            const statusClass = table.status === 'good' ? 'status-good' : 'status-warning';
            html += `<div class="anar-table-item">
                <div class="anar-table-name">${table.name}</div>
                <div class="anar-table-details">
                    <span>ردیف‌ها: ${table.rows}</span>
                    <span>اندازه داده: ${table.data_length}</span>
                    <span>موتور: ${table.engine}</span>
                    <span class="${statusClass}">${table.status === 'good' ? '✓' : '⚠'}</span>
                </div>
            </div>`;
        });
        
        html += '</div></div>';

        if (data.indexes) {
            html += '<div class="anar-widget-section">';
            html += '<h4>وضعیت ایندکس‌ها</h4>';
            html += '<div class="anar-indexes-health">';
            
            Object.entries(data.indexes).forEach(([key, index]) => {
                const statusClass = index.status === 'good' ? 'status-good' : 'status-warning';
                html += `<div class="anar-index-item">
                    <span class="anar-index-name">${index.name}</span>
                    <span class="${statusClass}">${index.exists ? '✓ موجود' : '✗ موجود نیست'}</span>
                </div>`;
            });
            
            html += '</div></div>';
        }

        return html;
    }

    generateProductStatsHTML(data) {
        let html = '<div class="anar-widget-section">';
        html += '<h4>آمار کلی محصولات</h4>';
        html += '<div class="anar-stats-grid">';
        
        Object.entries(data.total_products).forEach(([status, count]) => {
            html += `<div class="anar-stat-item">
                <span class="anar-stat-label">${this.getStatusLabel(status)}:</span>
                <span class="anar-stat-value">${count}</span>
            </div>`;
        });
        
        html += '</div></div>';

        if (data.anar_products) {
            html += '<div class="anar-widget-section">';
            html += '<h4>محصولات انار</h4>';
            html += '<div class="anar-stats-grid">';
            
            Object.entries(data.anar_products).forEach(([key, count]) => {
                html += `<div class="anar-stat-item">
                    <span class="anar-stat-label">${this.getLabel(key)}:</span>
                    <span class="anar-stat-value">${count}</span>
                </div>`;
            });
            
            html += '</div></div>';
        }

        if (data.sync_status) {
            html += '<div class="anar-widget-section">';
            html += '<h4>وضعیت همگام‌سازی</h4>';
            html += '<div class="anar-stats-grid">';
            
            Object.entries(data.sync_status).forEach(([key, count]) => {
                html += `<div class="anar-stat-item">
                    <span class="anar-stat-label">${this.getLabel(key)}:</span>
                    <span class="anar-stat-value">${count}</span>
                </div>`;
            });
            
            html += '</div></div>';
        }

        return html;
    }

    generateErrorLogHTML(data) {
        let html = '<div class="anar-widget-section">';
        
        if (data.summary) {
            html += '<h4>خلاصه خطاها (24 ساعت گذشته)</h4>';
            html += '<div class="anar-error-summary">';
            
            if (data.summary.total_errors > 0) {
                html += `<div class="anar-error-total">کل خطاها: <strong>${data.summary.total_errors}</strong></div>`;
                
                Object.entries(data.summary.last_24h).forEach(([level, count]) => {
                    const levelClass = level === 'critical' ? 'status-critical' : level === 'error' ? 'status-warning' : 'status-good';
                    html += `<div class="anar-error-level ${levelClass}">${this.getLevelLabel(level)}: ${count}</div>`;
                });
            } else {
                html += '<div class="status-good">هیچ خطایی در 24 ساعت گذشته ثبت نشده است</div>';
            }
            
            html += '</div>';
        }

        if (data.anar_logs && data.anar_logs.length > 0) {
            html += '<div class="anar-widget-section">';
            html += '<h4>آخرین خطاهای انار</h4>';
            html += '<div class="anar-error-logs">';
            
            data.anar_logs.slice(0, 10).forEach(log => {
                const levelClass = log.level === 'critical' ? 'status-critical' : 'status-warning';
                html += `<div class="anar-error-log-item">
                    <div class="anar-error-log-header">
                        <span class="anar-error-level ${levelClass}">${this.getLevelLabel(log.level)}</span>
                        <span class="anar-error-time">${log.created_at}</span>
                    </div>
                    <div class="anar-error-message">${log.message}</div>
                </div>`;
            });
            
            html += '</div></div>';
        }

        html += '</div>';
        return html;
    }

    getLabel(key) {
        const labels = {
            'version': 'نسخه',
            'multisite': 'چندسایتی',
            'language': 'زبان',
            'timezone': 'منطقه زمانی',
            'memory_limit': 'حد حافظه',
            'max_execution_time': 'حد زمان اجرا',
            'currency': 'واحد پول',
            'products_count': 'تعداد محصولات',
            'orders_count': 'تعداد سفارشات',
            'php_version': 'نسخه PHP',
            'mysql_version': 'نسخه MySQL',
            'server_software': 'نرم‌افزار سرور',
            'upload_max_filesize': 'حد آپلود فایل',
            'post_max_size': 'حد اندازه پست',
            'plugin_version': 'نسخه افزونه',
            'anar_products': 'محصولات انار',
            'last_sync': 'آخرین همگام‌سازی',
            'total': 'کل',
            'with_prices': 'با قیمت',
            'zero_profit': 'سود صفر',
            'deprecated': 'منسوخ شده',
            'recently_synced': 'همگام‌سازی شده',
            'not_synced_recently': 'همگام‌سازی نشده',
            'never_synced': 'هرگز همگام‌سازی نشده',
            'created_today': 'ایجاد شده امروز',
            'updated_today': 'به‌روزرسانی شده امروز'
        };
        
        return labels[key] || key;
    }

    getStatusLabel(status) {
        const labels = {
            'published': 'منتشر شده',
            'draft': 'پیش‌نویس',
            'pending': 'در انتظار بررسی',
            'private': 'خصوصی',
            'trash': 'سطل زباله'
        };
        
        return labels[status] || status;
    }

    getLevelLabel(level) {
        const labels = {
            'critical': 'بحرانی',
            'error': 'خطا',
            'warning': 'هشدار',
            'info': 'اطلاعات'
        };
        
        return labels[level] || level;
    }
}

/**
 * Initialize report widgets
 */
export function initReportWidgets() {
    new ReportWidgetsManager();
}

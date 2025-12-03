import $ from 'jquery';
import { awca_toast } from "../functions";
import { generateSystemInfoHTML } from './widgets/system-info-widget';
import { generateDatabaseHealthHTML } from './widgets/database-health-widget';
import { generateProductStatsHTML } from './widgets/product-stats-widget';
import { generateErrorLogHTML } from './widgets/error-log-widget';
import { generateBenchmarkHTML } from './widgets/benchmark-widget';
import { generateApiHealthHTML } from './widgets/api-health-widget';
import { generateCronHealthHTML } from './widgets/cron-health-widget';
import { generateCrontrolHTML } from './widgets/crontrol-widget';

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
            html += generateSystemInfoHTML(data);
        }

        // Database Health Widget
        if (data.tables) {
            html += generateDatabaseHealthHTML(data);
        }

        // Product Stats Widget
        if (data.total_products) {
            html += generateProductStatsHTML(data);
        }

        // Error Log Widget
        if (data.anar_logs || data.log_files) {
            html += generateErrorLogHTML(data);
        }

        // Benchmark Widget
        if (data.overall_score !== undefined) {
            html += generateBenchmarkHTML(data);
        }

        // API Health Widget
        if (data.endpoints !== undefined || data.health_score !== undefined) {
            html += generateApiHealthHTML(data);
        }

        // Cron Health Widget
        if (data.wp_cron_disabled !== undefined || data.important_jobs !== undefined) {
            html += generateCronHealthHTML(data);
        }

        // Crontrol Widget (has cron_jobs array but no strategies/product_meta_stats)
        if (data.cron_jobs !== undefined && Array.isArray(data.cron_jobs) && data.strategies === undefined && data.product_meta_stats === undefined) {
            html += generateCrontrolHTML(data);
        }

        return html || '<p>هیچ داده‌ای یافت نشد.</p>';
    }
}

/**
 * Initialize report widgets
 */
export function initReportWidgets() {
    new ReportWidgetsManager();
}

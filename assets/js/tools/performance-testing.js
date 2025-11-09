import {awca_toast} from "../functions";

/**
 * Handle performance testing and index status checking
 */
export function initPerformanceTesting() {
    jQuery(document).ready(function($) {
        // Test query performance
        $('#anar-test-performance').on('click', function(e) {
            e.preventDefault();

            const button = $(this);
            const originalText = button.html();

            // Disable button and show loading
            button.prop('disabled', true).html('<span class="dashicons dashicons-update"></span> در حال تست...');

            // Show status div
            $('#anar-performance-status').show();
            $('#anar-performance-message').html('<span class="dashicons dashicons-update" style="animation: spin 1s linear infinite;"></span> در حال تست عملکرد کوئری‌ها...');

            $.ajax({
                url: awca_ajax_object.ajax_url,
                type: 'POST',
                data: {
                    action: 'anar_test_query_performance'
                },
                success: function(response) {
                    if (response.success) {
                        const data = response.data;
                        const message = `
                        <div style="margin-bottom: 15px;">
                          <h4 style="margin: 0 0 10px 0;">نتایج تست عملکرد:</h4>
                          <table style="width: 100%; border-collapse: collapse;">
                            <tr style="border-bottom: 1px solid #ddd;">
                              <td style="padding: 5px;"><strong>روش قدیمی:</strong></td>
                              <td style="padding: 5px;">${data.old_approach.time}ms (${data.old_approach.count} محصول)</td>
                            </tr>
                            <tr style="border-bottom: 1px solid #ddd;">
                              <td style="padding: 5px;"><strong>روش جدید:</strong></td>
                              <td style="padding: 5px;">${data.new_approach.time}ms (${data.new_approach.count} محصول)</td>
                            </tr>
                            <tr>
                              <td style="padding: 5px;"><strong>بهبود:</strong></td>
                              <td style="padding: 5px; color: #46b450; font-weight: bold;">${data.improvement}% سریع‌تر</td>
                            </tr>
                          </table>
                        </div>
                      `;
                        $('#anar-performance-message').html('<span class="dashicons dashicons-chart-line" style="color: #46b450;"></span>' + message);
                        awca_toast('تست عملکرد با موفقیت انجام شد', 'success');
                    } else {
                        $('#anar-performance-message').html('<span class="dashicons dashicons-warning" style="color: #dc3232;"></span> ' + response.data.message);
                        awca_toast(response.data.message, 'error');
                    }
                },
                error: function(xhr, status, err) {
                    const errorMessage = 'خطا در ارتباط با سرور';
                    $('#anar-performance-message').html('<span class="dashicons dashicons-warning" style="color: #dc3232;"></span> ' + errorMessage);
                    awca_toast(errorMessage, 'error');
                },
                complete: function() {
                    // Re-enable button
                    button.prop('disabled', false).html(originalText);
                }
            });
        });

        // Check index status
        $('#anar-check-index-status').on('click', function(e) {
            e.preventDefault();

            const button = $(this);
            const originalText = button.html();

            // Disable button and show loading
            button.prop('disabled', true).html('<span class="dashicons dashicons-update"></span> در حال بررسی...');

            // Show status div
            $('#anar-performance-status').show();
            $('#anar-performance-message').html('<span class="dashicons dashicons-update" style="animation: spin 1s linear infinite;"></span> در حال بررسی وضعیت ایندکس‌ها...');

            $.ajax({
                url: awca_ajax_object.ajax_url,
                type: 'POST',
                data: {
                    action: 'anar_check_index_status'
                },
                success: function(response) {
                    if (response.success) {
                        const data = response.data;
                        let statusHtml = '<div style="margin-bottom: 15px;">';
                        statusHtml += '<h4 style="margin: 0 0 10px 0;">وضعیت ایندکس‌های پایگاه داده:</h4>';

                        // Show overall status
                        const overallStatus = data.all_exist ?
                            '<span style="color: #46b450; font-weight: bold;">✓ همه ایندکس‌ها موجود هستند</span>' :
                            '<span style="color: #dc3232; font-weight: bold;">✗ برخی ایندکس‌ها موجود نیستند</span>';

                        statusHtml += `<div style="margin-bottom: 10px;">${overallStatus}</div>`;

                        // Show detailed status
                        statusHtml += '<table style="width: 100%; border-collapse: collapse; font-size: 12px;">';
                        statusHtml += '<tr style="border-bottom: 1px solid #ddd;"><th style="padding: 5px; text-align: left;">ایندکس</th><th style="padding: 5px; text-align: left;">وضعیت</th></tr>';

                        Object.keys(data.status).forEach(function(indexName) {
                            const status = data.status[indexName];
                            const statusText = status === 'exists' ?
                                '<span style="color: #46b450;">✓ موجود</span>' :
                                '<span style="color: #dc3232;">✗ موجود نیست</span>';

                            statusHtml += `<tr style="border-bottom: 1px solid #eee;">
                          <td style="padding: 5px;">${indexName}</td>
                          <td style="padding: 5px;">${statusText}</td>
                        </tr>`;
                        });

                        statusHtml += '</table>';
                        statusHtml += `<div style="margin-top: 10px; font-size: 11px; color: #666;">
                        ${data.existing_count} از ${data.total_required} ایندکس موجود است
                      </div>`;
                        statusHtml += '</div>';

                        $('#anar-performance-message').html('<span class="dashicons dashicons-list-view" style="color: #0073aa;"></span>' + statusHtml);

                        if (!data.all_exist) {
                            awca_toast('برخی ایندکس‌ها موجود نیستند. برای بهبود عملکرد، روی "بهینه‌سازی و ریست زمان‌ها" کلیک کنید.', 'warning');
                        } else {
                            awca_toast('همه ایندکس‌ها موجود هستند', 'success');
                        }
                    } else {
                        $('#anar-performance-message').html('<span class="dashicons dashicons-warning" style="color: #dc3232;"></span> ' + response.data.message);
                        awca_toast(response.data.message, 'error');
                    }
                },
                error: function(xhr, status, err) {
                    const errorMessage = 'خطا در ارتباط با سرور';
                    $('#anar-performance-message').html('<span class="dashicons dashicons-warning" style="color: #dc3232;"></span> ' + errorMessage);
                    awca_toast(errorMessage, 'error');
                },
                complete: function() {
                    // Re-enable button
                    button.prop('disabled', false).html(originalText);
                }
            });
        });
    });
}


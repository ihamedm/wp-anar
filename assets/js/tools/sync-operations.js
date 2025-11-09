import {awca_toast} from "../functions";

/**
 * Handle sync-related operations
 */
export function initSyncOperations() {
    jQuery(document).ready(function($) {
        // Clear sync times
        $('#anar-clear-sync-times').on('click', function(e) {
            confirm('تایم آخرین بروزرسانی همه محصولات ریست می شوند و باید صبر کنید تا همه محصولاتت مجدد سینک شوند. مطمئنید؟');
            e.preventDefault();

            const button = $(this);
            const originalText = button.html();

            // Disable button and show loading
            button.prop('disabled', true).html('<span class="dashicons dashicons-update"></span> در حال بهینه‌سازی...');

            // Show status div
            $('#anar-performance-status').show();
            $('#anar-performance-message').html('<span class="dashicons dashicons-update" style="animation: spin 1s linear infinite;"></span> در حال بهینه‌سازی پایگاه داده...');

            $.ajax({
                url: awca_ajax_object.ajax_url,
                type: 'POST',
                data: {
                    action: 'anar_clear_sync_times'
                },
                success: function(response) {
                    if (response.success) {
                        const data = response.data;
                        const message = `
                          <div style="margin-bottom: 15px;">
                            <span class="dashicons dashicons-yes-alt" style="color: #46b450;"></span> ${data.message}
                            <div style="margin-top: 10px; font-size: 12px; color: #666;">
                              <strong>جزئیات:</strong><br>
                              • محصولات بروزرسانی شده: ${data.updated_count}<br>
                              • محصولات جدید اضافه شده: ${data.inserted_count}<br>
                              • کل محصولات پردازش شده: ${data.total_processed}<br>
                              • تاریخ تنظیم شده: ${data.reset_date}
                            </div>
                          </div>
                        `;
                        $('#anar-performance-message').html(message);
                        awca_toast(data.message, 'success');
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

        // Manual sync outdated products
        $('#anar-manual-sync-outdated').on('click', function(e) {
            e.preventDefault();

            const button = $(this);
            const originalText = button.html();

            // Disable button and show loading
            button.prop('disabled', true).html('<span class="dashicons dashicons-update"></span> در حال اجرا...');

            // Show status div
            $('#anar-performance-status').show();
            $('#anar-performance-message').html('<span class="dashicons dashicons-update" style="animation: spin 1s linear infinite;"></span> در حال اجرای همگام‌سازی محصولات منسوخ...');

            $.ajax({
                url: awca_ajax_object.ajax_url,
                type: 'POST',
                data: {
                    action: 'anar_manual_sync_outdated'
                },
                success: function(response) {
                    if (response.success) {
                        const data = response.data;
                        const message = `
                          <div style="margin-bottom: 15px;">
                            <span class="dashicons dashicons-yes-alt" style="color: #46b450;"></span> ${data.message}
                            <div style="margin-top: 10px; font-size: 12px; color: #666;">
                              <strong>جزئیات:</strong><br>
                              • محصولات پردازش شده: ${data.processed}<br>
                              • محصولات ناموفق: ${data.failed}<br>
                              • کل محصولات بررسی شده: ${data.total_checked}
                            </div>
                          </div>
                        `;
                        $('#anar-performance-message').html(message);
                        awca_toast(data.message, 'success');
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


import {awca_toast} from "../functions";

/**
 * Handle database index creation and optimization
 */
export function initIndexOptimization() {
    jQuery(document).ready(function($) {
        $('#anar-create-indexes').on('click', function(e) {
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
                    action: 'anar_create_indexes'
                },
                success: function(response) {
                    if (response.success) {
                        const data = response.data;
                        const message = `
                          <div style="margin-bottom: 15px;">
                            <span class="dashicons dashicons-yes-alt" style="color: #46b450;"></span> ${data.message}
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


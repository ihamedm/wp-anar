/**
 * Force Check Anar Products
 * 
 * Handles manual Anar product detection for orders created by plugins
 * that bypass WooCommerce's standard order creation hooks.
 */

jQuery(document).ready(function($) {
    'use strict';

    const $forceCheckBtn = $('#awca-force-check-btn');

    if (!$forceCheckBtn.length) {
        return; // Button not present on this page
    }

    /**
     * Handle force check button click
     */
    $forceCheckBtn.on('click', function(e) {
        e.preventDefault();

        const $btn = $(this);
        const orderId = $btn.data('order-id');
        const nonce = $('#awca_force_check_nonce_field').val();

        if (!orderId || !nonce) {
            alert('خطا در دریافت اطلاعات. لطفا صفحه را رفرش کنید.');
            return;
        }

        // Disable button and show loading state
        $btn.prop('disabled', true);
        $btn.html('<span class="dashicons dashicons-update dashicons-spin" style="margin-top: 3px;"></span> در حال بررسی...');

        // Send AJAX request
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'awca_force_check_anar_products',
                order_id: orderId,
                awca_force_check_nonce_field: nonce
            },
            success: function(response) {
                // Always reload page after check (success or error)
                location.reload();
            },
            error: function(xhr, status, error) {
                console.error('Force check AJAX error:', error);
                alert('خطا در ارتباط با سرور. لطفا دوباره تلاش کنید.');
                
                // Re-enable button on network error
                $btn.prop('disabled', false);
                $btn.html('<span class="dashicons dashicons-update" style="margin-top: 3px;"></span> بررسی و شناسایی محصولات انار');
            }
        });
    });
});


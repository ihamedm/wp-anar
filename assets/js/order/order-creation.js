/**
 * Order Creation Module
 * 
 * Handles the creation of Anar orders via AJAX
 */

import { awca_toast } from './index';
import MicroModal from 'micromodal';

/**
 * Create an Anar order with the specified parameters
 * @param {jQuery} $ - jQuery instance
 * @param {Object} options - Order creation options
 * @param {string} options.orderId - The order ID
 * @param {string} options.orderType - The order type (retail/wholesale)
 * @param {Function} options.onSuccess - Success callback
 * @param {Function} options.onError - Error callback
 * @param {Function} options.onComplete - Complete callback
 */
export function createAnarOrder($, options = {}) {
    const {
        orderId,
        orderType = 'retail',
        onSuccess = null,
        onError = null,
        onComplete = null
    } = options;

    const $button = $('#awca-create-anar-order');
    const loadingIcon = $button.find('.spinner-loading');
    let msgType = 'error';

    // Prepare data
    const data = {
        order_id: orderId,
        action: 'awca_create_anar_order_ajax'
    };

    // Add order type if provided
    if (orderType) {
        data.order_type = orderType;
    }

    jQuery.ajax({
        url: awca_ajax_object.ajax_url,
        type: "POST",
        dataType: "json",
        data: data,
        beforeSend: function() {
            loadingIcon.show();
            $button.attr("disabled", "disabled");
        },
        success: function(response) {
            if (response.success) {
                msgType = 'success';
                // Reload page after short delay for success case
                setTimeout(function() {
                    location.reload();
                }, 1000);
            } else {
                msgType = 'error';
            }
            awca_toast(response.data.message, msgType);
            
            // Call success callback if provided
            if (onSuccess && typeof onSuccess === 'function') {
                onSuccess(response);
            }
        },
        error: function(xhr, status, err) {
            awca_toast(xhr.responseText);
            
            // Call error callback if provided
            if (onError && typeof onError === 'function') {
                onError(xhr, status, err);
            }
        },
        complete: function() {
            // Always close modal and reset button after response
            try {
                MicroModal.close('preorder-modal');
            } catch (e) {
                console.error('MicroModal failed to close modal:', e);
            }

            loadingIcon.hide();
            $button.removeAttr("disabled");
            
            // Call complete callback if provided
            if (onComplete && typeof onComplete === 'function') {
                onComplete();
            }
        },
    });
}

export function initOrderCreation($) {
    // Handle create anar order button (now in modal)
    $(document).on('click', '#awca-create-anar-order', function(e) {
        e.preventDefault();

        const OrderID = $(this).data('order-id');
        
        // Use the reusable function
        createAnarOrder($, {
            orderId: OrderID,
            orderType: 'retail' // Default for regular order creation
        });
    });
}

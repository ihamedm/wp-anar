/**
 * Order Details Module
 * 
 * Handles fetching and displaying order details
 */

import { awca_toast } from './index';
import MicroModal from 'micromodal';

export function initOrderDetails($) {
    const anarOrderDetails = $('#anar-order-details');
    if (anarOrderDetails.length !== 0) {

        const loadingIcon = anarOrderDetails.find('.spinner-loading');
        const OrderID = anarOrderDetails.data('order-id');
        let msgType = 'error';

        jQuery.ajax({
            url: awca_ajax_object.ajax_url,
            type: "POST",
            dataType: "json",
            data: {
                order_id: OrderID,
                action: 'awca_fetch_order_details_ajax'
            },
            beforeSend: function() {
                loadingIcon.show();
            },
            success: function(response) {
                if (response.success) {
                    anarOrderDetails.html(response.data.output);
                    msgType = 'success';
                }
                awca_toast(response.data.message, msgType);

                if (response.data.paymentStatus == "unpaid") {
                    try {
                        MicroModal.show('order-payment-modal');
                    } catch (e) {
                        console.error('MicroModal failed to show modal:', e);
                    }
                }

            },
            error: function(xhr, status, err) {
                awca_toast(xhr.responseText);
                loadingIcon.hide();
            },
            complete: function() {
                loadingIcon.hide();
            },
        });
    }
}

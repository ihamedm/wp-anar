/**
 * Order details functionality module
 * Handles fetching and displaying order details on the frontend
 */
jQuery(document).ready(function($) {
    var anarOrderDetails = $('#anar-order-details-front');
    
    if(anarOrderDetails.length !== 0){
        var loadingIcon = anarOrderDetails.find('.spinner-loading');
        var OrderID = anarOrderDetails.data('order-id');
        var msgType = 'error';

        jQuery.ajax({
            url: awca_ajax_object.ajax_url,
            type: "POST",
            dataType: "json",
            data: {
                order_id : OrderID ,
                action: 'awca_fetch_order_details_public_ajax'
            },
            beforeSend: function () {
                loadingIcon.show();
            },
            success: function (response) {
                if (response.success) {
                    anarOrderDetails.html(response.data.output);
                }
            },
            error: function (xhr, status, err) {
                anarOrderDetails.text(xhr.responseText);
                loadingIcon.hide();
            },
            complete: function () {
                loadingIcon.hide();
            },
        });
    }
});

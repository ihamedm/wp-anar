import {awca_toast} from "./functions";
import MicroModal from 'micromodal';

try {
    MicroModal.init({
        openTrigger: 'data-payment-modal-open',
    });
} catch (e) {
    console.error('MicroModal failed to initialize:', e);
}

jQuery(document).ready(function($) {

    var createAnarOrder =  $('#awca-create-anar-order')
    if(createAnarOrder.length !== 0){

        createAnarOrder.on('click', function(e){
            e.preventDefault();

            var loadingIcon = $(this).find('.spinner-loading')
            var OrderID = $(this).data('order-id')
            var msgType = 'error'

            jQuery.ajax({
                url: awca_ajax_object.ajax_url,
                type: "POST",
                dataType: "json",
                data: {
                    order_id : OrderID ,
                    action: 'awca_create_anar_order_ajax'
                },
                beforeSend: function () {
                    loadingIcon.show();
                    $(this).attr("disabled", "disabled");
                },
                success: function (response) {
                    if (response.success) {
                        location.reload();
                        msgType = 'success'
                    }
                    awca_toast(response.data.message, msgType);
                },
                error: function (xhr, status, err) {
                    awca_toast(xhr.responseText)
                    loadingIcon.hide();
                    $(this).removeAttr("disabled");

                },
                complete: function () {
                    loadingIcon.hide();
                    $(this).removeAttr("disabled");
                },
            });

        })

    }

    var anarOrderDetails =  $('#anar-order-details')
    if(anarOrderDetails.length !== 0){

        var loadingIcon = anarOrderDetails.find('.spinner-loading')
        var OrderID = anarOrderDetails.data('order-id')
        var msgType = 'error'

        jQuery.ajax({
                url: awca_ajax_object.ajax_url,
                type: "POST",
                dataType: "json",
                data: {
                    order_id : OrderID ,
                    action: 'awca_fetch_order_details_ajax'
                },
                beforeSend: function () {
                    loadingIcon.show();
                },
                success: function (response) {
                    if (response.success) {
                        anarOrderDetails.html(response.data.output)
                        msgType = 'success'
                    }
                    awca_toast(response.data.message, msgType);

                    if(response.data.paymentStatus == "unpaid"){
                        try {
                            MicroModal.show('order-payment-modal');
                        } catch (e) {
                            console.error('MicroModal failed to show modal:', e);
                        }
                    }

                },
                error: function (xhr, status, err) {
                    awca_toast(xhr.responseText)
                    loadingIcon.hide();

                },
                complete: function () {
                    loadingIcon.hide();
                },
            });


    }


    $(document).on('click', '.anar-package-data header', function(e) {
        e.preventDefault();

        $(this).parent('div').toggleClass('open');

    });


})


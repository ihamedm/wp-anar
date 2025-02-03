import {awca_toast} from "./functions";

jQuery(document).ready(function($) {
    function publishAnarProducts() {
        var form = $('#publish-anar-products');
        var spinnerLoading = form.find('.spinner-loading');
        var formButton = form.find('#awca_publish_anar_products_btn');

        if (form.length !== 0) {
            form.on('submit', function (e) {
                e.preventDefault();

                var msgType = 'error';

                function publishDraftProductsAjax() {
                    $.ajax({
                        url: awca_ajax_object.ajax_url,
                        type: "POST",
                        dataType: "json",
                        data: {
                            action: 'awca_publish_draft_products_ajax',
                            security_nonce: form.find('input[name="security_nonce"]').val(),
                            skipp_out_of_stocks: form.find('input[name="skipp_out_of_stocks"]').prop('checked'),
                        },
                        beforeSend: function () {
                            spinnerLoading.show();
                            formButton.attr("disabled", "disabled");
                        },
                        success: function (response) {
                            if (response.success) {
                                msgType = 'success';
                                awca_toast(response.data.message, msgType);
                            } else {
                                spinnerLoading.hide();
                                formButton.removeAttr("disabled");
                                awca_toast(response.data.message, msgType);
                            }
                        },
                        error: function (xhr, status, err) {
                            spinnerLoading.hide();
                            formButton.removeAttr("disabled");
                            awca_toast(xhr.responseText);
                        },
                        complete: function () {
                            spinnerLoading.hide();
                            formButton.removeAttr("disabled");
                        }
                    });
                }

                // Start the first request
                publishDraftProductsAjax();
            }); // end on click

        }
    }

    publishAnarProducts();
});
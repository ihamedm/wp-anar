import {awca_toast} from "./functions";

jQuery(document).ready(function($) {
    function publishAnarProducts() {
        var form = $('#publish-anar-products');
        // Set task running flag

        if (form.length !== 0) {
            form.on('submit', function (e) {
                e.preventDefault();
                var formButton = form.find('.submit-button');
                var spinnerLoading = formButton.find('.spinner-loading');
                var progressEl = form.find('.anar-batch-progress');
                var messageEl = form.find('.anar-batch-messages');

                var page = 1;
                var totalPublished = 0;
                var totalProducts = 0;
                var hasMorePages = true;
                var msgType = 'error';

                function publishDraftProductsAjax(page) {
                    $.ajax({
                        url: awca_ajax_object.ajax_url,
                        type: "POST",
                        dataType: "json",
                        data: {
                            action: 'awca_publish_draft_products_ajax',
                            page: page,
                            limit: 100,
                            security_nonce: form.find('input[name="security_nonce"]').val(),
                            skipp_out_of_stocks: form.find('input[name="skipp_out_of_stocks"]').prop('checked'),
                        },
                        beforeSend: function () {
                            spinnerLoading.show();
                            progressEl.show()
                            messageEl.show()
                            formButton.attr("disabled", "disabled");
                        },
                        success: function (response) {
                            if (response.success) {
                                msgType = 'success';

                                totalPublished += response.loop_products;
                                if(page === 1) {
                                    totalProducts = response.found_products;
                                }

                                var percent = ((totalPublished / totalProducts) * 100).toFixed(2) + "%";

                                progressEl.show();
                                progressEl.find('.bar').animate({"width": percent}, 500);


                                var message = totalPublished + ' محصول از کل ' + totalProducts + ' پردازش شد';
                                messageEl.addClass('success').text(message);

                                hasMorePages = response.has_more
                                if (hasMorePages) {
                                    publishDraftProductsAjax(page + 1);
                                } else {
                                    messageEl.addClass('success').text('همه محصولات پردازش شدند');
                                    awca_toast('همه محصولات پردازش شدند', "success");
                                }

                            } else {
                                messageEl.addClass('error').text(response.message);
                                spinnerLoading.hide();
                                formButton.removeAttr("disabled");
                                awca_toast(response.message, msgType);
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
                publishDraftProductsAjax(page);
            }); // end on click
        }
    }

    publishAnarProducts()

});
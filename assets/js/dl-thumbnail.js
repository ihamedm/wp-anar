import {awca_toast} from "./functions";

jQuery(document).ready(function($) {

    const form = $('#anar-dl-products-thumbnail');
    const dlButton = form.find('#anar_dl_products_thumbnail');

    function dlAnarProductsThumbnail() {
        dlButton.on('click', function (e) {
            e.preventDefault();

            const formButton = $(this)
            const spinnerLoading = formButton.find('.spinner-loading');
            const progressEl = form.find('.anar-batch-progress');
            const messageEl = form.find('.anar-batch-messages');

            var page = 1;
            var totalPublished = 0;
            var totalDownloaded = 0;
            var totalProducts = 0;
            var hasMorePages = true;
            var msgType = 'error';

            function dlProductsThumbnailAjax(page) {
                $.ajax({
                    url: awca_ajax_object.ajax_url,
                    type: "POST",
                    dataType: "json",
                    data: {
                        action: 'anar_dl_products_thumbnail_ajax',
                        page: page,
                        limit: 1,
                        security_nonce: form.find('input[name="security_nonce"]').val(),
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
                            totalDownloaded += response.total_downloaded;
                            if(page === 1) {
                                totalProducts = response.found_products;
                            }

                            var percent = ((totalPublished / totalProducts) * 100).toFixed(2) + "%";

                            progressEl.show();
                            progressEl.find('.bar').animate({"width": percent}, 100);


                            var message = totalPublished + ' محصول از کل ' + totalProducts + ' پردازش شد - ' + totalDownloaded + ' تصویر دانلود شد.';
                            messageEl.addClass('success').text(message);

                            hasMorePages = response.has_more
                            if (hasMorePages) {
                                dlProductsThumbnailAjax(page + 1);
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
            dlProductsThumbnailAjax(page);
        });
    }
    dlAnarProductsThumbnail()

});

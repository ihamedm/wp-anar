import {awca_toast} from "./functions";

jQuery(document).ready(function($) {
    function AnarSyncOutdated() {
        var form = $('#anar-sync-outdated');

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
                var retryCount = 0;
                var maxRetries = 1;

                function syncOutdatedAjax(page) {
                    $.ajax({
                        url: awca_ajax_object.ajax_url,
                        type: "POST",
                        dataType: "json",
                        timeout: 60000, // 1-minute timeout
                        data: {
                            action: 'anar_sync_outdated_products',
                            limit: 1,
                            security: awca_ajax_object.nonce
                        },
                        beforeSend: function () {
                            spinnerLoading.show();
                            progressEl.show();
                            messageEl.show();
                            formButton.attr("disabled", "disabled");
                        },
                        success: function (response) {
                            if (response.success) {
                                totalPublished += response.loop_products;
                                if(page === 1) {
                                    totalProducts = response.found_products;
                                }

                                var percent = ((totalPublished / totalProducts) * 100).toFixed(2) + "%";
                                progressEl.find('.bar').animate({"width": percent}, 500);

                                var message = totalPublished + ' محصول از کل ' + totalProducts + ' پردازش شد';
                                messageEl.removeClass('error').addClass('success').text(message);

                                hasMorePages = response.has_more;
                                retryCount = 0; // Reset retry count on successful request

                                if (hasMorePages) {
                                    syncOutdatedAjax(page + 1);
                                } else {
                                    finishSync();
                                }
                            } else {
                                handleSyncError(response.message);
                            }
                        },
                        error: function (xhr, status, err) {
                            handleSyncError(err || status || 'Unknown error');
                        },
                        complete: function () {
                            spinnerLoading.hide();
                            formButton.removeAttr("disabled");
                        }
                    });
                }

                function handleSyncError(message) {
                    if (retryCount < maxRetries) {
                        retryCount++;
                        awca_toast(`خطا: ${message}. تلاش مجدد ${retryCount}...`, "warning");
                        setTimeout(() => syncOutdatedAjax(page), 2000 * retryCount);
                    } else {
                        messageEl.removeClass('success').addClass('error').text(message);
                        awca_toast(message, "error");
                        finishSync(false);
                    }
                }

                function finishSync(success = true) {
                    if (success) {
                        messageEl.addClass('success').text('همه محصولات پردازش شدند');
                        awca_toast('همه محصولات پردازش شدند', "success");
                    }
                    spinnerLoading.hide();
                    formButton.removeAttr("disabled");
                    progressEl.find('.bar').animate({"width": 100}, 500);
                }

                // Start the first request
                syncOutdatedAjax(page);
            });
        }
    }

    AnarSyncOutdated();
});
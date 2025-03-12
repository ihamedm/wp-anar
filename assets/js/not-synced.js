import {awca_toast} from "./functions";

jQuery(document).ready(function($) {
    function find_not_synced_products() {
        const form = $('#anar_tools_not_sync_form');
        const formSubmitBtn = form.find('#submit-form-btn');
        const $formSyncOutdated = $("#anar_tools_sync_outdated")

        if (form.length !== 0) {
            form.on('submit', function (e) {
                e.preventDefault();

                var msgType = 'error';

                // Collect all form data
                var formData = form.serializeArray();
                var additionalData = {};

                // Collect form inputs
                form.find('input, select, textarea').each(function() {
                    var $input = $(this);
                    if ($input.attr('name') && !$input.prop('disabled')) {
                        additionalData[$input.attr('name')] = $input.val();
                    }
                });

                // Merge serialized data with additional data
                var ajaxData = {
                    action: form.find('input[name="action"]').val() || '',
                    ...Object.fromEntries(formData.map(item => [item.name, item.value])),
                    ...additionalData
                };

                $.ajax({
                    url: awca_ajax_object.ajax_url,
                    type: "POST",
                    dataType: "json",
                    data: ajaxData,
                    beforeSend: function () {
                        formSubmitBtn.addClass('loading')
                        formSubmitBtn.attr("disabled", "disabled");
                    },
                    success: function (response) {
                        if (response.success) {
                            msgType = 'success';
                        }
                        if(response.data.found_posts > 0) {
                            $formSyncOutdated.show()
                        }
                        form.find('.form-results').show().html(response.data.message)
                        awca_toast(response.data.toast, msgType);
                    },
                    error: function (xhr, status, err) {
                        awca_toast(xhr.responseText);
                    },
                    complete: function () {
                        formSubmitBtn.removeClass('loading')
                        formSubmitBtn.removeAttr("disabled");
                    }
                });

            }); // end on click

        }
    }


    function DoSyncAjax($formDoSync){
        $formDoSync.on('submit', function(e){
            alert('success');
            e.preventDefault();
            const form = $formDoSync
            const formSubmitBtn = form.find('button[type="submit"]');
            var msgType = 'error';

            // Collect all form data
            var formData = form.serializeArray();
            var additionalData = {};

            // Collect form inputs
            form.find('input, select, textarea').each(function() {
                var $input = $(this);
                if ($input.attr('name') && !$input.prop('disabled')) {
                    additionalData[$input.attr('name')] = $input.val();
                }
            });

            // Merge serialized data with additional data
            var ajaxData = {
                action: form.find('input[name="action"]').val() || '',
                ...Object.fromEntries(formData.map(item => [item.name, item.value])),
                ...additionalData
            };

            $.ajax({
                url: awca_ajax_object.ajax_url,
                type: "POST",
                dataType: "json",
                data: ajaxData,
                beforeSend: function () {
                    formSubmitBtn.addClass('loading')
                    formSubmitBtn.attr("disabled", "disabled");
                },
                success: function (response) {
                    if (response.success) {
                        msgType = 'success';
                    }
                    awca_toast(response.data.message, msgType);
                },
                error: function (xhr, status, err) {
                    awca_toast(xhr.responseText);
                },
                complete: function () {
                    formSubmitBtn.removeClass('loading')
                    formSubmitBtn.removeAttr("disabled");
                }
            });

        })

    }

    find_not_synced_products();
});
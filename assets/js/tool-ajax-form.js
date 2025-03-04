import {awca_toast} from "./functions";

jQuery(document).ready(function($) {
    // Generic AJAX form handler
    $('.anar-tool-ajax-form').each(function() {
        var $form = $(this);
        var $spinnerLoading = $form.find('.spinner-loading');
        var $formButton = $form.find('.tool_submit_btn');


        $form.on('submit', function (e) {
            e.preventDefault();

            // Collect all form data
            var formData = $form.serializeArray();
            var additionalData = {};

            // Collect form inputs
            $form.find('input, select, textarea').each(function() {
                var $input = $(this);
                if ($input.attr('name') && !$input.prop('disabled')) {
                    additionalData[$input.attr('name')] = $input.val();
                }
            });

            // Merge serialized data with additional data
            var ajaxData = {
                action: $form.find('input[name="action"]').val() || '',
                ...Object.fromEntries(formData.map(item => [item.name, item.value])),
                ...additionalData
            };

            $.ajax({
                url: awca_ajax_object.ajax_url,
                type: "POST",
                dataType: "json",
                data: ajaxData,
                beforeSend: function () {
                    $spinnerLoading.show();
                    $formButton.attr("disabled", "disabled");
                },
                success: function (response) {
                    var msgType = response.success ? 'success' : 'error';

                    // Trigger custom event for additional handling
                    $form.trigger('awca_ajax_form_success', [response, msgType]);

                    // Show toast message
                    if (response.data && response.data.message) {
                        awca_toast(response.data.message, msgType);
                    }

                    // Reset form if success
                    if (response.success) {
                        $form[0].reset();
                    }
                },
                error: function (xhr, status, err) {
                    // Trigger custom event for error handling
                    $form.trigger('awca_ajax_form_error', [xhr, status, err]);

                    // Show error toast
                    awca_toast(xhr.responseText || 'An error occurred', 'error');
                },
                complete: function () {
                    $spinnerLoading.hide();
                    $formButton.removeAttr("disabled");
                }
            });
        });
    });


});
import {awca_toast} from "./functions";
import MicroModal from "micromodal";

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
                    if ($input.is(':checkbox')) {
                        additionalData[$input.attr('name')] = $input.prop('checked')
                            ? ($input.val() || 'on')
                            : 'off';
                    } else if ($input.is(':radio')) {
                        if ($input.prop('checked')) {
                            additionalData[$input.attr('name')] = $input.val();
                        }
                    } else {
                        additionalData[$input.attr('name')] = $input.val();
                    }
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
                    $form.addClass('loading');
                },
                success: function (response) {
                    var msgType = response.success ? 'success' : 'error';

                    console.log(response);

                    // Trigger custom event for additional handling
                    $form.trigger('awca_ajax_form_success', [response, msgType]);

                    // Show toast message
                    if (response.data && response.data.message) {
                        awca_toast(response.data.message, msgType);
                    }

                    // Reset form if success
                    if (response.success) {
                        if($form.data('reset')){
                            $form[0].reset();
                        }
                        if($form.data('reload')){
                            window.location.reload();
                        }
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
                    $form.removeClass('loading');
                }
            });
        });
    });


    $('.access-menu').on('click', '.access-menu-toggle', function(e) {
        e.preventDefault();
        const UL = $(this).siblings('ul')
        UL.toggle()
    })

    $('input[name="anar-sleep-mode-toggle"]').on('change', function(e) {
        e.preventDefault();
        MicroModal.show('anar-sleep-mode-modal',
            {
                onClose : () => {
                    $(this).parents('form').submit();
                }
            }
        )
    })

});

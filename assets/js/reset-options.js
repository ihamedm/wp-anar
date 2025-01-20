import {awca_toast} from "./functions";

jQuery(document).ready(function($) {
    function resetAllOptions() {
        var resetAllOptionsForm = $('#awca-reset-all-settings');
        var spinnerLoading = resetAllOptionsForm.find('.spinner-loading');
        var formButton = resetAllOptionsForm.find('#awca_reset_options_btn');

        if (resetAllOptionsForm.length !== 0) {
            resetAllOptionsForm.on('submit', function (e) {
                e.preventDefault();

                var msgType = 'error';

                function resetAllOptionsAjax() {
                    $.ajax({
                        url: awca_ajax_object.ajax_url,
                        type: "POST",
                        dataType: "json",
                        data: {
                            action: 'awca_reset_all_settings_ajax',
                            delete_maps: resetAllOptionsForm.find('#delete_map_data').prop('checked'),
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
                resetAllOptionsAjax();
            }); // end on click

        }
    }

    resetAllOptions();
});
import {awca_toast} from "./functions";

jQuery(document).ready(function($) {
    // Generic AJAX action handler for links
    $('.anar-ajax-action').each(function() {
        var $link = $(this);
        var $spinner = $link.find('.spinner-loading');

        $link.on('click', function (e) {
            e.preventDefault();

            // Collect all data attributes dynamically
            let actionData = {};
            let dataAttributes = $link[0].attributes;

            for (let i = 0; i < dataAttributes.length; i++) {
                let attr = dataAttributes[i];
                if (attr.name.startsWith('data-')) {
                    // Convert data-attribute-name to attribute_name format
                    let key = attr.name.replace('data-', '')
                    actionData[key] = attr.value;
                }
            }
            
            // Get reload configuration from data attributes
            var reloadOnSuccess = $link.data('reload') === 'success';
            var reloadOnError = $link.data('reload') === 'error';
            var reloadOnComplete = $link.data('reload') === 'complete';
            
            // Get custom reload timeout (default to standard delays if not set)
            var reloadTimeout = parseInt($link.data('reload-timeout')) || 0;
            
            $.ajax({
                url: awca_ajax_object.ajax_url,
                type: "POST",
                dataType: "json",
                data: actionData,
                beforeSend: function () {
                    $spinner.show();
                    $link.addClass('disabled').attr("disabled", "disabled");
                },
                success: function (response) {
                    var msgType = response.success ? 'success' : 'error';

                    // Trigger custom event for additional handling
                    $link.trigger('anar_ajax_action_success', [response, msgType]);

                    // Show toast message
                    if (response.data && response.data.message) {
                        awca_toast(response.data.message, msgType);
                    }
                    
                    // Handle reload on success
                    if (response.success && reloadOnSuccess) {
                        var delay = reloadTimeout > 0 ? reloadTimeout : 1000; // Default 1 second
                        setTimeout(function() {
                            location.reload();
                        }, delay);
                    }
                },
                error: function (xhr, status, err) {
                    // Trigger custom event for error handling
                    $link.trigger('anar_ajax_action_error', [xhr, status, err]);

                    // Show error toast
                    awca_toast(xhr.responseText || 'An error occurred', 'error');
                    
                    // Handle reload on error
                    if (reloadOnError) {
                        var delay = reloadTimeout > 0 ? reloadTimeout : 2000; // Default 2 seconds
                        setTimeout(function() {
                            location.reload();
                        }, delay);
                    }
                },
                complete: function () {
                    $spinner.hide();
                    $link.removeClass('disabled').removeAttr("disabled");
                    
                    // Handle reload on complete (regardless of success/error)
                    if (reloadOnComplete) {
                        var delay = reloadTimeout > 0 ? reloadTimeout : 1500; // Default 1.5 seconds
                        setTimeout(function() {
                            location.reload();
                        }, delay);
                    }
                }
            });
        });
    });

});
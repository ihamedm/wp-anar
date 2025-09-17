/**
 * Legacy public.js backup - Original functionality before refactoring
 * This file contains the original public.js code for reference
 * DO NOT EDIT - This is a backup file
 */

jQuery(document).ready(function($) {

    var tooltip = jQuery('<div class="awca-tooltip" style="position: absolute; background-color: black; color: white; padding: 5px 10px; border-radius: 4px; font-size: 12px; display: none; z-index: 1000;"></div>');
    jQuery('body').append(tooltip);

    jQuery(document).on('mouseenter', '.awca-tooltip-on', function() {
        // Get the title attribute
        var title = jQuery(this).attr('title');
        // Set the tooltip text
        tooltip.text(title);

        // Calculate the right offset for the tooltip
        var elementRightOffset = jQuery(this).offset().left + jQuery(this).outerWidth();
        var tooltipWidth = tooltip.outerWidth();

        tooltip.css({
            display: 'block',
            left: elementRightOffset - tooltipWidth + 'px',
            top: jQuery(this).offset().top - tooltip.outerHeight() + 'px'
        });
    });

    jQuery(document).on('mouseleave', '.awca-tooltip-on', function() {
        // Hide the tooltip
        tooltip.hide();
    });

    jQuery('.anar-delivery-option input[type="radio"]').on('change', function() {
        jQuery('.anar-delivery-option').removeClass('selected');
        jQuery(this).closest('.anar-delivery-option').addClass('selected');
    });


    function ensureRadioSelection() {
        $('input[type="radio"][data-input-group]').each(function() {
            var inputGroup = $(this).data('input-group');

            var radios = $('input[data-input-group="' + inputGroup + '"]');
            // If none are checked, set the first one as checked

            if (radios.filter(':checked').length === 0) {
                radios.first().prop('checked', true);
            }
        });
    }

    function validateRadioSelectionOnOrder() {
        $('#place_order').on('click', function(e) {
            var allChecked = true;

            // Check for each group if a radio button is checked
            $('input[type="radio"][data-input-group]').each(function() {
                var inputGroup = $(this).data('input-group');
                var radios = $('input[data-input-group="' + inputGroup + '"]');

                if (radios.filter(':checked').length === 0) {
                    allChecked = false;
                    return false; // Break out of the .each loop
                }
            });

            // If any group has no radio selected, alert the user
            if (!allChecked) {
                e.preventDefault(); // Prevent the form from submitting
                alert('Please select a delivery option before proceeding.');
            }
        });
    }

    function async_cart_products_update(){
        // Prevent starting a new cart update if one triggered by this handler is already running
        if (anarCartUpdateInProgress) {
            return;
        }
        $.ajax({
            url: awca_ajax_object.ajax_url,
            type: 'POST',
            dataType: 'json',
            data: {
                action: 'anar_update_cart_products_async', // New action name
                nonce: awca_ajax_object.nonce // Use the same nonce
            },
            beforeSend: function() {
            },
            success: function(response) {
                if (response.success) {
                    if (response.data.needs_refresh === true) {
                        anarCartUpdateInProgress = true;
                        $(document.body).trigger('update_checkout');
                        setTimeout(function() {
                            anarCartUpdateInProgress = false;
                        }, 500);
                    } else {
                    }
                } else {
                    console.error('Anar: Cart product update check failed.', response.data ? response.data.message : 'No error message provided.');
                }
            },
            error: function(xhr, status, error) {
                console.error('Anar: AJAX error during cart product update.', status, error, xhr.responseText);
                anarCartUpdateInProgress = false;
            },
            complete: function() {
            }
        });
    }

    var anarCartUpdateInProgress = false;

    $(document.body).on('updated_checkout', function() {
        ensureRadioSelection();
        validateRadioSelectionOnOrder();
        async_cart_products_update();
    });

    ensureRadioSelection();
    validateRadioSelectionOnOrder();


    var anarOrderDetails =  $('#anar-order-details-front')
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
                action: 'awca_fetch_order_details_public_ajax'
            },
            beforeSend: function () {
                loadingIcon.show();
            },
            success: function (response) {
                if (response.success) {
                    anarOrderDetails.html(response.data.output)
                }
            },
            error: function (xhr, status, err) {
                anarOrderDetails.text(xhr.responseText)
                loadingIcon.hide();
            },
            complete: function () {
                loadingIcon.hide();
            },
        });


    }


    var metaTag = $('meta[name="anar-product-id"]');
    var productId = null;

    if (metaTag.length > 0) {
        productId = metaTag.attr('content');
    }

    if (productId && !isNaN(productId)) {
        productId = parseInt(productId, 10);
        $.ajax({
            url: awca_ajax_object.ajax_url,
            type: 'POST',
            dataType: 'json',
            data: {
                action: 'anar_update_product_async',
                product_id: productId,
                nonce: awca_ajax_object.nonce
            },
            success: function(response) {
                if (response.success && response.data) {
                } else {
                }
                console.log(response);
            },
            error: function(xhr, status, error) {
            },
            complete: function() {
            }
        });
    } else {
    }
});

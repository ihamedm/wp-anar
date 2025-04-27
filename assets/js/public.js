jQuery(document).ready(function($) {

    var tooltip = jQuery('<div class="awca-tooltip" style="position: absolute; background-color: black; color: white; padding: 5px 10px; border-radius: 4px; font-size: 12px; display: none; z-index: 1000;"></div>');
    jQuery('body').append(tooltip);

    // Use event delegation to handle mouseenter and mouseleave
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
            console.log('Anar: Cart update already in progress, skipping new AJAX call.');
            return;
        }
        console.log('Anar: Initiating async cart product update.');
        $.ajax({
            url: awca_ajax_object.ajax_url,
            type: 'POST',
            dataType: 'json',
            data: {
                action: 'anar_update_cart_products_async', // New action name
                nonce: awca_ajax_object.nonce // Use the same nonce
            },
            beforeSend: function() {
                // Optional: Show some subtle loading indicator near the order review?
            },
            success: function(response) {
                if (response.success) {
                    console.log('Anar: Cart product update check completed.', response.data);
                    // Check if the PHP handler indicated updates happened
                    if (response.data.needs_refresh === true) {
                        console.log('Anar: Product updates occurred, triggering checkout refresh.');
                        // Set the flag to indicate we are about to trigger the update
                        anarCartUpdateInProgress = true;
                        // Trigger the standard WooCommerce checkout update
                        $(document.body).trigger('update_checkout');
                        // Reset the flag shortly after, allowing subsequent user actions to trigger updates
                        // Or reset it when the *next* updated_checkout event fires (handled implicitly by the check at the start)
                        // Let's reset after a small delay for safety
                        setTimeout(function() {
                            anarCartUpdateInProgress = false;
                        }, 500); // Reset after 500ms
                    } else {
                        console.log('Anar: No product updates needed refresh.');
                    }
                } else {
                    console.error('Anar: Cart product update check failed.', response.data ? response.data.message : 'No error message provided.');
                }
            },
            error: function(xhr, status, error) {
                console.error('Anar: AJAX error during cart product update.', status, error, xhr.responseText);
                // Ensure flag is reset on error too
                anarCartUpdateInProgress = false;
            },
            complete: function() {
                console.log('Anar: Async cart product update call complete.');
                // Optional: Hide loading indicator
                // We don't reset the flag here, only after triggering update_checkout or on error
            }
        });
    }

    // Flag to prevent immediate re-triggering of checkout update by our own AJAX call
    var anarCartUpdateInProgress = false;

    // Execute functions on WooCommerce updated_checkout event
    $(document.body).on('updated_checkout', function() {
        console.log('Anar: updated_checkout event triggered.');

        // Existing functions for delivery options
        ensureRadioSelection();
        validateRadioSelectionOnOrder();
        async_cart_products_update();


    });

    // Ensure one radio is selected and validation on first load
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


    // Async Product Update AJAX Call
    // Find the meta tag added by PHP
    var metaTag = $('meta[name="anar-product-id"]');
    var productId = null;

    if (metaTag.length > 0) {
        productId = metaTag.attr('content');
    }

    // Proceed only if the meta tag and a valid product ID were found
    if (productId && !isNaN(productId)) { // Check if it's a number
        productId = parseInt(productId, 10); // Ensure it's an integer
        console.log('Anar: Found product ID ' + productId + ' from meta tag. Initiating async update.');
        // Optional: You could show a loading indicator here if needed

        $.ajax({
            url: awca_ajax_object.ajax_url, // From wp_localize_script
            type: 'POST',
            dataType: 'json', // Expecting a JSON response from the server
            data: {
                action: 'anar_update_product_async', // Matches the PHP handler
                product_id: productId,
                nonce: awca_ajax_object.nonce // Use the nonce provided for public AJAX calls
            },
            success: function(response) {
                if (response.success && response.data) {
                    // --- TODO: Update your product page elements here ---
                    // Example: Update price, stock status, etc., based on response.data
                    // e.g., $('.product_title').text(response.data.new_title);
                    // e.g., $('.price').html(response.data.new_price_html);
                    console.log('Anar: Product update successful.', response.data);
                    // --- End TODO ---
                } else {
                    // Handle cases where the AJAX action succeeded but the operation failed
                    console.error('Anar: Product update failed.', response.data ? response.data.message : 'No error message provided.');
                }
            },
            error: function(xhr, status, error) {
                // Handle AJAX communication errors
                console.error('Anar: AJAX error during product update.', status, error, xhr.responseText);
            },
            complete: function() {
                // Optional: Hide loading indicator here
                console.log('Anar: Async product update call complete.');
            }
        });
    } else {
        // Log if the meta tag wasn't found or didn't contain a valid ID
        // This will now run on non-product pages as well, but that's fine.
        console.log('Anar: Could not find valid product ID meta tag on this page for async update.');
    }
});
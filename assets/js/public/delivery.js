/**
 * Delivery option selection module
 * Handles delivery option radio button selection and validation
 */
jQuery(document).ready(function($) {
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

    // Initialize functions
    ensureRadioSelection();
    validateRadioSelectionOnOrder();

    // Re-initialize on checkout update
    $(document.body).on('updated_checkout', function() {
        ensureRadioSelection();
        validateRadioSelectionOnOrder();
    });


    // Manual trigger logic moved to checkout-manual-trigger.js
    // This file now only handles delivery option selection and validation
});

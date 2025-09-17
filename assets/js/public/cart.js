/**
 * Cart update functionality module
 * Handles asynchronous cart product updates
 */
jQuery(document).ready(function($) {
    var anarCartUpdateInProgress = false;

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
                    console.info("Checkout items synced with Anar.");
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

    // Initialize cart update on checkout update
    $(document.body).on('updated_checkout', function() {
        async_cart_products_update();
    });
});

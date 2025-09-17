/**
 * Checkout Manual Trigger
 * This file contains the JavaScript trick that manually triggers update_checkout with delay
 * TEMPORARILY COMMENTED OUT for PHP debugging
 */

jQuery(document).ready(function($) {
    $('form.checkout').on('change', 'input, select', function() {
        const fieldName = $(this).attr('name') || $(this).attr('id') || 'unknown';
        const timestamp = new Date().toISOString();
        
        // Only log important field changes (not all autofill fields)
        if (fieldName.includes('billing_') || fieldName.includes('shipping_')) {
            console.log(`[TIMING] Field changed: ${fieldName} at ${timestamp}`);
        }
        
        // Disable checkout button immediately
        const placeOrderBtn = $('#place_order');
        if (placeOrderBtn.length) {
            placeOrderBtn.prop('disabled', true);
            placeOrderBtn.addClass('processing');
        }
        
        // Clear any existing timeout
        if (window.anarDeliveryTimeout) {
            clearTimeout(window.anarDeliveryTimeout);
        }
        
        // Set simple 4 second timeout
        const delay = 4000; // 4 seconds - stable and predictable
        
        window.anarDeliveryTimeout = setTimeout(function() {
            const updateTimestamp = new Date().toISOString();
            console.log(`[TIMING] Timeout triggered at ${updateTimestamp}`);
            console.log(`[TIMING] Triggering update_checkout...`);
            
            $('body').trigger('update_checkout');
            
            // Re-enable checkout button after a short delay
            setTimeout(function() {
                if (placeOrderBtn.length) {
                    placeOrderBtn.prop('disabled', false);
                    placeOrderBtn.removeClass('processing');
                    console.log(`[TIMING] Button re-enabled`);
                }
            }, 1000);
            
        }, delay);
    });
    
    // Also listen for WooCommerce's own update events to track timing
    $(document.body).on('updated_checkout', function() {
        const updateCompleteTimestamp = new Date().toISOString();
        console.log(`[TIMING] WooCommerce update completed at ${updateCompleteTimestamp}`);
        
        // Re-enable button if it's still disabled
        const placeOrderBtn = $('#place_order');
        if (placeOrderBtn.length && placeOrderBtn.prop('disabled')) {
            placeOrderBtn.prop('disabled', false);
            placeOrderBtn.removeClass('processing');
            console.log(`[TIMING] Button re-enabled by WooCommerce update`);
        }
    });
    
    // Listen for checkout form updates
    $(document.body).on('checkout_error', function() {
        console.log(`[TIMING] Checkout error - re-enabling button`);
        const placeOrderBtn = $('#place_order');
        if (placeOrderBtn.length) {
            placeOrderBtn.prop('disabled', false);
            placeOrderBtn.removeClass('processing');
        }
    });
});

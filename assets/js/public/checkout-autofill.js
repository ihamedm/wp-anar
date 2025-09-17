/**
 * Checkout Autofill Handler
 * Handles cases where browser autofill doesn't trigger WooCommerce's update_checkout event
 * This ensures shipping calculations run even when users autofill and go directly to checkout
 */

class CheckoutAutofillHandler {
    constructor() {
        this.init();
    }

    init() {
        // Only run on checkout page
        if (!this.isCheckoutPage()) {
            return;
        }

        this.setupAutofillDetection();
        this.setupFormChangeDetection();
        this.setupVisibilityChangeDetection();
        this.setupBeforeUnloadDetection();
        this.setupRecalculateButton();
        this.setupPlaceOrderButtonHandler();
    }

    isCheckoutPage() {
        return document.body.classList.contains('woocommerce-checkout') || 
               window.location.href.includes('checkout');
    }

    setupAutofillDetection() {
        // Detect autofill events on form fields
        const formFields = document.querySelectorAll('form.checkout input, form.checkout select');
        
        formFields.forEach(field => {
            // Listen for autofill events
            field.addEventListener('animationstart', (e) => {
                if (e.animationName === 'onAutoFillStart') {
                    this.handleAutofillDetected();
                }
            });

            // Fallback: detect value changes that might be autofill
            let lastValue = field.value;
            field.addEventListener('input', () => {
                // If value changed but no user interaction detected, might be autofill
                if (field.value !== lastValue && !this.hasUserInteraction) {
                    setTimeout(() => {
                        this.handleAutofillDetected();
                    }, 100);
                }
                lastValue = field.value;
            });

            // Special handling for select fields (including Select2)
            if (field.tagName === 'SELECT') {
                field.addEventListener('change', () => {
                    this.handleSelectChange(field);
                });
            }
        });

        // Setup Select2 specific detection
        this.setupSelect2Detection();
    }

    setupFormChangeDetection() {
        // Track user interactions
        this.hasUserInteraction = false;
        
        const checkoutForm = document.querySelector('form.checkout');
        if (!checkoutForm) return;

        // Mark as user interaction on any form change
        checkoutForm.addEventListener('input', () => {
            this.hasUserInteraction = true;
        });

        checkoutForm.addEventListener('change', () => {
            this.hasUserInteraction = true;
        });

        // Reset interaction flag after a delay
        checkoutForm.addEventListener('input', this.debounce(() => {
            setTimeout(() => {
                this.hasUserInteraction = false;
            }, 2000);
        }, 500));
    }

    setupVisibilityChangeDetection() {
        // Handle cases where user switches tabs and comes back
        document.addEventListener('visibilitychange', () => {
            if (!document.hidden) {
                // User came back to the page, check if form was autofilled
                setTimeout(() => {
                    this.checkForAutofilledFields();
                }, 500);
            }
        });
    }

    setupBeforeUnloadDetection() {
        // Check before user leaves the page
        window.addEventListener('beforeunload', () => {
            this.checkForAutofilledFields();
        });
    }

    setupSelect2Detection() {
        // Wait for Select2 to be initialized
        const checkSelect2 = () => {
            const select2Fields = document.querySelectorAll('.select2-hidden-accessible');
            if (select2Fields.length > 0) {
                select2Fields.forEach(field => {
                    // Listen for Select2 change events
                    jQuery(field).on('change', () => {
                        this.handleSelectChange(field);
                    });
                });
            }
        };

        // Check immediately and after delays
        checkSelect2();
        setTimeout(checkSelect2, 1000);
        setTimeout(checkSelect2, 3000);
    }

    handleSelectChange(selectField) {
        console.log('Anar: Select field changed:', selectField.name, 'Value:', selectField.value);
        
        // Trigger immediate update for select changes
        this.triggerImmediateUpdate();
    }

    triggerImmediateUpdate() {
        console.log('Anar: Triggering immediate checkout update for select change');
        
        // Trigger WooCommerce's update_checkout event
        if (typeof jQuery !== 'undefined' && jQuery.fn.trigger) {
            jQuery('body').trigger('update_checkout');
        }
    }

    checkForAutofilledFields() {
        const billingFields = [
            'billing_first_name',
            'billing_last_name', 
            'billing_company',
            'billing_address_1',
            'billing_address_2',
            'billing_city',
            'billing_state',
            'billing_postcode',
            'billing_country',
            'billing_phone',
            'billing_email'
        ];

        let hasAutofilledFields = false;

        billingFields.forEach(fieldName => {
            const field = document.querySelector(`[name="${fieldName}"]`);
            if (field && field.value && !this.hasUserInteraction) {
                hasAutofilledFields = true;
            }
        });

        if (hasAutofilledFields) {
            this.handleAutofillDetected();
        }
    }

    handleAutofillDetected() {
        console.log('Anar: Autofill detected, triggering checkout update');
        
        // Trigger WooCommerce's update_checkout event
        if (typeof jQuery !== 'undefined' && jQuery.fn.trigger) {
            jQuery('body').trigger('update_checkout');
        } else {
            // Fallback: dispatch custom event
            const event = new CustomEvent('update_checkout');
            document.body.dispatchEvent(event);
        }

        // Also trigger a manual form update
        this.triggerManualUpdate();
    }

    triggerManualUpdate() {
        // Get form data and send AJAX request to update checkout
        const checkoutForm = document.querySelector('form.checkout');
        if (!checkoutForm) return;

        const formData = new FormData(checkoutForm);
        
        // Add WooCommerce specific parameters
        formData.append('woocommerce_checkout_update_totals', '1');
        formData.append('action', 'woocommerce_checkout');

        // Send AJAX request
        fetch(window.location.href, {
            method: 'POST',
            body: formData,
            credentials: 'same-origin'
        }).then(response => {
            if (response.ok) {
                console.log('Anar: Manual checkout update completed');
            }
        }).catch(error => {
            console.log('Anar: Manual checkout update failed:', error);
        });
    }

    setupRecalculateButton() {
        // Add a recalculate button before the place order button
        const placeOrderButton = document.querySelector('#place_order');
        if (placeOrderButton && !document.querySelector('#anar-recalculate-btn')) {
            const recalculateBtn = document.createElement('button');
            recalculateBtn.type = 'button';
            recalculateBtn.id = 'anar-recalculate-btn';
            recalculateBtn.className = 'button anar-recalculate-button';
            recalculateBtn.innerHTML = '🔄 محاسبه مجدد هزینه ارسال';
            recalculateBtn.style.cssText = `
                margin-left: 10px;
                background: #f0f0f0;
                border: 1px solid #ccc;
                padding: 10px 15px;
                border-radius: 4px;
                cursor: pointer;
                font-size: 14px;
            `;
            
            recalculateBtn.addEventListener('click', () => {
                this.forceRecalculation();
            });
            
            placeOrderButton.parentNode.insertBefore(recalculateBtn, placeOrderButton);
        }
    }

    setupPlaceOrderButtonHandler() {
        const placeOrderButton = document.querySelector('#place_order');
        if (placeOrderButton) {
            placeOrderButton.addEventListener('click', (e) => {
                // Before placing order, ensure we have the latest calculation
                this.ensureLatestCalculation();
            });
        }
    }

    forceRecalculation() {
        console.log('Anar: Force recalculation triggered by user');
        
        // Show loading state
        const recalculateBtn = document.querySelector('#anar-recalculate-btn');
        if (recalculateBtn) {
            const originalText = recalculateBtn.innerHTML;
            recalculateBtn.innerHTML = '⏳ در حال محاسبه...';
            recalculateBtn.disabled = true;
            
            // Reset after update
            setTimeout(() => {
                recalculateBtn.innerHTML = originalText;
                recalculateBtn.disabled = false;
            }, 2000);
        }
        
        // Trigger multiple update methods
        this.triggerImmediateUpdate();
        this.triggerManualUpdate();
        
        // Also trigger a delayed update to catch any async changes
        setTimeout(() => {
            this.triggerImmediateUpdate();
        }, 500);
    }

    ensureLatestCalculation() {
        console.log('Anar: Ensuring latest calculation before place order');
        
        // Check if any select fields have values but haven't triggered updates
        const selectFields = document.querySelectorAll('form.checkout select');
        let needsUpdate = false;
        
        selectFields.forEach(field => {
            if (field.value && !this.hasUserInteraction) {
                needsUpdate = true;
            }
        });
        
        if (needsUpdate) {
            console.log('Anar: Detected unprocessed select changes, triggering update');
            this.triggerImmediateUpdate();
            
            // Wait a bit for the update to complete
            return new Promise(resolve => {
                setTimeout(() => {
                    resolve();
                }, 1000);
            });
        }
    }

    debounce(func, wait) {
        let timeout;
        return function executedFunction(...args) {
            const later = () => {
                clearTimeout(timeout);
                func(...args);
            };
            clearTimeout(timeout);
            timeout = setTimeout(later, wait);
        };
    }
}

// Initialize when DOM is ready
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', () => {
        new CheckoutAutofillHandler();
    });
} else {
    new CheckoutAutofillHandler();
}

// Also initialize after a short delay to catch late-loading forms
setTimeout(() => {
    if (!window.anarCheckoutAutofillHandler) {
        window.anarCheckoutAutofillHandler = new CheckoutAutofillHandler();
    }
}, 1000);

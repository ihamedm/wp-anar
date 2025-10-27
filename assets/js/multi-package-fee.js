/**
 * Multi-package Fee Settings Handler
 * Handles the display and toggle logic for multi-package fee options
 */

jQuery(document).ready(function($) {
    const $anarShippingSwitch = $('#anar_conf_feat__anar_shipping');
    const $enableMultiPackageFee = $('#anar_enable_multi_package_fee');
    const $showMultiPackageAlert = $('#anar_show_multi_package_alert');
    const $multiPackageWrapper = $('#awca_multi_package_wrapper');
    const $feeMethodRadios = $('input[name="anar_multi_package_fee_method"]');
    const $multiplierContent = $('#awca_multiplier_method_content');
    const $fixedFeeContent = $('#awca_fixed_fee_method_content');

    /**
     * Update switch label text
     */
    function updateSwitchLabel($checkbox, $label) {
        if ($checkbox.is(':checked')) {
            $label.text('فعال');
        } else {
            $label.text('غیرفعال');
        }
    }

    /**
     * Toggle visibility of multi-package options based on main enable/disable switch
     */
    function toggleMultiPackageOptions() {
        const isEnabled = $enableMultiPackageFee.is(':checked');
        const $label = $enableMultiPackageFee.siblings('.awca-switch-label');
        
        updateSwitchLabel($enableMultiPackageFee, $label);
        
        if (isEnabled) {
            $multiPackageWrapper.slideDown(300);
            // Show the active tab content
            updateFeeMethodDisplay();
        } else {
            $multiPackageWrapper.slideUp(300);
        }
    }

    /**
     * Update the display of fee method content based on selected radio button
     */
    function updateFeeMethodDisplay() {
        const selectedMethod = $('input[name="anar_multi_package_fee_method"]:checked').val();
        
        // Hide all content areas
        $('.awca-fee-method-content').removeClass('active');
        
        // Show the selected content area
        if (selectedMethod === 'multiplier') {
            $multiplierContent.addClass('active');
        } else if (selectedMethod === 'fixed') {
            $fixedFeeContent.addClass('active');
        }
    }

    /**
     * Initialize the display state on page load
     */
    function init() {
        // Set initial state for all switches
        if ($anarShippingSwitch.length) {
            const $label = $anarShippingSwitch.siblings('.awca-switch-label');
            updateSwitchLabel($anarShippingSwitch, $label);
        }
        
        if ($showMultiPackageAlert.length) {
            const $label = $showMultiPackageAlert.siblings('.awca-switch-label');
            updateSwitchLabel($showMultiPackageAlert, $label);
        }
        
        // Set initial state for multi-package fee
        toggleMultiPackageOptions();
        
        // If multi-package is enabled, ensure correct tab is shown
        if ($enableMultiPackageFee.is(':checked')) {
            updateFeeMethodDisplay();
        }
    }

    // Event listeners for switches
    $anarShippingSwitch.on('change', function() {
        const $label = $(this).siblings('.awca-switch-label');
        updateSwitchLabel($(this), $label);
    });

    $showMultiPackageAlert.on('change', function() {
        const $label = $(this).siblings('.awca-switch-label');
        updateSwitchLabel($(this), $label);
    });

    $enableMultiPackageFee.on('change', toggleMultiPackageOptions);
    $feeMethodRadios.on('change', updateFeeMethodDisplay);

    // Initialize on page load
    init();
});


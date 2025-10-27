/**
 * Ship to Stock Module
 * 
 * Handles the ship-to-stock functionality for Anar orders
 * Manages order type selection, address form switching, and auto-saving
 */

import { awca_toast, createAnarOrder } from './index';
import MicroModal from 'micromodal';

/**
 * Initialize ship-to-stock functionality
 */
export function initShipToStock($) {
    // Only proceed if the order type section exists (ship-to-stock is enabled)
    if ($('.anar-order-type-section').length === 0) {
        return;
    }

    // Initialize flags
    window.stockAddressChanged = false;

    // Check if order type is forced to retail (can't ship to stock)
    const isForcedRetail = $('input[name="order_type"][type="hidden"]').length > 0;
    
    if (isForcedRetail) {
        // Order can't ship to stock - show only customer address
        loadCustomerAddress($);
        $('#customer-address-section').show();
        $('#stock-address-section').hide();
        $('#stock-address-display-section').hide();
        $('#no-stock-address-section').hide();
        $('.stock-shipping-fee').hide();
        return;
    }

    // Load customer address when modal opens
    loadCustomerAddress($);
    
    // Load stock address if exists
    loadStockAddress($);
    
    // Load shipping fee when modal opens
    loadShippingFee($);
    
    // Handle order type radio button changes
    $('input[name="order_type"]').on('change', function() {
        handleOrderTypeChange($, $(this).val());
    });

    // Handle form submission to include order type
    $('#awca-create-anar-order').on('click', function(e) {
        handleOrderCreation($, e);
    });

    // Validate stock address form
    $('#stock-address-section input, #stock-address-section textarea').on('blur', function() {
        validateStockAddressField($(this));
    });

    // Handle edit address link
    $('#edit-stock-address-btn').on('click', function(e) {
        e.preventDefault();
        showStockAddressForm($);
    });

    // Handle add address link
    $('#add-stock-address-btn').on('click', function(e) {
        e.preventDefault();
        showStockAddressForm($);
    });

    // Handle form changes to set the flag
    $('#stock-address-section input, #stock-address-section textarea').on('input change', function() {
        window.stockAddressChanged = true;
    });
}

/**
 * Load and display customer address
 */
function loadCustomerAddress($) {
    const orderId = $('#awca-create-anar-order').data('order-id');
    if (!orderId) return;

    // Make AJAX call to get order details
    $.ajax({
        url: ajaxurl,
        type: 'POST',
        data: {
            action: 'awca_get_order_address_ajax',
            order_id: orderId,
            nonce: $('#awca_nonce_field').val()
        },
        success: function(response) {
            if (response.success) {
                $('#customer-address-display').html(response.data.address);
            } else {
                $('#customer-address-display').html('<span style="color: #e74c3c;">خطا در دریافت آدرس مشتری</span>');
            }
        },
        error: function() {
            $('#customer-address-display').html('<span style="color: #e74c3c;">خطا در دریافت آدرس مشتری</span>');
        }
    });
}

/**
 * Load and display stock address if exists
 */
function loadStockAddress($) {
    $.ajax({
        url: ajaxurl,
        type: 'POST',
        data: {
            action: 'awca_load_stock_address_ajax',
            awca_nonce_field: $('#awca_nonce_field').val()
        },
        success: function(response) {
            if (response.success) {
                $('#stock-address-display').html(response.data.address);
                // Store the data for form population
                window.stockAddressData = response.data.data;
            } else {
                // No saved address
                window.stockAddressData = null;
            }
        },
        error: function() {
            window.stockAddressData = null;
        }
    });
}

/**
 * Load shipping fee information
 */
function loadShippingFee($) {
    $.ajax({
        url: ajaxurl,
        type: 'POST',
        data: {
            action: 'awca_get_shipping_fee_ajax',
            awca_nonce_field: $('#awca_nonce_field').val()
        },
        success: function(response) {
            if (response.success) {
                // Update all shipping fee elements
                $('.stock-shipping-fee').html(response.data.message);
            } else {
                awca_toast(response.data.message || 'خطا در دریافت اطلاعات هزینه ارسال', 'error');
            }
        },
        error: function() {
            awca_toast('خطا در ارتباط با سرور برای دریافت هزینه ارسال', 'error');
        }
    });
}

/**
 * Handle order type radio button change
 * @param {jQuery} $ - jQuery instance
 * @param {string} orderType - The selected order type (retail or wholesale)
 */
function handleOrderTypeChange($, orderType) {
    if (orderType === 'retail') {
        // Show customer address, hide stock address sections
        $('#customer-address-section').show();
        $('#stock-address-section').hide();
        $('#stock-address-display-section').hide();
        $('#no-stock-address-section').hide();
        
        // Hide shipping fee for retail orders
        $('.stock-shipping-fee').hide();
        
        // Clear stock address form validation
        clearStockAddressValidation($);
    } else if (orderType === 'wholesale') {
        // Hide customer address
        $('#customer-address-section').hide();
        
        // Show shipping fee for wholesale orders
        $('.stock-shipping-fee').show();
        
        // Show appropriate stock address section
        if (window.stockAddressData) {
            showStockAddressDisplay($);
        } else {
            showNoStockAddressState($);
        }
    }
}

/**
 * Show stock address display section
 */
function showStockAddressDisplay($) {
    $('#stock-address-display-section').show();
    $('#stock-address-section').hide();
    $('#no-stock-address-section').hide();
}

/**
 * Show no stock address state
 */
function showNoStockAddressState($) {
    $('#no-stock-address-section').show();
    $('#stock-address-display-section').hide();
    $('#stock-address-section').hide();
}

/**
 * Show stock address form section
 */
function showStockAddressForm($) {
    $('#stock-address-display-section').hide();
    $('#no-stock-address-section').hide();
    $('#stock-address-section').show();
    
    // Populate form with saved data if available
    if (window.stockAddressData) {
        populateStockAddressForm($, window.stockAddressData);
    }
    
    // Set the flag when form is shown
    window.stockAddressChanged = true;
}

/**
 * Populate stock address form with data
 * @param {jQuery} $ - jQuery instance
 * @param {Object} data - Stock address data
 */
function populateStockAddressForm($, data) {
    $('#stock_first_name').val(data.first_name || '');
    $('#stock_last_name').val(data.last_name || '');
    $('#stock_state').val('تهران'); // Always set to Tehran
    $('#stock_city').val('تهران'); // Always set to Tehran
    $('#stock_address').val(data.address || '');
    $('#stock_postcode').val(data.postcode || '');
    $('#stock_phone').val(data.phone || '');
}

/**
 * Handle order creation with order type
 * @param {jQuery} $ - jQuery instance
 * @param {Event} e - Click event
 */
function handleOrderCreation($, e) {
    // Check if order type is forced to retail
    const hiddenOrderType = $('input[name="order_type"][type="hidden"]').val();
    const orderType = hiddenOrderType || $('input[name="order_type"]:checked').val();
    
    if (orderType === 'wholesale') {
        // For wholesale orders, save address first, then create order
        e.preventDefault();
        saveStockAddressAndCreateOrder($);
    } else {
        // For retail orders, proceed normally
        e.preventDefault();
        createAnarOrderWithType($, orderType);
    }
}

/**
 * Save stock address and then create order
 */
function saveStockAddressAndCreateOrder($) {
    // If we're in display mode, use saved address
    if ($('#stock-address-display-section').is(':visible')) {
        // Address is already saved, just create order
        createAnarOrderWithType($, 'wholesale');
        return;
    }

    // If we're in form mode, validate first
    if (!validateStockAddressForm($)) {
        return;
    }

    // Only save if the flag is true (form was shown/edited)
    if (window.stockAddressChanged) {
        const formData = {
            action: 'awca_save_stock_address_ajax',
            awca_nonce_field: $('#awca_nonce_field').val(),
            stock_first_name: $('#stock_first_name').val(),
            stock_last_name: $('#stock_last_name').val(),
            stock_state: $('#stock_state').val(),
            stock_city: $('#stock_city').val(),
            stock_address: $('#stock_address').val(),
            stock_postcode: $('#stock_postcode').val(),
            stock_phone: $('#stock_phone').val()
        };

        // Show loading state
        const $button = $('#awca-create-anar-order');
        const originalText = $button.html();
        $button.prop('disabled', true).html('در حال ذخیره آدرس و ایجاد سفارش...');

        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: formData,
            success: function(response) {
                if (response.success) {
                    // Address saved successfully, now create order
                    createAnarOrderWithType($, 'wholesale');
                } else {
                    awca_toast(response.data.message || 'خطا در ذخیره آدرس', 'error');
                    $button.prop('disabled', false).html(originalText);
                }
            },
            error: function() {
                awca_toast('خطا در ارتباط با سرور', 'error');
                $button.prop('disabled', false).html(originalText);
            }
        });
    } else {
        // No changes made, just create order
        createAnarOrderWithType($, 'wholesale');
    }
}

/**
 * Create Anar order with order type and address data
 * @param {jQuery} $ - jQuery instance
 * @param {string} orderType - The order type (retail or wholesale)
 */
function createAnarOrderWithType($, orderType) {
    const orderId = $('#awca-create-anar-order').data('order-id');
    
    // Use the reusable createAnarOrder function
    createAnarOrder($, {
        orderId: orderId,
        orderType: orderType
    });
}

/**
 * Validate stock address form
 * @param {jQuery} $ - jQuery instance
 * @returns {boolean} True if form is valid, false otherwise
 */
function validateStockAddressForm($) {
    let isValid = true;
    const requiredFields = [
        'stock_first_name',
        'stock_last_name', 
        'stock_address',
        'stock_postcode',
        'stock_phone'
    ];

    // Clear previous validation
    clearStockAddressValidation($);

    requiredFields.forEach(function(fieldId) {
        const field = $('#' + fieldId);
        if (!validateStockAddressField($, field)) {
            isValid = false;
        }
    });
    
    // Validate hidden fields (state and city are auto-set to Tehran)
    const stateField = $('#stock_state');
    const cityField = $('#stock_city');
    
    if (stateField.length && stateField.val() !== 'تهران') {
        stateField.val('تهران');
    }
    
    if (cityField.length && cityField.val() !== 'تهران') {
        cityField.val('تهران');
    }

    // Special validation for postcode (10 digits)
    const postcode = $('#stock_postcode').val();
    if (postcode && !/^\d{10}$/.test(postcode)) {
        showFieldError($, '#stock_postcode', 'کد پستی باید ۱۰ رقم باشد');
        isValid = false;
    }

    // Special validation for phone number
    const phone = $('#stock_phone').val();
    if (phone && !/^09\d{9}$/.test(phone)) {
        showFieldError($, '#stock_phone', 'شماره موبایل باید با ۰۹ شروع شده و ۱۱ رقم باشد');
        isValid = false;
    }

    if (!isValid) {
        awca_toast('لطفاً تمام فیلدهای آدرس انبار را به درستی پر کنید', 'error');
    }

    return isValid;
}

/**
 * Validate individual stock address field
 * @param {jQuery} $ - jQuery instance
 * @param {jQuery} field - The field to validate
 * @returns {boolean} True if field is valid, false otherwise
 */
function validateStockAddressField($, field) {
    const value = field.val().trim();
    
    if (!value) {
        showFieldError($, field, 'این فیلد اجباری است');
        return false;
    }

    // Clear any existing error for this field
    clearFieldError($, field);
    return true;
}

/**
 * Show field error
 * @param {jQuery} $ - jQuery instance
 * @param {string|jQuery} field - Field selector or jQuery object
 * @param {string} message - Error message
 */
function showFieldError($, field, message) {
    const $field = typeof field === 'string' ? $(field) : field;
    
    // Remove existing error
    clearFieldError($, $field);
    
    // Add error styling
    $field.css('border-color', '#e74c3c');
    
    // Add error message
    const errorDiv = $('<div class="field-error" style="color: #e74c3c; font-size: 12px; margin-top: 5px;">' + message + '</div>');
    $field.after(errorDiv);
}

/**
 * Clear field error
 * @param {jQuery} $ - jQuery instance
 * @param {jQuery} field - The field to clear error for
 */
function clearFieldError($, field) {
    field.css('border-color', '');
    field.siblings('.field-error').remove();
}

/**
 * Clear all stock address validation
 * @param {jQuery} $ - jQuery instance
 */
function clearStockAddressValidation($) {
    $('#stock-address-section input, #stock-address-section textarea').each(function() {
        clearFieldError($, $(this));
    });
}

/**
 * Preorder Modal Module
 * 
 * Handles the preorder modal functionality including shipping options
 */

import MicroModal from 'micromodal';
import { awca_toast } from './index';

export function initPreorderModal($) {
    // Handle pre-order modal open button
    const openPreorderModal = $('#awca-open-preorder-modal');
    if (openPreorderModal.length !== 0) {
        openPreorderModal.on('click', function(e) {
            e.preventDefault();
            try {
                MicroModal.show('preorder-modal');
                // Load shipping options when modal opens
                loadShippingOptions($);
            } catch (e) {
                console.error('MicroModal failed to show preorder modal:', e);
            }
        });
    }

    // Handle order type change
    $('input[name="order_type"]').on('change', function() {
        const orderType = $(this).val();
        toggleOrderTypeSections($, orderType);
    });

    // Handle create order button
    $('#awca-create-anar-order').on('click', function(e) {
        e.preventDefault();
        createAnarOrder($);
    });
}

/**
 * Load shipping options for retail orders
 */
function loadShippingOptions($) {
    const orderId = $('#awca-create-anar-order').data('order-id');
    
    if (!orderId) {
        return;
    }

    const shippingOptionsDisplay = $('#shipping-options-display');
    shippingOptionsDisplay.html('<div class="anar-bg-loading animated" style="padding:16px;border-radius: 10px"><span class="text">در حال بارگذاری روش‌های ارسال...</span></div>');

    $.ajax({
        url: awca_ajax_object.ajax_url,
        type: 'POST',
        dataType: 'json',
        data: {
            action: 'awca_get_shipping_options_ajax',
            order_id: orderId
        },
        success: function(response) {
            if (response.success) {
                displayShippingOptions($, response.data.shipping_html, response.data.shipping_heading);
            } else {
                shippingOptionsDisplay.html('<div class="error">خطا در بارگذاری روش‌های ارسال: ' + response.data.message + '</div>');
            }
        },
        error: function(xhr, status, error) {
            shippingOptionsDisplay.html('<div class="error">خطا در بارگذاری روش‌های ارسال</div>');
        }
    });
}

/**
 * Display shipping options in the modal
 */
function displayShippingOptions($, shippingHtml, shippingHeading) {
    const shippingOptionsDisplay = $('#shipping-options-display');
    const shippingOptionsSection = $('#shipping-options-section');
    
    // Update the heading
    if (shippingHeading) {
        shippingOptionsSection.find('h5').html(shippingHeading);
    }
    
    // Update the content
    shippingOptionsDisplay.html(shippingHtml);
    
    // Initialize total calculation and event listeners with a small delay
    setTimeout(function() {
        calculateTotalShippingFee($);
        attachShippingOptionListeners($);
    }, 100);
}

/**
 * Toggle sections based on order type
 */
function toggleOrderTypeSections($, orderType) {
    const customerAddressSection = $('#customer-address-section');
    const shippingOptionsSection = $('#shipping-options-section');
    const stockAddressSection = $('#stock-address-display-section');
    const noStockAddressSection = $('#no-stock-address-section');
    const stockAddressFormSection = $('#stock-address-section');
    
    if (orderType === 'retail') {
        customerAddressSection.show();
        shippingOptionsSection.show();
        stockAddressSection.hide();
        noStockAddressSection.hide();
        stockAddressFormSection.hide();
    } else {
        customerAddressSection.hide();
        shippingOptionsSection.hide();
        stockAddressSection.show();
        noStockAddressSection.show();
        stockAddressFormSection.show();
    }
}

/**
 * Create Anar order
 */
function createAnarOrder($) {
    const orderId = $('#awca-create-anar-order').data('order-id');
    const orderType = $('input[name="order_type"]:checked').val();
    
    if (!orderId) {
        awca_toast('خطا: شناسه سفارش یافت نشد', 'error');
        return;
    }
    
    // Validate shipping options for retail orders
    if (orderType === 'retail') {
        const hasShippingSelection = $('input[name^="shipping_option_"]:checked').length > 0;
        if (!hasShippingSelection) {
            awca_toast('لطفاً روش ارسال را انتخاب کنید', 'error');
            return;
        }
    }
    
    const button = $('#awca-create-anar-order');
    const loadingIcon = button.find('.spinner-loading');
    
    // Show loading
    loadingIcon.show();
    button.prop('disabled', true);
    
    // Prepare form data
    const formData = {
        action: 'awca_create_anar_order_ajax',
        order_id: orderId,
        order_type: orderType
    };
    
    // Add shipping options to form data
    $('input[name^="shipping_option_"]:checked').each(function() {
        const name = $(this).attr('name');
        const value = $(this).val();
        formData[name] = value;
    });
    
    // Add shipment IDs
    $('input[name^="shipment_id_"]').each(function() {
        const name = $(this).attr('name');
        const value = $(this).val();
        formData[name] = value;
    });
    
    // Add total shipping fee
    const totalFee = $('#anar-total-shipping-fee').text().replace(/[^\d.]/g, '') || '0';
    formData['total_shipping_fee'] = totalFee;
    
    $.ajax({
        url: awca_ajax_object.ajax_url,
        type: 'POST',
        dataType: 'json',
        data: formData,
        success: function(response) {
            if (response.success) {
                awca_toast(response.data.message, 'success');
                // Close modal and reload page
                MicroModal.close('preorder-modal');
                setTimeout(() => {
                    location.reload();
                }, 1500);
            } else {
                awca_toast(response.data.message, 'error');
            }
        },
        error: function(xhr, status, error) {
            awca_toast('خطا در ایجاد سفارش', 'error');
            console.error('Error creating order:', error);
        },
        complete: function() {
            loadingIcon.hide();
            button.prop('disabled', false);
        }
    });
}

/**
 * Calculate total shipping fee based on selected options
 */
function calculateTotalShippingFee($) {
    let totalFee = 0;
    
    // Check if we have any packages
    const $packages = $('.anar-shipments-package-row');
    
    if ($packages.length === 0) {
        return;
    }
    
    // Find all selected shipping options
    $packages.each(function(index) {
        const $package = $(this);
        let $selectedOption = $package.find('input[name^="shipping_option_"]:checked');
        
        // If no option is selected, select the first one by default
        if ($selectedOption.length === 0) {
            const $allOptions = $package.find('input[name^="shipping_option_"]');
            $selectedOption = $allOptions.first();
            if ($selectedOption.length > 0) {
                $selectedOption.prop('checked', true);
            }
        }
        
        if ($selectedOption.length > 0) {
            // Get the label and price element
            const $label = $selectedOption.closest('label');
            const $priceElement = $label.find('.price');
            
            // Try alternative approaches to find the price
            if ($priceElement.length === 0) {
                // Find the price element that corresponds to the selected radio button
                // The radio button and its corresponding price are in the same container
                const $selectedContainer = $selectedOption.closest('.anar-delivery-option');
                
                if ($selectedContainer.length > 0) {
                    const $containerPrice = $selectedContainer.find('.price');
                    
                    if ($containerPrice.length > 0) {
                        const rawPrice = $containerPrice.data('raw-price');
                        
                        if (rawPrice !== undefined && rawPrice !== null) {
                            const price = parseFloat(rawPrice) || 0;
                            totalFee += price;
                        }
                    }
                } else {
                    // Fallback: find price in siblings
                    const $siblingPrice = $selectedOption.siblings().find('.price');
                    
                    if ($siblingPrice.length > 0) {
                        const rawPrice = $siblingPrice.data('raw-price');
                        
                        if (rawPrice !== undefined && rawPrice !== null) {
                            const price = parseFloat(rawPrice) || 0;
                            totalFee += price;
                        }
                    }
                }
            } else {
                // Get raw price from data attribute
                const rawPrice = $priceElement.data('raw-price');
                
                if (rawPrice !== undefined && rawPrice !== null) {
                    const price = parseFloat(rawPrice) || 0;
                    totalFee += price;
                }
            }
        }
    });
    
    // Update total display
    const $totalDisplay = $('#anar-total-shipping-fee');
    
    if ($totalDisplay.length > 0) {
        const currentText = $totalDisplay.text();
        
        // Extract currency symbol from the original display
        const currencySymbol = currentText.match(/[^\d.\s]+$/)?.[0] || '';
        
        // Format the total fee with Persian numbers and currency symbol
        const formattedTotal = totalFee.toLocaleString('fa-IR') + ' ' + currencySymbol;
        
        $totalDisplay.text(formattedTotal);
    }
}

/**
 * Attach event listeners to shipping options
 */
function attachShippingOptionListeners($) {
    const $packages = $('.anar-shipments-package-row');
    
    // Remove existing listeners to avoid duplicates
    $packages.off('change', 'input[name^="shipping_option_"]');
    
    // Attach change listeners to all shipping option radio buttons
    $packages.on('change', 'input[name^="shipping_option_"]', function() {
        calculateTotalShippingFee($);
    });
}

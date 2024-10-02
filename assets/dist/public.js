/*
 * ATTENTION: The "eval" devtool has been used (maybe by default in mode: "development").
 * This devtool is neither made for production nor for readable output files.
 * It uses "eval()" calls to create a separate source file in the browser devtools.
 * If you are trying to read the output file, select a different devtool (https://webpack.js.org/configuration/devtool/)
 * or disable the default devtool with "devtool: false".
 * If you are looking for production-ready output files, see mode: "production" (https://webpack.js.org/configuration/mode/).
 */
/******/ (() => { // webpackBootstrap
/******/ 	var __webpack_modules__ = ({

/***/ "./assets/js/public.js":
/*!*****************************!*\
  !*** ./assets/js/public.js ***!
  \*****************************/
/***/ (() => {

eval("jQuery(document).ready(function ($) {\n  var tooltip = jQuery('<div class=\"awca-tooltip\" style=\"position: absolute; background-color: black; color: white; padding: 5px 10px; border-radius: 4px; font-size: 12px; display: none; z-index: 1000;\"></div>');\n  jQuery('body').append(tooltip);\n\n  // Use event delegation to handle mouseenter and mouseleave\n  jQuery(document).on('mouseenter', '.awca-tooltip-on', function () {\n    // Get the title attribute\n    var title = jQuery(this).attr('title');\n    // Set the tooltip text\n    tooltip.text(title);\n\n    // Calculate the right offset for the tooltip\n    var elementRightOffset = jQuery(this).offset().left + jQuery(this).outerWidth();\n    var tooltipWidth = tooltip.outerWidth();\n    tooltip.css({\n      display: 'block',\n      left: elementRightOffset - tooltipWidth + 'px',\n      top: jQuery(this).offset().top - tooltip.outerHeight() + 'px'\n    });\n  });\n  jQuery(document).on('mouseleave', '.awca-tooltip-on', function () {\n    // Hide the tooltip\n    tooltip.hide();\n  });\n  jQuery('.anar-delivery-option input[type=\"radio\"]').on('change', function () {\n    jQuery('.anar-delivery-option').removeClass('selected');\n    jQuery(this).closest('.anar-delivery-option').addClass('selected');\n  });\n  function ensureRadioSelection() {\n    $('input[type=\"radio\"][data-input-group]').each(function () {\n      var inputGroup = $(this).data('input-group');\n      var radios = $('input[data-input-group=\"' + inputGroup + '\"]');\n      // If none are checked, set the first one as checked\n\n      if (radios.filter(':checked').length === 0) {\n        radios.first().prop('checked', true);\n      }\n    });\n  }\n  function validateRadioSelectionOnOrder() {\n    $('#place_order').on('click', function (e) {\n      var allChecked = true;\n\n      // Check for each group if a radio button is checked\n      $('input[type=\"radio\"][data-input-group]').each(function () {\n        var inputGroup = $(this).data('input-group');\n        var radios = $('input[data-input-group=\"' + inputGroup + '\"]');\n        if (radios.filter(':checked').length === 0) {\n          allChecked = false;\n          return false; // Break out of the .each loop\n        }\n      });\n\n      // If any group has no radio selected, alert the user\n      if (!allChecked) {\n        e.preventDefault(); // Prevent the form from submitting\n        alert('Please select a delivery option before proceeding.');\n      }\n    });\n  }\n\n  // Execute functions on WooCommerce updated_checkout event\n  $(document.body).on('updated_checkout', function () {\n    console.log('wc updated checkout');\n    ensureRadioSelection();\n    validateRadioSelectionOnOrder();\n  });\n\n  // Ensure one radio is selected and validation on first load\n  ensureRadioSelection();\n  validateRadioSelectionOnOrder();\n  var anarOrderDetails = $('#anar-order-details-front');\n  if (anarOrderDetails.length !== 0) {\n    var loadingIcon = anarOrderDetails.find('.spinner-loading');\n    var OrderID = anarOrderDetails.data('order-id');\n    var msgType = 'error';\n    jQuery.ajax({\n      url: awca_ajax_object.ajax_url,\n      type: \"POST\",\n      dataType: \"json\",\n      data: {\n        order_id: OrderID,\n        action: 'awca_fetch_order_details_public_ajax'\n      },\n      beforeSend: function beforeSend() {\n        loadingIcon.show();\n      },\n      success: function success(response) {\n        console.log(response);\n        if (response.success) {\n          anarOrderDetails.html(response.data.output);\n        }\n      },\n      error: function error(xhr, status, err) {\n        anarOrderDetails.text(xhr.responseText);\n        loadingIcon.hide();\n      },\n      complete: function complete() {\n        loadingIcon.hide();\n      }\n    });\n  }\n});\n\n//# sourceURL=webpack://wp-anar/./assets/js/public.js?");

/***/ })

/******/ 	});
/************************************************************************/
/******/ 	
/******/ 	// startup
/******/ 	// Load entry module and return exports
/******/ 	// This entry module can't be inlined because the eval devtool is used.
/******/ 	var __webpack_exports__ = {};
/******/ 	__webpack_modules__["./assets/js/public.js"]();
/******/ 	
/******/ })()
;
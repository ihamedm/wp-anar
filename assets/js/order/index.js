/**
 * Order Management Module
 * 
 * Main entry point for order-related functionality
 * Imports and initializes all order modules
 */

import {awca_toast} from "../functions";
import MicroModal from 'micromodal';
import { initPreorderModal } from './preorder-modal';
import { initOrderCreation, createAnarOrder } from './order-creation';
import { initOrderDetails } from './order-details';
import { initPackageToggle } from './package-toggle';
import { initShipToStock } from './ship-to-stock';

// Initialize MicroModal
try {
    MicroModal.init({
        openTrigger: 'data-payment-modal-open',
        disableFocus: false,
        disableScroll: true,
        awaitCloseAnimation: true
    });
} catch (e) {
    console.error('MicroModal failed to initialize:', e);
}

jQuery(document).ready(function($) {
    $(document).on('click', '.modal__container', function(e) {
        e.stopPropagation();
    });
});

// Initialize all order modules
jQuery(document).ready(function($) {
    initPreorderModal($);
    initOrderCreation($);
    initOrderDetails($);
    initPackageToggle($);
    initShipToStock($);
});

export { awca_toast, createAnarOrder };

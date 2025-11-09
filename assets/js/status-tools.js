/**
 * Status and Tools Management
 * 
 * This file imports and initializes all tool modules for the admin status page.
 * Each module handles a specific set of functionality to keep the code organized and maintainable.
 */

import MicroModal from 'micromodal';

// Import all tool modules
import { initIndexOptimization } from './tools/index-optimization';
import { initSyncOperations } from './tools/sync-operations';
import { initPerformanceTesting } from './tools/performance-testing';
import { initReportModals } from './tools/generic-modal';
import { initReportWidgets } from './tools/report-widgets';

// Initialize MicroModal and all tool modules
jQuery(document).ready(function($) {
    // Initialize MicroModal for modal functionality
    MicroModal.init();

    // Initialize all tool modules
    initIndexOptimization();
    initSyncOperations();
    initPerformanceTesting();
    initReportModals(); // This handles all report modals generically
    initReportWidgets(); // This handles all report widgets
});

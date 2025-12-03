import $ from 'jquery';
import MicroModal from 'micromodal';
import { awca_toast } from "../functions";

/**
 * Log Preview Manager
 * Handles log file preview functionality
 */
export class LogPreviewManager {
    constructor() {
        this.currentFileId = null;
        this.currentFilename = null;
        this.autoRefreshInterval = null;
        this.autoRefreshEnabled = false;
        this.init();
    }

    init() {
        this.bindEvents();
    }

    bindEvents() {
        // Handle preview button clicks
        $(document).on('click', '.anar-preview-log', (e) => {
            e.preventDefault();
            const $button = $(e.currentTarget);
            const fileId = $button.data('file-id');
            const filename = $button.data('filename');
            
            this.previewLogFile(fileId, filename);
        });

        // Handle refresh button click
        $(document).on('click', '#anar-log-preview-refresh', (e) => {
            e.preventDefault();
            if (this.currentFileId && this.currentFilename) {
                this.refreshLogFile(true); // false = manual refresh, don't force scroll to bottom
            }
        });

        // Handle auto-refresh checkbox change
        $(document).on('change', '#anar-log-preview-auto-refresh', (e) => {
            const isChecked = $(e.target).is(':checked');
            this.toggleAutoRefresh(isChecked);
        });
    }

    /**
     * Preview a log file
     */
    previewLogFile(fileId, filename) {
        // Store current file info for refresh functionality
        this.currentFileId = fileId;
        this.currentFilename = filename;

        const $modal = $('#anar-log-preview-modal');
        const $loading = $('#anar-log-preview-loading');
        const $content = $('#anar-log-preview-content');
        const $error = $('#anar-log-preview-error');
        const $text = $('#anar-log-preview-text');
        const $filename = $('#anar-log-preview-filename');
        const $size = $('#anar-log-preview-size');
        const $lines = $('#anar-log-preview-lines');
        const $truncated = $('#anar-log-preview-truncated');

        // Reset modal state
        $loading.show();
        $content.hide();
        $error.hide();
        $text.text('');
        $filename.text('');
        $size.text('');
        $lines.text('');
        $truncated.hide();

        // Reset auto-refresh checkbox
        $('#anar-log-preview-auto-refresh').prop('checked', false);
        this.stopAutoRefresh();

        // Open modal
        MicroModal.show('anar-log-preview-modal', {
            onClose: () => {
                // Clean up on close
                $text.text('');
                this.currentFileId = null;
                this.currentFilename = null;
                this.stopAutoRefresh();
                $('#anar-log-preview-auto-refresh').prop('checked', false);
            }
        });

        // Load log file content
        this.loadLogContent(fileId);
    }

    /**
     * Refresh the current log file
     * @param {boolean} forceScrollToBottom - If true, always scroll to bottom (for auto-refresh)
     */
    refreshLogFile(forceScrollToBottom = false) {
        console.log('refreshLogFile');
        if (!this.currentFileId) {
            return;
        }

        const $refreshButton = $('#anar-log-preview-refresh');
        const $refreshIcon = $refreshButton.find('.dashicons');

        // Show loading state on refresh button only for manual refresh
        if (!forceScrollToBottom) {
            $refreshButton.prop('disabled', true);
            $refreshIcon.addClass('spin');
        }

        // Load log content
        this.loadLogContent(this.currentFileId, () => {
            // Restore button state only for manual refresh
            if (!forceScrollToBottom) {
                $refreshButton.prop('disabled', false);
                $refreshIcon.removeClass('spin');
            }
            console.log('loadLogContent');
        }, forceScrollToBottom);
    }

    /**
     * Toggle auto-refresh functionality
     * @param {boolean} enabled - Whether to enable auto-refresh
     */
    toggleAutoRefresh(enabled) {
        this.autoRefreshEnabled = enabled;

        if (enabled) {
            this.startAutoRefresh();
        } else {
            this.stopAutoRefresh();
        }
    }

    /**
     * Start auto-refresh interval
     */
    startAutoRefresh() {
        console.log('startAutoRefresh')
        // Clear any existing interval (but don't reset autoRefreshEnabled)
        if (this.autoRefreshInterval) {
            clearInterval(this.autoRefreshInterval);
            this.autoRefreshInterval = null;
        }

        if (!this.currentFileId) {
            return;
        }

        // Ensure autoRefreshEnabled is true
        this.autoRefreshEnabled = true;

        // Refresh immediately and then set interval
        this.refreshLogFile(true); // true = force scroll to bottom

        // Set interval to refresh every 5 seconds
        this.autoRefreshInterval = setInterval(() => {
            console.log('Interval tick - currentFileId:', this.currentFileId, 'autoRefreshEnabled:', this.autoRefreshEnabled);
            if (this.currentFileId && this.autoRefreshEnabled) {
                this.refreshLogFile(true); // true = force scroll to bottom
            } else {
                console.log('Stopping auto-refresh - conditions not met');
                this.clearAutoRefreshInterval();
            }
        }, 1000); // 5 seconds
    }

    /**
     * Stop auto-refresh interval (clears interval only, doesn't change enabled state)
     */
    clearAutoRefreshInterval() {
        console.log('clearAutoRefreshInterval')
        if (this.autoRefreshInterval) {
            clearInterval(this.autoRefreshInterval);
            this.autoRefreshInterval = null;
        }
    }

    /**
     * Stop auto-refresh interval and reset enabled state
     */
    stopAutoRefresh() {
        console.log('stopAutoRefresh')
        this.clearAutoRefreshInterval();
        this.autoRefreshEnabled = false;
    }

    /**
     * Load log file content via AJAX
     * @param {string} fileId - The file ID to load
     * @param {function} callback - Callback function to call after load
     * @param {boolean} forceScrollToBottom - If true, always scroll to bottom (for auto-refresh)
     */
    loadLogContent(fileId, callback = null, forceScrollToBottom = false) {
        const $loading = $('#anar-log-preview-loading');
        const $content = $('#anar-log-preview-content');
        const $error = $('#anar-log-preview-error');
        const $text = $('#anar-log-preview-text');
        const $filename = $('#anar-log-preview-filename');
        const $size = $('#anar-log-preview-size');
        const $lines = $('#anar-log-preview-lines');
        const $truncated = $('#anar-log-preview-truncated');

        // Show loading only if content is not already shown (for refresh)
        if (!$content.is(':visible')) {
            $loading.show();
            $content.hide();
        }
        $error.hide();

        // Make AJAX request to get log file content
        $.ajax({
            url: awca_ajax_object.ajax_url,
            type: 'POST',
            data: {
                action: 'anar_get_log_file_content',
                file_id: fileId,
                nonce: awca_ajax_object.nonce
            },
            success: (response) => {
                $loading.hide();

                if (response.success && response.data) {
                    const data = response.data;
                    
                    // Update modal info
                    $filename.text(data.filename);
                    $size.text(data.size_formatted);
                    $lines.text(data.lines.toLocaleString());
                    
                    if (data.truncated) {
                        $truncated.show();
                    } else {
                        $truncated.hide();
                    }
                    
                    // Store scroll position before updating content (only if not forcing scroll to bottom)
                    let scrollTop = 0;
                    let scrollHeight = 0;
                    let wasAtBottom = false;
                    
                    if (!forceScrollToBottom) {
                        scrollTop = $text.scrollTop();
                        scrollHeight = $text[0].scrollHeight;
                        wasAtBottom = scrollTop + $text.outerHeight() >= scrollHeight - 10;
                    }
                    
                    // Display log content
                    // Escape HTML but preserve line breaks
                    const escapedContent = this.escapeHtml(data.content);
                    $text.html(escapedContent);
                    
                    // Restore scroll position
                    if (forceScrollToBottom || wasAtBottom) {
                        // Always scroll to bottom for auto-refresh or if user was at bottom
                        $text.scrollTop($text[0].scrollHeight);
                    } else {
                        // Maintain scroll position for manual refresh
                        $text.scrollTop(scrollTop);
                    }
                    
                    // Show content (CSS will handle flex display)
                    $content.show();
                    
                    if (callback) {
                        callback();
                    }
                } else {
                    const errorMessage = response.data?.message || 'خطا در بارگذاری فایل لاگ';
                    $error.html(`<p><strong>خطا:</strong> ${this.escapeHtml(errorMessage)}</p>`).show();
                    awca_toast(errorMessage, 'error');
                    
                    if (callback) {
                        callback();
                    }
                }
            },
            error: (xhr, status, err) => {
                console.error('AJAX Error:', { xhr, status, err, responseText: xhr.responseText });
                $loading.hide();
                const errorMessage = `خطا در ارتباط با سرور: ${xhr.status} - ${xhr.statusText}`;
                $error.html(`<p><strong>خطا:</strong> ${this.escapeHtml(errorMessage)}</p>`).show();
                awca_toast('خطا در ارتباط با سرور', 'error');
                
                if (callback) {
                    callback();
                }
            }
        });
    }

    /**
     * Escape HTML characters
     */
    escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }
}

/**
 * Initialize log preview functionality
 */
export function initLogPreview() {
    new LogPreviewManager();
}


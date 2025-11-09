import {awca_toast} from "../functions";
import $ from 'jquery';

/**
 * Generic modal system for reports
 */
export class GenericModal {
    constructor(config) {
        this.config = {
            modalId: config.modalId,
            buttonId: config.buttonId,
            title: config.title,
            action: config.action,
            changeStatusAction: config.changeStatusAction,
            changeStatusText: config.changeStatusText,
            warningText: config.warningText,
            ...config
        };
        
        this.init();
    }

    init() {
        $(document).ready(() => {
            // Show modal
            $(`#${this.config.buttonId}`).on('click', (e) => {
                e.preventDefault();
                this.showModal();
            });

            // Change status action
            if (this.config.changeStatusAction) {
                $(`#${this.config.modalId}-change-status`).on('click', (e) => {
                    e.preventDefault();
                    this.changeStatus();
                });
            }
        });
    }

    showModal() {
        // Show loading state
        $(`#${this.config.modalId}-loading`).show();
        $(`#${this.config.modalId}-content`).hide();
        
        // Open MicroModal
        MicroModal.show(this.config.modalId);
        
        // Fetch data
        this.fetchData();
    }

    fetchData() {
        $.ajax({
            url: awca_ajax_object.ajax_url,
            type: 'POST',
            data: {
                action: this.config.action,
                nonce: awca_ajax_object.nonce
            },
            success: (response) => {
                if (response.success) {
                    this.handleSuccess(response.data);
                } else {
                    this.handleError(response.data);
                }
            },
            error: (xhr, status, err) => {
                this.handleError('خطا در ارتباط با سرور');
            }
        });
    }

    handleSuccess(data) {
        // Update counts
        if (data.count !== undefined) {
            $(`#${this.config.modalId}-count`).text(data.count);
        }
        if (data.total_products !== undefined) {
            $(`#${this.config.modalId}-total`).text(data.total_products);
        }
        
        // Update content
        $(`#${this.config.modalId}-list`).html(data.html);
        
        // Show/hide change status button
        if (this.config.changeStatusAction) {
            if (data.count > 0) {
                $(`#${this.config.modalId}-change-status`).show();
            } else {
                $(`#${this.config.modalId}-change-status`).hide();
            }
        }
        
        // Hide loading, show content
        $(`#${this.config.modalId}-loading`).hide();
        $(`#${this.config.modalId}-content`).show();
    }

    handleError(errorMessage) {
        $(`#${this.config.modalId}-loading`).hide();
        $(`#${this.config.modalId}-content`).html(
            `<p style="text-align: center; color: #dc3232; padding: 20px;">خطا در دریافت اطلاعات: ${errorMessage}</p>`
        ).show();
    }

    changeStatus() {
        if (!confirm(this.config.warningText || 'آیا مطمئن هستید؟')) {
            return;
        }
        
        const $button = $(`#${this.config.modalId}-change-status`);
        const originalText = $button.html();
        
        // Disable button and show loading
        $button.prop('disabled', true).html('<span class="dashicons dashicons-update"></span> در حال تغییر وضعیت...');
        
        $.ajax({
            url: awca_ajax_object.ajax_url,
            type: 'POST',
            data: {
                action: this.config.changeStatusAction,
                nonce: awca_ajax_object.nonce
            },
            success: (response) => {
                if (response.success) {
                    awca_toast(response.data.message, 'success');
                    // Refresh the data
                    this.fetchData();
                } else {
                    awca_toast(response.data.message || 'خطا در تغییر وضعیت', 'error');
                }
            },
            error: (xhr, status, err) => {
                awca_toast('خطا در ارتباط با سرور', 'error');
            },
            complete: () => {
                // Re-enable button
                $button.prop('disabled', false).html(originalText);
            }
        });
    }
}

/**
 * Initialize all report modals
 */
export function initReportModals() {
    $(document).ready(function() {
        // Zero Profit Products
        new GenericModal({
            modalId: 'anar-zero-profit-modal',
            buttonId: 'anar-zero-profit-products',
            title: 'محصولات با سود صفر',
            action: 'anar_get_zero_profit_products',
            changeStatusAction: null,
            warningText: null
        });

        // Deprecated Products
        new GenericModal({
            modalId: 'anar-deprecated-modal',
            buttonId: 'anar-deprecated-products',
            title: 'محصولات منسوخ شده',
            action: 'anar_get_deprecated_products',
            changeStatusAction: 'anar_change_deprecated_status',
            changeStatusText: 'تغییر وضعیت به "در انتظار بررسی"',
            warningText: 'آیا مطمئن هستید که می‌خواهید وضعیت همه محصولات منسوخ را به "در انتظار بررسی" تغییر دهید؟'
        });

        // Duplicate Products
        new GenericModal({
            modalId: 'anar-duplicate-modal',
            buttonId: 'anar-duplicate-products',
            title: 'محصولات تکراری',
            action: 'anar_get_duplicate_products',
            changeStatusAction: 'anar_change_duplicate_status',
            changeStatusText: 'تغییر وضعیت تکراری‌ها به "در انتظار بررسی"',
            warningText: 'آیا مطمئن هستید که می‌خواهید محصولات تکراری را به "در انتظار بررسی" تغییر دهید؟\n\nقدیمی‌ترین محصول از هر گروه حفظ می‌شود.'
        });
    });
}

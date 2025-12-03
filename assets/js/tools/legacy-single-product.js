/**
 * Legacy Single Product Creation Modal
 * 
 * Handles the modal and form submission for creating a single product
 * using the legacy import system.
 */

export function initLegacySingleProduct() {
    const singleProductButton = document.getElementById('awca-open-single-product-modal-legacy');
    const singleProductModal = document.getElementById('awca-single-product-modal-legacy');
    const singleProductForm = document.getElementById('awca-single-product-form-legacy');
    const singleProductInput = document.getElementById('awca-single-product-sku-legacy');
    const singleProductFeedback = document.getElementById('awca-single-product-feedback-legacy');
    const singleProductLogs = document.getElementById('awca-single-product-logs-legacy');
    const singleProductLogsContainer = document.getElementById('awca-single-product-logs-container-legacy');

    if (!singleProductButton || !singleProductModal || !singleProductForm) {
        return;
    }

    /**
     * Make AJAX request helper
     */
    const request = async (action, data = {}) => {
        if (typeof awca_ajax_object === 'undefined') {
            throw new Error('AJAX object not found');
        }

        const formData = new FormData();
        formData.append('action', action);
        formData.append('security', awca_ajax_object.nonce);
        
        Object.keys(data).forEach(key => {
            formData.append(key, data[key]);
        });

        const response = await fetch(awca_ajax_object.ajax_url, {
            method: 'POST',
            body: formData,
        });

        const result = await response.json();

        if (!result.success) {
            throw new Error(result.data?.message || 'خطای نامشخص رخ داد.');
        }

        return result.data;
    };

    /**
     * Append log entry to modal logs container
     */
    const appendModalLog = (message, type = 'info') => {
        if (!singleProductLogsContainer) {
            return;
        }

        const entry = document.createElement('p');
        const displayDate = new Date();
        const timeLabel = displayDate.toLocaleTimeString();
        entry.textContent = `[${timeLabel}] ${message}`;
        entry.dataset.type = type;
        
        // Add CSS class based on type
        if (type === 'success') {
            entry.style.color = '#2e8b57';
        } else if (type === 'error') {
            entry.style.color = '#d63638';
        }
        
        singleProductLogsContainer.appendChild(entry);
        singleProductLogsContainer.scrollTop = singleProductLogsContainer.scrollHeight;
    };

    /**
     * Open modal
     */
    const openSingleProductModal = () => {
        if (!singleProductModal) {
            return;
        }
        singleProductModal.setAttribute('aria-hidden', 'false');
        if (singleProductFeedback) {
            singleProductFeedback.textContent = '';
            singleProductFeedback.className = 'awca-modal-feedback';
        }
        singleProductForm?.classList.remove('is-loading');
        singleProductForm?.reset();
        if (singleProductLogsContainer) {
            singleProductLogsContainer.innerHTML = '';
        }
        if (singleProductLogs) {
            singleProductLogs.style.display = 'none';
        }
        singleProductInput?.focus();
    };

    /**
     * Close modal
     */
    const closeSingleProductModal = () => {
        if (singleProductModal) {
            singleProductModal.setAttribute('aria-hidden', 'true');
        }
    };

    /**
     * Handle form submission
     */
    const handleSingleProductSubmit = async (event) => {
        event.preventDefault();
        if (!singleProductForm || !singleProductInput) {
            return;
        }

        const sku = singleProductInput.value.trim();
        if (!sku) {
            if (singleProductFeedback) {
                singleProductFeedback.textContent = 'لطفاً شناسه SKU را وارد کنید.';
                singleProductFeedback.className = 'awca-modal-feedback awca-modal-feedback--error';
            }
            return;
        }

        singleProductForm.classList.add('is-loading');
        if (singleProductFeedback) {
            singleProductFeedback.textContent = '';
            singleProductFeedback.className = 'awca-modal-feedback';
        }

        try {
            const data = await request('awca_create_single_product_legacy', { anar_sku: sku });
            
            // Show logs in modal
            if (singleProductLogs) {
                singleProductLogs.style.display = 'block';
            }
            
            // Add success message to modal logs
            const actionText = data.created ? 'ساخته شد' : 'به‌روزرسانی شد';
            appendModalLog(`محصول با SKU ${sku} ${actionText} (ID: ${data.product_id || 'نامشخص'})`, 'success');
            
            if (singleProductFeedback) {
                singleProductFeedback.textContent = data.message || 'عملیات با موفقیت انجام شد.';
                singleProductFeedback.className = 'awca-modal-feedback awca-modal-feedback--success';
            }
            
            singleProductForm.reset();
        } catch (error) {
            if (singleProductFeedback) {
                singleProductFeedback.textContent = error.message;
                singleProductFeedback.className = 'awca-modal-feedback awca-modal-feedback--error';
            }
            
            // Show logs even on error
            if (singleProductLogs) {
                singleProductLogs.style.display = 'block';
            }
            appendModalLog(error.message, 'error');
        } finally {
            singleProductForm.classList.remove('is-loading');
        }
    };

    // Event listeners
    singleProductButton.addEventListener('click', (event) => {
        event.preventDefault();
        openSingleProductModal();
    });

    singleProductForm.addEventListener('submit', handleSingleProductSubmit);

    // Close modal on overlay/close button click
    singleProductModal.addEventListener('click', (event) => {
        if (event.target.closest('[data-modal-close]')) {
            event.preventDefault();
            closeSingleProductModal();
        }
    });

    // Close modal on Escape key
    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape' && singleProductModal?.getAttribute('aria-hidden') === 'false') {
            closeSingleProductModal();
        }
    });
}


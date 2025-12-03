export function initProductCreation() {
    if (typeof window.awcaImportV2Data === 'undefined') {
        return;
    }

    const config = window.awcaImportV2Data;
    const container = document.getElementById('awca-import-create-products');
    const startButton = container ? container.querySelector('#awca-start-creation') : null;
    const refreshButton = container ? container.querySelector('#awca-refresh-creation') : null;
    const cancelButton = container ? container.querySelector('#awca-cancel-creation') : null;
    const triggerButton = container ? container.querySelector('#awca-trigger-batch') : null;
    const logContainer = container ? container.querySelector('#awca-creation-log') : null;
    const statElements = container ? container.querySelectorAll('[data-stat]') : [];
    const progressBar = container ? container.querySelector('[data-stat="progress-bar"]') : null;
    const singleProductButton = document.getElementById('awca-open-single-product-modal');
    const singleProductModal = document.getElementById('awca-single-product-modal');
    const singleProductForm = singleProductModal?.querySelector('#awca-single-product-form') || null;
    const singleProductInput = singleProductModal?.querySelector('#awca-single-product-sku') || null;
    const singleProductFeedback = singleProductModal?.querySelector('#awca-single-product-feedback') || null;
    const singleProductLogs = singleProductModal?.querySelector('#awca-single-product-logs') || null;
    const singleProductLogsContainer = singleProductModal?.querySelector('#awca-single-product-logs-container') || null;

    let pollingTimer = null;
    let lastStatus = null;
    let fetchStatusFn = null;
    const renderedLogIds = new Set();

    const appendLog = (message, type = 'info', timestamp = null, id = null) => {
        if (!logContainer) {
            return;
        }

        if (id) {
            if (renderedLogIds.has(id)) {
                return;
            }
            renderedLogIds.add(id);
        }

        const entry = document.createElement('p');
        const displayDate = timestamp
            ? new Date(timestamp.replace(' ', 'T'))
            : new Date();
        const timeLabel = Number.isNaN(displayDate.getTime())
            ? new Date().toLocaleTimeString()
            : displayDate.toLocaleTimeString();
        entry.textContent = `[${timeLabel}] ${message}`;
        entry.dataset.type = type;
        logContainer.appendChild(entry);
        logContainer.scrollTop = logContainer.scrollHeight;
    };

    const renderServerLogs = (entries = []) => {
        entries.forEach((entry) => {
            if (!entry || !entry.message) {
                return;
            }
            appendLog(entry.message, entry.type || 'info', entry.time || null, entry.id || null);
        });
    };

    const request = async (action, payload = {}) => {
        const response = await fetch(config.ajaxUrl, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded;charset=UTF-8',
            },
            body: new URLSearchParams({
                action,
                security: config.nonce,
                ...payload,
            }).toString(),
        });

        if (!response.ok) {
            throw new Error(config.i18n?.unexpected || 'خطای غیرمنتظره رخ داد.');
        }

        const result = await response.json();

        if (!result.success) {
            throw new Error(result.data?.message || config.i18n?.failed || 'خطایی رخ داد.');
        }

        return result.data;
    };

    const schedulePolling = () => {
        clearTimeout(pollingTimer);
        pollingTimer = setTimeout(() => fetchStatus(), 5000);
    };

    const updateStats = (job, pendingProducts, estimatedRemainingMinutes = null) => {
        const totals = {
            total: job?.total_products ?? pendingProducts ?? 0,
            processed: job?.processed_products ?? 0,
            created: job?.created_products ?? 0,
            skipped: job?.existing_products ?? 0,
            failed: job?.failed_products ?? 0,
            estimated_minutes: estimatedRemainingMinutes !== null && estimatedRemainingMinutes > 0 && job && job.status === 'in_progress' ? estimatedRemainingMinutes : '-',
        };

        statElements.forEach((el) => {
            const key = el.dataset.stat;
            if (totals[key] !== undefined) {
                el.textContent = totals[key];
            }
        });

        if (progressBar) {
            const percent = totals.total ? Math.round((totals.processed / totals.total) * 100) : 0;
            progressBar.style.width = `${percent}%`;
        }

        const inProgress = job && job.status === 'in_progress';
        if (startButton) {
            startButton.disabled = !!inProgress;
        }
        if (cancelButton) {
            cancelButton.disabled = !inProgress;
        }
        if (triggerButton) {
            triggerButton.style.display = inProgress ? 'inline-block' : 'none';
            triggerButton.disabled = !inProgress;
        }

        if (job && job.status !== lastStatus) {
            if (job.status === 'in_progress') {
                appendLog('فرآیند ساخت محصولات در پس‌زمینه آغاز شد.', 'info');
            } else {
                appendLog('فرآیند ساخت محصولات به پایان رسید.', 'success');
            }
            lastStatus = job.status;
        }
    };

    const fetchStatus = async () => {
        try {
            const data = await request('awca_import_v2_get_progress');
            updateStats(data.job, data.pending_products, data.estimated_remaining_minutes);
            renderServerLogs(data.logs || []);

            if (data.job && data.job.status === 'in_progress') {
                schedulePolling();
            } else if (data.job && (data.job.status === 'completed' || data.job.status === 'failed' || data.job.status === 'cancelled')) {
                // Stop polling when job is complete
                clearTimeout(pollingTimer);
                if (data.job.status === 'failed') {
                    appendLog('فرآیند ساخت محصولات با خطا مواجه شد.', 'error');
                } else if (data.job.status === 'cancelled') {
                    appendLog('فرآیند ساخت محصولات متوقف شد.', 'warning');
                }
            }
        } catch (error) {
            appendLog(error.message, 'error');
            // Continue polling even on error to recover
            schedulePolling();
        }
    };

    const handleStart = async () => {
        if (startButton) {
            startButton.disabled = true;
        }
        clearTimeout(pollingTimer);

        try {
            const data = await request('awca_import_v2_start_creation');
            appendLog('فرآیند ساخت محصولات در پس‌زمینه آغاز شد.', 'info');
            updateStats(data.job, data.job?.total_products ?? 0, data.estimated_remaining_minutes);
            renderServerLogs(data.logs || []);
            schedulePolling();
        } catch (error) {
            if (startButton) {
                startButton.disabled = false;
            }
            appendLog(error.message, 'error');
        }
    };

    const handleRefresh = (event) => {
        if (event) {
            event.preventDefault();
        }
        fetchStatus();
    };

    const handleCancel = async () => {
        if (!cancelButton || cancelButton.disabled) {
            return;
        }

        cancelButton.disabled = true;
        appendLog('در حال توقف فرآیند ساخت محصولات...', 'info');
        clearTimeout(pollingTimer);

        try {
            const data = await request('awca_import_v2_cancel_creation');
            appendLog(data.message || 'فرآیند ساخت محصولات متوقف شد.', 'warning');
            updateStats(data.job, data.pending_products ?? 0, data.estimated_remaining_minutes);
            renderServerLogs(data.logs || []);
        } catch (error) {
            appendLog(error.message, 'error');
            schedulePolling();
        }
    };

    const handleTriggerBatch = async () => {
        if (!triggerButton || triggerButton.disabled) {
            return;
        }

        triggerButton.disabled = true;
        appendLog('در حال پردازش دستی بسته...', 'info');

        try {
            const data = await request('awca_import_v2_trigger_batch');
            appendLog(data.message || 'بسته پردازش شد.', 'info');
            updateStats(data.job, data.pending_products ?? 0, data.estimated_remaining_minutes);
            renderServerLogs(data.logs || []);
            
            // Continue polling after manual trigger
            schedulePolling();
        } catch (error) {
            appendLog(error.message, 'error');
        } finally {
            triggerButton.disabled = false;
        }
    };

    const appendModalLog = (message, type = 'info', timestamp = null) => {
        if (!singleProductLogsContainer) {
            return;
        }

        const entry = document.createElement('p');
        const displayDate = timestamp
            ? new Date(timestamp.replace(' ', 'T'))
            : new Date();
        const timeLabel = Number.isNaN(displayDate.getTime())
            ? new Date().toLocaleTimeString()
            : displayDate.toLocaleTimeString();
        entry.textContent = `[${timeLabel}] ${message}`;
        entry.dataset.type = type;
        singleProductLogsContainer.appendChild(entry);
        singleProductLogsContainer.scrollTop = singleProductLogsContainer.scrollHeight;
    };

    const openSingleProductModal = () => {
        if (!singleProductModal) {
            return;
        }
        singleProductModal.setAttribute('aria-hidden', 'false');
        singleProductFeedback && (singleProductFeedback.textContent = '');
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

    const closeSingleProductModal = () => {
        singleProductModal?.setAttribute('aria-hidden', 'true');
    };

    const handleSingleProductSubmit = async (event) => {
        event.preventDefault();
        if (!singleProductForm || !singleProductInput) {
            return;
        }

        const sku = singleProductInput.value.trim();
        if (!sku) {
            if (singleProductFeedback) {
                singleProductFeedback.textContent = 'لطفاً شناسه SKU را وارد کنید.';
            }
            return;
        }

        singleProductForm.classList.add('is-loading');
        if (singleProductFeedback) {
            singleProductFeedback.textContent = '';
        }

        try {
            const data = await request('awca_import_v2_create_single_product', { anar_sku: sku });
            
            // Show logs in modal
            if (singleProductLogs) {
                singleProductLogs.style.display = 'block';
            }
            
            // Add success message to modal logs
            appendModalLog(`محصول با SKU ${sku} ساخته شد (ID: ${data.product_id || 'نامشخص'})`, 'success');
            
            // Render server logs in modal
            if (data.logs && Array.isArray(data.logs)) {
                data.logs.forEach((entry) => {
                    if (entry && entry.message) {
                        appendModalLog(entry.message, entry.type || 'info', entry.time || null);
                    }
                });
            }
            
            // Also add to main log container
            appendLog(`محصول با SKU ${sku} ساخته شد (ID: ${data.product_id || 'نامشخص'})`, 'success');
            renderServerLogs(data.logs || []);
            
            if (singleProductFeedback) {
                singleProductFeedback.textContent = data.message || 'محصول با موفقیت ساخته شد.';
                singleProductFeedback.className = 'awca-modal-feedback awca-modal-feedback--success';
            }
            
            singleProductForm.reset();
            
            // Refresh stats if function exists
            if (typeof fetchStatusFn === 'function') {
                fetchStatusFn();
            }
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
            appendLog(error.message, 'error');
        } finally {
            singleProductForm.classList.remove('is-loading');
        }
    };

    startButton?.addEventListener('click', (event) => {
        event.preventDefault();
        handleStart();
    });

    refreshButton?.addEventListener('click', handleRefresh);
    cancelButton?.addEventListener('click', (event) => {
        event.preventDefault();
        handleCancel();
    });
    triggerButton?.addEventListener('click', (event) => {
        event.preventDefault();
        handleTriggerBatch();
    });
    singleProductButton?.addEventListener('click', (event) => {
        event.preventDefault();
        openSingleProductModal();
    });
    singleProductForm?.addEventListener('submit', handleSingleProductSubmit);
    singleProductModal?.addEventListener('click', (event) => {
        if (event.target.closest('[data-modal-close]')) {
            event.preventDefault();
            closeSingleProductModal();
        }
    });
    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape' && singleProductModal?.getAttribute('aria-hidden') === 'false') {
            closeSingleProductModal();
        }
    });

    if (!container) {
        return;
    }

    fetchStatusFn = fetchStatus;
    fetchStatus();
}


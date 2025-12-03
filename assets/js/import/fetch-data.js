export function initFetchDataStep() {
    const container = document.getElementById('awca-import-fetch-data');

    if (!container || typeof window.awcaImportV2Data === 'undefined') {
        return;
    }

    const config = window.awcaImportV2Data;

    const startButton = container.querySelector('#awca-import-fetch-start');
    const nextButton = container.querySelector('#awca-import-fetch-next');
    const logContainer = container.querySelector('#awca-import-fetch-log');

    let isProcessing = false;

    const updateStatus = (entity, status, percent = null) => {
        const item = container.querySelector(`[data-entity="${entity}"]`);
        if (!item) {
            return;
        }

        const statusLabel = item.querySelector('.awca-fetch-item__status');
        if (statusLabel) {
            statusLabel.textContent = status;
        }

        if (percent !== null) {
            const bar = item.querySelector('.awca-progress-bar__inner');
            if (bar) {
                bar.style.width = `${Math.min(100, percent)}%`;
            }
        }
    };

    const appendLog = (message, type = 'info') => {
        if (!logContainer) {
            return;
        }
        const entry = document.createElement('p');
        entry.textContent = `[${new Date().toLocaleTimeString()}] ${message}`;
        entry.dataset.type = type;
        logContainer.appendChild(entry);
        logContainer.scrollTop = logContainer.scrollHeight;
    };

    const request = async (action, payload = {}) => {
        const params = new URLSearchParams({
            action,
            security: config.nonce,
            ...payload,
        });

        const response = await fetch(config.ajaxUrl, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded;charset=UTF-8',
            },
            body: params.toString(),
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

    const processCategories = async () => {
        let page = 1;
        let processed = 0;
        let total = null;
        updateStatus('categories', 'در حال دریافت...', 5);
        appendLog('دریافت دسته‌بندی‌ها آغاز شد.');

        while (true) {
            const data = await request('awca_import_v2_fetch_categories', { page });

            total = typeof data.total === 'number' ? data.total : total;
            const batchCount = typeof data.saved === 'number' ? data.saved : 0;
            processed += batchCount;

            const safeTotal = total && total > 0 ? total : processed;
            const percent = safeTotal ? Math.min(100, Math.round((processed / safeTotal) * 100)) : 100;

            updateStatus('categories', `صفحه ${page} ذخیره شد`, percent);
            appendLog(`صفحه ${page} دسته‌بندی‌ها ذخیره شد. (${processed}/${safeTotal})`);

            if (!data.has_more) {
                break;
            }

            page = data.page ? data.page + 1 : page + 1;
        }

        updateStatus('categories', 'تکمیل شد', 100);
        appendLog(`دسته‌بندی‌ها (کل: ${processed}) با موفقیت ذخیره شد.`);
    };

    const processAttributes = async () => {
        let page = 1;
        let processed = 0;
        let total = null;
        updateStatus('attributes', 'در حال دریافت...', 5);
        appendLog('دریافت ویژگی‌ها آغاز شد.');

        while (true) {
            const data = await request('awca_import_v2_fetch_attributes', { page });

            total = typeof data.total === 'number' ? data.total : total;
            const batchCount = typeof data.saved === 'number' ? data.saved : 0;
            processed += batchCount;

            const safeTotal = total && total > 0 ? total : processed;
            const percent = safeTotal ? Math.min(100, Math.round((processed / safeTotal) * 100)) : 100;

            updateStatus('attributes', `صفحه ${page} ذخیره شد`, percent);
            appendLog(`صفحه ${page} ویژگی‌ها ذخیره شد. (${processed}/${safeTotal})`);

            if (!data.has_more) {
                break;
            }

            page = data.page ? data.page + 1 : page + 1;
        }

        updateStatus('attributes', 'تکمیل شد', 100);
        appendLog(`ویژگی‌ها (کل: ${processed}) با موفقیت ذخیره شد.`);
    };

    const processProducts = async () => {
        let page = 1;
        let processed = 0;
        let total = null;
        updateStatus('products', 'در حال دریافت...', 5);
        appendLog('دریافت محصولات آغاز شد.');

        while (true) {
            const data = await request('awca_import_v2_fetch_products', { page });

            total = typeof data.total === 'number' ? data.total : total;
            const batchCount = typeof data.count === 'number' ? data.count : 0;
            processed += batchCount;

            const safeTotal = total && total > 0 ? total : processed;
            const percent = safeTotal ? Math.min(100, Math.round((processed / safeTotal) * 100)) : 100;

            updateStatus('products', `صفحه ${page} ذخیره شد`, percent);
            appendLog(`صفحه ${page} محصولات ذخیره شد. (${processed}/${safeTotal})`);

            if (!data.has_more) {
                break;
            }

            page = data.next_page || page + 1;
        }

        updateStatus('products', 'تکمیل شد', 100);
        appendLog('دریافت همه محصولات با موفقیت پایان یافت.');
    };

    const executeSequence = async () => {
        if (isProcessing) {
            return;
        }
        isProcessing = true;
        if (startButton) {
            startButton.disabled = true;
        }
        if (nextButton) {
            nextButton.setAttribute('disabled', 'disabled');
        }

        try {
            await processCategories();
            await processAttributes();
            await processProducts();

            appendLog('همه داده‌ها آماده شدند. اکنون می‌توانید به مرحله بعد بروید.');
            if (nextButton) {
                nextButton.removeAttribute('disabled');
            }
        } catch (error) {
            appendLog(error.message, 'error');
            if (startButton) {
                startButton.disabled = false;
            }
        } finally {
            isProcessing = false;
        }
    };

    if (startButton) {
        startButton.addEventListener('click', (event) => {
            event.preventDefault();
            executeSequence();
        });
    }
}


export function initCategoryModal() {
    const modal = document.getElementById('awca-category-map-modal');

    if (!modal || typeof window.awcaImportV2Data === 'undefined') {
        return;
    }

    const config = window.awcaImportV2Data;
    const form = modal.querySelector('#awca-category-map-form');
    const feedback = modal.querySelector('#awca-category-modal-feedback');
    const removeButton = modal.querySelector('#awca-category-map-remove');
    const selectElement = modal.querySelector('#awca-category-select');
    const $select = window.jQuery(selectElement);
    const description = document.getElementById('awca-category-modal-description');
    const hiddenId = document.getElementById('awca-modal-anar-id');
    const hiddenName = document.getElementById('awca-modal-anar-name');

    let currentCategory = null;
    let selectInitialized = false;

    const initSelect = () => {
        if (selectInitialized || !$select.length || typeof $select.selectWoo !== 'function') {
            return;
        }

        $select.selectWoo({
            width: '100%',
            placeholder: $select.data('placeholder'),
            dropdownParent: window.jQuery(document.body),
            ajax: {
                transport: function (params, success, failure) {
                    window.jQuery.ajax({
                        url: config.ajaxUrl,
                        method: 'POST',
                        data: {
                            action: 'awca_import_v2_get_wc_categories',
                            security: config.nonce,
                            search: params.data.term || '',
                            page: params.data.page || 1,
                        },
                        success(response) {
                            if (response.success) {
                                success(response.data);
                            } else {
                                failure(response);
                            }
                        },
                        error(xhr) {
                            failure(xhr);
                        },
                    });
                },
                processResults(data) {
                    return data;
                },
            },
            language: {
                searching: () => 'در حال جستجو...',
                noResults: () => 'موردی یافت نشد.',
            },
        });

        selectInitialized = true;
    };

    const openModal = (button) => {
        currentCategory = {
            id: button.dataset.anarId,
            name: button.dataset.anarName,
        };

        const actionsContainer = button.closest('.awca-category-node__actions');
        const mappedId = actionsContainer?.dataset.selectedTerm || '';
        const mappedName = actionsContainer?.dataset.selectedName || '';

        modal.setAttribute('aria-hidden', 'false');
        hiddenId.value = currentCategory.id;
        hiddenName.value = currentCategory.name;
        feedback.textContent = '';
        description.textContent = `دسته‌بندی انتخابی: ${currentCategory.name}`;

        initSelect();

        if (mappedId && selectInitialized) {
            const optionExists = $select.find(`option[value="${mappedId}"]`).length > 0;
            if (!optionExists) {
                const option = new Option(mappedName, mappedId, true, true);
                $select.append(option);
            }
            $select.val(mappedId).trigger('change');
        } else if (selectInitialized) {
            $select.val(null).trigger('change');
        }
    };

    const closeModal = () => {
        modal.setAttribute('aria-hidden', 'true');
        currentCategory = null;
    };

    const request = async (payload) => {
        const response = await fetch(config.ajaxUrl, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded;charset=UTF-8',
            },
            body: new URLSearchParams({
                action: 'awca_import_v2_save_category_map',
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

    const updateNodeState = (data) => {
        if (!currentCategory) {
            return;
        }

        const node = document.querySelector(`[data-node-id="${currentCategory.id}"]`);
        if (!node) {
            return;
        }

        const mappingLabel = node.querySelector('.awca-category-node__mapping');
        const actionsContainer = node.querySelector('.awca-category-node__actions');

        if (data.removed) {
            if (mappingLabel) {
                mappingLabel.textContent = 'هنوز معادل‌سازی نشده است';
                mappingLabel.classList.add('awca-category-node__mapping--pending');
            }
            if (actionsContainer) {
                actionsContainer.dataset.selectedTerm = '';
                actionsContainer.dataset.selectedName = '';
            }
            return;
        }

        const mapping = data.mapping;
        if (mappingLabel) {
            mappingLabel.textContent = `معادل شده با: ${mapping.wc_term_name}`;
            mappingLabel.classList.remove('awca-category-node__mapping--pending');
        }

        if (actionsContainer) {
            actionsContainer.dataset.selectedTerm = mapping.wc_term_id;
            actionsContainer.dataset.selectedName = mapping.wc_term_name;
        }
    };

    modal.addEventListener('click', (event) => {
        if (event.target.matches('[data-modal-close]')) {
            event.preventDefault();
            closeModal();
        }
    });

    document.addEventListener('click', (event) => {
        const target = event.target.closest('.awca-map-category');
        if (!target) {
            return;
        }
        event.preventDefault();
        openModal(target);
    });

    form?.addEventListener('submit', async (event) => {
        event.preventDefault();
        if (!currentCategory) {
            return;
        }

        const selectedId = $select.val();
        if (!selectedId) {
            feedback.textContent = 'لطفاً یک دسته‌بندی را انتخاب کنید.';
            return;
        }

        try {
            form.classList.add('is-loading');
            const data = await request({
                anar_category_id: currentCategory.id,
                anar_category_name: currentCategory.name,
                wc_category_id: selectedId,
            });
            updateNodeState(data);
            feedback.textContent = 'معادل‌سازی با موفقیت ذخیره شد.';
            closeModal();
        } catch (error) {
            feedback.textContent = error.message;
        } finally {
            form.classList.remove('is-loading');
        }
    });

    removeButton?.addEventListener('click', async (event) => {
        event.preventDefault();
        if (!currentCategory) {
            return;
        }

        try {
            form.classList.add('is-loading');
            const data = await request({
                anar_category_id: currentCategory.id,
                remove: '1',
            });
            updateNodeState(data);
            feedback.textContent = 'معادل‌سازی حذف شد.';
            closeModal();
        } catch (error) {
            feedback.textContent = error.message;
        } finally {
            form.classList.remove('is-loading');
        }
    });

    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape') {
            closeModal();
        }
    });
}


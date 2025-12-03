export function initAttributeMapping() {
    const container = document.querySelector('.awca-attribute-grid');
    const modal = document.getElementById('awca-attribute-map-modal');
    const infoModal = document.getElementById('awca-attribute-info-modal');

    if (!container || !modal || typeof window.awcaImportV2Data === 'undefined') {
        return;
    }

    const config = window.awcaImportV2Data;
    const form = modal.querySelector('#awca-attribute-map-form');
    const selectElement = modal.querySelector('#awca-attribute-select');
    const $select = window.jQuery(selectElement);
    const description = document.getElementById('awca-attribute-modal-description');
    const hiddenKey = document.getElementById('awca-modal-attribute-key');
    const hiddenName = document.getElementById('awca-modal-attribute-name');
    const feedback = document.getElementById('awca-attribute-modal-feedback');
    const removeButton = document.getElementById('awca-attribute-map-remove');

    let currentAttribute = null;
    let selectInitialized = false;

    const initializeSelect = () => {
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
                            action: 'awca_import_v2_get_wc_attributes',
                            security: config.nonce,
                            search: params.data.term || '',
                        },
                        success(response) {
                            console.log(response);
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
                    let list = [];
                    
                    if (Array.isArray(data?.results)) {
                        // Results is already an array
                        list = data.results;
                    } else if (data?.results && typeof data.results === 'object') {
                        // Results is an object, convert to array
                        list = Object.values(data.results);
                    } else if (Array.isArray(data)) {
                        // Data itself is an array
                        list = data;
                    }

                    return {
                        results: list.map((item) => ({
                            id: item.id,
                            text: `${item.text} (${item.slug})`,
                        })),
                        pagination: data?.pagination ?? { more: false },
                    };
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
        currentAttribute = {
            key: button.dataset.anarKey,
            name: button.dataset.anarName,
        };

        const actionsContainer = button.closest('.awca-attribute-card__actions');
        const mappedId = actionsContainer?.dataset.selectedId || '';
        const mappedLabel = actionsContainer?.dataset.selectedLabel || '';

        modal.setAttribute('aria-hidden', 'false');
        hiddenKey.value = currentAttribute.key;
        hiddenName.value = currentAttribute.name;
        description.textContent = `ویژگی انتخابی: ${currentAttribute.name}`;
        feedback.textContent = '';

        initializeSelect();

        if (mappedId && selectInitialized) {
            const optionExists = $select.find(`option[value="${mappedId}"]`).length > 0;
            if (!optionExists && mappedLabel) {
                const option = new Option(mappedLabel, mappedId, true, true);
                $select.append(option);
            }
            $select.val(mappedId).trigger('change');
        } else if (selectInitialized) {
            $select.val(null).trigger('change');
        }
    };

    const closeModal = () => {
        modal.setAttribute('aria-hidden', 'true');
        currentAttribute = null;
    };

    const request = async (payload) => {
        const response = await fetch(config.ajaxUrl, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded;charset=UTF-8',
            },
            body: new URLSearchParams({
                action: 'awca_import_v2_save_attribute_map',
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

    const updateCardState = (data) => {
        if (!currentAttribute) {
            return;
        }

        const card = document.querySelector(`[data-attribute-key="${currentAttribute.key}"]`);
        if (!card) {
            return;
        }

        const mappingLabel = card.querySelector('.awca-attribute-card__mapping');
        const actionsContainer = card.querySelector('.awca-attribute-card__actions');

        if (data.removed) {
            if (mappingLabel) {
                mappingLabel.textContent = 'هنوز معادل‌سازی نشده است';
                mappingLabel.classList.add('awca-attribute-card__mapping--pending');
            }
            if (actionsContainer) {
                actionsContainer.dataset.selectedId = '';
                actionsContainer.dataset.selectedLabel = '';
            }
            // Remove mapped class
            card.classList.remove('awca-attribute-card--mapped');
            return;
        }

        const mapping = data.mapping;
        if (mappingLabel) {
            mappingLabel.textContent = `معادل شده با: ${mapping.wc_attribute_label}`;
            mappingLabel.classList.remove('awca-attribute-card__mapping--pending');
        }
        if (actionsContainer) {
            actionsContainer.dataset.selectedId = mapping.wc_attribute_id;
            actionsContainer.dataset.selectedLabel = mapping.wc_attribute_label;
        }
        // Add mapped class
        card.classList.add('awca-attribute-card--mapped');
    };

    container.addEventListener('click', (event) => {
        const button = event.target.closest('.awca-map-attribute');
        if (!button) {
            return;
        }
        event.preventDefault();
        openModal(button);
    });

    modal.addEventListener('click', (event) => {
        if (event.target.matches('[data-modal-close]')) {
            event.preventDefault();
            closeModal();
        }
    });

    form?.addEventListener('submit', async (event) => {
        event.preventDefault();
        if (!currentAttribute) {
            return;
        }

        const selectedId = $select.val();
        if (!selectedId) {
            feedback.textContent = 'لطفاً یک ویژگی را انتخاب کنید.';
            return;
        }

        try {
            form.classList.add('is-loading');
            const data = await request({
                anar_attribute_key: currentAttribute.key,
                anar_attribute_name: currentAttribute.name,
                wc_attribute_id: selectedId,
            });
            updateCardState(data);
            closeModal();
        } catch (error) {
            feedback.textContent = error.message;
        } finally {
            form.classList.remove('is-loading');
        }
    });

    removeButton?.addEventListener('click', async (event) => {
        event.preventDefault();
        if (!currentAttribute) {
            return;
        }

        try {
            form.classList.add('is-loading');
            const data = await request({
                anar_attribute_key: currentAttribute.key,
                remove: '1',
            });
            updateCardState(data);
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
            if (infoModal && infoModal.getAttribute('aria-hidden') === 'false') {
                closeInfoModal();
            }
        }
    });

    // Info Modal handlers
    if (infoModal) {
        const infoName = document.getElementById('awca-attribute-info-name');
        const infoKeysList = document.getElementById('awca-attribute-info-keys-list');
        const infoMapping = document.getElementById('awca-attribute-info-mapping');

        const openInfoModal = (button) => {
            const attributeName = button.dataset.attributeName || '';
            const keysJson = button.dataset.attributeKeys || '[]';
            const valuesJson = button.dataset.attributeValues || '[]';
            
            let keys = [];
            let values = [];
            
            try {
                keys = JSON.parse(keysJson);
                values = JSON.parse(valuesJson);
            } catch (e) {
                console.error('Error parsing attribute data:', e);
            }

            // Set attribute name
            if (infoName) {
                infoName.textContent = attributeName;
            }

            // Build keys and values list as LTR table
            if (infoKeysList) {
                infoKeysList.innerHTML = '';
                
                if (Array.isArray(values) && values.length > 0) {
                    const table = document.createElement('table');
                    
                    // Create table header
                    const thead = document.createElement('thead');
                    const headerRow = document.createElement('tr');
                    const keyHeader = document.createElement('th');
                    keyHeader.textContent = 'Key';
                    const valueHeader = document.createElement('th');
                    valueHeader.textContent = 'Values';
                    headerRow.appendChild(keyHeader);
                    headerRow.appendChild(valueHeader);
                    thead.appendChild(headerRow);
                    table.appendChild(thead);
                    
                    // Create table body
                    const tbody = document.createElement('tbody');
                    values.forEach((item) => {
                        const row = document.createElement('tr');
                        
                        const keyCell = document.createElement('td');
                        keyCell.textContent = item.key || '';
                        keyCell.style.fontWeight = '600';
                        keyCell.style.color = '#6366f1';
                        
                        const valueCell = document.createElement('td');
                        const valuesArray = Array.isArray(item.values) ? item.values : [];
                        valueCell.textContent = valuesArray.length > 0 
                            ? valuesArray.join(', ') 
                            : '(no values)';
                        valueCell.style.color = '#6b7280';
                        
                        row.appendChild(keyCell);
                        row.appendChild(valueCell);
                        tbody.appendChild(row);
                    });
                    table.appendChild(tbody);
                    
                    infoKeysList.appendChild(table);
                } else {
                    infoKeysList.textContent = '(no keys)';
                }
            }

            // Get mapping info from card
            const card = button.closest('.awca-attribute-card');
            const actionsContainer = card?.querySelector('.awca-attribute-card__actions');
            const mappedLabel = actionsContainer?.dataset.selectedLabel || '';
            const isCreated = card?.classList.contains('awca-attribute-card--created') || false;
            
            if (infoMapping) {
                if (isCreated) {
                    const mappingText = card?.querySelector('.awca-attribute-card__mapping')?.textContent || '';
                    infoMapping.textContent = mappingText || 'ویژگی از قبل ساخته شده است';
                } else if (mappedLabel) {
                    infoMapping.textContent = `معادل شده با: ${mappedLabel}`;
                } else {
                    infoMapping.textContent = 'هنوز معادل‌سازی نشده است';
                }
            }

            infoModal.setAttribute('aria-hidden', 'false');
        };

        const closeInfoModal = () => {
            infoModal.setAttribute('aria-hidden', 'true');
        };

        // Handle info icon clicks
        container.addEventListener('click', (event) => {
            const infoButton = event.target.closest('.awca-attribute-card__info');
            if (infoButton) {
                event.preventDefault();
                openInfoModal(infoButton);
            }
        });

        // Handle info modal close
        infoModal.addEventListener('click', (event) => {
            if (event.target.matches('[data-modal-close]')) {
                event.preventDefault();
                closeInfoModal();
            }
        });
    }
}


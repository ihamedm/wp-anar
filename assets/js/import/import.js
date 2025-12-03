import { initWizardNavigation } from './wizard-navigation.js';
import { initFetchDataStep } from './fetch-data.js';
import { initCategoryTree } from './category-tree.js';
import { initCategoryModal } from './category-modal.js';
import { initAttributeMapping } from './attribute-mapping.js';
import { initProductCreation } from './product-creation.js';

document.addEventListener('DOMContentLoaded', () => {
    initWizardNavigation();
    initFetchDataStep();
    initCategoryTree();
    initCategoryModal();
    initAttributeMapping();
    initProductCreation();
});


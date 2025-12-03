export function initCategoryTree() {
    const treeWrapper = document.querySelector('.awca-category-tree-wrapper');
    if (!treeWrapper) {
        return;
    }

    treeWrapper.addEventListener('click', (event) => {
        const toggle = event.target.closest('.awca-category-toggle');
        if (!toggle || toggle.classList.contains('awca-category-toggle--placeholder')) {
            return;
        }

        const node = toggle.closest('.awca-category-node');
        if (!node) {
            return;
        }

        node.classList.toggle('expanded');
        toggle.textContent = node.classList.contains('expanded') ? '-' : '+';
    });
}


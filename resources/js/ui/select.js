// Searchable selects: <select data-select>. The library is loaded only on pages that have one.

let loader = null;

function load() {
    loader ??= Promise.all([
        import('tom-select'),
        import('tom-select/dist/css/tom-select.bootstrap5.min.css'),
    ]).then(([module]) => module.default);

    return loader;
}

export function initSelects(root = document) {
    const selects = root.querySelectorAll ? root.querySelectorAll('select[data-select]:not(.tomselected)') : [];

    if (!selects.length) {
        return;
    }

    load().then((TomSelect) => {
        selects.forEach((select) => {
            if (select.tomselect) {
                return;
            }

            new TomSelect(select, {
                allowEmptyOption: true,
                maxOptions: 500,
                plugins: select.multiple ? ['remove_button'] : [],
                placeholder: select.dataset.placeholder || '',
                render: {
                    no_results: () => `<div class="no-results">${(window.I18N && window.I18N.no_results) || ''}</div>`,
                },
            });
        });
    });
}

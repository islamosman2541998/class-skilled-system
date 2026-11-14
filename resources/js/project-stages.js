// Project stages tab: the add / edit modal and drag ordering (saved right away).
import Sortable from 'sortablejs';

const modalElement = document.getElementById('stage-modal');
const form = modalElement?.querySelector('form');
const fields = ['name_ar', 'name_en', 'weight', 'status', 'progress', 'planned_start', 'planned_end', 'actual_start', 'actual_end', 'notes'];

const open = (row) => {
    form.reset();
    form.action = row ? form.dataset.updateUrl.replace('__ID__', row.id) : form.dataset.storeUrl;
    form.querySelector('[name="_method"]').value = row ? 'PUT' : 'POST';
    modalElement.querySelector('.modal-title').textContent = row ? form.dataset.titleEdit : form.dataset.titleNew;
    fields.forEach((name) => {
        form.elements.namedItem(name).value = row ? (row[name] ?? '') : (name === 'status' ? 'not_started' : '');
    });
    window.bootstrap.Modal.getOrCreateInstance(modalElement).show();
};

if (form) {
    document.addEventListener('click', (event) => {
        const edit = event.target.closest('[data-stage-edit]');

        if (edit) {
            open(JSON.parse(edit.dataset.stageEdit));
        }

        if (event.target.closest('[data-stage-new]')) {
            open(null);
        }
    });
}

const list = document.querySelector('[data-stage-list]');

if (list) {
    Sortable.create(list, {
        handle: '.sort-handle',
        animation: 120,
        onEnd: () => fetch(list.dataset.reorderUrl, {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                Accept: 'application/json',
                'Content-Type': 'application/json',
            },
            credentials: 'same-origin',
            body: JSON.stringify({ ids: Array.from(list.children).map((item) => Number(item.dataset.id)) }),
        })
            .then((response) => window.toast(response.ok ? 'success' : 'error', response.ok ? window.I18N.saved : window.I18N.request_failed))
            .catch(() => window.toast('error', window.I18N.request_failed)),
    });
}

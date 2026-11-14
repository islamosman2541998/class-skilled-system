// Interface text editor: filters in the browser, edits one key at a time through a modal (spec §5.8).
import { Modal } from 'bootstrap';

const root = document.querySelector('[data-translations]');
const modalElement = document.getElementById('translation-modal');

if (root && modalElement) {
    const csrf = document.querySelector('meta[name="csrf-token"]')?.content || '';
    const rows = Array.from(root.querySelectorAll('[data-row]'));
    const search = root.querySelector('[data-t-search]');
    const group = root.querySelector('[data-t-group]');
    const overridden = root.querySelector('[data-t-overridden]');
    const empty = root.querySelector('[data-t-empty]');
    const modal = Modal.getOrCreateInstance(modalElement);
    const form = modalElement.querySelector('[data-t-form]');
    let current = null;

    const filter = () => {
        const term = search.value.trim().toLowerCase();
        let shown = 0;

        rows.forEach((row) => {
            const visible = (!term || row.dataset.search.includes(term))
                && (!group.value || row.dataset.group === group.value)
                && (!overridden.checked || row.dataset.overridden === '1');
            row.hidden = !visible;
            shown += visible ? 1 : 0;
        });

        empty.classList.toggle('d-none', shown > 0);
    };

    [search, group, overridden].forEach((input) => input.addEventListener('input', filter));

    const clearErrors = () => form.querySelectorAll('[data-error-for]').forEach((node) => { node.textContent = ''; });

    const send = (url, method, body) => fetch(url, {
        method,
        headers: { 'X-CSRF-TOKEN': csrf, Accept: 'application/json', 'Content-Type': 'application/json' },
        body: JSON.stringify(body),
    }).then(async (response) => {
        const data = await response.json().catch(() => ({}));

        if (response.status === 422) {
            Object.entries(data.errors || {}).forEach(([field, messages]) => {
                const node = form.querySelector(`[data-error-for="${field}"]`);
                if (node) node.textContent = messages[0];
            });
            throw new Error('');
        }

        if (!response.ok) {
            throw new Error(data.message || window.I18N.request_failed);
        }

        return data;
    });

    const apply = (data, isOverridden) => {
        current.querySelector('[data-ar]').textContent = data.ar;
        current.querySelector('[data-en]').textContent = data.en;
        current.dataset.overridden = isOverridden ? '1' : '0';
        current.dataset.search = `${current.dataset.group}.${current.dataset.key} ${data.ar} ${data.en}`.toLowerCase();
        current.querySelector('.t-flag').classList.toggle('d-none', !isOverridden);
        modal.hide();
        window.toast('success', data.message);
    };

    root.addEventListener('click', (event) => {
        const button = event.target.closest('[data-t-edit]');

        if (!button) {
            return;
        }

        current = button.closest('[data-row]');
        clearErrors();
        modalElement.querySelector('[data-t-label]').textContent = `${current.dataset.group}.${current.dataset.key}`;
        form.elements.ar.value = current.querySelector('[data-ar]').textContent;
        form.elements.en.value = current.querySelector('[data-en]').textContent;
        modal.show();
    });

    form.addEventListener('submit', (event) => {
        event.preventDefault();
        clearErrors();

        send(root.dataset.updateUrl, 'PUT', {
            group: current.dataset.group,
            key: current.dataset.key,
            ar: form.elements.ar.value,
            en: form.elements.en.value,
        }).then((data) => apply(data, true)).catch((error) => error.message && window.toast('error', error.message));
    });

    modalElement.querySelector('[data-t-reset]').addEventListener('click', () => {
        send(root.dataset.resetUrl, 'DELETE', { group: current.dataset.group, key: current.dataset.key })
            .then((data) => apply(data, false))
            .catch((error) => error.message && window.toast('error', error.message));
    });
}

// Attachment grid behaviour (spec §5.4): filter in the browser, preview in a modal, rename and delete.
import { Modal } from 'bootstrap';

const t = (key) => (window.I18N && window.I18N.attachments && window.I18N.attachments[key]) || '';
const csrf = () => document.querySelector('meta[name="csrf-token"]')?.content || '';

const request = (url, method, body = null) => fetch(url, {
    method,
    headers: { 'X-CSRF-TOKEN': csrf(), Accept: 'application/json', 'Content-Type': 'application/json' },
    body: body ? JSON.stringify(body) : null,
}).then(async (response) => {
    const data = await response.json().catch(() => ({}));

    if (!response.ok) {
        throw new Error(data.message || (window.I18N && window.I18N.request_failed) || '');
    }

    return data;
});

function initFilters(root) {
    const inputs = root.querySelectorAll('[data-filter]');
    const items = root.querySelectorAll('[data-item]');
    const noMatch = root.querySelector('[data-no-match]');

    const apply = () => {
        const state = {};
        inputs.forEach((input) => { state[input.dataset.filter] = input.value.trim().toLowerCase(); });
        let shown = 0;

        items.forEach((item) => {
            const visible = (!state.title || item.dataset.title.includes(state.title))
                && (!state.kind || item.dataset.kind === state.kind)
                && (!state.category || item.dataset.category === state.category);
            item.hidden = !visible;
            shown += visible ? 1 : 0;
        });

        noMatch?.classList.toggle('d-none', shown > 0);
    };

    inputs.forEach((input) => input.addEventListener('input', apply));
}

function initViewer() {
    const element = document.getElementById('attachment-viewer');

    if (!element) {
        return;
    }

    const modal = Modal.getOrCreateInstance(element);
    const body = element.querySelector('[data-viewer-body]');
    const title = element.querySelector('[data-viewer-title]');

    element.addEventListener('hidden.bs.modal', () => { body.replaceChildren(); });

    document.addEventListener('click', (event) => {
        const trigger = event.target.closest('[data-view]');

        if (!trigger) {
            return;
        }

        const { src, kind, mime, poster, name } = trigger.dataset;
        let node;

        if (kind === 'image') {
            node = Object.assign(document.createElement('img'), { src, alt: name });
        } else if (kind === 'video') {
            node = Object.assign(document.createElement('video'), { src, controls: true, preload: 'metadata', playsInline: true });
            if (poster) node.poster = poster;
        } else if (mime === 'application/pdf') {
            node = Object.assign(document.createElement('iframe'), { src, title: name });
        } else {
            window.location.href = `${src}?download=1`;

            return;
        }

        title.textContent = name;
        body.replaceChildren(node);
        modal.show();
    });
}

function initActions() {
    document.addEventListener('click', async (event) => {
        const rename = event.target.closest('[data-rename]');
        const remove = event.target.closest('[data-delete]');

        if (rename) {
            const item = rename.closest('[data-item]');
            const current = item.querySelector('[data-name]').textContent.trim();
            const Swal = (await import('sweetalert2')).default;
            const result = await Swal.fire({
                title: t('rename'),
                input: 'text',
                inputValue: current,
                inputAttributes: { maxlength: 190 },
                showCancelButton: true,
                confirmButtonText: window.I18N.confirm_yes,
                cancelButtonText: window.I18N.confirm_cancel,
                buttonsStyling: false,
                customClass: { confirmButton: 'btn btn-primary', cancelButton: 'btn btn-outline' },
                inputValidator: (value) => (value.trim() === '' ? t('title_required') : undefined),
            });

            if (!result.isConfirmed) {
                return;
            }

            request(rename.dataset.rename, 'PATCH', { title: result.value.trim() })
                .then((data) => {
                    item.querySelector('[data-name]').textContent = result.value.trim();
                    item.dataset.title = result.value.trim().toLowerCase();
                    window.toast('success', data.message);
                })
                .catch((error) => window.toast('error', error.message));
        }

        if (remove) {
            const ok = await window.confirmAction({ title: t('delete_confirm'), danger: true });

            if (!ok) {
                return;
            }

            request(remove.dataset.delete, 'DELETE')
                .then((data) => {
                    remove.closest('[data-item]').remove();
                    window.toast('success', data.message);
                })
                .catch((error) => window.toast('error', error.message));
        }
    });
}

document.querySelectorAll('[data-attachments]').forEach(initFilters);
initViewer();
initActions();

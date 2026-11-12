// Forms submitted without leaving the page: <form data-ajax>.
// The server answers JSON: {message} on success, 422 {errors: {field: [..]}} on validation failure.
// data-ajax-refresh also refreshes the Livewire tables on the page and closes the surrounding modal.

const t = (key, fallback = '') => (window.I18N && window.I18N[key]) || fallback;

function showErrors(form, errors) {
    form.querySelectorAll('[data-error-for]').forEach((node) => { node.textContent = ''; });
    form.querySelectorAll('.is-invalid').forEach((node) => node.classList.remove('is-invalid'));

    let first = null;

    Object.entries(errors || {}).forEach(([field, messages]) => {
        const holder = form.querySelector(`[data-error-for="${CSS.escape(field)}"]`);
        const input = form.elements.namedItem(field) || form.elements.namedItem(`${field}[]`);

        if (holder) {
            holder.textContent = messages[0];
        }

        if (input && input.classList) {
            input.classList.add('is-invalid');
            first ??= input;
        }
    });

    first?.focus();
}

async function submit(form) {
    const buttons = form.querySelectorAll('button[type="submit"]');
    buttons.forEach((button) => { button.classList.add('is-busy'); button.disabled = true; });

    try {
        const response = await fetch(form.action, {
            method: 'POST',
            headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            body: new FormData(form),
            credentials: 'same-origin',
        });

        const data = await response.json().catch(() => ({}));

        if (response.status === 422) {
            showErrors(form, data.errors);

            return;
        }

        if (!response.ok) {
            window.toast('error', data.message || t('request_failed'));

            return;
        }

        showErrors(form, {});
        window.toast('success', data.message);
        form.dispatchEvent(new CustomEvent('ajax-form:success', { bubbles: true, detail: data }));

        if (form.hasAttribute('data-ajax-refresh')) {
            const modal = form.closest('.modal');

            if (modal) {
                window.bootstrap.Modal.getInstance(modal)?.hide();
            }

            window.Livewire?.dispatch('table-refresh');
        }
    } catch (error) {
        window.toast('error', t('request_failed'));
    } finally {
        buttons.forEach((button) => { button.classList.remove('is-busy'); button.disabled = false; });
        delete form.dataset.submitting;
    }
}

export function initAjaxForms() {
    document.addEventListener('submit', (event) => {
        const form = event.target;

        if (form instanceof HTMLFormElement && form.hasAttribute('data-ajax')) {
            event.preventDefault();
            submit(form);
        }
    });
}

const t = (key, fallback = '') => (window.I18N && window.I18N[key]) || fallback;

export function initForms() {
    let dirty = false;

    // Warn before leaving a form that has unsaved edits.
    document.addEventListener('input', (event) => {
        if (event.target.closest('form[data-dirty-check]')) {
            dirty = true;
        }
    });

    window.addEventListener('beforeunload', (event) => {
        if (dirty) {
            event.preventDefault();
            event.returnValue = t('unsaved_changes');
        }
    });

    // Block a second submit while the first one is on its way.
    document.addEventListener('submit', (event) => {
        const form = event.target;

        if (!(form instanceof HTMLFormElement) || event.defaultPrevented) {
            return;
        }

        if (form.dataset.submitting === '1') {
            event.preventDefault();

            return;
        }

        form.dataset.submitting = '1';
        dirty = false;

        form.querySelectorAll('button[type="submit"], button:not([type])').forEach((button) => {
            button.classList.add('is-busy');
            button.setAttribute('aria-disabled', 'true');
        });
    });

    // A page restored from the back/forward cache must be usable again.
    window.addEventListener('pageshow', () => {
        document.querySelectorAll('form[data-submitting]').forEach((form) => {
            delete form.dataset.submitting;
            form.querySelectorAll('.is-busy').forEach((button) => {
                button.classList.remove('is-busy');
                button.removeAttribute('aria-disabled');
            });
        });
    });
}

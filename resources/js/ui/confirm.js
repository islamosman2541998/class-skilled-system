// SweetAlert2 is loaded on the first confirmation, not on every page.
let swal = null;
const loadSwal = () => (swal ??= import('sweetalert2').then((module) => module.default));

const t = (key, fallback = '') => (window.I18N && window.I18N[key]) || fallback;

/**
 * Ask before an action. Resolves true when confirmed.
 * requireText: the user must type this exact value (used for "delete all" and permanent delete).
 */
export function confirmAction({ title, text = '', confirmText, danger = false, requireText = null } = {}) {
    const options = {
        title: title || t('confirm_title'),
        text,
        icon: undefined,
        showCancelButton: true,
        confirmButtonText: confirmText || t('confirm_yes'),
        cancelButtonText: t('confirm_cancel'),
        reverseButtons: document.documentElement.dir !== 'rtl',
        buttonsStyling: false,
        focusCancel: danger,
        customClass: {
            confirmButton: danger ? 'btn btn-danger' : 'btn btn-primary',
            cancelButton: 'btn btn-outline',
        },
    };

    if (requireText !== null) {
        const expected = String(requireText);

        Object.assign(options, {
            input: 'text',
            inputLabel: t('confirm_type', '').replace(':value', expected),
            inputAttributes: { autocomplete: 'off', dir: 'ltr', inputmode: 'numeric' },
            preConfirm: (value) => {
                if (String(value).trim() !== expected) {
                    options.swal.showValidationMessage(t('confirm_mismatch'));

                    return false;
                }

                return true;
            },
        });
    }

    return loadSwal().then((Swal) => {
        options.swal = Swal;
        const { swal: unused, ...config } = options;

        return Swal.fire(config);
    }).then((result) => result.isConfirmed);
}

export function initConfirm() {
    window.confirmAction = confirmAction;

    // <form data-confirm="Question" data-confirm-danger> asks before submitting.
    document.addEventListener('submit', (event) => {
        const form = event.target;

        if (!(form instanceof HTMLFormElement) || !form.hasAttribute('data-confirm') || form.dataset.confirmed === '1') {
            return;
        }

        event.preventDefault();
        event.stopImmediatePropagation();

        confirmAction({
            title: form.dataset.confirm,
            text: form.dataset.confirmText || '',
            danger: form.hasAttribute('data-confirm-danger'),
        }).then((ok) => {
            if (ok) {
                form.dataset.confirmed = '1';
                form.requestSubmit();
            }
        });
    }, true);
}

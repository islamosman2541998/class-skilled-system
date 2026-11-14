// Date inputs: <input data-date> and <input data-date-range>.
// The value sent to the server is always Y-m-d; the user sees d/m/Y with Western digits.

let loader = null;

function load() {
    loader ??= Promise.all([
        import('flatpickr'),
        import('flatpickr/dist/l10n/ar.js'),
        import('flatpickr/dist/flatpickr.min.css'),
    ]).then(([core, arabic]) => ({ flatpickr: core.default, arabic: arabic.Arabic || arabic.default.ar }));

    return loader;
}

export function initDatepickers(root = document) {
    const inputs = root.querySelectorAll ? root.querySelectorAll('[data-date]:not(.flatpickr-input), [data-date-range]:not(.flatpickr-input)') : [];

    if (!inputs.length) {
        return;
    }

    load().then(({ flatpickr, arabic }) => {
        const rtl = document.documentElement.lang === 'ar';

        inputs.forEach((input) => {
            if (input._flatpickr) {
                return;
            }

            const range = input.hasAttribute('data-date-range');
            const time = input.hasAttribute('data-enable-time');

            flatpickr(input, {
                locale: rtl ? { ...arabic, rangeSeparator: ' - ' } : { rangeSeparator: ' - ' },
                mode: range ? 'range' : 'single',
                enableTime: time,
                time_24hr: true,
                dateFormat: time ? 'Y-m-d H:i' : 'Y-m-d',
                altInput: true,
                altFormat: time ? 'd/m/Y H:i' : 'd/m/Y',
                allowInput: !range,
                disableMobile: true,
                onClose(selected, value, instance) {
                    // Tell Livewire and plain listeners that the value changed.
                    instance.input.dispatchEvent(new Event('change', { bubbles: true }));

                    if (range) {
                        const format = (date) => instance.formatDate(date, 'Y-m-d');

                        instance.input.dispatchEvent(new CustomEvent('date-range', {
                            bubbles: true,
                            detail: {
                                from: selected[0] ? format(selected[0]) : null,
                                to: selected[1] ? format(selected[1]) : (selected[0] ? format(selected[0]) : null),
                            },
                        }));
                    }
                },
            });
        });
    });
}

// Client form behaviour: contact rows, duplicate phone warning, quick add from other forms (spec §5.3, §8.1).
const csrf = () => document.querySelector('meta[name="csrf-token"]')?.content || '';

// Repeater rows (contacts and any other [data-repeater] block).
document.querySelectorAll('[data-repeater]').forEach((list) => {
    const panel = list.parentElement;
    const template = panel.querySelector('template[data-repeater-template]');
    let next = list.querySelectorAll('[data-repeater-row]').length;

    panel.querySelector('[data-repeater-add]')?.addEventListener('click', () => {
        list.insertAdjacentHTML('beforeend', template.innerHTML.replaceAll('__INDEX__', String(next)));
        next += 1;
        list.lastElementChild?.querySelector('input')?.focus();
    });

    list.addEventListener('click', (event) => {
        event.target.closest('[data-repeater-remove]')?.closest('[data-repeater-row]')?.remove();
    });
});

// Warn, without blocking, when the phone already belongs to another client.
document.querySelectorAll('input[data-duplicate-check]').forEach((input) => {
    const field = input.closest('.field');
    const box = field.querySelector('[data-duplicate-box]');
    const list = field.querySelector('[data-duplicate-list]');
    let timer = null;

    const check = () => {
        const phone = input.value.trim();

        if (phone.replace(/\D/g, '').length < 7) {
            box.classList.add('d-none');

            return;
        }

        const url = `${input.dataset.duplicateCheck}?phone=${encodeURIComponent(phone)}&ignore=${input.dataset.ignore || ''}`;

        fetch(url, { headers: { Accept: 'application/json' } })
            .then((response) => (response.ok ? response.json() : []))
            .then((matches) => {
                list.replaceChildren(...matches.map((match, index) => {
                    const link = Object.assign(document.createElement('a'), { href: match.url, textContent: match.name, target: '_blank' });
                    const span = document.createElement('span');
                    if (index > 0) span.append(document.createTextNode(' · '));
                    span.append(link);

                    return span;
                }));
                box.classList.toggle('d-none', matches.length === 0);
            });
    };

    input.addEventListener('input', () => {
        clearTimeout(timer);
        timer = setTimeout(check, 400);
    });
});

// Areas follow the chosen emirate.
document.querySelectorAll('select[data-parent-select]').forEach((parent) => {
    const child = document.getElementById(parent.dataset.parentSelect);

    if (!child) {
        return;
    }

    const apply = () => {
        child.querySelectorAll('option[data-parent]').forEach((option) => {
            option.hidden = parent.value !== '' && option.dataset.parent !== parent.value;
            if (option.hidden && option.selected) child.value = '';
        });
    };

    parent.addEventListener('change', apply);
    apply();
});

// Quick add modal: saves by AJAX and hands the new client to the form that opened it.
const quickForm = document.querySelector('[data-client-quick-form]');

if (quickForm) {
    quickForm.addEventListener('submit', (event) => {
        event.preventDefault();
        quickForm.querySelectorAll('[data-error-for]').forEach((node) => { node.textContent = ''; });

        fetch(quickForm.action, {
            method: 'POST',
            headers: { Accept: 'application/json', 'X-CSRF-TOKEN': csrf() },
            body: new FormData(quickForm),
        }).then(async (response) => {
            const data = await response.json().catch(() => ({}));

            if (response.status === 422) {
                Object.entries(data.errors || {}).forEach(([field, messages]) => {
                    const node = quickForm.querySelector(`[data-error-for="${field}"]`);
                    if (node) node.textContent = messages[0];
                });

                return;
            }

            if (!response.ok) {
                window.toast('error', window.I18N.request_failed);

                return;
            }

            document.querySelectorAll('select[data-client-select]').forEach((select) => {
                const option = new Option(data.name, data.id, true, true);

                if (select.tomselect) {
                    select.tomselect.addOption({ value: String(data.id), text: data.name });
                    select.tomselect.setValue(String(data.id));
                } else {
                    select.append(option);
                    select.value = String(data.id);
                }

                select.dispatchEvent(new Event('change', { bubbles: true }));
            });

            window.dispatchEvent(new CustomEvent('client-created', { detail: data }));
            window.bootstrap.Modal.getOrCreateInstance(quickForm.closest('.modal')).hide();
            quickForm.reset();
            window.toast('success', data.message);
        });
    });
}

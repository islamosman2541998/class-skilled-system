// Claim form (spec §8.14): per line, enter the current quantity or the cumulative percent.
// Only the active input is enabled, so the server receives one value per line. A cumulative
// outside zero..contract is flagged here; the server checks it again.
const t = (key) => (window.I18N && window.I18N.claims && window.I18N.claims[key]) || '';

const form = document.querySelector('[data-claim-form]');

if (form) {
    const number = (value) => {
        const parsed = parseFloat(String(value).replace(/,/g, ''));
        return Number.isFinite(parsed) ? parsed : null;
    };

    const check = (row) => {
        const contract = number(row.dataset.contract) ?? 0;
        const previous = number(row.dataset.previous) ?? 0;
        const percentMode = row.querySelector('[data-mode][value="percent"]').checked;
        const input = row.querySelector(percentMode ? '[data-entry="percent"]' : '[data-entry="quantity"]');
        const value = number(input.value);
        const warning = row.querySelector('[data-line-warning]');
        let bad = false;

        if (value !== null) {
            const cumulative = percentMode ? (contract * value) / 100 : previous + value;
            const low = Math.min(0, contract);
            const high = Math.max(0, contract);
            bad = cumulative < low - 0.0005 || cumulative > high + 0.0005;
        }

        input.classList.toggle('is-invalid', bad);
        warning.textContent = bad ? t('beyond_contract') : '';
        warning.classList.toggle('d-block', bad);
    };

    const setMode = (row) => {
        const percentMode = row.querySelector('[data-mode][value="percent"]').checked;
        const quantity = row.querySelector('[data-entry="quantity"]');
        const percentWrap = row.querySelector('[data-entry-wrap="percent"]');
        const percent = row.querySelector('[data-entry="percent"]');

        quantity.hidden = percentMode;
        quantity.disabled = percentMode;
        percentWrap.hidden = !percentMode;
        percent.disabled = !percentMode;
        check(row);
    };

    form.querySelectorAll('[data-claim-line]').forEach((row) => {
        row.querySelectorAll('[data-mode]').forEach((radio) => radio.addEventListener('change', () => setMode(row)));
        row.querySelectorAll('[data-entry]').forEach((input) => input.addEventListener('input', () => check(row)));
    });
}

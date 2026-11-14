// Quotation form (spec §8.5): item rows with drag sorting, price list picker, live totals and the
// payment schedule. Totals here are for display only; the server recomputes them on save.
import Sortable from 'sortablejs';

const form = document.querySelector('[data-quotation-form]');

if (form) {
    const decimals = Number(form.dataset.decimals || 2);
    const vatRate = form.dataset.vat || '0';
    const currency = form.dataset.currency || '';
    const items = form.querySelector('[data-items]');
    const payments = form.querySelector('[data-payments]');
    let nextItem = 10000;
    let nextPayment = 10000;

    // Fixed-point arithmetic in integer units of 10^-scale, with half-up rounding like the server.
    const SCALE = 6;
    const toUnits = (value) => {
        const text = String(value ?? '').replace(/,/g, '').trim();
        if (!/^\d*(\.\d*)?$/.test(text) || text === '' || text === '.') return 0n;
        const [whole, fraction = ''] = text.split('.');
        return BigInt(whole || '0') * 10n ** BigInt(SCALE) + BigInt((fraction + '0'.repeat(SCALE)).slice(0, SCALE) || '0');
    };
    const roundUnits = (units, places = decimals) => {
        const step = 10n ** BigInt(SCALE - places);
        return ((units + step / 2n) / step) * step;
    };
    const mul = (a, b) => (a * b) / 10n ** BigInt(SCALE);
    const pct = (a, percent) => mul(a, percent) / 100n;
    const format = (units) => {
        const step = 10n ** BigInt(SCALE - decimals);
        const value = roundUnits(units) / step;
        const whole = (value / 10n ** BigInt(decimals)).toString().replace(/\B(?=(\d{3})+(?!\d))/g, ',');
        const fraction = decimals > 0 ? `.${(value % 10n ** BigInt(decimals)).toString().padStart(decimals, '0')}` : '';
        return `${whole}${fraction}`;
    };
    const money = (units) => `${format(units)} ${currency}`;

    const recalc = () => {
        let subtotal = 0n;

        items.querySelectorAll('[data-item]').forEach((row) => {
            const line = roundUnits(mul(toUnits(row.querySelector('[data-field="quantity"]').value), toUnits(row.querySelector('[data-field="unit_price"]').value)));
            row.querySelector('[data-line-total]').textContent = format(line);
            subtotal += line;
        });

        const type = form.querySelector('#discount_type').value;
        const discountValue = toUnits(form.querySelector('#discount_value').value);
        let discount = 0n;
        if (type === 'percent') discount = roundUnits(pct(subtotal, discountValue));
        if (type === 'amount') discount = roundUnits(discountValue);
        const taxable = subtotal - discount;
        const vat = roundUnits(pct(taxable, toUnits(vatRate)));
        const total = taxable + vat;

        const set = (key, units) => form.querySelectorAll(`[data-total="${key}"]`).forEach((node) => { node.textContent = money(units); });
        set('subtotal', subtotal);
        set('discount', discount);
        set('taxable', taxable);
        set('vat', vat);
        set('total', total);

        // Payment amounts: rounded per line, the last one takes the remainder (as on the server).
        const rows = Array.from(payments.querySelectorAll('[data-payment]'));
        let allocated = 0n;
        let percentSum = 0n;
        rows.forEach((row, index) => {
            const percent = toUnits(row.querySelector('[data-field="percent"]').value);
            percentSum += percent;
            const amount = index === rows.length - 1 ? total - allocated : roundUnits(pct(total, percent));
            allocated += amount;
            row.querySelector('[data-payment-amount]').textContent = format(amount);
        });

        const sum = form.querySelector('[data-payment-sum]');
        sum.textContent = format(percentSum).replace(/,/g, '');
        sum.classList.toggle('text-danger', percentSum !== 100n * 10n ** BigInt(SCALE));
    };

    const addItem = (values = {}) => {
        const html = form.querySelector('template[data-item-template]').innerHTML.replaceAll('__I__', String(nextItem++));
        items.insertAdjacentHTML('beforeend', html);
        const row = items.lastElementChild;
        Object.entries(values).forEach(([field, value]) => {
            const input = row.querySelector(`[data-field="${field}"]`);
            if (input) input.value = value ?? '';
        });
        recalc();
        return row;
    };

    const addPayment = (values = {}) => {
        const html = form.querySelector('template[data-payment-template-row]').innerHTML.replaceAll('__P__', String(nextPayment++));
        payments.insertAdjacentHTML('beforeend', html);
        const row = payments.lastElementChild;
        Object.entries(values).forEach(([field, value]) => {
            const input = row.querySelector(`[data-field="${field}"]`);
            if (input) input.value = value ?? '';
        });
    };

    form.addEventListener('input', (event) => {
        if (event.target.matches('[data-calc]')) recalc();
        if (event.target.matches('textarea.q-desc, textarea.q-desc-en')) {
            event.target.style.height = 'auto';
            event.target.style.height = `${event.target.scrollHeight}px`;
        }
    });
    form.addEventListener('change', (event) => { if (event.target.matches('[data-calc]')) recalc(); });

    form.addEventListener('click', (event) => {
        if (event.target.closest('[data-item-add]')) {
            const last = items.querySelector('[data-item]:last-child [data-field="section_ar"]');
            addItem({ section_ar: last ? last.value : '', quantity: '1', unit_price: '0' }).querySelector('[data-field="description_ar"]').focus();
        }

        if (event.target.closest('[data-item-remove]') && items.querySelectorAll('[data-item]').length > 1) {
            event.target.closest('[data-item]').remove();
            recalc();
        }

        if (event.target.closest('[data-payment-add]')) {
            addPayment();
            recalc();
        }

        if (event.target.closest('[data-payment-remove]')) {
            event.target.closest('[data-payment]').remove();
            recalc();
        }

        if (event.target.closest('[data-payment-apply]')) {
            const templates = JSON.parse(form.querySelector('[data-payment-templates]').textContent || '[]');
            const template = templates[Number(form.querySelector('[data-payment-template]').value)];
            if (template) {
                payments.replaceChildren();
                template.lines.forEach((line) => addPayment(line));
                recalc();
            }
        }
    });

    // Price list picker: choosing an item adds a filled row, then the picker clears.
    const picker = form.querySelector('[data-price-picker]');
    picker?.addEventListener('change', () => {
        const ts = picker.tomselect;
        const data = ts && picker.value ? ts.options[picker.value] : null;
        if (!data) return;

        const last = items.querySelector('[data-item]:last-child');
        const lastEmpty = last && last.querySelector('[data-field="description_ar"]').value.trim() === '';
        if (lastEmpty) last.remove();

        addItem({
            price_item_id: data.id,
            section_ar: last && !lastEmpty ? last.querySelector('[data-field="section_ar"]').value : '',
            description_ar: data.description_ar,
            description_en: data.description_en || '',
            unit_id: data.unit_id,
            quantity: '1',
            unit_price: data.price,
        });
        ts.clear(true);
    });

    // The picker offers items of the chosen service (plus general ones).
    const service = form.querySelector('[data-service-select]');
    const baseRemote = picker?.dataset.remote;
    const syncPickerService = () => {
        if (!picker || !baseRemote) return;
        const url = new URL(baseRemote, window.location.origin);
        if (service.value) url.searchParams.set('service_id', service.value);
        picker.dataset.remote = url.toString();
        picker.tomselect?.clearOptions();
    };
    service?.addEventListener('change', syncPickerService);
    syncPickerService();

    Sortable.create(items, { handle: '[data-sort-handle]', animation: 150 });

    recalc();
}

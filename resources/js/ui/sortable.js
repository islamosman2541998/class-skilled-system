// Drag ordering: <ul data-sortable data-url="..."> with <li data-id>, saved by a [data-sortable-save] button
// in the same modal. SortableJS is loaded only when such a list exists.

const t = (key, fallback = '') => (window.I18N && window.I18N[key]) || fallback;

export function initSortables() {
    const lists = document.querySelectorAll('[data-sortable]');

    if (!lists.length) {
        return;
    }

    import('sortablejs').then(({ default: Sortable }) => {
        lists.forEach((list) => {
            Sortable.create(list, { animation: 120, handle: '.sort-handle', ghostClass: 'is-dragging' });

            const scope = list.closest('.modal') || document;
            const save = scope.querySelector('[data-sortable-save]');

            save?.addEventListener('click', async () => {
                save.disabled = true;

                try {
                    const response = await fetch(list.dataset.url, {
                        method: 'POST',
                        headers: {
                            Accept: 'application/json',
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                        },
                        credentials: 'same-origin',
                        body: JSON.stringify({ ids: [...list.querySelectorAll('[data-id]')].map((item) => Number(item.dataset.id)) }),
                    });

                    const data = await response.json().catch(() => ({}));

                    if (!response.ok) {
                        window.toast('error', data.message || t('request_failed'));

                        return;
                    }

                    window.toast('success', data.message);
                    window.bootstrap.Modal.getInstance(list.closest('.modal'))?.hide();
                    window.Livewire?.dispatch('table-refresh');
                } catch (error) {
                    window.toast('error', t('request_failed'));
                } finally {
                    save.disabled = false;
                }
            });
        });
    });
}

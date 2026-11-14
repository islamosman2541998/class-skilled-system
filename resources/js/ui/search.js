// Global search modal (spec §5.6): Ctrl+K, 250 ms debounce, arrow keys and Enter, Escape closes.
import { Modal } from 'bootstrap';

const text = (key) => (window.I18N && window.I18N[key]) || '';

export function initSearch() {
    const element = document.getElementById('search-modal');

    if (!element) {
        return;
    }

    const modal = Modal.getOrCreateInstance(element);
    const input = element.querySelector('#search-input');
    const results = element.querySelector('[data-search-results]');
    const hint = results.innerHTML;
    let timer = null;
    let controller = null;
    let active = -1;

    const links = () => Array.from(results.querySelectorAll('a.search-result'));

    const highlight = (index) => {
        const items = links();
        items.forEach((item, i) => item.classList.toggle('is-active', i === index));
        active = index;
        items[index]?.scrollIntoView({ block: 'nearest' });
    };

    const render = (groups) => {
        results.replaceChildren();
        active = -1;
        input.setAttribute('aria-expanded', groups.length ? 'true' : 'false');

        if (!groups.length) {
            const empty = document.createElement('p');
            empty.className = 'search-empty';
            empty.textContent = text('no_results');
            results.append(empty);

            return;
        }

        groups.forEach((group) => {
            const heading = document.createElement('div');
            heading.className = 'search-group';
            heading.textContent = group.label;
            results.append(heading);

            group.results.forEach((result) => {
                const link = document.createElement('a');
                link.className = 'search-result';
                link.href = result.url;
                link.setAttribute('role', 'option');

                const title = document.createElement('span');
                title.className = 'search-result-title';
                title.textContent = result.title;
                link.append(title);

                if (result.subtitle) {
                    const subtitle = document.createElement('span');
                    subtitle.className = 'search-result-subtitle';
                    subtitle.textContent = result.subtitle;
                    link.append(subtitle);
                }

                results.append(link);
            });
        });
    };

    const run = () => {
        const term = input.value.trim();
        controller?.abort();

        if (term.length < 2) {
            results.innerHTML = hint;

            return;
        }

        controller = new AbortController();
        const url = `${element.dataset.searchUrl}?q=${encodeURIComponent(term)}`;

        fetch(url, { headers: { Accept: 'application/json' }, signal: controller.signal })
            .then((response) => (response.ok ? response.json() : Promise.reject(new Error('failed'))))
            .then(render)
            .catch((error) => {
                if (error.name !== 'AbortError') {
                    results.textContent = text('request_failed');
                }
            });
    };

    input.addEventListener('input', () => {
        clearTimeout(timer);
        timer = setTimeout(run, 250);
    });

    input.addEventListener('keydown', (event) => {
        const items = links();

        if (event.key === 'ArrowDown' && items.length) {
            event.preventDefault();
            highlight((active + 1) % items.length);
        } else if (event.key === 'ArrowUp' && items.length) {
            event.preventDefault();
            highlight((active - 1 + items.length) % items.length);
        } else if (event.key === 'Enter' && items[active]) {
            event.preventDefault();
            window.location.href = items[active].href;
        }
    });

    element.addEventListener('shown.bs.modal', () => input.focus());

    document.querySelectorAll('[data-search-open]').forEach((trigger) => trigger.addEventListener('click', () => modal.show()));

    document.addEventListener('keydown', (event) => {
        if ((event.ctrlKey || event.metaKey) && event.key.toLowerCase() === 'k') {
            event.preventDefault();
            modal.show();
        }
    });
}

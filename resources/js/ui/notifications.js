// Topbar bell: the newest notifications are fetched only when the dropdown opens (spec §5.5).
export function initNotifications() {
    const bell = document.querySelector('[data-notifications]');

    if (!bell) {
        return;
    }

    const body = bell.querySelector('[data-notifications-body]');
    let loaded = false;

    bell.addEventListener('show.bs.dropdown', () => {
        if (loaded) {
            return;
        }

        fetch(bell.dataset.latestUrl, { headers: { Accept: 'text/html', 'X-Requested-With': 'XMLHttpRequest' } })
            .then((response) => (response.ok ? response.text() : Promise.reject()))
            .then((html) => { body.innerHTML = html; loaded = true; })
            .catch(() => { body.textContent = (window.I18N && window.I18N.request_failed) || ''; });
    });
}

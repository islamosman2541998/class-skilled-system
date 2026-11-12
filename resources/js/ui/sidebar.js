const KEY = 'sidebar-collapsed';

export function initSidebar() {
    const app = document.querySelector('.app');

    if (!app) {
        return;
    }

    const desktop = () => window.matchMedia('(min-width: 992px)').matches;

    document.querySelectorAll('[data-sidebar-toggle]').forEach((button) => {
        button.addEventListener('click', () => {
            if (desktop()) {
                const collapsed = app.classList.toggle('is-collapsed');

                try {
                    localStorage.setItem(KEY, collapsed ? '1' : '0');
                } catch (error) {
                    // Storage can be unavailable; the sidebar still works for this page.
                }
            } else {
                app.classList.toggle('sidebar-open');
            }
        });
    });

    document.querySelector('.sidebar-backdrop')?.addEventListener('click', () => app.classList.remove('sidebar-open'));

    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape') {
            app.classList.remove('sidebar-open');
        }
    });
}

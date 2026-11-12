import toastr from 'toastr';
import 'toastr/build/toastr.min.css';

const TYPES = ['success', 'error', 'warning', 'info'];

export function initToasts() {
    const rtl = document.documentElement.dir === 'rtl';

    toastr.options = {
        rtl,
        positionClass: window.innerWidth < 576 ? 'toast-top-center' : (rtl ? 'toast-top-left' : 'toast-top-right'),
        timeOut: 3500,
        extendedTimeOut: 1500,
        closeButton: false,
        progressBar: false,
        newestOnTop: true,
        preventDuplicates: true,
        escapeHtml: true,
    };

    window.toast = (type, message) => {
        if (!message) {
            return;
        }

        toastr[TYPES.includes(type) ? type : 'info'](message);
    };

    // Messages flashed by the server on a normal page load.
    const flash = document.getElementById('flash-data');

    if (flash) {
        Object.entries(JSON.parse(flash.textContent || '{}')).forEach(([type, message]) => window.toast(type, message));
    }

    // Messages dispatched by Livewire components: $this->dispatch('toast', type: 'success', message: '...').
    window.addEventListener('toast', (event) => {
        const detail = Array.isArray(event.detail) ? event.detail[0] : event.detail;

        window.toast(detail?.type, detail?.message);
    });
}

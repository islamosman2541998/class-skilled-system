import $ from 'jquery';
import { Dropdown, Collapse, Modal } from 'bootstrap';

import { initToasts } from './ui/toast';
import { initConfirm } from './ui/confirm';
import { initForms } from './ui/forms';
import { initSelects } from './ui/select';
import { initDatepickers } from './ui/datepicker';
import { initSidebar } from './ui/sidebar';

window.$ = window.jQuery = $;
window.bootstrap = { Dropdown, Collapse, Modal };

// Controls that may appear after a Livewire update are initialised through this one function.
window.initUi = (root = document) => {
    initSelects(root);
    initDatepickers(root);
};

$(() => {
    initToasts();
    initConfirm();
    initForms();
    initSidebar();
    window.initUi();
});

document.addEventListener('livewire:init', () => {
    window.Livewire.hook('morphed', ({ el }) => window.initUi(el));
});

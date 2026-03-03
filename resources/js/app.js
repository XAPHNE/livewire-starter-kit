import flatpickr from 'flatpickr';
import TomSelect from 'tom-select';

window.flatpickr = flatpickr;
window.TomSelect = TomSelect;

import './../../vendor/power-components/livewire-powergrid/dist/powergrid';

document.addEventListener('livewire:navigating', () => {
    if (window.Alpine && window.Alpine.store('pgBulkActions')) {
        window.Alpine.store('pgBulkActions').clearAll();
    }
});
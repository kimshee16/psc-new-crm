import flatpickr from 'flatpickr';
import 'flatpickr/dist/flatpickr.min.css';
import '../css/critical-date-range.css';

document.querySelectorAll('[data-critical-date-range]').forEach(root => {
    const input = root.querySelector('[data-range-input]');
    const from = root.querySelector('[data-range-from]');
    const to = root.querySelector('[data-range-to]');
    const clear = root.querySelector('[data-range-clear]');
    const initialDates = () => [from.value, to.value].filter(Boolean);
    const format = value => value ? value.split('-').reverse().join('/') : '';
    let hint;

    const display = () => {
        input.value = from.value && to.value ? `${format(from.value)} – ${format(to.value)}`
            : from.value ? `From ${format(from.value)}` : to.value ? `Until ${format(to.value)}` : '';
        clear.hidden = !from.value && !to.value;
    };
    const commit = (dates, picker) => {
        if (dates.length === 2) {
            from.value = picker.formatDate(dates[0], 'Y-m-d');
            to.value = picker.formatDate(dates[1], 'Y-m-d');
        } else if (dates.length === 0) {
            from.value = ''; to.value = '';
        }
        display();
    };
    const picker = flatpickr(input, {
        mode: 'range',
        dateFormat: 'd/m/Y',
        defaultDate: initialDates().map(value => flatpickr.parseDate(value, 'Y-m-d')),
        disableMobile: true,
        locale: {firstDayOfWeek: 1},
        ariaDateFormat: 'l, j F Y',
        onReady: (_dates, _value, instance) => {
            instance.calendarContainer.classList.add('critical-range-picker');
            instance.calendarContainer.id = 'critical-date-calendar';
            instance.calendarContainer.setAttribute('role', 'dialog');
            instance.calendarContainer.setAttribute('aria-label', 'Choose critical date range');
            input.setAttribute('aria-controls', instance.calendarContainer.id);
            hint = document.createElement('p');
            hint.className = 'critical-range-hint';
            hint.setAttribute('aria-live', 'polite');
            hint.textContent = 'Choose a start and end date. Both dates are inclusive.';
            instance.calendarContainer.append(hint);
            display();
        },
        onOpen: () => { input.setAttribute('aria-expanded', 'true'); },
        onChange: (dates, _value, instance) => {
            hint.textContent = dates.length === 1 ? 'Now choose the end date.' : 'Choose a start and end date. Both dates are inclusive.';
            if (dates.length !== 1) commit(dates, instance);
        },
        onClose: (dates, _value, instance) => {
            input.setAttribute('aria-expanded', 'false');
            if (dates.length === 1) {
                // Dismissing an unfinished selection keeps the previously applied range.
                instance.setDate(initialDates().map(value => flatpickr.parseDate(value, 'Y-m-d')), false);
                display();
            } else {
                commit(dates, instance);
            }
            hint.textContent = 'Choose a start and end date. Both dates are inclusive.';
        },
    });
    clear.addEventListener('click', () => {
        picker.clear();
        input.focus();
        picker.close();
    });
});

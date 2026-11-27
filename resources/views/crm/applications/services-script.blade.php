<script>
(() => {
    const dialog = document.getElementById('application-service-dialog');
    const form = dialog.querySelector('form');
    const type = form.elements.type;
    const option = form.elements.option;
    const quantity = form.elements.quantity;
    const screenshot = form.elements.screenshot;
    const error = form.querySelector('[role="alert"]');
    const submit = form.querySelector('[type="submit"]');
    const remove = form.querySelector('[data-remove-service]');
    const totalBox = form.querySelector('[data-service-total]');
    const fields = [...form.querySelectorAll('[data-service-types]')];
    const visaLines = [...form.querySelectorAll('[data-visa-line]')];
    const storeUrl = @js(route('applications.services.store', $application));
    const rows = @js($rowsForEdit);
    const configuredFees = @js((object) $visaOptions);
    const fixedRates = @js(\App\Models\ApplicationService::RATES);
    const money = cents => 'A$ ' + (cents / 100).toLocaleString('en-AU', {
        minimumFractionDigits: 2, maximumFractionDigits: 2,
    });
    let row = null;
    let feeOptions = {};
    let busy = false;

    function render() {
        for (const field of fields) {
            field.hidden = field.disabled = !field.dataset.serviceTypes.split(' ').includes(type.value);
        }

        const visa = type.value === 'visa';
        const rates = feeOptions[option.value]?.rates;
        form.querySelector('[data-visa-option]').hidden = !rates || Object.keys(feeOptions).length < 2;
        form.querySelector('[data-no-visa-fees]').hidden = !!rates;
        let total = 0;
        for (const line of visaLines) {
            const key = line.dataset.visaLine;
            const price = rates?.[key];
            const input = line.querySelector('input');
            line.hidden = input.disabled = !price || !visa;
            if (price) {
                const lineTotal = price * Number(input.value);
                line.querySelector('[data-visa-rate]').textContent = money(price) + ' per applicant';
                line.querySelector('[data-visa-total]').textContent = money(lineTotal);
                total += lineTotal;
            }
        }

        const rate = row?.type === type.value ? row.details.rate : fixedRates[type.value];
        if (rate) {
            quantity.readOnly = type.value === 'gsr';
            form.querySelector('[data-fixed-rate]').textContent = money(rate);
            form.querySelector('[data-rate-unit]').textContent = {
                translation: 'Per page', afp: 'Per check', gsr: 'One per application',
            }[type.value];
            total = rate * (type.value === 'gsr' ? 1 : Number(quantity.value));
        }
        form.querySelector('[data-amount-label]').textContent = type.value === 'other' ? 'Price (AUD)' : 'Amount (AUD)';
        totalBox.hidden = !(visa && rates) && !rate;
        totalBox.querySelector('output').textContent = money(total);
        submit.disabled = busy || !type.value || (visa && !rates);
    }

    function chooseFees() {
        feeOptions = { ...configuredFees };
        if (row?.type === 'visa') {
            feeOptions[row.details.option] = row.details;
        }
        option.replaceChildren(...Object.entries(feeOptions).map(([key, value]) => new Option(value.name || 'Standard fee', key)));
        if (row?.type === 'visa') option.value = row.details.option;
    }

    function open(id) {
        row = rows[id] ?? null;
        form.reset();
        chooseFees();
        if (row) {
            type.value = row.type;
            for (const [key, value] of Object.entries(row.details)) {
                if (form.elements[key] && typeof value !== 'object') form.elements[key].value = value;
            }
        }
        for (const line of visaLines) {
            const key = line.dataset.visaLine;
            if (key !== 'primary') line.querySelector('input').value = row?.details.quantities?.[key] ?? 0;
        }
        form.querySelector('[data-screenshot-status]').textContent = row?.screenshot
            ? 'Screenshot attached. Paste or choose an image to replace it.'
            : 'Paste a screenshot or choose a PNG, JPEG or WebP image.';
        dialog.querySelector('h2').textContent = row ? 'Edit service' : 'Add a service';
        submit.textContent = row ? 'Save' : 'Add';
        remove.hidden = !row;
        error.hidden = true;
        render();
        dialog.showModal();
    }

    async function save(method) {
        if (busy) return;
        busy = true;
        error.hidden = true;
        remove.disabled = true;
        render();
        const body = method === 'DELETE' ? new FormData() : new FormData(form);
        body.set('_token', form.elements._token.value);
        if (method !== 'POST') body.set('_method', method);

        try {
            const response = await fetch(row?.url ?? storeUrl, {
                method: 'POST', body, headers: { Accept: 'application/json' },
            });
            if (!response.ok) {
                const result = await response.json().catch(() => ({}));
                throw new Error(Object.values(result.errors ?? {}).flat().join('\n') || result.message || 'The service could not be saved. Try again.');
            }
            window.location.reload();
        } catch (failure) {
            error.textContent = failure.message;
            error.hidden = false;
            busy = false;
            remove.disabled = false;
            render();
        }
    }

    document.getElementById('add-application-service').addEventListener('click', () => open(null));
    document.querySelectorAll('[data-edit-service]').forEach(button => {
        button.addEventListener('click', () => open(button.dataset.editService));
    });
    dialog.querySelectorAll('[data-service-close]').forEach(button => {
        button.addEventListener('click', () => { if (!busy) dialog.close(); });
    });
    dialog.addEventListener('cancel', event => { if (busy) event.preventDefault(); });
    form.addEventListener('input', render);
    type.addEventListener('change', () => { quantity.value = row?.type === type.value ? row.details.quantity ?? 1 : 1; render(); });
    dialog.addEventListener('paste', event => {
        if (type.value !== 'oshc') return;
        const file = [...(event.clipboardData?.files ?? [])].find(item => item.type.startsWith('image/'));
        if (!file) return;
        event.preventDefault();
        const transfer = new DataTransfer();
        transfer.items.add(file);
        screenshot.files = transfer.files;
    });
    form.addEventListener('submit', event => { event.preventDefault(); save(row ? 'PATCH' : 'POST'); });
    remove.addEventListener('click', () => {
        if (window.confirm('Remove this service from the application?')) save('DELETE');
    });
})();
</script>

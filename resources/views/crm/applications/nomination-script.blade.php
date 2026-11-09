<script>
(() => {
    const form = document.getElementById('nomination-record-form');
    const field = name => form.elements.namedItem(name);
    const outcome = field('outcome'), finalOutcome = field('final_outcome');
    const appeal = form.querySelector('[data-appeal-section]');
    const expiry = form.querySelector('[data-expiry-field]');
    const sbsCodes = @json($sbsRequiredSubclasses);
    const hasActiveSbs = @json($hasActiveSbs);
    const activeSbsId = @json($activeSbsId);
    const lockedSubclass = @json($application->nomination_pathway);
    const refresh = () => {
        const refused = outcome.value === 'Refused';
        appeal.hidden = !refused;
        appeal.disabled = !refused;
        field('outcome_date').required = ['Approved', 'Refused', 'Withdrawn'].includes(outcome.value);
        field('final_outcome_date').required = refused && !!finalOutcome.value;
        field('appeal_deadline').disabled = !refused || field('appeal_status').value === 'Not reviewable';
        if (refused && field('appeal_status').value === 'Not reviewable') field('appeal_deadline').value = '';
        const approved = outcome.value === 'Approved' || (refused && finalOutcome.value === 'Approved');
        expiry.hidden = !approved;
        field('expiry_date').disabled = !approved;
        field('expiry_date').required = refused && finalOutcome.value === 'Approved';
        form.querySelector(refused ? '[data-final-expiry-slot]' : '[data-original-expiry-slot]').append(expiry);
        form.querySelectorAll('[data-sponsorship-id]').forEach(item => { item.hidden = item.dataset.sponsorshipId !== field('sponsorship_id').value; });
        const subclass = field('nomination_pathway')?.value || lockedSubclass || '';
        form.querySelector('[data-sbs-warning]').hidden = hasActiveSbs || !sbsCodes.includes(subclass.slice(0, 3));
    };
    ['outcome', 'final_outcome', 'appeal_status', 'sponsorship_id', 'nomination_pathway'].forEach(name => field(name)?.addEventListener('change', refresh));
    field('nomination_pathway')?.addEventListener('change', () => {
        field('sponsorship_id').value = sbsCodes.includes(field('nomination_pathway').value.slice(0, 3)) ? (activeSbsId || '') : '';
        refresh();
    });
    refresh();

    const picker = form.querySelector('[data-nominee-picker]');
    if (!picker) return;
    const checkbox = picker.querySelector('input[type=checkbox]');
    const clientId = field('nominee_client_reference');
    const search = document.getElementById('nominee-client-search');
    const results = document.getElementById('nominee-results');
    const status = document.getElementById('nominee-selection');
    let timer, generation = 0, controller;
    const toggleNominee = () => {
        picker.querySelector('[data-internal-nominee]').hidden = checkbox.checked;
        picker.querySelector('[data-external-nominee]').hidden = !checkbox.checked;
        clientId.disabled = checkbox.checked;
        search.disabled = checkbox.checked;
        field('nominee_name').disabled = !checkbox.checked;
        field('nominee_name').required = checkbox.checked;
        if (checkbox.checked) { generation++; controller?.abort(); results.replaceChildren(); }
    };
    checkbox.addEventListener('change', toggleNominee);
    toggleNominee();
    search.addEventListener('input', () => {
        clearTimeout(timer); controller?.abort();
        const current = ++generation;
        clientId.value = ''; results.replaceChildren();
        status.textContent = 'Select a client from the results.';
        const query = search.value.trim();
        if (!query) return;
        timer = setTimeout(async () => {
            controller = new AbortController(); status.textContent = 'Searching clients…';
            try {
                const url = new URL(picker.dataset.searchUrl, location.href); url.searchParams.set('q', query);
                const response = await fetch(url, {credentials:'same-origin', headers:{Accept:'application/json'}, signal:controller.signal});
                if (!response.ok || response.redirected) throw new Error('Unable to search clients. Refresh and try again.');
                const data = await response.json();
                if (current !== generation) return;
                results.replaceChildren();
                data.clients.forEach(client => {
                    const button = document.createElement('button'); button.type = 'button';
                    button.className = 'block w-full rounded border border-slate-200 p-2 text-left hover:bg-slate-50';
                    button.textContent = `${client.name} · Client ${client.client_id}`;
                    button.addEventListener('click', () => {
                        clientId.value = client.client_id; search.value = client.name;
                        results.replaceChildren(); status.textContent = `Selected Client ${client.client_id}`;
                    });
                    results.append(button);
                });
                status.textContent = data.clients.length ? 'Select a client.' : 'No clients found.';
            } catch (error) {
                if (error.name !== 'AbortError' && current === generation) status.textContent = error.message;
            }
        }, 250);
    });
    form.addEventListener('submit', event => {
        if (!checkbox.checked && !clientId.value) {
            event.preventDefault(); status.textContent = 'Select a nominee from the search results.'; search.focus();
        }
    });
})();
</script>

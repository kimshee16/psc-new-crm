@php
    $reopenNomination = old('_create_nomination') === '1' && $errors->any();
    $nomineeLabel = $selectedNominee ? trim(implode(' ', array_filter([$selectedNominee->first_name, $selectedNominee->middle_name, $selectedNominee->surname]))) : '';
@endphp
<style>
    .nomination-dialog { width: min(536px, calc(100vw - 32px)); max-height: calc(100dvh - 32px); margin: auto; padding: 0; overflow-y: auto; border: 0; border-radius: 11px; background: white; color: #1b2027; box-shadow: 0 24px 50px #0004; font-size: 13px; }
    .nomination-dialog::backdrop { background: #20282e99; }
    .nomination-dialog header { display: flex; align-items: center; justify-content: space-between; padding: 17px 22px; border-bottom: 1px solid #e1e4e8; }
    .nomination-dialog h2 { margin: 0; font-size: 18px; font-weight: 750; }
    .nomination-dialog .nomination-close { padding: 0 2px; border: 0; background: transparent; font-size: 24px; line-height: 26px; font-weight: 400; }
    .nomination-fields { display: grid; grid-template-columns: 140px minmax(0, 1fr); column-gap: 17px; row-gap: 17px; padding: 19px 22px 21px; }
    .nomination-fields > label, .nomination-fields > .nomination-label { padding-top: 10px; font-size: 12px; font-weight: 700; }
    .nomination-fields > .nomination-label { padding-top: 0; }
    .nomination-organisation { line-height: 22px; overflow-wrap: anywhere; }
    .nomination-dialog input[type="text"], .nomination-dialog select { display: block; width: 100%; min-height: 40px; padding: 9px 11px; border: 1px solid #bfc6ce; border-radius: 6px; background: white; font: inherit; }
    .nomination-dialog input::placeholder { color: #505b6b; opacity: 1; }
    .nomination-external { display: flex; align-items: flex-start; gap: 9px; margin-top: 15px; font-size: 12px; line-height: 18px; cursor: pointer; }
    .nomination-external input { flex-shrink: 0; width: 16px; height: 16px; margin-top: 1px; accent-color: #204f7f; }
    .nomination-dialog footer { display: flex; justify-content: flex-end; gap: 10px; padding: 14px 22px; border-top: 1px solid #dfe3e9; background: #f4f6fb; }
    .nomination-dialog button { cursor: pointer; }
    .nomination-dialog footer button { min-height: 40px; padding: 9px 18px; border: 1px solid #b9c1cc; border-radius: 7px; background: white; font: inherit; font-weight: 700; }
    .nomination-dialog footer button[type="submit"] { border-color: #204f7f; background: #204f7f; color: white; }
    .nomination-dialog button:disabled { cursor: wait; opacity: .65; }
    .nomination-dialog :focus-visible { outline: 2px solid #204f7f; outline-offset: 2px; }
    .nomination-dialog [hidden] { display: none; }
    .nomination-search { position: relative; }
    .nomination-results { position: absolute; z-index: 1; top: 44px; left: 0; right: 0; max-height: 180px; overflow-y: auto; border: 1px solid #bfc6ce; border-radius: 6px; background: white; box-shadow: 0 8px 18px #0002; }
    .nomination-result { padding: 9px 11px; overflow-wrap: anywhere; cursor: pointer; }
    .nomination-result:hover, .nomination-result[aria-selected="true"] { background: #eaf1f9; }
    .nomination-search-status { margin-top: 6px; color: #526071; font-size: 12px; }
    .nomination-errors { margin: 16px 22px 0; padding: 12px; border: 1px solid #fecaca; border-radius: 6px; background: #fef2f2; color: #991b1b; }
    @media (max-width: 520px) { .nomination-fields { grid-template-columns: minmax(0, 1fr); row-gap: 9px; } .nomination-fields > label { padding-top: 6px; } .nomination-external { margin-bottom: 7px; } }
</style>
<dialog id="nomination-create-dialog" class="nomination-dialog" aria-labelledby="nomination-create-title">
    <form method="POST" action="{{ route('organisations.applications.store', $organisation) }}">
        @csrf
        <input type="hidden" name="category" value="Nomination">
        <input type="hidden" name="_create_nomination" value="1">
        <header>
            <h2 id="nomination-create-title">Create a nomination</h2>
            <button type="button" class="nomination-close" data-nomination-close aria-label="Close Create a nomination">&times;</button>
        </header>
        @if ($reopenNomination)
            <div class="nomination-errors" role="alert" tabindex="-1">
                <p class="font-semibold">The nomination could not be created.</p>
                <ul class="mt-1 list-disc pl-4">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
            </div>
        @endif
        <div class="nomination-fields">
            <div class="nomination-label" id="nomination-organisation-label">Organisation name</div>
            <div class="nomination-organisation" aria-labelledby="nomination-organisation-label">
                <div>{{ $organisation->name }}</div>
                @if (filled($organisation->trading_name))<div>{{ $organisation->trading_name }}</div>@endif
            </div>
            <label id="nomination-name-label" for="nomination-client-search">Nominee name <span class="sr-only">(required)</span></label>
            <div>
                <div class="nomination-search">
                    <input id="nomination-client-search" type="text" role="combobox" aria-autocomplete="list" aria-expanded="false" aria-controls="nomination-client-results" aria-describedby="nomination-search-status" autocomplete="off" maxlength="255" placeholder="Search client by name or ID" required autofocus>
                    <input id="nomination-client-reference" type="hidden" name="nominee_client_reference">
                    <div id="nomination-client-results" class="nomination-results" role="listbox" aria-label="Matching clients" hidden></div>
                </div>
                <input id="nomination-external-name" type="text" name="nominee_name" aria-labelledby="nomination-name-label" autocomplete="off" maxlength="255" placeholder="Enter nominee’s full name" hidden disabled>
                <p id="nomination-search-status" class="nomination-search-status" role="status" aria-live="polite" hidden></p>
                <label class="nomination-external" for="nomination-is-external">
                    <input id="nomination-is-external" name="external_nominee" type="checkbox" value="1">
                    <span>External: enter the nominee’s full name instead</span>
                </label>
            </div>
            <label for="nomination-visa-subclass">Visa subclass <span class="sr-only">(required)</span></label>
            <select id="nomination-visa-subclass" name="visa_subclass" required>
                <option value="">Select subclass</option>
                @foreach ($nominationSubclasses as $subclass)<option value="{{ $subclass }}">{{ $subclass }}</option>@endforeach
            </select>
        </div>
        <footer>
            <button type="button" data-nomination-close>Cancel</button>
            <button type="submit">Create</button>
        </footer>
    </form>
</dialog>
<script>
(() => {
    const dialog = document.getElementById('nomination-create-dialog');
    const trigger = document.getElementById('create-nomination');
    const form = dialog.querySelector('form');
    const search = form.querySelector('#nomination-client-search');
    const reference = form.elements.nominee_client_reference;
    const external = form.elements.external_nominee;
    const name = form.elements.nominee_name;
    const label = form.querySelector('#nomination-name-label');
    const results = form.querySelector('#nomination-client-results');
    const status = form.querySelector('#nomination-search-status');
    const submit = form.querySelector('[type="submit"]');
    let timer, controller, generation = 0, matches = [], active = -1;
    const message = text => { status.textContent = text; status.hidden = !text; };
    const hideResults = () => {
        results.hidden = true;
        search.setAttribute('aria-expanded', 'false');
        search.removeAttribute('aria-activedescendant');
        active = -1;
    };
    const stopSearch = () => {
        clearTimeout(timer);
        controller?.abort();
        generation++;
        hideResults();
    };
    const validateSelection = () => search.setCustomValidity(!external.checked && !reference.value ? 'Select a client from the search results, or choose External.' : '');
    const syncMode = () => {
        stopSearch();
        search.hidden = external.checked;
        search.disabled = external.checked;
        search.required = !external.checked;
        reference.disabled = external.checked;
        name.hidden = !external.checked;
        name.disabled = !external.checked;
        name.required = external.checked;
        label.htmlFor = external.checked ? name.id : search.id;
        message('');
        validateSelection();
    };
    const select = index => {
        const client = matches[index];
        if (!client) return;
        stopSearch();
        reference.value = client.client_id;
        search.value = client.name;
        validateSelection();
        message(`Selected: ${client.name} · Client ${client.client_id}`);
        search.focus();
    };
    const highlight = index => {
        active = index;
        [...results.children].forEach((option, i) => option.setAttribute('aria-selected', String(i === active)));
        const option = results.children[active];
        if (option) {
            search.setAttribute('aria-activedescendant', option.id);
            option.scrollIntoView({ block: 'nearest' });
        }
    };
    const lookup = async (term, current) => {
        controller = new AbortController();
        message('Searching…');
        try {
            const url = new URL(@js(route('organisations.nominees', $organisation)), window.location.href);
            url.searchParams.set('q', term);
            const response = await fetch(url, { signal: controller.signal, headers: { Accept: 'application/json' } });
            if (!response.ok) throw new Error('Search failed');
            const data = await response.json();
            if (current !== generation || !dialog.open || external.checked) return;
            matches = data.clients;
            results.replaceChildren();
            matches.forEach((client, index) => {
                const option = document.createElement('div');
                option.id = `nomination-client-option-${index}`;
                option.className = 'nomination-result';
                option.setAttribute('role', 'option');
                option.setAttribute('aria-selected', 'false');
                option.textContent = `${client.name} · Client ${client.client_id}`;
                option.addEventListener('mousedown', event => event.preventDefault());
                option.addEventListener('click', () => select(index));
                results.append(option);
            });
            results.hidden = !matches.length;
            search.setAttribute('aria-expanded', String(!!matches.length));
            message(matches.length ? `${matches.length} matching client${matches.length === 1 ? '' : 's'}. Select a nominee.${matches.length === 20 ? ' Refine your search for more specific results.' : ''}` : 'No clients found. Try another name or ID, or choose External.');
        } catch (error) {
            if (error.name !== 'AbortError' && current === generation) message('Client search is unavailable. Try searching again.');
        }
    };
    search.addEventListener('input', () => {
        stopSearch();
        reference.value = '';
        validateSelection();
        const term = search.value.trim();
        message('');
        if (term) {
            const current = generation;
            timer = setTimeout(() => lookup(term, current), 250);
        }
    });
    search.addEventListener('keydown', event => {
        if (!results.hidden && ['ArrowDown', 'ArrowUp', 'Enter', 'Escape'].includes(event.key)) {
            event.preventDefault();
            if (event.key === 'Escape') { event.stopPropagation(); hideResults(); }
            else if (event.key === 'Enter') { if (active >= 0) select(active); }
            else highlight(active < 0 ? (event.key === 'ArrowDown' ? 0 : matches.length - 1) : (active + (event.key === 'ArrowDown' ? 1 : -1) + matches.length) % matches.length);
        } else if (event.key === 'Enter' && !reference.value) event.preventDefault();
    });
    search.addEventListener('blur', stopSearch);
    external.addEventListener('change', () => {
        reference.value = search.value = name.value = '';
        syncMode();
        (external.checked ? name : search).focus();
    });
    trigger.addEventListener('click', () => dialog.showModal());
    form.querySelectorAll('[data-nomination-close]').forEach(button => button.addEventListener('click', () => dialog.close()));
    dialog.addEventListener('cancel', event => { if (!results.hidden) { event.preventDefault(); hideResults(); } });
    dialog.addEventListener('close', () => {
        stopSearch();
        form.reset();
        reference.value = search.value = name.value = '';
        external.checked = false;
        form.elements.visa_subclass.value = '';
        syncMode();
        form.querySelector('.nomination-errors')?.setAttribute('hidden', '');
        submit.disabled = false;
        submit.textContent = 'Create';
        trigger.focus();
    });
    form.addEventListener('submit', event => {
        validateSelection();
        if (!form.reportValidity()) { event.preventDefault(); return; }
        submit.disabled = true;
        submit.textContent = 'Creating…';
    });
    if (@js($reopenNomination)) {
        external.checked = @js(old('external_nominee') === '1');
        name.value = @js(is_string(old('nominee_name')) ? old('nominee_name') : '');
        reference.value = @js($selectedNominee?->client_id ?? '');
        search.value = @js($nomineeLabel);
        form.elements.visa_subclass.value = @js(is_string(old('visa_subclass')) ? old('visa_subclass') : '');
    }
    syncMode();
    if (@js($reopenNomination || request()->query('create_nomination') === '1')) {
        dialog.showModal();
        form.querySelector('[role="alert"]')?.focus();
    }
})();
</script>

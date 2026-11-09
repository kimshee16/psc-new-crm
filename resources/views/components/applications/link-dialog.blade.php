@props(['application'])

<dialog id="application-links-dialog" aria-labelledby="application-links-title" class="m-auto max-h-[90vh] w-[min(960px,95vw)] overflow-y-auto rounded-xl border border-slate-200 bg-white p-6 text-slate-800 shadow-xl backdrop:bg-slate-900/50"
    data-url="{{ route('applications.links.edit', $application) }}" data-save-url="{{ route('applications.links.update', $application) }}" data-token="{{ csrf_token() }}">
    <h2 id="application-links-title" class="text-xl font-bold">Link/Unlink Items</h2>
    <p class="mt-1 text-sm text-slate-500">Application #{{ $application->id }}. Changes take effect when you save.</p>
    @if ($application->category === 'Nomination')
        <p class="mt-2 text-sm text-slate-600">Active visa applications under the nominee are suggested first. Only one visa can be linked to this nomination; each visa can link to only one nomination. Linking fills and locks the nominee and subclass. Unlinking clears both fields.</p>
    @endif
    <p data-link-message role="status" aria-live="polite" class="my-3 text-sm text-[#1f5f74]"></p>
    <fieldset data-link-controls disabled class="min-w-0 disabled:opacity-60">
        <section aria-labelledby="link-items-title">
            <h3 id="link-items-title" class="text-lg font-bold">Link items</h3>
            <p class="mt-1 text-sm text-slate-500">Applications under the same client are suggested first. Enter an Application ID to find another item.</p>
            <form data-link-lookup class="my-4 flex flex-wrap items-end gap-3">
                <label class="text-sm font-semibold" for="link-application-id">Application ID
                    <input id="link-application-id" name="application_id" type="text" inputmode="numeric" pattern="[0-9]+" required maxlength="19" class="mt-1 block rounded-md border border-slate-300 px-3 py-2 font-normal" placeholder="Enter Application ID">
                </label>
                <button class="rounded-md border border-slate-300 px-4 py-2 text-sm font-semibold">Find application</button>
                <button type="button" data-link-suggestions class="px-2 py-2 text-sm text-[#1f7890] underline">Same-client suggestions</button>
            </form>
            <div data-link-choices class="max-h-56 space-y-2 overflow-y-auto rounded-md border border-slate-200 p-3"></div>
            <div class="mt-2 flex items-center gap-3 text-sm">
                <button type="button" data-link-prev class="text-[#1f7890] disabled:text-slate-400">Previous</button>
                <span data-link-page></span>
                <button type="button" data-link-next class="text-[#1f7890] disabled:text-slate-400">Next</button>
            </div>
            <div class="my-4 flex flex-wrap gap-3">
                <button type="button" data-link-stage class="rounded-md bg-[#1f7890] px-4 py-2 text-sm font-semibold text-white disabled:opacity-50">Link 0 items</button>
                <button type="button" data-link-another class="px-2 py-2 text-sm font-semibold text-[#1f7890] underline">Link another item</button>
            </div>
        </section>
        <section class="border-t border-slate-200 pt-4" aria-labelledby="linked-application-title">
            <h3 id="linked-application-title" class="mb-3 text-lg font-bold">Items linked to application</h3>
            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <thead class="bg-slate-50"><tr><th scope="col" class="p-3">Application ID #</th><th scope="col" class="p-3">Application description</th><th scope="col" class="p-3">Status</th><th scope="col" class="p-3">Action</th></tr></thead>
                    <tbody data-link-current class="divide-y divide-slate-100"></tbody>
                </table>
            </div>
        </section>
        <footer class="mt-5 flex justify-end gap-3 border-t border-slate-200 pt-4">
            <button type="button" data-link-cancel class="rounded-md border border-slate-300 px-5 py-2 text-sm font-semibold">Cancel</button>
            <button type="button" data-link-save class="rounded-md bg-[#1f7890] px-5 py-2 text-sm font-semibold text-white">Save</button>
        </footer>
    </fieldset>
</dialog>
<script>
(() => {
    const dialog = document.getElementById('application-links-dialog');
    const find = selector => dialog.querySelector(selector);
    const controls = find('[data-link-controls]');
    const message = find('[data-link-message]');
    const input = find('[name="application_id"]');
    const stage = find('[data-link-stage]');
    let original = new Map(), draft = new Map(), selected = new Map(), choices = [];
    let page = 1, lastPage = 1, lookup = '', generation = 0, saving = false, ready = false;
    const key = item => String(item.id);
    const count = () => {
        stage.textContent = `Link ${selected.size} item${selected.size === 1 ? '' : 's'}`;
        stage.disabled = selected.size === 0;
        // Unstaged selections must be linked or deselected before Save.
        find('[data-link-save]').disabled = selected.size > 0;
    };
    const render = () => {
        const list = find('[data-link-choices]');
        list.replaceChildren();
        choices.filter(item => !draft.has(key(item))).forEach(item => {
            const label = document.createElement('label');
            label.className = 'flex items-center gap-3 rounded p-2 text-sm hover:bg-slate-50';
            const checkbox = document.createElement('input');
            checkbox.type = 'checkbox';
            checkbox.checked = selected.has(key(item));
            checkbox.addEventListener('change', () => {
                if (checkbox.checked) selected.set(key(item), item); else selected.delete(key(item));
                count();
            });
            const text = document.createElement('span');
            text.textContent = `#${item.id} · ${item.client_name || ''} · ${item.description || item.category} · ${item.status}`;
            label.append(checkbox, text);
            list.append(label);
        });
        if (!list.childElementCount) list.textContent = lookup
            ? 'No available application found. Check the ID; it may already be linked or unavailable to you.'
            : 'No more same-client applications available. Enter an Application ID to link another item.';
        find('[data-link-page]').textContent = `Page ${page} of ${lastPage}`;
        find('[data-link-prev]').disabled = page <= 1;
        find('[data-link-next]').disabled = page >= lastPage;
        const table = find('[data-link-current]');
        table.replaceChildren();
        draft.forEach(item => {
            const row = document.createElement('tr');
            [ `#${item.id}`, item.description || item.category, item.status ].forEach(value => {
                const cell = document.createElement('td');
                cell.className = 'p-3'; cell.textContent = value; row.append(cell);
            });
            const cell = document.createElement('td'); cell.className = 'p-3';
            const remove = document.createElement('button');
            remove.type = 'button'; remove.textContent = 'Remove';
            remove.className = 'rounded-md border border-red-200 px-3 py-2 text-red-700';
            remove.setAttribute('aria-label', `Remove application ${item.id}`);
            remove.addEventListener('click', () => {
                if (!confirm(`Remove application #${item.id} from linked items? Both applications will be unlinked when you save.`)) return;
                draft.delete(key(item));
                if (!choices.some(choice => key(choice) === key(item))) choices.push(item);
                render();
                message.textContent = 'Item removed from the draft. Save to apply changes.';
            });
            cell.append(remove); row.append(cell); table.append(row);
        });
        if (!draft.size) {
            const row = table.insertRow(); const cell = row.insertCell();
            cell.colSpan = 4; cell.className = 'p-3 text-slate-500'; cell.textContent = 'No linked items available.';
        }
        count();
    };
    const request = async (url, options = {}) => {
        const response = await fetch(url, {credentials: 'same-origin', ...options,
            headers: {'Accept': 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': dialog.dataset.token}});
        if (response.redirected || !response.headers.get('content-type')?.includes('application/json'))
            throw new Error('Your session may have expired. Sign in again before saving.');
        const data = await response.json();
        if (!response.ok) throw new Error(Object.values(data.errors || {}).flat().join(' ') || data.message || 'Unable to update linked items.');
        return data;
    };
    const load = async (initial = false) => {
        const current = ++generation;
        controls.disabled = true; message.textContent = 'Loading applications…';
        try {
            const url = new URL(dialog.dataset.url, location.href);
            url.searchParams.set('page', page);
            if (lookup) url.searchParams.set('application_id', lookup);
            const result = await request(url);
            if (current !== generation || !dialog.open) return;
            if (initial) {
                original = new Map(result.linked.map(item => [key(item), item]));
                draft = new Map(original); ready = true;
            }
            choices = result.candidates.data;
            page = result.candidates.current_page; lastPage = result.candidates.last_page;
            render(); message.textContent = '';
        } catch (error) {
            if (current === generation) message.textContent = error.message || 'Unable to load applications. Please try again.';
        } finally {
            if (current === generation) {
                controls.disabled = false;
                find('[data-link-save]').disabled = !ready || selected.size > 0;
                stage.disabled = !ready || selected.size === 0;
            }
        }
    };
    document.querySelectorAll('[data-open-application-links]').forEach(button => button.addEventListener('click', () => {
        original.clear(); draft.clear(); selected.clear(); choices = []; ready = false;
        lookup = ''; input.value = ''; page = 1; lastPage = 1;
        render(); dialog.showModal(); load(true);
    }));
    find('[data-link-lookup]').addEventListener('submit', event => {
        event.preventDefault(); lookup = input.value.trim(); page = 1; load(!ready);
    });
    find('[data-link-suggestions]').addEventListener('click', () => { lookup = ''; input.value = ''; page = 1; load(!ready); });
    find('[data-link-prev]').addEventListener('click', () => { page--; load(); });
    find('[data-link-next]').addEventListener('click', () => { page++; load(); });
    stage.addEventListener('click', () => {
        selected.forEach((item, id) => draft.set(id, item)); selected.clear(); render();
        message.textContent = 'Items added to the draft. Save to apply changes.';
    });
    find('[data-link-another]').addEventListener('click', () => { input.value = ''; input.focus(); });
    find('[data-link-cancel]').addEventListener('click', () => dialog.close());
    dialog.addEventListener('cancel', event => { if (saving) event.preventDefault(); });
    dialog.addEventListener('close', () => { generation++; });
    find('[data-link-save]').addEventListener('click', async () => {
        if (saving || !ready || selected.size) return;
        saving = true; controls.disabled = true; message.textContent = 'Saving linked items…';
        try {
            await request(dialog.dataset.saveUrl, {method: 'POST', body: JSON.stringify({
                action: 'save',
                add: [...draft.keys()].filter(id => !original.has(id)),
                remove: [...original.keys()].filter(id => !draft.has(id)),
            })});
            location.reload();
        } catch (error) {
            message.textContent = error.message || 'Unable to save. Your draft has been kept. Try again.';
            saving = false; controls.disabled = false;
        }
    });
})();
</script>

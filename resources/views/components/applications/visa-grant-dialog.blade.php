@props(['application'])
@php
    $effectiveFrom = old('grant_effective_from', '');
    $expiresOn = old('grant_expires_on', '');
@endphp
<dialog id="visa-grant-dialog" aria-labelledby="visa-grant-title" class="m-auto w-[min(480px,95vw)] rounded-xl border border-slate-200 bg-white p-6 text-slate-800 shadow-xl backdrop:bg-slate-900/50"
    data-saved-status="{{ $application->status }}" data-has-errors="{{ $errors->any() ? 'true' : 'false' }}">
    <h2 id="visa-grant-title" class="text-xl font-bold">Visa granted</h2>
    <p class="mt-2 text-sm text-slate-600">Save the grant dates to update the Client's Current Visa and Visa Expiry from the Effective From date.</p>
    <form id="visa-grant-form" class="mt-5 space-y-4">
        <label class="block text-sm font-semibold" for="grant-visa-subclass">Visa subclass
            <input id="grant-visa-subclass" type="text" readonly class="mt-1 block w-full rounded-md border border-slate-200 bg-slate-100 px-3 py-2 font-normal" value="{{ $application->visa_subclass }}">
        </label>
        <label class="block text-sm font-semibold" for="grant-effective-from">Effective from <span class="text-red-600">*</span>
            <input id="grant-effective-from" type="date" required class="mt-1 block w-full rounded-md border border-slate-300 px-3 py-2 font-normal" value="{{ is_string($effectiveFrom) ? $effectiveFrom : '' }}">
        </label>
        <label class="block text-sm font-semibold" for="grant-expires-on">Expires on <span class="text-red-600">*</span>
            <input id="grant-expires-on" type="date" required class="mt-1 block w-full rounded-md border border-slate-300 px-3 py-2 font-normal" value="{{ is_string($expiresOn) ? $expiresOn : '' }}">
        </label>
        @if ($errors->any())
            <div role="alert" class="rounded-md bg-red-50 p-3 text-sm text-red-800">
                @foreach ($errors->all() as $error)<p>{{ $error }}</p>@endforeach
            </div>
        @endif
        <p data-grant-message role="status" class="text-sm text-red-700"></p>
        <footer class="flex justify-end gap-3 border-t border-slate-200 pt-4">
            <button type="button" data-grant-cancel class="rounded-md border border-slate-300 px-4 py-2 text-sm font-semibold">Cancel</button>
            <button type="submit" class="rounded-md bg-[#1f7890] px-4 py-2 text-sm font-semibold text-white">Save</button>
        </footer>
    </form>
</dialog>
<script>
(() => {
    const summary = document.getElementById('application-summary-form');
    const status = summary.querySelector('[name="status"]');
    const subclass = summary.querySelector('[name="visa_subclass"]');
    const dialog = document.getElementById('visa-grant-dialog');
    const grantForm = document.getElementById('visa-grant-form');
    const effective = document.getElementById('grant-effective-from');
    const expiry = document.getElementById('grant-expires-on');
    const message = dialog.querySelector('[data-grant-message]');
    let previousStatus = status.value === 'Granted' ? dialog.dataset.savedStatus : status.value;
    let confirmed = false, submitting = false;
    const isTransition = () => status.value === 'Granted' && dialog.dataset.savedStatus !== 'Granted';
    const clearDates = () => summary.querySelectorAll('[data-grant-value]').forEach(input => input.remove());
    const open = () => {
        document.getElementById('grant-visa-subclass').value = subclass.value;
        expiry.min = effective.value;
        message.textContent = '';
        if (!dialog.open) dialog.showModal();
    };
    const cancel = () => {
        if (submitting) return;
        confirmed = false; clearDates(); status.value = previousStatus;
        dialog.close(); status.focus();
    };
    status.addEventListener('change', () => {
        confirmed = false; clearDates();
        if (isTransition()) open(); else previousStatus = status.value;
    });
    effective.addEventListener('input', () => { expiry.min = effective.value; });
    dialog.querySelector('[data-grant-cancel]').addEventListener('click', cancel);
    dialog.addEventListener('cancel', event => { event.preventDefault(); cancel(); });
    summary.addEventListener('submit', event => {
        if (isTransition() && !confirmed) { event.preventDefault(); open(); }
    });
    grantForm.addEventListener('submit', event => {
        event.preventDefault();
        if (submitting) return;
        expiry.min = effective.value;
        if (!grantForm.reportValidity()) return;
        if (!summary.checkValidity()) {
            message.textContent = 'Complete the required summary fields before saving the grant. Cancel to return to the summary.';
            return;
        }
        clearDates();
        [['grant_effective_from', effective.value], ['grant_expires_on', expiry.value]].forEach(([name, value]) => {
            const input = document.createElement('input');
            input.type = 'hidden'; input.name = name; input.value = value; input.dataset.grantValue = 'true';
            summary.append(input);
        });
        confirmed = true; submitting = true;
        grantForm.querySelectorAll('button').forEach(button => { button.disabled = true; });
        summary.requestSubmit();
    });
    // Preserve the popup and dates after server-side validation errors.
    if (isTransition() && dialog.dataset.hasErrors === 'true') open();
})();
</script>

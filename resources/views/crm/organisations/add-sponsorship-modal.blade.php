@php($reopenSponsorship = old('_add_sponsorship') === '1' && $errors->any())
<style>
    .sponsorship-dialog { width: min(600px, calc(100vw - 32px)); max-height: calc(100dvh - 32px); margin: auto; padding: 0; overflow-y: auto; border: 0; border-radius: 12px; background: white; color: #1b2027; box-shadow: 0 24px 50px #0004; }
    .sponsorship-dialog::backdrop { background: #20282e99; }
    .sponsorship-dialog header { display: flex; align-items: center; justify-content: space-between; gap: 16px; padding: 22px 24px; border-bottom: 1px solid #e1e4e8; }
    .sponsorship-dialog h2 { font-size: 20px; font-weight: 750; }
    .sponsorship-close { padding: 0 2px; background: transparent; font-size: 28px; line-height: 28px; }
    .sponsorship-body { padding: 24px; font-size: 14px; }
    .sponsorship-required { margin-bottom: 18px; text-align: right; color: #526071; font-size: 12px; }
    .sponsorship-organisation { display: grid; grid-template-columns: 160px minmax(0, 1fr); gap: 16px; margin-bottom: 20px; line-height: 24px; }
    .sponsorship-organisation dd { overflow-wrap: anywhere; }
    .sponsorship-types { display: grid; grid-template-columns: 160px minmax(0, 1fr); gap: 16px; }
    .sponsorship-types legend { float: left; width: 160px; padding-top: 12px; font-weight: 700; }
    .sponsorship-choices { grid-column: 2; display: grid; gap: 10px; }
    .sponsorship-choice { display: flex; align-items: center; gap: 12px; min-height: 46px; padding: 12px 14px; border: 1px solid #bfc6ce; border-radius: 8px; cursor: pointer; }
    .sponsorship-choice:has(input:checked) { border-color: #15638e; background: #f0f7fb; }
    .sponsorship-choice input { width: 18px; height: 18px; accent-color: #15638e; }
    .sponsorship-dialog footer { display: flex; justify-content: flex-end; gap: 12px; padding: 16px 24px; border-top: 1px solid #dfe3e9; background: #f7f8fb; }
    .sponsorship-dialog footer button { min-height: 42px; padding: 10px 20px; border: 1px solid #bfc6ce; border-radius: 8px; background: white; font-size: 14px; font-weight: 700; }
    .sponsorship-dialog footer [type="submit"] { border-color: #15638e; background: #15638e; color: white; }
    .sponsorship-dialog button { cursor: pointer; }
    .sponsorship-dialog button:disabled { cursor: wait; opacity: .65; }
    .sponsorship-dialog :focus-visible { outline: 2px solid #15638e; outline-offset: 3px; }
    @media (max-width: 520px) { .sponsorship-organisation, .sponsorship-types { display: block; } .sponsorship-organisation dt { margin-bottom: 6px; } .sponsorship-types legend { float: none; width: auto; margin-bottom: 10px; padding-top: 0; } }
</style>
<dialog id="sponsorship-create-dialog" class="sponsorship-dialog" aria-labelledby="sponsorship-create-title">
    <form method="POST" action="{{ route('organisations.applications.store', $organisation) }}">
        @csrf
        <input type="hidden" name="category" value="SBS">
        <input type="hidden" name="_add_sponsorship" value="1">
        <header>
            <h2 id="sponsorship-create-title">Add sponsorship</h2>
            <button type="button" class="sponsorship-close" data-sponsorship-close aria-label="Close Add sponsorship">&times;</button>
        </header>
        <div class="sponsorship-body">
            @if($reopenSponsorship)
                <div role="alert" tabindex="-1" class="mb-5 rounded-md border border-red-200 bg-red-50 p-3 text-sm text-red-800">
                    <p class="font-semibold">The sponsorship could not be created.</p>
                    <ul class="mt-1 list-disc pl-4">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
                </div>
            @endif
            <p class="sponsorship-required"><span class="text-red-700">*</span> Required</p>
            <dl class="sponsorship-organisation">
                <dt class="font-bold">Organisation name</dt>
                <dd><div>{{ $organisation->name }}</div>@if(filled($organisation->trading_name))<div class="mt-1 text-slate-500">{{ $organisation->trading_name }}</div>@endif</dd>
            </dl>
            <fieldset class="sponsorship-types">
                <legend>Sponsorship type <span class="text-red-700" aria-hidden="true">*</span><span class="sr-only"> (required)</span></legend>
                <div class="sponsorship-choices">
                    @foreach(['Standard Business Sponsor' => 'SBS', 'Labour Agreement' => 'Labour Agreement'] as $value => $label)
                        <label class="sponsorship-choice"><input type="radio" name="sponsor_type" value="{{ $value }}" required @checked($reopenSponsorship && old('sponsor_type') === $value)><span>{{ $label }}</span></label>
                    @endforeach
                </div>
            </fieldset>
        </div>
        <footer><button type="button" data-sponsorship-close>Cancel</button><button type="submit">Create</button></footer>
    </form>
</dialog>
<script>
(() => {
    const dialog = document.getElementById('sponsorship-create-dialog');
    const trigger = document.getElementById('add-sponsorship');
    const form = dialog.querySelector('form');
    const submit = form.querySelector('[type="submit"]');
    let submitting = false;
    const resetSubmission = () => { submitting = false; submit.disabled = false; submit.textContent = 'Create'; };
    const open = () => { resetSubmission(); dialog.showModal(); dialog.querySelector('[role="alert"]')?.focus(); };
    trigger.addEventListener('click', () => { form.reset(); form.querySelectorAll('[type="radio"]').forEach(radio => radio.checked = false); open(); });
    dialog.querySelectorAll('[data-sponsorship-close]').forEach(button => button.addEventListener('click', () => dialog.close()));
    dialog.addEventListener('close', () => trigger.focus());
    form.addEventListener('submit', event => {
        if (submitting) { event.preventDefault(); return; }
        submitting = true;
        submit.disabled = true;
        submit.textContent = 'Creating…';
    });
    window.addEventListener('pageshow', resetSubmission);
    if (@json($reopenSponsorship || request()->query('add_sponsorship') === '1')) open();
})();
</script>

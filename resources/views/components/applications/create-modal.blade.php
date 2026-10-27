@props(['action', 'trigger', 'configuration', 'fromLead' => false])
@php
    $reopen = old('_create_application') === '1' && $errors->any();
    $values = collect(['category', 'application_workflow_id', 'visa_workflow_id', 'visa_subclass', 'assessment_body', 'psc_client', 'migration_agent_id', 'create_lead'])
        ->mapWithKeys(fn ($key) => [$key => is_scalar(old($key)) ? (string) old($key) : ''])->all();
@endphp
<style>
    .application-create-dialog { width: min(560px, calc(100vw - 32px)); max-height: calc(100dvh - 32px); margin: auto; padding: 0; border: 1px solid #d8dee8; border-radius: 12px; color: #101820; background: white; box-shadow: 0 24px 64px #0004; }
    .application-create-dialog::backdrop { background: #0f172a80; }
    .application-create-dialog header, .application-create-dialog footer { display: flex; align-items: center; justify-content: space-between; gap: 12px; padding: 20px 24px; }
    .application-create-dialog header { border-bottom: 1px solid #e2e8f0; }
    .application-create-dialog footer { justify-content: flex-end; border-top: 1px solid #e2e8f0; }
    .application-create-fields { display: grid; gap: 18px; padding: 24px; }
    .application-create-dialog label { display: block; font-size: 13px; font-weight: 700; }
    .application-create-dialog select, .application-create-dialog input[type="search"] { width: 100%; min-height: 40px; margin-top: 7px; padding: 8px 10px; border: 1px solid #b9c1cc; border-radius: 5px; background: white; font: inherit; font-size: 13px; }
    .application-create-dialog input[type="search"] { font-size: 12px; }
    .application-create-dialog button { border: 1px solid #b9c1cc; border-radius: 6px; padding: 9px 18px; font-size: 13px; font-weight: 700; cursor: pointer; }
    .application-create-dialog button[type="submit"] { background: #1f7890; border-color: #1f7890; color: white; }
    .application-create-dialog button:disabled { opacity: .6; cursor: wait; }
    .application-create-dialog :focus-visible { outline: 2px solid #1f7890; outline-offset: 2px; }
    .application-create-dialog [hidden] { display: none; }
    .application-create-help { color: #64748b; font-size: 13px; line-height: 1.5; }
</style>
<dialog id="application-create-dialog" class="application-create-dialog" aria-labelledby="application-create-heading">
    <form method="POST" action="{{ $action }}">
        @csrf
        <input type="hidden" name="_create_application" value="1">
        <header>
            <h2 id="application-create-heading" class="text-lg font-extrabold">Create Application</h2>
            <button type="button" data-close aria-label="Close Create Application">&times;</button>
        </header>
        <div class="application-create-fields">
            @if ($reopen)
                <div role="alert" class="rounded-md border border-red-200 bg-red-50 p-3 text-sm text-red-800" tabindex="-1">
                    <p class="font-semibold">The application could not be created.</p>
                    <ul class="mt-1 list-disc pl-5">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
                </div>
            @endif
            <div>
                <label for="create-category">Application Type <span aria-hidden="true">*</span></label>
                <select id="create-category" name="category" required autofocus>
                    <option value="">Select application type</option>
                    @foreach ($configuration['types'] as $value => $label)<option value="{{ $value }}">{{ $label }}</option>@endforeach
                </select>
            </div>
            <p data-combined hidden class="application-create-help">Creates a Study Application and a linked 500 - Student Visa application. The Study Application opens first.</p>
            @foreach ([
                'application_workflow_id' => 'Workflow template',
                'visa_workflow_id' => '500 Visa workflow template',
                'visa_subclass' => 'Subclass',
                'assessment_body' => 'Assessment body',
                'psc_client' => 'PSC Client',
                'migration_agent_id' => 'Migration Agent',
            ] as $name => $label)
                <div data-field="{{ $name }}" hidden>
                    <label for="create-{{ $name }}">{{ $label }} <span data-required aria-hidden="true">*</span></label>
                    <input type="search" data-search="{{ $name }}" aria-label="Search {{ $label }}" aria-controls="create-{{ $name }}" placeholder="Search {{ strtolower($label) }}" autocomplete="off" disabled>
                    <select id="create-{{ $name }}" name="{{ $name }}" disabled><option value="">Select {{ strtolower($label) }}</option></select>
                    <p data-empty hidden class="application-create-help">No matching choices.</p>
                </div>
            @endforeach
            <p data-agent-default hidden class="application-create-help"></p>
            <label style="display: flex; align-items: center; gap: 10px">
                <input type="checkbox" name="create_lead" value="1"> Lead only
            </label>
            <p data-lead-help hidden class="application-create-help">{{ $fromLead ? 'Keeps this Lead without creating an application or converting it.' : 'Creates a Lead for this client without creating an application.' }}</p>
        </div>
        <footer>
            <button type="button" data-close>Cancel</button>
            <button type="submit">Create</button>
        </footer>
    </form>
</dialog>
<script>
(() => {
    const dialog = document.getElementById('application-create-dialog');
    const trigger = document.getElementById(@js($trigger));
    const config = @js($configuration);
    const initial = @js($values);
    const form = dialog.querySelector('form');
    const type = form.elements.category;
    const leadOnly = form.elements.create_lead;
    const fields = {};
    let restoring = true;

    dialog.querySelectorAll('[data-field]').forEach(wrapper => {
        const name = wrapper.dataset.field;
        fields[name] = { wrapper, select: form.elements[name], search: wrapper.querySelector('input'), choices: [], enabled: false };
    });
    const paint = (field, selected) => {
        const query = field.search.value.trim().toLocaleLowerCase();
        const choices = field.choices.filter(choice => String(choice.id) === selected || choice.name.toLocaleLowerCase().includes(query));
        field.select.replaceChildren(new Option('Select an option', ''));
        choices.forEach(choice => field.select.add(new Option(choice.name, String(choice.id))));
        field.select.value = selected;
        field.wrapper.querySelector('[data-empty]').hidden = choices.length > 0;
    };
    const setField = (name, enabled, choices = [], required = true) => {
        const field = fields[name];
        const selected = restoring ? initial[name] : field.select.value;
        field.choices = choices;
        field.enabled = enabled;
        field.wrapper.hidden = !enabled;
        field.select.disabled = field.search.disabled = !enabled;
        field.select.required = enabled && required;
        field.wrapper.querySelector('[data-required]').hidden = !required;
        paint(field, enabled ? selected : '');
    };
    const workflow = name => config.workflows.find(item => String(item.id) === fields[name].select.value);
    const choices = (template, name) => (template?.options[name] ?? config.options[name] ?? []).map(value => ({ id: value, name: value }));
    const sync = () => {
        const combined = type.value === 'Study and 500 Visa';
        const enabled = !!type.value && !leadOnly.checked;
        const category = combined ? 'Study' : type.value;
        const templates = config.workflows.filter(item => item.category === category);
        const visaTemplates = config.workflows.filter(item => item.category === 'Visa' && (item.options.visa_subclass ?? config.options.visa_subclass).includes('500 - Student Visa'));
        setField('application_workflow_id', enabled && templates.length > 0, templates);
        setField('visa_workflow_id', enabled && combined && config.workflows.some(item => item.category === 'Visa'), visaTemplates);
        const selected = workflow('application_workflow_id');
        setField('visa_subclass', enabled && ['Visa', 'ART Appeal'].includes(category), choices(selected, 'visa_subclass'));
        setField('assessment_body', enabled && category === 'Assessment', choices(selected, 'assessment_body'), false);
        setField('psc_client', enabled && category === 'ART Appeal', [{ id: '1', name: 'Yes' }, { id: '0', name: 'No' }]);
        const visa = combined ? workflow('visa_workflow_id') : selected;
        const needsAgent = enabled && (combined || category === 'Visa');
        setField('migration_agent_id', needsAgent && !visa?.migration_agent_id, visa?.staff ?? config.staff);
        const agentHelp = dialog.querySelector('[data-agent-default]');
        agentHelp.hidden = !(needsAgent && visa?.migration_agent_id);
        agentHelp.textContent = visa?.migration_agent_id ? 'Migration Agent: ' + (visa.staff.find(person => person.id === visa.migration_agent_id)?.name ?? 'Template default') : '';
        dialog.querySelector('[data-combined]').hidden = !(enabled && combined);
        dialog.querySelector('[data-lead-help]').hidden = !leadOnly.checked;
    };
    Object.values(fields).forEach(field => {
        field.search.addEventListener('input', () => paint(field, field.select.value));
        field.search.addEventListener('keydown', event => {
            if (event.key === 'Enter') event.preventDefault();
        });
    });
    ['application_workflow_id', 'visa_workflow_id'].forEach(name => fields[name].select.addEventListener('change', sync));
    type.addEventListener('change', () => {
        Object.values(fields).forEach(field => { field.select.value = ''; field.search.value = ''; });
        sync();
    });
    leadOnly.addEventListener('change', sync);
    type.value = initial.category;
    leadOnly.checked = initial.create_lead === '1';
    sync();
    restoring = false;
    trigger?.setAttribute('aria-haspopup', 'dialog');
    trigger?.setAttribute('aria-controls', dialog.id);
    trigger?.addEventListener('click', () => dialog.showModal());
    dialog.querySelectorAll('[data-close]').forEach(button => button.addEventListener('click', () => dialog.close()));
    dialog.addEventListener('close', () => trigger?.focus());
    form.addEventListener('submit', () => {
        form.querySelector('[type="submit"]').disabled = true;
        form.querySelector('[type="submit"]').textContent = 'Creating…';
    });
    if (@js($reopen)) { dialog.showModal(); dialog.querySelector('[role="alert"]')?.focus(); }
})();
</script>

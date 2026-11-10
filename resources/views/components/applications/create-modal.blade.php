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
    .application-create-picker { position: relative; margin-top: 7px; }
    .application-create-picker > button { display: flex; align-items: center; justify-content: space-between; gap: 12px; width: 100%; min-height: 40px; padding: 8px 10px; background: white; text-align: left; font-weight: 400; }
    .application-create-picker > button::after { content: ''; width: 7px; height: 7px; border-right: 2px solid #475569; border-bottom: 2px solid #475569; transform: rotate(45deg); flex-shrink: 0; margin: 0 3px 4px; }
    .application-create-picker-menu { position: absolute; z-index: 20; top: calc(100% + 4px); left: 0; right: 0; padding: 8px; border: 1px solid #b9c1cc; border-radius: 6px; background: white; box-shadow: 0 8px 24px #0f172a26; }
    .application-create-picker-menu input[type="search"] { margin-top: 0; }
    .application-create-options { max-height: 180px; overflow-y: auto; margin-top: 6px; }
    .application-create-options button { display: block; width: 100%; padding: 9px 10px; border: 0; border-radius: 4px; background: white; text-align: left; font-weight: 400; }
    .application-create-options button:hover, .application-create-options button[data-active], .application-create-options button[aria-selected="true"] { background: #eaf7fc; color: #123348; }
    .application-create-picker-error { margin-top: 6px; color: #b91c1c; font-size: 12px; }
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
                    <label id="create-{{ $name }}-label" for="create-{{ $name }}">{{ $label }} <span data-required aria-hidden="true">*</span></label>
                    <div class="application-create-picker">
                        <button id="create-{{ $name }}" type="button" data-picker-trigger aria-haspopup="listbox" aria-expanded="false" aria-controls="create-{{ $name }}-options" aria-labelledby="create-{{ $name }}-label create-{{ $name }}-selected" aria-describedby="create-{{ $name }}-error" disabled>
                            <span id="create-{{ $name }}-selected" data-selected>Select an option</span>
                        </button>
                        <div class="application-create-picker-menu" data-picker-menu hidden>
                            <input type="search" data-search="{{ $name }}" role="combobox" aria-autocomplete="list" aria-expanded="false" aria-label="Search {{ $label }}" aria-controls="create-{{ $name }}-options" placeholder="Search {{ strtolower($label) }}" autocomplete="off" disabled>
                            <div id="create-{{ $name }}-options" class="application-create-options" data-options role="listbox" aria-labelledby="create-{{ $name }}-label"></div>
                            <p data-empty hidden class="application-create-help">No matching choices.</p>
                        </div>
                    </div>
                    <select name="{{ $name }}" hidden aria-hidden="true" tabindex="-1" disabled><option value="">Select {{ strtolower($label) }}</option></select>
                    <p id="create-{{ $name }}-error" data-picker-error hidden class="application-create-picker-error">Select {{ strtolower($label) }}.</p>
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
    let invalidPicker = null;

    dialog.querySelectorAll('[data-field]').forEach(wrapper => {
        const name = wrapper.dataset.field;
        fields[name] = {
            wrapper, select: form.elements[name], search: wrapper.querySelector('input'),
            button: wrapper.querySelector('[data-picker-trigger]'), menu: wrapper.querySelector('[data-picker-menu]'),
            options: wrapper.querySelector('[data-options]'), choices: [], enabled: false, active: -1,
        };
    });
    const filter = field => {
        const query = field.search.value.trim().toLocaleLowerCase();
        const choices = field.choices.filter(choice => choice.name.toLocaleLowerCase().includes(query));
        field.options.replaceChildren();
        const addOption = (value, label) => {
            const option = document.createElement('button');
            option.type = 'button';
            option.tabIndex = -1;
            option.id = field.options.id + '-' + field.options.children.length;
            option.setAttribute('role', 'option');
            option.setAttribute('aria-selected', String(value === field.select.value));
            option.dataset.value = value;
            option.textContent = label;
            field.options.append(option);
        };
        if (!field.select.required) addOption('', 'None');
        choices.forEach(choice => addOption(String(choice.id), choice.name));
        field.wrapper.querySelector('[data-empty]').hidden = choices.length > 0;
        field.active = -1;
        field.search.removeAttribute('aria-activedescendant');
    };
    const closePicker = field => {
        field.menu.hidden = true;
        field.button.setAttribute('aria-expanded', 'false');
        field.search.setAttribute('aria-expanded', 'false');
        field.search.removeAttribute('aria-activedescendant');
    };
    const activate = (field, index) => {
        const options = [...field.options.children];
        if (!options.length) return;
        field.active = (index + options.length) % options.length;
        options.forEach((option, i) => option.toggleAttribute('data-active', i === field.active));
        field.search.setAttribute('aria-activedescendant', options[field.active].id);
        options[field.active].scrollIntoView({block: 'nearest'});
    };
    const openPicker = field => {
        if (!field.enabled) return;
        Object.values(fields).forEach(closePicker);
        field.search.value = '';
        filter(field);
        field.menu.hidden = false;
        field.button.setAttribute('aria-expanded', 'true');
        field.search.setAttribute('aria-expanded', 'true');
        field.search.focus();
    };
    const choose = (field, value) => {
        field.select.value = value;
        field.wrapper.querySelector('[data-selected]').textContent = field.select.selectedOptions[0]?.textContent || 'Select an option';
        field.button.removeAttribute('aria-invalid');
        field.wrapper.querySelector('[data-picker-error]').hidden = true;
        closePicker(field);
        field.button.focus();
        field.select.dispatchEvent(new Event('change', {bubbles: true}));
    };
    const paint = (field, selected) => {
        field.select.replaceChildren(new Option('Select an option', ''));
        field.choices.forEach(choice => field.select.add(new Option(choice.name, String(choice.id))));
        field.select.value = selected;
        field.wrapper.querySelector('[data-selected]').textContent = field.select.selectedOptions[0]?.textContent || 'Select an option';
        if (!field.enabled || field.select.value) {
            field.button.removeAttribute('aria-invalid');
            field.wrapper.querySelector('[data-picker-error]').hidden = true;
        }
        filter(field);
    };
    const setField = (name, enabled, choices = [], required = true) => {
        const field = fields[name];
        const selected = restoring ? initial[name] : field.select.value;
        field.choices = choices;
        field.enabled = enabled;
        field.wrapper.hidden = !enabled;
        field.select.disabled = field.search.disabled = field.button.disabled = !enabled;
        if (!enabled) closePicker(field);
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
        field.button.addEventListener('click', () => field.menu.hidden ? openPicker(field) : closePicker(field));
        field.button.addEventListener('keydown', event => {
            if (['ArrowDown', 'ArrowUp'].includes(event.key)) {
                event.preventDefault();
                openPicker(field);
                activate(field, event.key === 'ArrowDown' ? 0 : field.options.children.length - 1);
            }
        });
        field.search.addEventListener('input', () => filter(field));
        field.search.addEventListener('keydown', event => {
            if (['ArrowDown', 'ArrowUp'].includes(event.key)) {
                event.preventDefault();
                activate(field, field.active < 0 ? (event.key === 'ArrowDown' ? 0 : field.options.children.length - 1) : field.active + (event.key === 'ArrowDown' ? 1 : -1));
            } else if (event.key === 'Enter') {
                event.preventDefault();
                const option = field.options.children[Math.max(field.active, 0)];
                if (option) choose(field, option.dataset.value);
            } else if (event.key === 'Escape') {
                event.preventDefault();
                event.stopPropagation();
                closePicker(field);
                field.button.focus();
            }
        });
        field.options.addEventListener('click', event => {
            const option = event.target.closest('[role="option"]');
            if (option) choose(field, option.dataset.value);
        });
        field.wrapper.addEventListener('focusout', event => {
            if (!field.wrapper.contains(event.relatedTarget)) closePicker(field);
        });
        field.select.addEventListener('invalid', event => {
            event.preventDefault();
            field.button.setAttribute('aria-invalid', 'true');
            field.wrapper.querySelector('[data-picker-error]').hidden = false;
            if (!invalidPicker) {
                invalidPicker = field;
                openPicker(field);
                queueMicrotask(() => { invalidPicker = null; });
            }
        });
    });
    dialog.addEventListener('click', event => {
        Object.values(fields).forEach(field => {
            if (!field.wrapper.contains(event.target)) closePicker(field);
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
    dialog.addEventListener('close', () => { Object.values(fields).forEach(closePicker); trigger?.focus(); });
    form.addEventListener('submit', () => {
        form.querySelector('[type="submit"]').disabled = true;
        form.querySelector('[type="submit"]').textContent = 'Creating…';
    });
    if (@js($reopen)) { dialog.showModal(); dialog.querySelector('[role="alert"]')?.focus(); }
})();
</script>

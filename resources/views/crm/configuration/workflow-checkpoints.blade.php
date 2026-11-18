<section aria-labelledby="workflow-checkpoints-heading">
    <h2 id="workflow-checkpoints-heading" class="text-lg font-bold">Workflow Checkpoints</h2>
    <p class="mb-5 mt-2 text-sm text-slate-600">Choose the order of custom checkpoints between the fixed checkpoints. Named workflows inherit their application type's checkpoints unless overridden.</p>
    @if($checkpoints->isEmpty())
        <a class="config-action config-primary" href="{{ route('configuration', ['tab' => 'checkpoints']) }}">Add a checkpoint</a>
    @endif
    <div class="grid gap-3">
        @foreach($applicationTypes as $category => $label)
            @include('crm.configuration.workflow-checkpoint-form', [
                'label' => $label,
                'workflow' => null,
                'ids' => $applicationTypeSettings->get($category)?->checkpoint_ids ?? [],
            ])
        @endforeach
        @foreach($applicationWorkflows as $workflow)
            @include('crm.configuration.workflow-checkpoint-form', [
                'label' => $workflow->name.' ('.$workflow->category.')',
                'category' => $workflow->category,
                'ids' => $workflow->checkpoint_ids ?? $applicationTypeSettings->get($workflow->category)?->checkpoint_ids ?? [],
            ])
        @endforeach
    </div>
</section>
<script>
    document.querySelectorAll('[data-checkpoint-order]').forEach(form => {
        const list = form.querySelector('ol');
        const picker = form.querySelector('[data-checkpoint-picker]');
        const add = form.querySelector('[data-add-checkpoint]');
        const inherit = form.querySelector('[name="inherit"]');
        const refresh = () => {
            const items = [...list.children];
            const used = items.map(item => item.querySelector('input').value);
            [...picker.options].slice(1).forEach(option => { option.disabled = used.includes(option.value); });
            if (picker.selectedOptions[0]?.disabled) picker.value = '';
            add.disabled = !picker.value || items.length >= 50;
            items.forEach((item, index) => {
                item.querySelector('[data-move="up"]').disabled = index === 0;
                item.querySelector('[data-move="down"]').disabled = index === items.length - 1;
            });
            form.querySelector('fieldset').disabled = inherit?.checked ?? false;
        };
        picker.addEventListener('change', refresh);
        inherit?.addEventListener('change', refresh);
        add.addEventListener('click', () => {
            const item = form.querySelector('template').content.firstElementChild.cloneNode(true);
            item.querySelector('input').value = picker.value;
            item.querySelector('[data-checkpoint-name]').textContent = picker.selectedOptions[0].textContent;
            list.append(item);
            refresh();
            picker.focus();
        });
        list.addEventListener('click', event => {
            const button = event.target.closest('button');
            if (!button) return;
            const item = button.closest('li');
            if (button.dataset.move === 'up') list.insertBefore(item, item.previousElementSibling);
            if (button.dataset.move === 'down') list.insertBefore(item.nextElementSibling, item);
            if (button.hasAttribute('data-remove')) item.remove();
            refresh();
            if (item.isConnected) (button.disabled ? item.querySelector('[data-remove]') : button).focus();
            else picker.focus();
        });
        refresh();
    });
</script>

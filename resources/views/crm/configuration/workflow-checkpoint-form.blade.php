@php
    $submitted = $workflow ? (int) old('workflow_id') === $workflow->id : old('category') === $category;
    $selectedIds = $submitted && is_array(old('checkpoint_ids')) ? old('checkpoint_ids') : $ids;
    $lookup = $checkpoints->keyBy('id');
@endphp
<details class="rounded-lg border border-slate-200 bg-white p-4" @if($submitted) open @endif>
    <summary class="cursor-pointer font-semibold">{{ $label }}</summary>
    <form method="POST" data-checkpoint-order action="{{ route('configuration.workflow-checkpoints.update') }}" class="mt-4">
        @csrf
        @method('PATCH')
        @if($workflow)
            <input type="hidden" name="workflow_id" value="{{ $workflow->id }}">
            <label class="mb-3 flex items-center gap-2 text-sm">
                <input type="checkbox" name="inherit" value="1" @checked($submitted ? old('inherit') : $workflow->checkpoint_ids === null)>
                Use application type checkpoints
            </label>
        @else
            <input type="hidden" name="category" value="{{ $category }}">
        @endif
        <fieldset class="disabled:opacity-60">
            <ol class="mb-3 space-y-2" aria-label="Checkpoint order">
                @foreach($selectedIds as $id)
                    @if(is_scalar($id) && $lookup->has($id))
                        @include('crm.configuration.workflow-checkpoint-item', ['checkpoint' => $lookup->get($id)])
                    @endif
                @endforeach
            </ol>
            <label class="block text-sm font-semibold">Checkpoint to add
                <select data-checkpoint-picker class="config-control mt-1">
                    <option value="">Choose a checkpoint</option>
                    @foreach($checkpoints as $checkpoint)<option value="{{ $checkpoint->id }}">{{ $checkpoint->name }}</option>@endforeach
                </select>
            </label>
            <button type="button" data-add-checkpoint class="config-action config-primary mt-2 disabled:opacity-50" disabled>Add checkpoint</button>
        </fieldset>
        <template>@include('crm.configuration.workflow-checkpoint-item', ['checkpoint' => null])</template>
        <button class="config-action config-primary mt-4">Save checkpoints</button>
    </form>
</details>

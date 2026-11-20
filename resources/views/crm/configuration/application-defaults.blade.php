<div class="grid gap-4 sm:grid-cols-2">
    <label class="grid gap-1 text-sm font-semibold">
        Service Agreement ID
        <input type="number" name="service_agreement_id" min="1" class="rounded-md border border-slate-300 px-3 py-2"
            value="{{ $useOld ? old('service_agreement_id') : ($defaults['service_agreement_id'] ?? '') }}">
    </label>
    <label class="grid gap-1 text-sm font-semibold">
        Migration agent
        <select name="migration_agent_id" class="rounded-md border border-slate-300 px-3 py-2">
            <option value="">No default agent</option>
            @foreach ($migrationAgents->whereNotNull('user_id') as $agent)
                <option value="{{ $agent->user_id }}" @selected((string) $agent->user_id === (string) ($useOld ? old('migration_agent_id') : ($defaults['migration_agent_id'] ?? '')))>{{ $agent->name }}</option>
            @endforeach
        </select>
    </label>
    @foreach (\App\Models\ApplicationTypeSetting::LIST_FIELDS as $key => $fieldLabel)
        <label class="grid gap-1 text-sm font-semibold">
            {{ $fieldLabel }}
            <textarea name="{{ $key }}" rows="3" maxlength="10000" placeholder="One entry per line"
                class="rounded-md border border-slate-300 px-3 py-2">{{ $useOld ? old($key) : implode("\n", $defaults[$key] ?? []) }}</textarea>
        </label>
    @endforeach
</div>
<fieldset>
    <legend class="text-sm font-semibold">Checkpoints</legend>
    @php
        $selectedCheckpoints = array_map('strval', array_filter((array) ($useOld ? old('checkpoint_ids', []) : $checkpointIds), 'is_scalar'));
        $orderedCheckpoints = $checkpoints->sortBy(function ($checkpoint) use ($selectedCheckpoints) {
            $position = array_search((string) $checkpoint->id, $selectedCheckpoints, true);
            return $position === false ? PHP_INT_MAX : $position;
        });
    @endphp
    <div class="mt-2 flex flex-wrap gap-x-6 gap-y-3">
        @forelse ($orderedCheckpoints as $checkpoint)
            <label class="flex items-center gap-2 text-sm">
                <input type="checkbox" name="checkpoint_ids[]" value="{{ $checkpoint->id }}"
                    @checked(in_array((string) $checkpoint->id, $selectedCheckpoints, true))>
                {{ $checkpoint->name }}
            </label>
        @empty
            <p class="text-sm text-slate-600">No checkpoints are configured.</p>
        @endforelse
    </div>
</fieldset>

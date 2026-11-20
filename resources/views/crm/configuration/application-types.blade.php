<section aria-labelledby="application-types-heading">
    <h2 id="application-types-heading" class="text-lg font-bold">Application Types</h2>
    <p class="mt-2 mb-5 text-sm text-slate-600">Choose the tabs shown for each type. New subtypes copy the other settings and share the type's tabs.</p>
    <div class="grid gap-4">
        @foreach ($applicationTypes as $category => $label)
            @php
                $setting = $applicationTypeSettings->get($category);
                $defaults = $setting?->defaults ?? [];
                $checkpointIds = $setting?->checkpoint_ids ?? [];
                $selectedTabs = $setting?->tabs ?? \App\Models\ApplicationTypeSetting::defaultTabs($category);
            @endphp
            <details class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm" @if(old('category') === $category) open @endif>
                <summary class="cursor-pointer font-bold">{{ $label }}</summary>
                <form method="POST" action="{{ route('configuration.application-types.update') }}" class="mt-4 grid gap-4">
                    @csrf
                    @method('PATCH')
                    <input type="hidden" name="mode" value="type">
                    <input type="hidden" name="category" value="{{ $category }}">
                    @include('crm.configuration.application-defaults', ['useOld' => old('mode') === 'type' && old('category') === $category])
                    <fieldset>
                        <legend class="text-sm font-semibold">Tabs</legend>
                        <div class="mt-2 flex flex-wrap gap-x-6 gap-y-3">
                            @foreach ($applicationTabOptions as $key => $tabLabel)
                                @continue($key === 'nominations' && $category !== 'SBS')
                                <label class="flex items-center gap-2 text-sm">
                                    <input type="checkbox" name="tabs[]" value="{{ $key }}"
                                        @checked(in_array($key, (array) (old('mode') === 'type' && old('category') === $category ? old('tabs', []) : $selectedTabs), true))>
                                    {{ $tabLabel }}
                                </label>
                            @endforeach
                        </div>
                    </fieldset>
                    <div><button type="submit" class="config-action config-primary">Save type settings</button></div>
                </form>

                <h3 class="mt-6 mb-3 border-t border-slate-200 pt-4 font-semibold">Subtypes</h3>
                @foreach ($applicationWorkflows->where('category', $category) as $workflow)
                    @php
                        $defaults = ($workflow->defaults ?? $setting?->defaults ?? []);
                        $defaults['migration_agent_id'] = $workflow->migration_agent_id
                            ?? ($workflow->defaults === null ? ($defaults['migration_agent_id'] ?? null) : null);
                        $checkpointIds = $workflow->checkpoint_ids ?? $setting?->checkpoint_ids ?? [];
                        $useOld = old('mode') === 'subtype' && (string) old('workflow_id') === (string) $workflow->id;
                    @endphp
                    <details class="mb-3 rounded-lg border border-slate-200 p-4" @if($useOld) open @endif>
                        <summary class="cursor-pointer text-sm font-semibold">{{ $workflow->name }}</summary>
                        <form method="POST" action="{{ route('configuration.application-types.update') }}" class="mt-4 grid gap-4">
                            @csrf
                            @method('PATCH')
                            <input type="hidden" name="mode" value="subtype">
                            <input type="hidden" name="category" value="{{ $category }}">
                            <input type="hidden" name="workflow_id" value="{{ $workflow->id }}">
                            @include('crm.configuration.application-defaults')
                            <div><button type="submit" class="config-action config-primary">Save subtype settings</button></div>
                        </form>
                    </details>
                @endforeach

                <form method="POST" action="{{ route('configuration.application-types.update') }}" class="flex flex-wrap items-end gap-3">
                    @csrf
                    @method('PATCH')
                    <input type="hidden" name="mode" value="create">
                    <input type="hidden" name="category" value="{{ $category }}">
                    <label class="grid gap-1 text-sm font-semibold">
                        Subtype name
                        <input name="name" required maxlength="255" class="rounded-md border border-slate-300 px-3 py-2"
                            value="{{ old('mode') === 'create' && old('category') === $category ? old('name') : '' }}">
                    </label>
                    <button type="submit" class="config-action config-primary">Add subtype</button>
                </form>
            </details>
        @endforeach
    </div>
</section>

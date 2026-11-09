<section aria-labelledby="application-types-heading">
    <h2 id="application-types-heading" class="text-lg font-bold">Application Types</h2>
    <p class="mt-2 mb-5 text-sm text-slate-600">Choose the tabs shown on applications of each type. Changes apply to existing and new applications. Tab content is work in progress.</p>
    <div class="grid gap-4">
        @foreach ($applicationTypes as $category => $label)
            @php
                $selectedTabs = old('category') === $category
                    ? old('tabs', [])
                    : array_keys(\App\Models\ApplicationTypeSetting::tabsFor($category));
                $selectedTabs = is_array($selectedTabs) ? $selectedTabs : [];
            @endphp
            <form method="POST" action="{{ route('configuration.application-types.update') }}" class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm">
                @csrf
                @method('PATCH')
                <input type="hidden" name="category" value="{{ $category }}">
                <fieldset>
                    <legend class="font-bold">{{ $label }}</legend>
                    <div class="mt-4 flex flex-wrap gap-x-6 gap-y-3">
                        @foreach ($applicationTabOptions as $key => $tabLabel)
                            <label class="flex items-center gap-2 text-sm">
                                <input type="checkbox" name="tabs[]" value="{{ $key }}" @checked(in_array($key, $selectedTabs, true)) class="h-4 w-4 accent-[#1f7890]">
                                {{ $tabLabel }}
                            </label>
                        @endforeach
                    </div>
                </fieldset>
                <div class="mt-4 flex flex-wrap items-center justify-between gap-3">
                    <p class="text-xs text-slate-500">Clear all selections to hide all tabs for this type.</p>
                    <button type="submit" class="config-action config-primary" aria-label="Save tabs for {{ $label }}">Save tabs</button>
                </div>
            </form>
        @endforeach
    </div>
</section>

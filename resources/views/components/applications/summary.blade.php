@props(['application', 'staff', 'typeField' => null, 'typeOptions' => []])
@php
    $criticalDateValue = old('critical_date', $application->critical_date?->format('Y-m-d'));
    $criticalDateValue = is_string($criticalDateValue) ? $criticalDateValue : '';
@endphp
@once
    <style>
        .application-summary-row { display: grid; grid-template-columns: minmax(120px, .8fr) minmax(0, 1.3fr); gap: 16px; align-items: center; padding: 9px 0; font-size: 13px; }
        .application-summary-row > :first-child { color: #475569; font-weight: 600; }
        .application-summary-control { width: 100%; min-height: 36px; border: 1px solid #cbd5e1; border-radius: 5px; padding: 7px 9px; background: #fff; color: #1e293b; font: inherit; }
        .application-summary-control:focus-visible, .application-summary button:focus-visible, .application-summary a:focus-visible { outline: 2px solid #1f7890; outline-offset: 2px; }
        .application-summary-control[aria-invalid="true"] { border-color: #b91c1c; }
        @media (max-width: 640px) { .application-summary-row { grid-template-columns: 1fr; gap: 6px; } }
    </style>
@endonce
<section class="application-summary rounded-lg border border-slate-200 bg-white shadow-sm" aria-labelledby="summary-heading">
    <header class="flex items-center justify-between gap-4 border-b border-slate-200 px-6 py-4">
        <h2 id="summary-heading" class="text-lg font-bold">Summary</h2>
        <span class="text-xs text-slate-500"><span class="text-red-600">*</span> Required</span>
    </header>
    <form id="application-summary-form" method="POST" action="{{ route('applications.update', $application->id) }}" class="px-6 pb-5 pt-3">
        @csrf
        @method('PATCH')
        @if ($errors->any())
            <div role="alert" class="mb-3 rounded-md border border-red-200 bg-red-50 p-3 text-sm text-red-800">
                <p class="font-semibold">The summary could not be saved.</p>
                <ul class="mt-1 list-disc pl-5">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
            </div>
        @endif
        <dl class="border-b border-slate-100 pb-3">
            <div class="application-summary-row">
                <dt>{{ $application->organisation_id ? 'Organisation Name' : 'Client Name' }}</dt>
                <dd class="break-words"><a class="font-medium text-[#1f7890] underline underline-offset-2" href="{{ $application->organisation_id ? route('organisations.show', $application->organisation_id) : route('clients.show', $application->client->client_id) }}">{{ $application->owner_name }}</a></dd>
            </div>
            <div class="application-summary-row"><dt>Application ID</dt><dd>{{ $application->id }}</dd></div>
            <div class="application-summary-row"><dt>Application type</dt><dd>{{ $application->type_label }}</dd></div>
        </dl>
        <div class="py-3">
            @if ($typeField)
                @if (in_array($application->category, ['Visa', 'Assessment'], true))
                    <x-applications.summary-select :name="$typeField[0]" :label="$typeField[1]" :options="array_combine($typeOptions, $typeOptions)" :value="$application->{$typeField[0]}" required />
                    @if (empty($typeOptions))<p class="mb-2 text-xs text-amber-800">No choices are configured for this workflow. Contact an administrator.</p>@endif
                @else
                    <dl><div class="application-summary-row">
                        <dt>{{ $typeField[1] }} <span class="text-red-600" aria-hidden="true">*</span></dt>
                        <dd>{{ $application->category === 'ART Appeal' ? ($application->psc_client === null ? 'Not set' : ($application->psc_client ? 'Yes' : 'No')) : ($application->nomination_pathway ?: 'Not set') }}</dd>
                    </div></dl>
                @endif
            @endif
            <x-applications.summary-select name="status" label="Status" :options="array_combine($application->statusOptions(), $application->statusOptions())" :value="$application->status" required />
            <x-applications.summary-select name="priority" label="Priority" :options="\App\Models\Application::PRIORITIES" :value="$application->priority" required />
            <div class="application-summary-row">
                <label for="summary-critical_date">Critical Date</label>
                <div>
                    <input type="date" id="summary-critical_date" name="critical_date" class="application-summary-control" value="{{ $criticalDateValue }}"
                        @if ($errors->has('critical_date')) aria-invalid="true" aria-describedby="summary-critical_date-error" @endif>
                    @error('critical_date')<p id="summary-critical_date-error" class="mt-1 text-xs text-red-700">{{ $message }}</p>@enderror
                </div>
            </div>
        </div>
        <div class="border-t border-slate-100 py-3">
            @foreach ([['primary_counsellor_id', 'Counsellor 1', 'primaryCounsellor', true], ['secondary_counsellor_id', 'Counsellor 2', 'secondaryCounsellor', false], ['migration_agent_id', 'Migration Agent', 'migrationAgent', $application->category === 'Visa']] as [$field, $label, $relation, $required])
                <x-applications.summary-select :name="$field" :label="$label" :options="$staff->pluck('name', 'id')->all()" :value="$application->$field" :required="$required" />
                @if ($application->$field && ! $staff->contains('id', $application->$field))
                    <p class="mb-2 text-xs text-amber-800">{{ $label }}: {{ $application->$relation?->name ?? 'Previous staff member' }} is no longer eligible. Select a replacement{{ $required ? '.' : ' or None.' }}</p>
                @endif
            @endforeach
        </div>
        <dl class="border-t border-slate-100 py-3">
            @if ($application->exists)
                <div class="application-summary-row"><dt>Date Created</dt><dd>{{ $application->created_at?->format('d/m/Y') }}</dd></div>
                <div class="application-summary-row"><dt>Last Modified</dt><dd><time datetime="{{ $application->updated_at?->toIso8601String() }}">{{ $application->updated_at?->format('d/m/Y H:i:s T') }}</time></dd></div>
            @endif
        </dl>
        <footer class="mt-5 flex items-center justify-end gap-3 border-t border-slate-100 pt-4">
            <a href="{{ route('applications.show', $application->id) }}" class="rounded-md border border-slate-300 px-4 py-2 text-sm text-slate-600">Cancel</a>
            <button type="submit" class="rounded-md bg-[#1f7890] px-4 py-2 text-sm font-semibold text-white hover:bg-[#17657a]">Save summary</button>
        </footer>
    </form>
</section>
@if ($application->category === 'Visa')
    <x-applications.visa-grant-dialog :application="$application" />
@endif

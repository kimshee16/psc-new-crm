@extends('layouts.payroll')

@section('content')
    <style>
        .application-summary-row { display:grid; grid-template-columns:minmax(130px,.8fr) minmax(0,1.6fr); gap:18px; align-items:center; padding:10px 0; font-size:13px; }
        .application-summary-row > :first-child { color:#475569; font-weight:600; }
        .application-summary-control { width:100%; min-height:40px; border:1px solid #cbd5e1; border-radius:6px; padding:8px 10px; background:#fff; color:#1e293b; font:inherit; }
        .application-summary-control:focus-visible { outline:2px solid #1f7890; outline-offset:2px; }
        .application-summary-control[aria-invalid=true] { border-color:#b91c1c; }
        .sbs-card { min-width:0; border:1px solid #e2e8f0; border-radius:12px; background:white; }
        .sbs-card > h2 { border-bottom:1px solid #e2e8f0; padding:20px 24px; font-size:18px; font-weight:700; }
        [hidden] { display:none !important; }
        @media(max-width:640px) { .application-summary-row { grid-template-columns:1fr; gap:6px; } }
    </style>
    <header class="mb-6 flex flex-wrap items-end justify-between gap-4">
        <div>
            <nav aria-label="Breadcrumb" class="text-sm text-slate-500"><a href="{{ route('organisations') }}" class="text-[#1f7890] underline">Organisations</a> / <a href="{{ route('organisations.show', $application->organisation_id) }}" class="text-[#1f7890] underline">{{ $application->organisation->name }}</a> / {{ $application->record_reference }}</nav>
            <h1 class="mt-3 text-[26px] font-bold">{{ $application->record_reference }} - SBS Application</h1>
            <p class="mt-1 text-sm text-slate-500">Sponsorship record</p>
        </div>
        <div class="flex flex-wrap items-center gap-3">
            <a href="#applications-{{ $application->id }}-notes" class="rounded-md border border-slate-300 bg-white px-4 py-2 text-sm font-semibold text-[#1f7890]">Notes</a>
            <a href="{{ route('applications.show', $application) }}" class="rounded-md border border-slate-300 bg-white px-4 py-2 text-sm">Cancel</a>
            <button type="submit" form="sbs-record-form" class="rounded-md bg-[#15638e] px-5 py-2 text-sm font-semibold text-white">Save changes</button>
        </div>
    </header>
    @if(session('success'))<p role="status" class="mb-5 rounded-md border border-emerald-200 bg-emerald-50 p-3 text-sm text-emerald-900">{{ session('success') }}</p>@endif
    <form id="sbs-record-form" method="POST" action="{{ route('applications.update', $application) }}">
        @csrf
        @method('PATCH')
        @include('crm.organisations.errors')
        <div class="grid items-stretch gap-6 xl:grid-cols-2">
            <section class="sbs-card" aria-labelledby="summary-heading">
                <h2 id="summary-heading">Summary <span class="float-right text-xs font-normal text-slate-500"><span class="text-red-600">*</span> Required</span></h2>
                <div class="px-6 py-4">
                    <dl class="border-b border-slate-100 pb-3">
                        <div class="application-summary-row"><dt>SBS ID</dt><dd>{{ $application->record_reference }}</dd></div>
                        <div class="application-summary-row"><dt>Sponsor type</dt><dd>{{ $application->sponsor_type ?: 'Standard Business Sponsor' }}</dd></div>
                        <div class="application-summary-row"><dt>Organisation name</dt><dd><a href="{{ route('organisations.show', $application->organisation_id) }}" class="text-[#1f7890] underline">{{ $application->organisation->name }}</a><p class="mt-1 text-slate-500">{{ $application->organisation->trading_name }}</p></dd></div>
                    </dl>
                    <div class="py-3">
                        <x-applications.summary-select name="status" label="Status" :options="array_combine($application->statusOptions(), $application->statusOptions())" :value="$application->status" required />
                        <x-applications.summary-select name="priority" label="Priority" :options="\App\Models\Application::PRIORITIES" :value="$application->priority" required />
                        <x-applications.nomination-field :application="$application" name="critical_date" label="Critical date" type="date" />
                    </div>
                    <div class="border-y border-slate-100 py-3">
                        @foreach(['primary_counsellor_id' => 'Counsellor 1', 'secondary_counsellor_id' => 'Counsellor 2', 'migration_agent_id' => 'Migration Agent'] as $field => $label)
                            <x-applications.summary-select :name="$field" :label="$label" :options="$staff->pluck('name', 'id')->all()" :value="$application->$field" :required="$field !== 'secondary_counsellor_id'" />
                        @endforeach
                    </div>
                    <dl class="py-3">
                        <div class="application-summary-row"><dt>Date created</dt><dd>{{ $application->created_at?->format('d/m/Y') }}</dd></div>
                        <div class="application-summary-row"><dt>Last modified</dt><dd><time datetime="{{ $application->updated_at?->toIso8601String() }}">{{ $application->updated_at?->format('d/m/Y H:i:s T') }}</time></dd></div>
                    </dl>
                </div>
            </section>
            <section class="sbs-card" aria-labelledby="sponsorship-heading">
                <h2 id="sponsorship-heading">Sponsorship</h2>
                <div class="px-6 py-4">
                    <fieldset class="border-b border-slate-100 pb-4">
                        <legend class="sr-only">Sponsorship milestones</legend>
                        @foreach(\App\Support\PSC\SbsRecord::MILESTONES as $field => $options)
                            <x-applications.summary-select :name="$field" :label="['initial_consultation' => 'Initial consultation', 'service_agreement' => 'Service agreement', 'supporting_documents' => 'Supporting documents', 'application_stage' => 'Application'][$field]" :options="array_combine($options, $options)" :value="$application->$field ?? 'To do'" />
                        @endforeach
                        <x-applications.nomination-field :application="$application" name="lodgement_date" label="Lodgement date" type="date" />
                        <x-applications.nomination-field :application="$application" name="trn" label="TRN" pattern="[A-Za-z0-9]{13}" aria-describedby="sbs-trn-help" />
                        <p id="sbs-trn-help" class="text-xs text-slate-500">13 alphanumeric characters. Required when status is Active.</p>
                    </fieldset>
                    <div class="py-3">
                        <x-applications.summary-select name="outcome" label="Outcome" :options="array_combine(\App\Support\PSC\NominationRecord::OUTCOMES, \App\Support\PSC\NominationRecord::OUTCOMES)" :value="$application->outcome" />
                        <x-applications.nomination-field :application="$application" name="outcome_date" label="Outcome date" type="date" />
                        <div data-original-expiry-slot></div>
                    </div>
                    <fieldset data-appeal-section class="border-t border-slate-100 py-4">
                        <legend class="pt-3 font-bold">Appeal</legend>
                        <x-applications.nomination-field :application="$application" name="appeal_deadline" label="Appeal deadline" type="date" />
                        <x-applications.summary-select name="appeal_status" label="Appeal status" :options="array_combine(\App\Support\PSC\NominationRecord::APPEAL_STATUSES, \App\Support\PSC\NominationRecord::APPEAL_STATUSES)" :value="$application->appeal_status" />
                        <x-applications.nomination-field :application="$application" name="art_case_number" label="ART case number" />
                        <x-applications.summary-select name="final_outcome" label="Final outcome" :options="array_combine(\App\Support\PSC\NominationRecord::FINAL_OUTCOMES, \App\Support\PSC\NominationRecord::FINAL_OUTCOMES)" :value="$application->final_outcome" />
                        <x-applications.nomination-field :application="$application" name="final_outcome_date" label="Final outcome date" type="date" />
                        <div data-final-expiry-slot></div>
                    </fieldset>
                    <div data-expiry-field><x-applications.nomination-field :application="$application" name="expiry_date" label="Expiry date" type="date" /></div>
                </div>
            </section>
        </div>
        <div class="mt-4 flex justify-end"><button class="rounded-md bg-[#15638e] px-5 py-2 text-sm font-semibold text-white">Save changes</button></div>
    </form>
    <x-applications.tabs :application="$application" :tabs="$applicationTabs" :active-tab="$activeApplicationTab" :activities="$activities" :nominations="$nominations" />
    @if ($activeApplicationTab !== 'notes')
        <x-related-record-notes :record="$application" module="applications" :record-id="$application->id" />
    @endif
    @include('crm.applications.sbs-script')
@endsection

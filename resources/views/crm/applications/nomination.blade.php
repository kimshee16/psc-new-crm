@extends('layouts.payroll')

@section('content')
    @php
        $external = old('external_nominee', filled($application->nominee_name) && ! $application->nominee_client_id);
        $sponsorshipId = old('sponsorship_id', $application->sponsorship_id);
    @endphp
    <style>
        .application-summary-row { display:grid; grid-template-columns:minmax(130px,.8fr) minmax(0,1.6fr); gap:18px; align-items:center; padding:10px 0; font-size:13px; }
        .application-summary-row > :first-child { color:#475569; font-weight:600; }
        .application-summary-control { width:100%; min-height:40px; border:1px solid #cbd5e1; border-radius:6px; padding:8px 10px; background:#fff; color:#1e293b; font:inherit; }
        .application-summary-control:focus-visible { outline:2px solid #1f7890; outline-offset:2px; }
        .application-summary-control[aria-invalid=true] { border-color:#b91c1c; }
        .nomination-card { min-width:0; border:1px solid #e2e8f0; border-radius:12px; background:white; }
        .nomination-card > h2 { border-bottom:1px solid #e2e8f0; padding:20px 24px; font-size:18px; font-weight:700; }
        [hidden] { display:none !important; }
        @media(max-width:640px) { .application-summary-row { grid-template-columns:1fr; gap:6px; } }
    </style>
    <header class="mb-6 flex flex-wrap items-end justify-between gap-4">
        <div>
            <nav aria-label="Breadcrumb" class="text-sm text-slate-500"><a href="{{ route('organisations') }}" class="text-[#1f7890] underline">Organisations</a> / <a href="{{ route('organisations.show', $application->organisation_id) }}" class="text-[#1f7890] underline">{{ $application->organisation->name }}</a> / {{ $application->record_reference }}</nav>
            <h1 class="mt-3 text-[26px] font-bold">{{ $application->record_reference }} - Nomination Application</h1>
            <p class="mt-1 text-sm text-slate-500">Nomination record</p>
        </div>
        <div class="flex flex-wrap items-center gap-3">
            <a href="#applications-{{ $application->id }}-notes" class="rounded-md border border-slate-300 bg-white px-4 py-2 text-sm font-semibold text-[#1f7890]">Notes</a>
            <details class="relative">
                <summary class="cursor-pointer rounded-md border border-slate-300 bg-white px-4 py-2 text-sm font-semibold">Actions</summary>
                <div class="absolute right-0 z-20 mt-2 w-60 space-y-3 rounded-lg border border-slate-200 bg-white p-4 text-sm shadow-lg">
                    <button type="button" data-open-application-links class="block text-[#1f7890] underline">Link/Unlink Items</button>
                    <a href="{{ route('organisations.show', $application->organisation_id) }}" class="block text-[#1f7890] underline">Open organisation</a>
                    <a href="#nomination-google_drive_url" class="block text-[#1f7890] underline">Set Google Drive folder</a>
                </div>
            </details>
            <a href="{{ $application->google_drive_url ?: '#nomination-google_drive_url' }}" @if($application->google_drive_url) target="_blank" rel="noopener noreferrer" @endif class="rounded-md border border-slate-300 bg-white px-4 py-2 text-sm font-semibold">Google Drive</a>
            <a href="{{ route('applications.show', $application) }}" class="rounded-md border border-slate-300 bg-white px-4 py-2 text-sm">Cancel</a>
            <button type="submit" form="nomination-record-form" class="rounded-md bg-[#15638e] px-5 py-2 text-sm font-semibold text-white">Save changes</button>
        </div>
    </header>
    @if(session('success'))<p role="status" class="mb-5 rounded-md border border-emerald-200 bg-emerald-50 p-3 text-sm text-emerald-900">{{ session('success') }}</p>@endif
    <form id="nomination-record-form" method="POST" action="{{ route('applications.update', $application) }}">
        @csrf
        @method('PATCH')
        @include('crm.organisations.errors')
        <div class="grid items-stretch gap-6 xl:grid-cols-2">
            <section class="nomination-card" aria-labelledby="summary-heading">
                <h2 id="summary-heading">Summary <span class="float-right text-xs font-normal text-slate-500"><span class="text-red-600">*</span> Required</span></h2>
                <div class="px-6 py-4">
                    <dl>
                        <div class="application-summary-row"><dt>Nomination ID</dt><dd>{{ $application->record_reference }}</dd></div>
                        <div class="application-summary-row"><dt>Organisation Name</dt><dd><a href="{{ route('organisations.show', $application->organisation_id) }}" class="text-[#1f7890] underline">{{ $application->organisation->name }}</a><p class="mt-1 text-slate-500">{{ $application->organisation->trading_name }}</p></dd></div>
                    </dl>
                    @if($nomineeLocked)
                        <dl class="border-b border-slate-100 py-3">
                            <div class="application-summary-row"><dt>Nominee Name *</dt><dd>@include('crm.organisations.nominee')</dd></div>
                            <div class="application-summary-row"><dt>Visa subclass *</dt><dd>{{ $application->nomination_pathway ?: 'Not set on linked visa' }}</dd></div>
                        </dl>
                        <p class="my-3 text-xs text-slate-500">Nominee and subclass are locked by the linked visa application. Unlink it to edit these fields.</p>
                    @else
                        <fieldset class="border-b border-slate-100 py-3" data-nominee-picker data-search-url="{{ route('organisations.nominees', $application->organisation_id) }}">
                            <legend class="sr-only">Nominee and subclass</legend>
                            <input type="hidden" name="external_nominee" value="0">
                            <label class="mb-3 block text-sm"><input type="checkbox" name="external_nominee" value="1" @checked($external)> External: enter the nominee's full name instead</label>
                            <div data-internal-nominee @if($external) hidden @endif>
                                <div class="application-summary-row"><label for="nominee-client-search">Nominee Name *</label><div>
                                    <input id="nominee-client-search" type="search" class="application-summary-control" placeholder="Search by name or Client ID" autocomplete="off" value="{{ $selectedNominee ? trim(implode(' ', array_filter([$selectedNominee->first_name, $selectedNominee->middle_name, $selectedNominee->surname]))) : '' }}" aria-controls="nominee-results" aria-describedby="nominee-selection">
                                    <input type="hidden" name="nominee_client_reference" value="{{ old('nominee_client_reference', $application->nomineeClient?->client_id) }}" @disabled($external)>
                                    <div id="nominee-results" class="mt-1 space-y-1 text-sm"></div>
                                    <p id="nominee-selection" role="status" class="mt-1 text-xs text-slate-500">{{ $selectedNominee ? 'Selected Client '.$selectedNominee->client_id : 'Select a client from the results.' }}</p>
                                </div></div>
                            </div>
                            <div data-external-nominee @if(!$external) hidden @endif>
                                <x-applications.nomination-field :application="$application" name="nominee_name" label="Nominee full name" :required="(bool) $external" :disabled="!$external" />
                            </div>
                            <x-applications.summary-select name="nomination_pathway" label="Visa subclass" :options="array_combine($typeOptions, $typeOptions)" :value="$application->nomination_pathway" required />
                            <p class="mt-1 text-xs text-slate-500">Link a visa application under Linked Items to fill and lock these fields automatically.</p>
                        </fieldset>
                    @endif
                    <div class="py-3">
                        <x-applications.summary-select name="status" label="Status" :options="array_combine($application->statusOptions(), $application->statusOptions())" :value="$application->status" required />
                        <x-applications.summary-select name="priority" label="Priority" :options="\App\Models\Application::PRIORITIES" :value="$application->priority" required />
                        <x-applications.nomination-field :application="$application" name="critical_date" label="Critical Date" type="date" />
                    </div>
                    <div class="border-y border-slate-100 py-3">
                        @foreach(['primary_counsellor_id' => 'Counsellor 1', 'secondary_counsellor_id' => 'Counsellor 2', 'migration_agent_id' => 'Migration Agent'] as $field => $label)
                            <x-applications.summary-select :name="$field" :label="$label" :options="$staff->pluck('name', 'id')->all()" :value="$application->$field" :required="$field === 'primary_counsellor_id'" />
                        @endforeach
                    </div>
                    <dl class="py-3">
                        <div class="application-summary-row"><dt>Date Created</dt><dd>{{ $application->created_at?->format('d/m/Y') }}</dd></div>
                        <div class="application-summary-row"><dt>Last Modified</dt><dd><time datetime="{{ $application->updated_at?->toIso8601String() }}">{{ $application->updated_at?->format('d/m/Y H:i:s T') }}</time></dd></div>
                    </dl>
                    <x-applications.nomination-field :application="$application" name="google_drive_url" label="Google Drive folder URL" type="url" maxlength="2048" placeholder="https://drive.google.com/drive/folders/..." />
                </div>
            </section>
            <section class="nomination-card" aria-labelledby="nomination-heading">
                <h2 id="nomination-heading">Nomination</h2>
                <div class="px-6 py-4">
                    <div class="border-b border-slate-100 pb-4">
                        <x-applications.summary-select name="sponsorship_id" label="Sponsorship" :options="$sponsorships->mapWithKeys(fn ($sbs) => [$sbs->id => $sbs->record_reference.' · '.$sbs->sponsor_type.' · '.$sbs->status.' · Expires '.($sbs->expiry_date?->format('d/m/Y') ?? 'not set')])->all()" :value="$application->sponsorship_id" />
                        <div data-sponsorship-details>
                            @foreach($sponsorships as $sbs)
                                <p data-sponsorship-id="{{ $sbs->id }}" @if((string)$sponsorshipId !== (string)$sbs->id) hidden @endif class="mb-3 text-xs text-slate-500"><span class="font-semibold">{{ $sbs->status }}</span> · Expires {{ $sbs->expiry_date?->format('d/m/Y') ?? 'not set' }} <a href="{{ route('applications.show', $sbs) }}" class="ml-2 text-[#1f7890] underline">Open record</a></p>
                            @endforeach
                        </div>
                        <p class="text-xs text-slate-500">Defaults to the organisation's active SBS when the subclass requires sponsorship. You can choose another sponsorship or labour agreement.</p>
                        <p data-sbs-warning role="status" class="mt-2 rounded-md bg-amber-50 p-3 text-sm text-amber-900" @if(!app(\App\Support\PSC\NominationRecord::class)->requiresSbs($application->nomination_pathway) || $hasActiveSbs) hidden @endif>This subclass requires SBS, but the organisation has no active SBS available. Check sponsorship before proceeding.</p>
                    </div>
                    <div class="border-b border-slate-100 py-3">
                        <x-applications.nomination-field :application="$application" name="nominated_position_title" label="Position title" />
                        <x-applications.nomination-field :application="$application" name="anzsco_code" label="ANZSCO code" inputmode="numeric" pattern="[0-9]{6}" placeholder="6-digit code" />
                        <x-applications.nomination-field :application="$application" name="annual_salary" label="Annual salary (AUD)" type="number" min="0" max="9999999999.99" step="0.01" />
                    </div>
                    <div class="py-3">
                        <x-applications.nomination-field :application="$application" name="lodgement_date" label="Lodgement date" type="date" />
                        <x-applications.nomination-field :application="$application" name="reference_number" label="Reference number" />
                        <x-applications.summary-select name="outcome" label="Outcome" :options="array_combine(\App\Support\PSC\NominationRecord::OUTCOMES, \App\Support\PSC\NominationRecord::OUTCOMES)" :value="$application->outcome" />
                        <x-applications.nomination-field :application="$application" name="outcome_date" label="Outcome date" type="date" />
                        <div data-original-expiry-slot></div>
                    </div>
                    <fieldset data-appeal-section class="border-t border-slate-100 py-4">
                        <legend class="pt-3 font-bold">Appeal</legend>
                        <p class="mb-2 text-xs text-slate-500">Shown when Outcome is Refused. The original outcome stays Refused after an appeal.</p>
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
    <x-applications.linked-items :application="$application" :items="$linkedItems" />
    <x-applications.tabs :application="$application" :tabs="$applicationTabs" :active-tab="$activeApplicationTab" :activities="$activities" />
    @if ($activeApplicationTab !== 'notes')
        <x-related-record-notes :record="$application" module="applications" :record-id="$application->id" />
    @endif
    @include('crm.applications.nomination-script')
@endsection

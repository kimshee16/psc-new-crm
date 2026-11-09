@extends('layouts.payroll')

@section('content')
    <header class="mb-7">
        @if($application->category === 'SBS' && $application->organisation_id)
            <p class="text-sm text-slate-500"><a href="{{ route('organisations.show', $application->organisation_id) }}" class="font-medium text-[#1f7890] underline">{{ $application->organisation->name }}</a> / {{ $application->record_reference }}</p>
            <h1 class="mt-3 text-[26px] font-bold">{{ $application->record_reference }} - {{ $application->type_label }}</h1>
        @else
            <a href="{{ route('applications') }}" class="text-sm font-medium text-[#1f7890]">&larr; Applications</a>
            <h1 class="mt-3 text-[26px] font-bold">Application {{ $application->id }}</h1>
        @endif
        <p class="mt-2 text-sm text-slate-600">{{ $application->owner_name }} &middot; {{ $application->type_label }}@if ($application->category === 'Study') &middot; {{ $application->course ?: 'Course TBD' }}@endif</p>
    </header>
    @if (session('success'))
        <p role="status" class="mb-5 rounded-md border border-emerald-200 bg-emerald-50 p-3 text-sm text-emerald-900">{{ session('success') }}</p>
    @endif
    <div class="grid items-start gap-6 xl:grid-cols-2">
        <x-applications.summary :application="$application" :staff="$staff" :type-field="$typeField" :type-options="$typeOptions" />
        <section class="rounded-lg border border-slate-200 bg-white p-6 shadow-sm" aria-labelledby="application-workflow-heading">
            <h2 id="application-workflow-heading" class="text-lg font-bold">{{ $application->type_label }}</h2>
            <p class="mt-3 text-sm text-slate-600">Workflow template: {{ $application->workflow?->name ?? $application->type_label }}</p>
            @if ($application->category === 'Study')
                <p class="mt-3 text-sm">Course: {{ $application->course ?: 'Course TBD' }}</p>
            @elseif ($application->category === 'ART Appeal')
                <p class="mt-3 text-sm">Subclass: {{ $application->visa_subclass ?: 'Not set' }}</p>
            @endif
        </section>
    </div>
    @if ($application->organisation_id && in_array($application->category, ['SBS', 'Nomination']))
        @include('crm.organisations.application-details')
    @endif
    <x-applications.linked-items :application="$application" :items="$linkedItems" />
    <x-applications.tabs :application="$application" :tabs="$applicationTabs" :active-tab="$activeApplicationTab" :activities="$activities" />
    <x-record-notes :stored="$application->notes" module="applications" :record-id="$application->id" :editable="true" />
@endsection

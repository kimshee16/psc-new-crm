@extends('layouts.payroll')

@section('content')
    <header class="mb-7">
        <a href="{{ route('applications') }}" class="text-sm font-medium text-[#1f7890]">&larr; Applications</a>
        <h1 class="mt-3 text-[26px] font-bold">Application {{ $application->id }}</h1>
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
            @if ($linkedApplication)
                <h3 class="mt-6 font-semibold">Linked application</h3>
                <a class="mt-2 block text-sm text-[#1f7890] underline" href="{{ route('applications.show', $linkedApplication) }}">{{ $linkedApplication->type_label }} — Application ID {{ $linkedApplication->id }}</a>
                @if ($linkedApplication->visa_subclass)<p class="mt-2 text-sm text-slate-600">{{ $linkedApplication->visa_subclass }}</p>@endif
            @endif
        </section>
    </div>
@endsection

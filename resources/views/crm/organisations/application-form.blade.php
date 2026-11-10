@extends('layouts.payroll')

@section('content')
    @php
        $sbs = $application->category === 'SBS';
        $fields = $sbs
            ? ['sponsor_type' => 'Sponsor type', 'approval_date' => 'Approval date', 'expiry_date' => 'Expiry date', 'home_affairs_reference' => 'Home Affairs reference', 'trn' => 'TRN for the SBS application']
            : ['nominated_position_title' => 'Nominated position title', 'nomination_pathway' => 'Nomination subclass', 'outcome' => 'Outcome', 'nominee_name' => 'Nominee Name', 'nominee_client_reference' => 'Linked Client ID'];
        if (! $sbs && ! $application->exists) $fields['visa_application_id'] = 'Linked Visa Application Service ID';
    @endphp
    <header class="mb-7">
        <a href="{{ $application->exists ? route('applications.show', $application) : route('organisations.show', $organisation) }}" class="text-sm font-medium text-[#1f7890]">&larr; {{ $organisation->name }}</a>
        <h1 class="mt-3 text-[26px] font-bold">{{ $application->exists ? 'Edit' : 'Create' }} {{ $sbs ? 'sponsorship' : 'nomination' }}</h1>
        <p class="mt-2 text-sm text-slate-600">{{ $organisation->reference }} &middot; {{ $organisation->name }}</p>
    </header>
    <form method="POST" action="{{ $application->exists ? route('applications.organisation-details.update', $application) : route('organisations.applications.store', $organisation) }}" class="max-w-3xl space-y-5 rounded-lg border border-slate-200 bg-white p-6 shadow-sm">
        @csrf
        @if ($application->exists) @method('PATCH') @else <input type="hidden" name="category" value="{{ $application->category }}"> @endif
        @include('crm.organisations.errors')
        @foreach ($fields as $field => $label)
            @php
                $date = in_array($field, ['approval_date', 'expiry_date']);
                $number = in_array($field, ['nominee_client_reference', 'visa_application_id']);
                $value = $field === 'nominee_client_reference' ? $application->nomineeClient?->client_id : ($date ? $application->$field?->format('Y-m-d') : $application->$field);
            @endphp
            <label class="block text-sm font-semibold" for="{{ $field }}">{{ $label }}{{ in_array($field, ['sponsor_type', 'nominated_position_title']) ? ' *' : '' }}
                <input id="{{ $field }}" name="{{ $field }}" type="{{ $date ? 'date' : ($number ? 'number' : 'text') }}" @if($number) min="1" step="1" @endif @readonly($field === 'sponsor_type' && $application->exists) value="{{ old($field, $value) }}" @required(in_array($field, ['sponsor_type', 'nominated_position_title'])) maxlength="255" class="mt-2 block w-full rounded-md border border-slate-300 px-3 py-2 font-normal">
            </label>
        @endforeach
        @if (! $sbs)<p class="text-sm text-slate-500">The nominee can be recorded without a Client ID. When linked, the client's name is used if Nominee Name is blank. Manage visa links using Link/Unlink items on the application record.</p>@endif
        @if (! $application->exists)<p class="text-sm text-slate-500">The application starts as Not Started and is assigned to you. Update status, counsellors and migration agent in its Summary.</p>@endif
        <button type="submit" class="rounded-md bg-[#1f7890] px-5 py-2.5 text-sm font-semibold text-white">{{ $application->exists ? 'Save details' : ($sbs ? 'Create sponsorship' : 'Create nomination') }}</button>
    </form>
@endsection

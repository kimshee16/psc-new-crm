@extends('layouts.payroll')

@section('content')
    <style>
        .organisation-search-shell { max-width: 640px; margin: 0 auto; }
        .organisation-search-form { display: flex; flex-direction: column; gap: 12px; }
        .organisation-search-row { display: grid; grid-template-columns: 240px minmax(0, 1fr); align-items: center; gap: 10px; }
        .organisation-search-row label { color: #64748b; font-size: 14px; font-weight: 500; text-align: right; }
        .organisation-search-field { width: 100%; height: 30px; border: 1px solid #64748b; border-radius: 2px; background: white; color: #334155; padding: 0 10px; outline: none; }
        .organisation-search-field:focus { border-color: #1f7890; box-shadow: 0 0 0 3px rgb(31 120 144 / 15%); }
        .organisation-search-actions { display: flex; flex-wrap: wrap; justify-content: center; gap: 18px; margin-top: 18px; border-top: 1px solid #d8dee8; padding-top: 16px; }
        .organisation-search-action { display: inline-flex; min-height: 46px; align-items: center; justify-content: center; border-radius: 999px; background: #4d70df; color: white; padding: 10px 24px; font-size: 14px; font-weight: 600; }
        .organisation-search-action:hover { background: #3f60c8; }
        .organisation-search-action:focus-visible { outline: 2px solid #1f7890; outline-offset: 3px; }
        @media (max-width: 900px) { .organisation-search-row { grid-template-columns: 1fr; } .organisation-search-row label { text-align: left; } }
    </style>
    <header class="mb-8">
        <h1 class="text-[26px] font-bold leading-tight text-[#101820]">Organisation Search</h1>
        <p class="mt-2 text-sm text-slate-600">Search all organisations across the CRM by organisation or contact person details.</p>
    </header>
    @include('crm.organisations.errors')
    <section class="organisation-search-shell" aria-label="Search organisations">
        <form method="GET" action="{{ route('organisations.search') }}" class="organisation-search-form">
            @foreach (['id' => 'Search by Organisation ID', 'organisation_name' => 'Search by Organisation Name', 'contact_name' => 'Search by Contact First Name / Surname', 'contact_mobile' => 'Search by Contact Mobile No.', 'contact_email' => 'Search by Contact Email'] as $field => $label)
                <div class="organisation-search-row">
                    <label for="organisation-search-{{ $field }}">{{ $label }}</label>
                    <input id="organisation-search-{{ $field }}" type="search" name="{{ $field }}" value="{{ $filters[$field] ?? '' }}" maxlength="255" class="organisation-search-field">
                </div>
            @endforeach
            <div class="organisation-search-actions">
                <a href="{{ route('organisations') }}" class="organisation-search-action">View My Organisations</a>
                <a href="{{ route('organisations.create') }}" class="organisation-search-action">Create Organisation</a>
                <button type="submit" class="organisation-search-action">Search</button>
                @if ($hasSearch)<a href="{{ route('organisations.search') }}" class="self-center text-sm text-[#1f7890] underline">Clear search</a>@endif
            </div>
        </form>
    </section>
    @if ($hasSearch)
        <section class="mt-12 rounded-lg border border-slate-200 bg-white shadow-sm" aria-label="Organisation search results">
            <p role="status" class="border-b border-slate-200 px-5 py-4 text-sm text-slate-600">{{ $organisations->total() === 1 ? '1 organisation found' : $organisations->total().' organisations found' }}</p>
            @if ($organisations->isEmpty())
                <p class="px-5 py-10 text-center text-sm text-slate-500">No organisations match the current search.</p>
            @else
                <div class="overflow-x-auto" tabindex="0" role="region" aria-label="Organisation results; scroll horizontally for all columns">
                    <table class="min-w-full text-left text-sm">
                        <thead class="bg-slate-50 text-xs text-slate-500"><tr>
                            @foreach (['Organisation ID', 'Legal name', 'Trading name', 'Contact persons', 'Actions'] as $heading)<th scope="col" class="px-5 py-3">{{ $heading }}</th>@endforeach
                        </tr></thead>
                        <tbody class="divide-y divide-slate-100">
                            @foreach ($organisations as $organisation)
                                <tr class="align-top">
                                    <td class="whitespace-nowrap px-5 py-4 font-semibold">{{ $organisation->reference }}</td>
                                    <td class="px-5 py-4">{{ $organisation->name }}</td>
                                    <td class="px-5 py-4">{{ $organisation->trading_name ?: '—' }}</td>
                                    <td class="px-5 py-4"><ul class="space-y-3">
                                        @forelse ($organisation->contacts as $contact)
                                            <li><p class="font-semibold">{{ $contact->name }}</p><p class="text-slate-600">{{ $contact->phone ?: '—' }}</p><p class="break-all text-slate-600">{{ $contact->email ?: '—' }}</p></li>
                                        @empty<li class="text-slate-500">No contact persons added.</li>@endforelse
                                    </ul></td>
                                    <td class="px-5 py-4"><a href="{{ route('organisations.search.show', $organisation) }}" aria-label="Open organisation {{ $organisation->reference }}" class="inline-flex min-h-9 items-center justify-center rounded-md border border-[#b9dce4] px-3 py-2 text-[#1f7890]">{!! payroll_icon('eye', 'h-4 w-4') !!}</a></td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <div class="px-5 py-4">{{ $organisations->links() }}</div>
            @endif
        </section>
    @endif
@endsection

@extends('layouts.payroll')

@section('content')
    <header class="mb-6 flex flex-wrap items-center justify-between gap-4">
        <div>
            <a href="{{ route('organisations.search') }}" class="text-sm text-[#1f7890] underline">Back to Organisation Search</a>
            <h1 class="mt-2 text-[26px] font-bold">{{ $organisation->name }}</h1>
            <p class="mt-1 text-sm text-slate-500">{{ $organisation->reference }}</p>
        </div>
        @if ($canOpenRecord)<a href="{{ route('organisations.show', $organisation) }}" class="rounded-md bg-[#1f7890] px-4 py-2 text-sm font-semibold text-white">Open full record</a>@endif
    </header>
    <section class="rounded-lg border border-slate-200 bg-white p-6 shadow-sm">
        <h2 class="text-lg font-bold">Organisation details</h2>
        <dl class="mt-4 grid gap-x-5 gap-y-3 text-sm sm:grid-cols-[160px_1fr]">
            @foreach (['name' => 'Legal name', 'trading_name' => 'Trading name', 'abn' => 'ABN', 'address' => 'Address'] as $field => $label)
                <dt class="text-slate-500">{{ $label }}</dt><dd class="whitespace-pre-line break-words">{{ $organisation->$field ?: '—' }}</dd>
            @endforeach
        </dl>
    </section>
    <section class="mt-6 rounded-lg border border-slate-200 bg-white p-6 shadow-sm">
        <h2 class="text-lg font-bold">Contact persons</h2>
        <ul class="mt-4 divide-y divide-slate-100">
            @forelse ($organisation->contacts as $contact)
                <li class="py-4 text-sm"><p class="font-semibold">{{ $contact->name }}</p><p class="mt-1">{{ $contact->role ?: '—' }}</p><p class="mt-1">{{ $contact->phone ?: '—' }}</p><p class="mt-1 break-all">{{ $contact->email ?: '—' }}</p></li>
            @empty<li class="py-4 text-sm text-slate-500">No contact persons added.</li>@endforelse
        </ul>
    </section>
@endsection

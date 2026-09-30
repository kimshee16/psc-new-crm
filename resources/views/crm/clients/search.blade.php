@extends('layouts.payroll')

@php
    $statusClasses = function (string $status): string {
        return match ($status) {
            'Active' => 'bg-emerald-50 text-emerald-700',
            'Prospect' => 'bg-blue-50 text-blue-700',
            'On hold' => 'bg-amber-50 text-amber-700',
            default => 'bg-slate-100 text-slate-600',
        };
    };
@endphp

@section('content')
    <style>
        .client-search-shell {
            max-width: 640px;
            margin: 0 auto;
        }

        .client-search-form {
            display: flex;
            flex-direction: column;
            gap: 12px;
        }

        .client-search-row {
            display: grid;
            grid-template-columns: 220px minmax(0, 1fr);
            align-items: center;
            gap: 10px;
        }

        .client-search-row label {
            color: #64748b;
            font-size: 14px;
            font-weight: 500;
            text-align: right;
        }

        .client-search-field {
            width: 100%;
            height: 30px;
            border: 1px solid #64748b;
            border-radius: 2px;
            background: #fff;
            color: #334155;
            padding: 0 10px;
            outline: none;
        }

        .client-search-field:focus {
            border-color: #1f7890;
            box-shadow: 0 0 0 3px rgb(31 120 144 / 15%);
        }

        .client-search-status {
            max-width: 96px;
        }

        .client-search-divider {
            margin: 18px 0 16px;
            border-top: 1px solid #d8dee8;
        }

        .client-search-actions {
            display: flex;
            flex-wrap: wrap;
            justify-content: center;
            gap: 18px;
        }

        .client-search-action {
            display: inline-flex;
            min-width: 118px;
            min-height: 46px;
            align-items: center;
            justify-content: center;
            border: 0;
            border-radius: 999px;
            background: #4d70df;
            color: #fff;
            padding: 10px 24px;
            font-size: 14px;
            font-weight: 600;
            text-decoration: none;
            transition: background-color 160ms ease;
        }

        .client-search-action:hover {
            background: #3f60c8;
            color: #fff;
        }

        .client-search-results {
            margin-top: 52px;
        }

        @media (max-width: 720px) {
            .client-search-row {
                grid-template-columns: 1fr;
            }

            .client-search-row label {
                text-align: left;
            }
        }
    </style>

    <header class="mb-8">
        <div>
            <div class="text-xs font-bold uppercase tracking-[0.12em] text-[#1f7890]">PSC-40</div>
            <h1 class="mt-2 text-[26px] font-bold leading-tight text-[#101820]">Client Search</h1>
        </div>
    </header>

    <section class="client-search-shell">
        <form method="GET" action="{{ route('clients.search') }}" class="client-search-form">
            <div class="client-search-row">
                <label for="client-search-id">Search By Client ID</label>
                <input id="client-search-id" name="client_id" value="{{ request('client_id') }}" type="search" class="client-search-field">
            </div>
            <div class="client-search-row">
                <label for="client-search-first-name">Search by Client First Name</label>
                <input id="client-search-first-name" name="first_name" value="{{ request('first_name') }}" type="search" class="client-search-field">
            </div>
            <div class="client-search-row">
                <label for="client-search-surname">Search by Client Surname</label>
                <input id="client-search-surname" name="surname" value="{{ request('surname') }}" type="search" class="client-search-field">
            </div>
            <div class="client-search-row">
                <label for="client-search-mobile">Search by Client Mobile No.</label>
                <input id="client-search-mobile" name="mobile" value="{{ request('mobile') }}" type="search" class="client-search-field">
            </div>
            <div class="client-search-row">
                <label for="client-search-email">Search by Client Email</label>
                <input id="client-search-email" name="email" value="{{ request('email') }}" type="search" class="client-search-field">
            </div>
            <div class="client-search-row">
                <label for="client-search-status">Search by Client Status</label>
                <select id="client-search-status" name="status" class="client-search-field client-search-status">
                    <option value=""></option>
                    @foreach ($statuses as $status)
                        <option value="{{ $status }}" @selected(request('status') === $status)>{{ $status }}</option>
                    @endforeach
                </select>
            </div>
            <div class="client-search-row">
                <label for="client-search-event">Search by Events</label>
                <select id="client-search-event" name="event" class="client-search-field">
                    <option value=""></option>
                    @foreach ($events as $event)
                        <option value="{{ $event }}" @selected(request('event') === $event)>{{ $event }}</option>
                    @endforeach
                </select>
            </div>

            <div class="client-search-divider"></div>

            <div class="client-search-actions">
                <a href="{{ route('clients') }}" class="client-search-action">View My Clients</a>
                <a href="{{ route('clients.create') }}" class="client-search-action">Create Client</a>
                <button type="submit" class="client-search-action">Search</button>
            </div>
        </form>
    </section>

    @if ($hasSearch)
        <section class="client-search-results rounded-lg border border-slate-200 bg-white shadow-sm">
            <div class="border-b border-slate-200 px-5 py-4 text-sm text-slate-600">
                {{ count($clients) === 1 ? '1 client found' : count($clients).' clients found' }}
            </div>
            @if ($clients === [])
                <div class="px-5 py-10 text-center text-sm text-slate-500">No clients match the current search.</div>
            @else
                <div class="overflow-x-auto">
                    <table class="min-w-full text-left text-sm">
                        <thead class="bg-slate-50 text-[11px] uppercase tracking-[0.08em] text-slate-500">
                            <tr>
                                <th class="px-5 py-3 font-bold">Client ID</th>
                                <th class="px-5 py-3 font-bold">First Name</th>
                                <th class="px-5 py-3 font-bold">Middle Name</th>
                                <th class="px-5 py-3 font-bold">Surname</th>
                                <th class="px-5 py-3 font-bold">Mobile Number</th>
                                <th class="px-5 py-3 font-bold">Status</th>
                                <th class="px-5 py-3 font-bold">Tag</th>
                                <th class="px-5 py-3 text-right font-bold">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            @foreach ($clients as $client)
                                <tr>
                                    <td class="px-5 py-4 font-bold text-[#101820]">CL-{{ $client['client_id'] }}</td>
                                    <td class="px-5 py-4 text-slate-700">{{ $client['first_name'] }}</td>
                                    <td class="px-5 py-4 text-slate-700">{{ $client['middle_name'] }}</td>
                                    <td class="px-5 py-4 font-semibold text-[#101820]">{{ $client['surname'] }}</td>
                                    <td class="px-5 py-4 text-slate-600">{{ $client['mobile'] }}</td>
                                    <td class="px-5 py-4"><span class="rounded-full px-3 py-1 text-xs font-bold {{ $statusClasses($client['status']) }}">{{ $client['status'] }}</span></td>
                                    <td class="px-5 py-4"><span class="rounded-md bg-[#eef8fa] px-2 py-1 text-xs font-bold text-[#1f7890]">{{ $client['tag'] }}</span></td>
                                    <td class="px-5 py-4 text-right">
                                        <a href="{{ route('clients.show', $client['client_id']) }}" title="Open client record" class="inline-flex min-h-9 items-center justify-center rounded-md border border-[#b9dce4] bg-white px-3 py-2 text-sm font-medium text-[#1f7890] transition hover:border-[#1f7890]">
                                            {!! payroll_icon('eye', 'h-4 w-4') !!}
                                            <span class="sr-only">Open client record</span>
                                        </a>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </section>
    @endif
@endsection

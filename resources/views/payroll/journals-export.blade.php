@php
    $title = 'Journals & Export';
    $activeLabel = 'Journals & Export';
    $entries = [
        ['entity' => '2338', 'name' => 'Haven Anaheim', 'debits' => '85,296.85', 'credits' => '84,273.19', 'balance' => '1,023.66'],
        ['entity' => '5LBS', 'name' => 'Haven Cultivation', 'debits' => '46,210.72', 'credits' => '46,212.95', 'balance' => '2.23'],
        ['entity' => 'BANT', 'name' => 'B AND T Solutions', 'debits' => '80,616.66', 'credits' => '79,474.48', 'balance' => '1,142.18'],
        ['entity' => 'BELM', 'name' => 'Haven Belmont', 'debits' => '77,238.96', 'credits' => '63,121.57', 'balance' => '14,117.39'],
        ['entity' => 'CORO', 'name' => 'Haven Corona', 'debits' => '56,968.52', 'credits' => '45,583.50', 'balance' => '11,385.02'],
    ];
@endphp

@extends('layouts.payroll')

@section('content')
    <header class="mb-8">
        <h1 class="text-3xl font-bold text-[#07152f]">Journals & Export</h1>
        <p class="mt-2 text-base text-slate-500">Generate, approve, and export QuickBooks IIF files per entity</p>
    </header>

    <section class="mb-6 flex flex-col gap-5 rounded-lg border border-slate-200 bg-white p-7 shadow-sm lg:flex-row lg:items-center lg:justify-between">
        <div>
            <h2 class="text-lg font-bold text-[#07152f]">Haven Stores</h2>
            <p class="mt-2 text-sm font-bold">Journal Entries</p>
            <p class="mt-2 text-base text-slate-500">6/1/2026 - 6/30/2026</p>
        </div>
        <div class="flex flex-wrap gap-3">
            <select class="rounded-lg border border-slate-200 px-4 py-2.5 text-sm"><option>6/1/2026 - 6/30/2026 (APPROVED)</option></select>
            <button class="rounded-lg bg-[#583cea] px-5 py-2.5 text-sm font-bold text-white">Generate Journals</button>
            <button class="rounded-lg bg-[#0076a8] px-5 py-2.5 text-sm font-bold text-white">Download All IIF (15)</button>
        </div>
    </section>

    <section class="mb-6 rounded-lg border border-slate-200 bg-white p-5 shadow-sm">
        <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
            <div class="flex flex-wrap items-center gap-5">
                @foreach (['Open', 'Processing', 'Review', 'Approved', 'Exported'] as $step)
                    <div class="flex items-center gap-3">
                        <span class="flex h-7 w-7 items-center justify-center rounded-full {{ $step === 'Approved' ? 'bg-[#583cea] text-white ring-4 ring-violet-100' : ($step === 'Exported' ? 'bg-slate-50 text-slate-300 ring-2 ring-slate-200' : 'bg-emerald-600 text-white') }}">
                            @if ($step === 'Approved')
                                <span class="h-3 w-3 rounded-full bg-white/30"></span>
                            @else
                                {!! payroll_icon('check', 'h-4 w-4') !!}
                            @endif
                        </span>
                        <span class="text-xs font-semibold {{ $step === 'Approved' ? 'text-[#583cea]' : ($step === 'Exported' ? 'text-slate-500' : 'text-emerald-700') }}">{{ $step }}</span>
                    </div>
                    @if (!$loop->last)
                        <span class="hidden h-px w-10 bg-slate-200 lg:block"></span>
                    @endif
                @endforeach
            </div>
            <button class="rounded-lg border border-slate-200 bg-slate-50 px-5 py-2.5 text-sm font-bold">Mark as Exported</button>
        </div>
    </section>

    <section class="overflow-hidden rounded-lg border border-slate-200 bg-white shadow-sm">
        <div class="border-b border-slate-200 px-6 py-5">
            <h2 class="text-lg font-bold text-[#07152f]">Entries</h2>
        </div>
        <div class="flex border-b border-slate-200 bg-slate-50 px-6">
            <button class="border-b-2 border-[#583cea] px-4 py-4 text-sm font-bold text-[#583cea]">Journal Entries <span class="ml-2 rounded-full bg-violet-100 px-2 py-0.5 text-xs">18</span></button>
            <button class="px-4 py-4 text-sm font-bold text-slate-500">Invoice Entries</button>
        </div>
        <div class="overflow-x-auto p-5">
            <table class="w-full min-w-[900px] text-sm">
                <thead class="bg-slate-50 text-left text-xs uppercase tracking-wide text-slate-500">
                    <tr>
                        <th class="px-4 py-3 font-bold">Entity</th>
                        <th class="px-4 py-3 font-bold">Status</th>
                        <th class="px-4 py-3 text-right font-bold">Total Debits</th>
                        <th class="px-4 py-3 text-right font-bold">Total Credits</th>
                        <th class="px-4 py-3 text-right font-bold">Balance</th>
                        <th class="px-4 py-3 text-right font-bold">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @foreach ($entries as $entry)
                        <tr>
                            <td class="px-4 py-4">
                                <a class="font-bold text-[#583cea]" href="#">{{ $entry['entity'] }}</a>
                                <div class="mt-1 text-xs text-slate-500">{{ $entry['name'] }}</div>
                            </td>
                            <td class="px-4 py-4"><span class="rounded-md bg-emerald-50 px-3 py-1 text-xs font-bold text-emerald-700">APPROVED</span></td>
                            <td class="px-4 py-4 text-right">{{ $entry['debits'] }}</td>
                            <td class="px-4 py-4 text-right">{{ $entry['credits'] }}</td>
                            <td class="px-4 py-4 text-right text-orange-700">Off by {{ $entry['balance'] }}</td>
                            <td class="px-4 py-4">
                                <div class="flex justify-end gap-2">
                                    <button class="rounded-md border border-violet-200 bg-violet-50 px-3 py-1.5 text-xs font-bold text-[#583cea]">IIF Preview</button>
                                    <button class="rounded-md border border-slate-200 px-3 py-1.5 text-xs font-bold">Lines</button>
                                    <button class="rounded-md border border-blue-200 bg-blue-50 px-3 py-1.5 text-xs font-bold text-blue-700">IIF</button>
                                </div>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </section>
@endsection

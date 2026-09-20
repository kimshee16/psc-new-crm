@php
    $title = 'Summary';
    $activeLabel = 'Summary';
    $rows = [
        ['idx' => 1, 'account' => 'G&A: Payroll', 'd1' => '16,133.41', 'd2' => '63.78', 'd3' => '15,370.91', 'total' => '31,568.10'],
        ['idx' => 3, 'account' => 'G&A: Payroll Taxes - FED', 'd1' => '1,909.62', 'd2' => '4.87', 'd3' => '1,957.66', 'total' => '3,872.15'],
        ['idx' => 5, 'account' => 'Accrued Sick Pay', 'd1' => '0.00', 'd2' => '0.00', 'd3' => '1,084.45', 'total' => '1,084.45'],
        ['idx' => 6, 'account' => 'Accrued Vacation Pay', 'd1' => '0.00', 'd2' => '0.00', 'd3' => '237.00', 'total' => '237.00'],
        ['idx' => 8, 'account' => 'Kaya Tip Liability', 'd1' => '9,243.82', 'd2' => '0.00', 'd3' => '9,332.90', 'total' => '18,576.72'],
        ['idx' => 10, 'account' => 'G&A: Cell Phone Reimbursement', 'd1' => '0.00', 'd2' => '0.00', 'd3' => '30.00', 'total' => '30.00'],
        ['idx' => 12, 'account' => 'G&A: Health Insurance', 'd1' => '0.00', 'd2' => '0.00', 'd3' => '0.00', 'total' => '0.00', 'credit' => '839.98'],
    ];
@endphp

@extends('layouts.payroll')

@section('content')
    <header class="mb-8 flex items-start justify-between">
        <div>
            <h1 class="text-3xl font-bold text-[#07152f]">Summary</h1>
            <p class="mt-2 text-base text-slate-500">Detailed journal entry tables for individual operating locations</p>
        </div>
        <div class="flex gap-3">
            <button class="flex items-center gap-2 rounded-lg border border-slate-200 bg-white px-4 py-3 text-sm font-bold">{!! payroll_icon('building', 'h-4 w-4') !!} LOSA</button>
            <button class="rounded-lg border border-slate-200 bg-white px-4 py-3 text-sm font-bold text-[#583cea]">View Summary</button>
        </div>
    </header>

    <section class="mb-5 grid grid-cols-1 gap-6 rounded-lg border border-slate-200 bg-white p-7 shadow-sm lg:grid-cols-3">
        <div>
            <h2 class="text-lg font-bold text-[#07152f]">Haven Los Alamitos</h2>
            <p class="mt-1 text-sm text-slate-500">Haven Los Alamitos</p>
            <p class="mt-3 text-sm font-bold">Kaya Journal Entries</p>
            <p class="mt-2 text-base text-slate-500">6/30/2026</p>
        </div>
        <div>
            <p class="text-sm text-slate-500">Payroll End Date After Month End</p>
            <p class="mt-2 font-bold">Friday, May 1, 2026</p>
            <p class="mt-5 text-sm text-slate-500">Using this date for payroll accrual</p>
            <p class="mt-2 font-bold text-red-600">Saturday, June 27, 2026</p>
        </div>
        <div>
            <p class="text-sm text-slate-500">G&A: Payroll Gross</p>
            <p class="mt-2 font-bold">31,568.10</p>
            <p class="mt-5 text-sm text-slate-500">G&A: Payroll Taxes</p>
            <p class="mt-2 font-bold">3,872.15</p>
            <p class="mt-5 text-sm text-slate-500">Month End Accrued Payroll</p>
            <p class="mt-2 font-bold text-red-600">(35,440.25)</p>
        </div>
    </section>

    <section class="mb-4 flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
        <label class="relative block w-full max-w-sm">
            <span class="absolute left-4 top-1/2 -translate-y-1/2 text-slate-400">{!! payroll_icon('search', 'h-4 w-4') !!}</span>
            <input class="w-full rounded-lg border border-slate-200 bg-white py-2.5 pl-11 pr-4 text-sm" placeholder="Filter accounts...">
        </label>
        <div class="flex flex-wrap gap-3">
            <button class="flex items-center gap-2 rounded-lg border border-slate-200 bg-white px-4 py-2.5 text-sm">{!! payroll_icon('filter', 'h-4 w-4') !!} Month end 6/1/2026 - 6/30/2026</button>
            <button class="flex items-center gap-2 rounded-lg border border-slate-200 bg-white px-4 py-2.5 text-sm">{!! payroll_icon('filter', 'h-4 w-4') !!} Non-zero dates</button>
            <label class="flex items-center gap-2 text-sm text-slate-500"><input checked type="checkbox" class="rounded accent-[#583cea]"> Non-zero only</label>
        </div>
    </section>

    <section class="overflow-hidden rounded-lg border border-slate-200 bg-white shadow-sm">
        <div class="overflow-x-auto">
            <table class="w-full min-w-[1180px] text-sm">
                <thead class="text-slate-500">
                    <tr class="border-b border-slate-200 bg-slate-50">
                        <th rowspan="3" class="w-80 px-4 py-3 text-left align-bottom font-bold text-[#07152f]">Account</th>
                        <th colspan="2" class="border-x border-slate-200 px-4 py-3 text-center font-bold text-[#07152f]">Month 6</th>
                        <th colspan="2" class="border-x border-slate-200 px-4 py-3 text-center font-bold text-[#07152f]">Month 6</th>
                        <th colspan="2" class="border-x border-slate-200 px-4 py-3 text-center font-bold text-[#07152f]">Month 6</th>
                        <th colspan="2" class="bg-indigo-50 px-4 py-3 text-center font-bold text-[#07152f]">Totals</th>
                    </tr>
                    <tr class="border-b border-slate-200 bg-slate-50 text-xs">
                        <th colspan="2" class="border-x border-slate-200 px-4 py-3">Payroll #2</th>
                        <th colspan="2" class="border-x border-slate-200 px-4 py-3">Payroll #3</th>
                        <th colspan="2" class="border-x border-slate-200 px-4 py-3">Payroll #4</th>
                        <th colspan="2" class="bg-indigo-50 px-4 py-3">All Periods</th>
                    </tr>
                    <tr class="border-b border-slate-200 bg-slate-50 text-xs">
                        @foreach (range(1, 4) as $i)
                            <th class="border-l border-slate-200 px-4 py-3 text-right">Debit</th>
                            <th class="border-l border-slate-200 px-4 py-3 text-right">Credit</th>
                        @endforeach
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @foreach ($rows as $row)
                        <tr>
                            <td class="px-4 py-3"><span class="mr-2 rounded-full bg-slate-200 px-2 py-1 text-xs font-bold text-slate-500">{{ $row['idx'] }}</span>{{ $row['account'] }}</td>
                            <td class="px-4 py-3 text-right font-bold text-red-600">{{ $row['d1'] }}</td>
                            <td class="px-4 py-3 text-right text-slate-500">0.00</td>
                            <td class="px-4 py-3 text-right font-bold text-red-600">{{ $row['d2'] }}</td>
                            <td class="px-4 py-3 text-right text-slate-500">0.00</td>
                            <td class="px-4 py-3 text-right font-bold text-red-600">{{ $row['d3'] }}</td>
                            <td class="px-4 py-3 text-right text-slate-500">0.00</td>
                            <td class="px-4 py-3 text-right font-bold">{{ $row['total'] }}</td>
                            <td class="px-4 py-3 text-right font-bold">{{ $row['credit'] ?? '0.00' }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </section>
@endsection

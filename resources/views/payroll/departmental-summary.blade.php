@php
    $title = 'Departmental Summary';
    $activeLabel = 'Departmental Summary';
    $departments = ['PR' => '49.96%', 'TP' => '29.26%', 'JL' => '20.78%'];
    $rows = [
        ['account' => 'G&A: Payroll', 'pr' => '31,585.60', 'tp' => '18,501.72', 'jl' => '13,140.28', 'bant' => '63,227.60', 'check' => '0.00'],
        ['account' => 'G&A: Payroll Bonus', 'pr' => '0.00', 'tp' => '0.00', 'jl' => '0.00', 'bant' => '0.00', 'check' => '0.00'],
        ['account' => 'G&A: Payroll Taxes - FED', 'pr' => '2,145.28', 'tp' => '1,256.63', 'jl' => '892.48', 'bant' => '4,294.39', 'check' => '0.00'],
        ['account' => 'G&A: Payroll Taxes - CA', 'pr' => '78.68', 'tp' => '46.09', 'jl' => '32.73', 'bant' => '157.51', 'check' => '0.00'],
        ['account' => 'Marketing: Commission', 'pr' => '0.00', 'tp' => '0.00', 'jl' => '0.00', 'bant' => '0.00', 'check' => '0.00'],
        ['account' => 'G&A: Health Insurance', 'pr' => '949.35', 'tp' => '556.10', 'jl' => '394.95', 'bant' => '1,900.40', 'check' => '0.00'],
        ['account' => 'G&A: Cell Phone Reimbursement', 'pr' => '0.00', 'tp' => '0.00', 'jl' => '0.00', 'bant' => '0.00', 'check' => '0.00'],
        ['account' => 'Accrued Finished Goods Commission', 'pr' => '0.00', 'tp' => '0.00', 'jl' => '0.00', 'bant' => '0.00', 'check' => '0.00'],
        ['account' => 'Accrued Bulk Commission', 'pr' => '0.00', 'tp' => '0.00', 'jl' => '0.00', 'bant' => '0.00', 'check' => '0.00'],
        ['account' => 'Month End Payroll Accrual - Gross', 'pr' => '0.00', 'tp' => '0.00', 'jl' => '0.00', 'bant' => '0.00', 'check' => '0.00', 'red' => true],
    ];
@endphp

@extends('layouts.payroll')

@section('content')
    <header class="mb-8 flex items-start justify-between">
        <div>
            <h1 class="text-3xl font-bold text-[#07152f]">Departmental Summary</h1>
            <p class="mt-2 text-base text-slate-500">Payroll expense breakdown by department for invoicing and allocation</p>
        </div>
        <button class="flex items-center gap-2 rounded-lg border border-slate-200 bg-white px-4 py-3 text-sm font-bold">{!! payroll_icon('building', 'h-4 w-4') !!} BANT</button>
    </header>

    <section class="mb-6 rounded-lg border border-slate-200 bg-white p-7 shadow-sm">
        <h2 class="text-lg font-bold text-[#07152f]">BANT</h2>
        <p class="mt-2 text-sm font-bold">B AND T Solutions Invoicing Out</p>
        <p class="mt-2 text-base text-slate-500">6/1/2026 - 6/30/2026</p>
    </section>

    <section class="overflow-hidden rounded-lg border border-slate-200 bg-white shadow-sm">
        <div class="flex flex-col gap-4 border-b border-slate-200 px-6 py-5 lg:flex-row lg:items-center lg:justify-between">
            <h2 class="text-lg font-bold text-[#07152f]">Departmental Summary</h2>
            <div class="flex gap-3">
                <button class="flex items-center gap-2 rounded-lg border border-slate-200 px-4 py-2 text-sm">{!! payroll_icon('filter', 'h-4 w-4') !!} 6/1/2026 - 6/30/2026 · APPROVED</button>
                <button class="flex items-center gap-2 px-4 py-2 text-sm font-bold text-[#583cea]">{!! payroll_icon('download', 'h-4 w-4') !!} Export CSV</button>
            </div>
        </div>
        <div class="flex flex-wrap items-center justify-between gap-3 border-b border-slate-200 bg-slate-50 px-6 py-3 text-xs text-slate-500">
            <div class="flex flex-wrap items-center gap-2">
                <span class="font-bold uppercase tracking-wide">Departments</span>
                @foreach ($departments as $dept => $value)
                    <span class="rounded-full border border-blue-200 bg-blue-50 px-3 py-1 font-bold text-blue-700">{{ $dept }} <span class="font-medium text-slate-500">{{ $value }}</span></span>
                @endforeach
            </div>
            <div><em>Auto-allocated from payroll CSV (% = department G&A: Payroll ÷ total)</em> <span class="ml-2">Total: 100.00%</span></div>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full min-w-[1100px] text-sm">
                <thead>
                    <tr class="bg-emerald-50 text-[#07152f]">
                        <th rowspan="2" class="w-[420px] border-r border-emerald-100 px-4 py-5 text-left font-bold">Payroll Expenses</th>
                        <th colspan="3" class="border-r border-emerald-100 bg-emerald-100 px-4 py-3 text-center font-bold text-emerald-800">B AND T Solutions Invoicing Out</th>
                        <th rowspan="2" class="border-r border-emerald-100 px-4 py-3 text-center font-bold">BANT</th>
                        <th rowspan="2" class="px-4 py-3 text-center font-bold">Check</th>
                    </tr>
                    <tr class="border-b border-slate-200 bg-slate-50 text-xs text-slate-500">
                        @foreach ($departments as $dept => $value)
                            <th class="border-r border-slate-200 px-4 py-3 text-center"><span class="block font-bold text-[#07152f]">{{ $dept }}</span>{{ $value }}</th>
                        @endforeach
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @foreach ($rows as $row)
                        <tr>
                            <td class="border-r border-emerald-100 bg-emerald-50/60 px-4 py-3 {{ $row['red'] ?? false ? 'text-red-600' : '' }}">{{ $row['account'] }}</td>
                            <td class="border-r border-slate-100 px-4 py-3 text-right">{{ $row['pr'] }}</td>
                            <td class="border-r border-slate-100 px-4 py-3 text-right">{{ $row['tp'] }}</td>
                            <td class="border-r border-slate-100 px-4 py-3 text-right">{{ $row['jl'] }}</td>
                            <td class="border-r border-slate-100 px-4 py-3 text-right">{{ $row['bant'] }}</td>
                            <td class="px-4 py-3 text-right text-slate-500">{{ $row['check'] }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </section>
@endsection

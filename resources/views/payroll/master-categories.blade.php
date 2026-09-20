@php
    $title = 'Master Categories';
    $activeLabel = 'Master Categories';
    $tabs = [
        ['label' => 'Mappings', 'count' => 96, 'active' => true],
        ['label' => 'Payroll Periods', 'count' => 5],
        ['label' => 'Company Names', 'count' => 18],
        ['label' => 'QuickBooks Accounts', 'count' => 27],
        ['label' => 'Kaya Categories', 'count' => 101],
        ['label' => 'Accrual Settings', 'count' => 0],
        ['label' => 'Distributed Payroll Settings', 'count' => 11],
    ];
    $rows = [
        ['desc' => '401K Percentage EE Pretax', 'account' => 'Kaya Accrued 401k', 'cat' => '401K Percentage EE Pretax', 'dir' => 'CREDIT'],
        ['desc' => '401K Roth', 'account' => 'Kaya Accrued 401k', 'cat' => '401K Roth', 'dir' => 'CREDIT'],
        ['desc' => '401k Roth Percentage', 'account' => 'Kaya Accrued 401k', 'cat' => '401k Roth Percentage', 'dir' => 'CREDIT'],
        ['desc' => 'Advance', 'account' => 'Not Used (Duplicated amount)', 'cat' => 'Advance', 'dir' => 'DEBIT', 'muted' => true],
        ['desc' => 'Bereavement Paid', 'account' => 'G&A: Payroll', 'cat' => 'Bereavement Paid', 'dir' => 'DEBIT'],
        ['desc' => 'Bereavement Taken', 'account' => 'G&A: Payroll', 'cat' => 'Bereavement Taken', 'dir' => 'DEBIT'],
        ['desc' => 'Bereavement Unpaid Taken', 'account' => 'Not Used (Duplicated amount)', 'cat' => 'Bereavement Unpaid Taken', 'dir' => 'DEBIT', 'muted' => true],
        ['desc' => 'Bonus (Discretionary)', 'account' => 'G&A: Payroll Bonus', 'cat' => 'Bonus (Discretionary)', 'dir' => 'DEBIT'],
        ['desc' => 'Cell Phone Reimbursement', 'account' => 'G&A: Cell Phone Reimbursement', 'cat' => 'Cell Phone Reimbursement', 'dir' => 'DEBIT'],
    ];
@endphp

@extends('layouts.payroll')

@section('content')
    <header class="mb-8">
        <h1 class="text-3xl font-bold text-[#07152f]">Master Categories</h1>
        <p class="mt-2 text-base text-slate-500">Map Kaya Push payroll descriptions to QuickBooks accounts</p>
    </header>

    <section class="mb-5 rounded-lg border border-slate-200 bg-white px-4 py-3 shadow-sm">
        <div class="flex flex-wrap gap-2">
            @foreach ($tabs as $tab)
                <button class="rounded-lg border px-3 py-2 text-sm font-semibold {{ $tab['active'] ?? false ? 'border-[#8d73ff] bg-violet-50 text-[#583cea]' : 'border-slate-200 bg-slate-50 text-slate-500' }}">
                    {{ $tab['label'] }} <span class="ml-1 rounded-full bg-slate-200 px-2 py-0.5 text-xs">{{ $tab['count'] }}</span>
                </button>
            @endforeach
        </div>
    </section>

    <section class="rounded-lg border border-slate-200 bg-white shadow-sm">
        <div class="flex flex-col gap-4 border-b border-slate-200 p-5 lg:flex-row lg:items-center lg:justify-between">
            <label class="relative block w-full max-w-md">
                <span class="absolute left-4 top-1/2 -translate-y-1/2 text-slate-400">{!! payroll_icon('search', 'h-4 w-4') !!}</span>
                <input class="w-full rounded-lg border border-slate-200 py-2.5 pl-11 pr-4 text-sm outline-none" placeholder="Search Kaya Push descriptions...">
            </label>
            <div class="flex gap-2">
                <button class="flex items-center gap-2 rounded-lg border border-[#8d73ff] px-4 py-2 text-sm font-semibold text-[#583cea]">{!! payroll_icon('upload', 'h-4 w-4') !!} Import CSV</button>
                <button class="flex items-center gap-2 rounded-lg border border-slate-200 px-4 py-2 text-sm font-semibold text-slate-600">{!! payroll_icon('download', 'h-4 w-4') !!} Template</button>
                <button class="flex items-center gap-2 rounded-lg bg-[#583cea] px-4 py-2 text-sm font-bold text-white">{!! payroll_icon('plus', 'h-4 w-4') !!} Add Mapping</button>
            </div>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full min-w-[1000px] text-sm">
                <thead class="bg-slate-50 text-left text-xs text-slate-600">
                    <tr>
                        <th class="px-6 py-4 font-bold">Description Kaya Push</th>
                        <th class="px-6 py-4 font-bold">QB Account</th>
                        <th class="px-6 py-4 font-bold">Kaya Category</th>
                        <th class="px-6 py-4 font-bold">Direction</th>
                        <th class="px-6 py-4 text-right font-bold">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @foreach ($rows as $row)
                        <tr class="{{ $row['muted'] ?? false ? 'text-slate-500' : 'text-[#07152f]' }}">
                            <td class="px-6 py-4">{{ $row['desc'] }}</td>
                            <td class="px-6 py-4 {{ $row['muted'] ?? false ? 'italic' : '' }}">{{ $row['account'] }}</td>
                            <td class="px-6 py-4"><span class="rounded-md bg-slate-100 px-2 py-1 text-xs font-bold text-[#07152f]">{{ $row['cat'] }}</span></td>
                            <td class="px-6 py-4">{{ $row['dir'] }}</td>
                            <td class="px-6 py-4">
                                <div class="flex justify-end gap-5 text-[#583cea]">
                                    {!! payroll_icon('edit', 'h-4 w-4') !!}
                                    <span class="text-red-500">{!! payroll_icon('trash', 'h-4 w-4') !!}</span>
                                </div>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </section>
@endsection

@php
    $title = 'PSC CRM';
    $activeLabel = 'Home';
    $stats = [
        ['label' => 'Total Entities', 'value' => '20', 'icon' => 'building', 'color' => 'bg-blue-50 text-blue-600'],
        ['label' => 'Active Employees', 'value' => '0', 'icon' => 'chart', 'color' => 'bg-green-50 text-green-600'],
        ['label' => 'Monthly Payroll', 'value' => '-', 'icon' => 'dollar', 'color' => 'bg-violet-50 text-violet-600'],
        ['label' => 'Open Periods', 'value' => '1', 'icon' => 'calendar', 'color' => 'bg-orange-50 text-orange-600'],
    ];
    $actions = [
        ['route' => 'data-upload', 'label' => 'Upload Payroll Data', 'sub' => 'Import CSV or sync from external providers', 'icon' => 'upload', 'color' => 'bg-blue-50 text-blue-700'],
        ['route' => 'master-categories', 'label' => 'Master Categories', 'sub' => 'Configure mappings to QuickBooks accounts', 'icon' => 'settings', 'color' => 'bg-violet-50 text-violet-700'],
        ['route' => 'summary', 'label' => 'View Summary', 'sub' => 'Detailed journal entry tables for individual operating locations', 'icon' => 'building', 'color' => 'bg-emerald-50 text-emerald-700'],
        ['route' => 'project-status', 'label' => 'Run Calculations', 'sub' => 'Track sick time, vacation, WC analysis', 'icon' => 'calculator', 'color' => 'bg-orange-50 text-orange-700'],
        ['route' => 'project-status', 'label' => 'Advanced Analysis', 'sub' => 'Pivot tables and data exploration', 'icon' => 'chart', 'color' => 'bg-pink-50 text-pink-700'],
        ['route' => 'project-status', 'label' => 'Project Status', 'sub' => 'Architecture, layers, schema, and open items', 'icon' => 'file', 'color' => 'bg-indigo-50 text-indigo-700'],
    ];
@endphp

@extends('layouts.payroll')

@section('content')
    <header class="mb-7">
        <h1 class="text-3xl font-bold leading-tight text-[#07152f]">Welcome to PSC CRM</h1>
        <p class="mt-2 text-base text-slate-500">Manage payroll data, standardization, and analysis</p>
    </header>

    <section class="mb-9 grid grid-cols-1 gap-5 md:grid-cols-2 xl:grid-cols-4">
        @foreach ($stats as $stat)
            <div class="flex items-center justify-between rounded-lg border border-slate-200 bg-white p-6 shadow-sm">
                <div>
                    <div class="text-sm font-medium text-slate-500">{{ $stat['label'] }}</div>
                    <div class="mt-2 text-3xl font-bold text-[#07152f]">{{ $stat['value'] }}</div>
                </div>
                <div class="flex h-12 w-12 items-center justify-center rounded-xl {{ $stat['color'] }}">
                    {!! payroll_icon($stat['icon'], 'h-5 w-5') !!}
                </div>
            </div>
        @endforeach
    </section>

    <section class="mb-9">
        <h2 class="mb-5 text-lg font-bold text-[#07152f]">Quick Actions</h2>
        <div class="grid grid-cols-1 gap-4 lg:grid-cols-3">
            @foreach ($actions as $action)
                <a href="{{ route($action['route']) }}" class="flex items-center gap-4 rounded-lg p-5 transition hover:shadow-sm {{ $action['color'] }}">
                    <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-current/10">
                        {!! payroll_icon($action['icon'], 'h-5 w-5') !!}
                    </span>
                    <span>
                        <span class="block text-sm font-bold">{{ $action['label'] }}</span>
                        <span class="mt-1 block text-sm opacity-70">{{ $action['sub'] }}</span>
                    </span>
                </a>
            @endforeach
        </div>
    </section>

    <section class="rounded-lg border border-slate-200 bg-white shadow-sm">
        <div class="flex items-center justify-between px-7 py-5">
            <div>
                <h2 class="text-lg font-bold text-[#07152f]">Recent Activity</h2>
                <p class="mt-1 text-sm text-slate-500">10 events</p>
            </div>
            {!! payroll_icon('arrow-right', 'h-5 w-5 text-slate-500') !!}
        </div>
    </section>
@endsection

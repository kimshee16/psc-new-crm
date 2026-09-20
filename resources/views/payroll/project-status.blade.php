@php
    $title = 'Project Status';
    $activeLabel = 'Distributed Payroll Summary';
@endphp

@extends('layouts.payroll')

@section('content')
    <header class="mb-8">
        <h1 class="text-3xl font-bold text-[#07152f]">Project Status</h1>
        <p class="mt-2 text-base text-slate-500">Architecture, layers, schema, and open items</p>
    </header>

    <section class="grid grid-cols-1 gap-5 lg:grid-cols-3">
        @foreach ([
            ['label' => 'UI Layer', 'value' => 'Laravel Blade views', 'color' => 'bg-blue-50 text-blue-700'],
            ['label' => 'Payroll Period', 'value' => 'Jun 2026 approved', 'color' => 'bg-emerald-50 text-emerald-700'],
            ['label' => 'Open Items', 'value' => '6 modules pending data wiring', 'color' => 'bg-amber-50 text-amber-700'],
        ] as $card)
            <div class="rounded-lg border border-slate-200 bg-white p-6 shadow-sm">
                <div class="text-sm font-semibold text-slate-500">{{ $card['label'] }}</div>
                <div class="mt-3 rounded-lg px-4 py-3 text-sm font-bold {{ $card['color'] }}">{{ $card['value'] }}</div>
            </div>
        @endforeach
    </section>
@endsection

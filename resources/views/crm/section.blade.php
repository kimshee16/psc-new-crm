@extends('layouts.payroll')

@section('content')
    <header class="mb-7 flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
        <div>
            <h1 class="text-[26px] font-bold leading-tight text-[#101820]">{{ $heading }}</h1>
            <p class="mt-2 text-sm text-slate-600">Progress Study CRM workspace.</p>
        </div>
        <a href="{{ route('home') }}" class="rounded-md border border-slate-300 bg-white px-4 py-2 text-sm font-medium text-slate-700 shadow-sm transition hover:border-[#1f7890] hover:text-[#1f7890]">Back to Home</a>
    </header>

    <section class="grid grid-cols-1 gap-5 xl:grid-cols-[1.2fr_0.8fr]">
        <article class="rounded-lg border border-slate-200 bg-white p-6 shadow-sm">
            <div class="text-sm font-semibold text-slate-500">{{ $heading }}</div>
            <div class="mt-2 text-xl font-bold text-[#101820]">Ready for setup</div>
            <p class="mt-3 max-w-2xl text-sm leading-6 text-slate-600">This module is wired into the official navigation and ready for the next implementation pass.</p>
        </article>
        <article class="rounded-lg border border-slate-200 bg-white p-6 shadow-sm">
            <div class="text-sm font-semibold text-slate-500">Office</div>
            <div class="mt-2 text-xl font-bold text-[#101820]">Melbourne Office</div>
            <p class="mt-3 text-sm leading-6 text-slate-600">Counsellor view</p>
        </article>
    </section>
@endsection

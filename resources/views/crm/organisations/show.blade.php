@extends('layouts.payroll')

@section('content')
    <header class="mb-7">
        <a href="{{ route('applications') }}" class="text-sm font-medium text-[#1f7890]">&larr; Applications</a>
        <h1 class="mt-3 text-[26px] font-bold">{{ $organisation->name }}</h1>
        <p class="mt-2 text-sm text-slate-600">Organisation record &middot; {{ $organisation->id }}</p>
    </header>
    <section class="rounded-lg border border-slate-200 bg-white p-6 shadow-sm">
        <h2 class="mb-4 text-lg font-bold">Applications assigned to you</h2>
        <ul class="divide-y divide-slate-100">
            @foreach ($applications as $application)
                <li class="py-3"><a href="{{ route('applications.show', $application->id) }}" class="font-medium text-[#1f7890]">Application {{ $application->id }} &middot; {{ $application->type_label }}</a><span class="ml-3 text-sm text-slate-500">{{ $application->status }}</span></li>
            @endforeach
        </ul>
    </section>
@endsection

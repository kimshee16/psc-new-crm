@extends('layouts.payroll')

@section('content')
    <a href="{{ route('holidays') }}" class="text-sm font-semibold text-[#1f7890] hover:underline">&larr; Holidays</a>
    <h1 class="mb-6 mt-3 text-2xl font-bold">{{ $holiday->exists ? 'Edit leave' : 'Record leave' }}</h1>

    <section class="max-w-2xl rounded-lg border border-slate-200 bg-white p-6 shadow-sm">
        <p class="mb-5 text-sm text-slate-600">You will be excluded from round-robin lead allocation from the start date through the end date, inclusive ({{ config('app.timezone') }}). Allocation resumes automatically after your leave ends.</p>
        @if ($errors->any())
            <div role="alert" class="mb-5 rounded-md border border-red-200 bg-red-50 p-4 text-sm text-red-800">
                <p class="font-semibold">The leave period was not saved.</p>
                <ul class="mt-2 list-disc pl-5">
                    @foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach
                </ul>
            </div>
        @endif
        <form method="POST" action="{{ $holiday->exists ? route('holidays.update', $holiday) : route('holidays.store') }}">
            @csrf
            @if ($holiday->exists) @method('PATCH') @endif
            <div class="grid gap-5 sm:grid-cols-2">
                <label class="block">
                    <span class="text-sm font-semibold text-slate-700">Start date</span>
                    <input type="date" name="starts_on" value="{{ old('starts_on', $holiday->starts_on?->format('Y-m-d')) }}" required @error('starts_on') aria-invalid="true" @enderror class="mt-1 block w-full rounded-md border border-slate-300 px-3 py-2 text-sm focus:border-[#1f7890] focus:outline-none focus:ring-2 focus:ring-[#1f7890]/20">
                </label>
                <label class="block">
                    <span class="text-sm font-semibold text-slate-700">End date</span>
                    <input type="date" name="ends_on" value="{{ old('ends_on', $holiday->ends_on?->format('Y-m-d')) }}" required @error('ends_on') aria-invalid="true" @enderror class="mt-1 block w-full rounded-md border border-slate-300 px-3 py-2 text-sm focus:border-[#1f7890] focus:outline-none focus:ring-2 focus:ring-[#1f7890]/20">
                </label>
            </div>
            <label class="mt-5 block">
                <span class="text-sm font-semibold text-slate-700">Reason <span class="font-normal text-slate-500">(optional)</span></span>
                <input type="text" name="reason" value="{{ old('reason', $holiday->reason) }}" maxlength="160" @error('reason') aria-invalid="true" @enderror class="mt-1 block w-full rounded-md border border-slate-300 px-3 py-2 text-sm focus:border-[#1f7890] focus:outline-none focus:ring-2 focus:ring-[#1f7890]/20">
                <span class="mt-1 block text-xs text-slate-500">Visible to managers who can view leave for your office.</span>
            </label>
            <div class="mt-6 flex items-center gap-4">
                <button type="submit" class="rounded-md bg-[#123348] px-4 py-2.5 text-sm font-semibold text-white hover:bg-[#1f7890]">Save leave</button>
                <a href="{{ route('holidays') }}" class="text-sm font-semibold text-slate-600 hover:underline">Back to holidays</a>
            </div>
        </form>
    </section>
@endsection

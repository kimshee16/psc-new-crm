@extends('layouts.payroll')

@section('content')
    <header class="mb-6">
        <a href="{{ route('applications.show', $application) }}#linked-items" class="text-sm font-semibold text-[#1f7890]">&larr; Application {{ $application->id }}</a>
        <h1 class="mt-3 text-[26px] font-bold">Link/Unlink items</h1>
        <p class="mt-2 text-sm text-slate-600">Application #{{ $application->id }} &middot; {{ $application->owner_name }} &middot; {{ $application->type_label }}</p>
        <p class="mt-2 text-sm text-slate-600">Link applications to show them on both records. Only applications you can access are shown. Unlinking keeps both application records.</p>
    </header>
    @if (session('success'))
        <p role="status" class="mb-5 rounded-md border border-emerald-200 bg-emerald-50 p-3 text-sm text-emerald-900">{{ session('success') }}</p>
    @endif
    @if ($errors->any())
        <div role="alert" class="mb-5 rounded-md border border-red-200 bg-red-50 p-3 text-sm text-red-800">
            <ul class="list-disc pl-5">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
        </div>
    @endif
    <section class="mb-6 rounded-lg border border-slate-200 bg-white p-5 shadow-sm" aria-labelledby="current-links-heading">
        <h2 id="current-links-heading" class="mb-4 text-lg font-bold">Linked Items</h2>
        @include('crm.applications.link-choices', ['items' => $linkedItems, 'action' => 'unlink'])
    </section>
    <section class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm" aria-labelledby="available-links-heading">
        <h2 id="available-links-heading" class="text-lg font-bold">Available applications</h2>
        <form method="GET" action="{{ route('applications.links.edit', $application) }}" class="my-4 flex flex-wrap items-end gap-3">
            <label class="flex-1 text-sm font-semibold" for="linked-items-search">Search by ID, description, client, type or status
                <input id="linked-items-search" type="search" name="search" value="{{ $search }}" maxlength="255" class="mt-2 block w-full rounded-md border border-slate-300 px-3 py-2 font-normal">
            </label>
            <button type="submit" class="rounded-md bg-[#1f7890] px-4 py-2 text-sm font-semibold text-white">Search</button>
            <a href="{{ route('applications.links.edit', $application) }}" class="px-2 py-2 text-sm text-[#1f7890] underline">Clear</a>
        </form>
        @include('crm.applications.link-choices', ['items' => $candidates, 'action' => 'link'])
        <div class="mt-4">{{ $candidates->links() }}</div>
    </section>
@endsection

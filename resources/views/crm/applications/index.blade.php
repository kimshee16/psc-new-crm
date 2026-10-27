@extends('layouts.payroll')

@section('content')
    <style>
        .applications-table { width: 100%; border-collapse: collapse; }
        .applications-table th { min-width: 150px; padding: 14px 12px; vertical-align: top; }
        .applications-table th:first-child { min-width: 95px; }
        .applications-table th:nth-child(4) { min-width: 250px; }
        .applications-table th:nth-child(6), .applications-table th:last-child { min-width: 90px; }
        .applications-table td { padding: 16px 12px; border-top: 1px solid #e2e8f0; }
        .applications-table tbody tr:nth-child(even) { background: #f8fafc; }
        .applications-table tbody tr[data-tone="red"] { background: #fff1f2; }
        .applications-table tbody tr[data-tone="red"]:nth-child(even) { background: #ffe4e6; }
        .applications-table tbody tr[data-tone="amber"] { background: #fffbeb; }
        .applications-table tbody tr[data-tone="amber"]:nth-child(even) { background: #fef3c7; }
        .applications-table tr[data-tone="red"] td:first-child { box-shadow: inset 3px 0 #e11d48; }
        .applications-table tr[data-tone="amber"] td:first-child { box-shadow: inset 3px 0 #d97706; }
        .application-filter { display: block; width: 100%; min-height: 36px; margin-top: 10px; border: 1px solid #cbd5e1; border-radius: 5px; padding: 6px 8px; background: white; color: #334155; font-size: 12px; font-weight: 400; text-transform: none; letter-spacing: normal; }
        .application-filter:focus-visible, .application-sort:focus-visible { outline: 2px solid #1f7890; outline-offset: 2px; }
        .application-sort { display: inline-flex; align-items: center; gap: 6px; min-height: 30px; text-align: left; }
        .application-sort:hover { color: #1f7890; }
    </style>

    <form id="applications-filters" method="GET" action="{{ route('applications') }}">
        <input type="hidden" name="sort" value="{{ $filters['sort'] ?? 'id' }}">
        <input type="hidden" name="direction" value="{{ $filters['direction'] ?? 'desc' }}">

        <header class="mb-7 flex flex-wrap items-start justify-between gap-4">
            <div>
                <h1 class="text-[26px] font-bold leading-tight text-[#101820]">Applications</h1>
                <p class="mt-2 text-sm text-slate-600">Active applications assigned to you.</p>
            </div>
            <div class="flex flex-wrap gap-2">
                <button type="submit" class="inline-flex min-h-10 items-center gap-2 rounded-md bg-[#1f7890] px-4 py-2 text-sm font-bold text-white hover:bg-[#17657a]">{!! payroll_icon('filter') !!} Apply filters</button>
                <a href="{{ route('applications') }}" class="inline-flex min-h-10 items-center gap-2 rounded-md border border-slate-300 bg-white px-4 py-2 text-sm font-medium text-slate-700 hover:border-[#1f7890]">{!! payroll_icon('x') !!} Clear filters</a>
                <button type="submit" formaction="{{ route('applications.export') }}" class="inline-flex min-h-10 items-center gap-2 rounded-md border border-slate-300 bg-white px-4 py-2 text-sm font-medium text-slate-700 hover:border-[#1f7890]">{!! payroll_icon('download') !!} Download CSV</button>
            </div>
        </header>

        @if ($errors->any())
            <div role="alert" class="mb-4 rounded-md border border-red-200 bg-red-50 p-4 text-sm text-red-800">
                @foreach ($errors->all() as $error)<p>{{ $error }}</p>@endforeach
            </div>
        @endif

        <section class="rounded-lg border border-slate-200 bg-white shadow-sm" aria-label="Applications list">
            <div class="flex flex-wrap items-center justify-between gap-3 border-b border-slate-200 px-5 py-4">
                <p class="text-sm text-slate-600" role="status">
                    Showing {{ $applications->firstItem() ?? 0 }}–{{ $applications->lastItem() ?? 0 }} of {{ $applications->total() }} applications
                </p>
                <span class="text-xs text-slate-500">Finalised and withdrawn applications are excluded</span>
            </div>
            <div class="overflow-x-auto" tabindex="0" role="region" aria-label="Applications table; scroll horizontally for all columns">
                <table class="applications-table text-left text-sm">
                    <caption class="sr-only">Your active applications. Filter each column and use its heading to change the sort order.</caption>
                    <thead class="bg-slate-50 text-xs text-slate-600">
                        <tr>
                            @foreach ($columns as $column => $label)
                                @php
                                    $isSorted = ($filters['sort'] ?? 'id') === $column;
                                    $direction = $filters['direction'] ?? 'desc';
                                    $nextDirection = $isSorted && $direction === 'asc' ? 'desc' : 'asc';
                                    $sortLabel = in_array($column, ['id', 'priority']) ? ($nextDirection === 'asc' ? 'lowest to highest' : 'highest to lowest') : ($column === 'critical_date' ? ($nextDirection === 'asc' ? 'earliest to latest' : 'latest to earliest') : ($nextDirection === 'asc' ? 'A to Z' : 'Z to A'));
                                @endphp
                                <th scope="col" aria-sort="{{ $isSorted ? ($direction === 'asc' ? 'ascending' : 'descending') : 'none' }}">
                                    <a class="application-sort font-bold" href="{{ route('applications', array_merge(request()->except('page'), ['sort' => $column, 'direction' => $nextDirection])) }}" data-sort="{{ $column }}" data-direction="{{ $nextDirection }}" aria-label="Sort {{ $label }} {{ $sortLabel }}">
                                        {{ $label }} <span aria-hidden="true">{{ $isSorted ? ($direction === 'asc' ? '↑' : '↓') : '↕' }}</span>
                                    </a>
                                    @if ($column === 'critical_date')
                                        <label class="sr-only" for="date-from">Critical date from</label>
                                        <input id="date-from" type="date" name="date_from" value="{{ $filters['date_from'] ?? '' }}" class="application-filter" aria-label="Critical date from">
                                        <label class="sr-only" for="date-to">Critical date to</label>
                                        <input id="date-to" type="date" name="date_to" value="{{ $filters['date_to'] ?? '' }}" class="application-filter" aria-label="Critical date to">
                                    @elseif ($column === 'priority')
                                        <details class="mt-2 rounded-md border border-slate-300 bg-white p-2 font-normal">
                                            <summary class="cursor-pointer whitespace-nowrap" aria-label="Filter priorities">{{ empty($filters['priority']) ? 'All' : implode(', ', $filters['priority']) }}</summary>
                                            <div class="mt-2 space-y-2">
                                                @foreach (['H' => 'High', 'M' => 'Medium', 'L' => 'Low'] as $value => $priorityLabel)
                                                    <label class="flex cursor-pointer items-center gap-2"><input type="checkbox" name="priority[]" value="{{ $value }}" @checked(in_array($value, $filters['priority'] ?? []))> {{ $priorityLabel }}</label>
                                                @endforeach
                                            </div>
                                        </details>
                                    @else
                                        <label class="sr-only" for="filter-{{ $column }}">Search {{ $label }}</label>
                                        <input id="filter-{{ $column }}" type="search" name="{{ $column }}" value="{{ $filters[$column] ?? '' }}" placeholder="Search {{ strtolower($label) }}" class="application-filter" maxlength="255">
                                    @endif
                                </th>
                            @endforeach
                            <th scope="col"><span class="inline-flex min-h-[30px] items-center">Action</span></th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($applications as $application)
                            @php $urgency = $dateFormatting->forDate($application->critical_date, $application->category); @endphp
                            <tr data-tone="{{ $urgency['tone'] }}">
                                @foreach ($columns as $column => $label)
                                    <td>
                                        @if ($column === 'critical_date')
                                            <span class="whitespace-nowrap font-medium">{{ $application->critical_date ? \Illuminate\Support\Carbon::parse($application->critical_date)->format('d/m/Y') : '—' }}</span>
                                            <span class="mt-1 block text-xs {{ $urgency['tone'] === 'red' ? 'font-semibold text-red-700' : 'text-slate-500' }}">{{ $urgency['label'] }}</span>
                                        @elseif ($column === 'priority')
                                            <span title="{{ ['H' => 'High', 'M' => 'Medium', 'L' => 'Low'][$application->priority] }}" class="inline-flex h-7 w-7 items-center justify-center rounded-md text-xs font-bold {{ $application->priority === 'H' ? 'bg-red-100 text-red-800' : ($application->priority === 'M' ? 'bg-amber-100 text-amber-800' : 'bg-slate-100 text-slate-700') }}">{{ $application->priority }}</span>
                                        @elseif ($column === 'status')
                                            <span class="inline-block rounded-full bg-white/80 px-2.5 py-1 text-xs font-medium ring-1 ring-slate-200">{{ $application->status }}</span>
                                        @else
                                            <span class="{{ in_array($column, ['id', 'client_name']) ? 'font-semibold text-[#101820]' : 'text-slate-700' }}">{{ $application->$column !== '' ? $application->$column : '—' }}</span>
                                        @endif
                                    </td>
                                @endforeach
                                <td>
                                    <a href="{{ route('applications.show', $application->id) }}" target="_blank" rel="noopener noreferrer" aria-label="Open application {{ $application->id }} in a new tab" title="Open application in a new tab" class="inline-flex h-9 w-9 items-center justify-center rounded-md border border-slate-300 bg-white text-[#1f7890] hover:bg-slate-50">
                                        <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="M14 3h7v7M21 3 10 14M10 3H5a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-5"/></svg>
                                    </a>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="11" class="h-40 text-center">
                                <p class="font-semibold text-slate-700">No applications found</p>
                                <p class="mt-2 text-sm text-slate-500">No active applications match your assignments and filters.</p>
                            </td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="flex flex-wrap items-center justify-between gap-4 border-t border-slate-200 px-5 py-4">
                <label class="flex items-center gap-2 text-sm text-slate-600">Records per page
                    <select name="per_page" id="applications-per-page" class="rounded-md border border-slate-300 bg-white px-3 py-2">
                        @foreach ([10, 20, 50] as $size)<option value="{{ $size }}" @selected(($filters['per_page'] ?? 10) == $size)>{{ $size }}</option>@endforeach
                    </select>
                </label>
                <div class="min-w-0 flex-1">{{ $applications->onEachSide(1)->links() }}</div>
            </div>
        </section>
    </form>
    <script>
        (() => {
            const form = document.getElementById('applications-filters');
            form.querySelectorAll('[data-sort]').forEach(link => link.addEventListener('click', event => {
                if (event.ctrlKey || event.metaKey || event.shiftKey || event.altKey) return;
                event.preventDefault();
                form.elements.namedItem('sort').value = link.dataset.sort;
                form.elements.namedItem('direction').value = link.dataset.direction;
                form.requestSubmit();
            }));
            document.getElementById('applications-per-page').addEventListener('change', () => form.requestSubmit());
        })();
    </script>
@endsection

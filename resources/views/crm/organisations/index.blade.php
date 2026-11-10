@extends('layouts.payroll')

@section('content')
    <style>
        .organisations-table { width: 100%; border-collapse: collapse; }
        .organisations-table th { min-width: 160px; padding: 14px 16px; vertical-align: top; }
        .organisations-table th:nth-child(2), .organisations-table th:nth-child(3) { min-width: 220px; }
        .organisations-table th:last-child { min-width: 80px; }
        .organisations-table td { padding: 16px; border-top: 1px solid #e2e8f0; }
        .organisations-table tbody tr:nth-child(even) { background: #f8fafc; }
        .organisations-table tbody tr:hover { background: #f0f9fc; }
        .organisation-filter { display: block; width: 100%; min-height: 36px; margin-top: 10px; border: 1px solid #cbd5e1; border-radius: 5px; padding: 6px 8px; background: white; color: #334155; font-size: 12px; font-weight: 400; }
        .organisation-sort { display: inline-flex; align-items: center; gap: 6px; min-height: 30px; text-align: left; }
        .organisation-sort:hover { color: #1f7890; }
        .organisation-filter:focus-visible, .organisation-sort:focus-visible, .organisation-action:focus-visible { outline: 2px solid #1f7890; outline-offset: 2px; }
    </style>

    <form id="organisations-filters" method="GET" action="{{ route('organisations') }}">
        <input type="hidden" name="sort" value="{{ $filters['sort'] ?? 'trading_name' }}">
        <input type="hidden" name="direction" value="{{ $filters['direction'] ?? 'asc' }}">
        <header class="mb-7 flex flex-wrap items-start justify-between gap-4">
            <div>
                <h1 class="text-[26px] font-bold leading-tight text-[#101820]">Organisations</h1>
                <p class="mt-2 text-sm text-slate-600">Find organisations you created or have assigned applications for.</p>
            </div>
            <div class="flex flex-wrap gap-2">
                <a href="{{ route('organisations.search') }}" class="inline-flex min-h-10 items-center gap-2 rounded-md border border-slate-300 bg-white px-4 py-2 text-sm font-medium text-slate-700">{!! payroll_icon('search', 'h-4 w-4') !!} Organisation search</a>
                <button type="submit" class="inline-flex min-h-10 items-center gap-2 rounded-md bg-[#1f7890] px-4 py-2 text-sm font-bold text-white hover:bg-[#17657a]">{!! payroll_icon('filter') !!} Apply filters</button>
                <a href="{{ route('organisations') }}" class="inline-flex min-h-10 items-center gap-2 rounded-md border border-slate-300 bg-white px-4 py-2 text-sm font-medium text-slate-700">{!! payroll_icon('x') !!} Clear filters</a>
                <button type="submit" formaction="{{ route('organisations.export') }}" class="inline-flex min-h-10 items-center gap-2 rounded-md border border-slate-300 bg-white px-4 py-2 text-sm font-medium text-slate-700">{!! payroll_icon('download') !!} Download CSV</button>
                <a href="{{ route('organisations.create') }}" class="inline-flex min-h-10 items-center gap-2 rounded-md border border-slate-300 bg-white px-4 py-2 text-sm font-semibold text-[#1f7890]">{!! payroll_icon('plus') !!} Create organisation</a>
            </div>
        </header>
        @include('crm.organisations.errors')

        <section class="rounded-lg border border-slate-200 bg-white shadow-sm" aria-label="Organisations list">
            <div class="flex flex-wrap items-end justify-between gap-4 border-b border-slate-200 px-5 py-4">
                <label for="organisation-search" class="text-sm font-semibold text-slate-700">Search organisations
                    <input id="organisation-search" type="search" name="search" value="{{ $filters['search'] ?? '' }}" placeholder="Organisation or contact details" aria-describedby="organisation-search-help" maxlength="255" class="mt-2 block w-72 max-w-full rounded-md border border-slate-300 px-3 py-2 font-normal">
                    <span id="organisation-search-help" class="mt-1 block text-xs font-normal text-slate-500">Name, ABN, ID, contact name, mobile or email.</span>
                </label>
                <p class="text-sm text-slate-600" role="status">Showing {{ $organisations->firstItem() ?? 0 }}&ndash;{{ $organisations->lastItem() ?? 0 }} of {{ $organisations->total() }} organisations</p>
            </div>
            <div class="overflow-x-auto" tabindex="0" role="region" aria-label="Organisations table; scroll horizontally for all columns">
                <table class="organisations-table text-left text-sm">
                    <caption class="sr-only">Organisations. Filter each column and use its heading to change the sort order.</caption>
                    <thead class="bg-slate-50 text-xs text-slate-600">
                        <tr>
                            @foreach ($columns as $column => $label)
                                @php
                                    $isSorted = ($filters['sort'] ?? 'trading_name') === $column;
                                    $direction = $filters['direction'] ?? 'asc';
                                    $nextDirection = $isSorted && $direction === 'asc' ? 'desc' : 'asc';
                                    $sortLabel = match ($column) {
                                        'id' => $nextDirection === 'asc' ? 'lowest to highest' : 'highest to lowest',
                                        'renew' => $nextDirection === 'asc' ? 'no flag first' : 'flag first',
                                        default => $nextDirection === 'asc' ? 'A to Z' : 'Z to A',
                                    };
                                @endphp
                                <th scope="col" aria-sort="{{ $isSorted ? ($direction === 'asc' ? 'ascending' : 'descending') : 'none' }}">
                                    <a class="organisation-sort font-bold" href="{{ route('organisations', array_merge(request()->except('page'), ['sort' => $column, 'direction' => $nextDirection])) }}" data-sort="{{ $column }}" data-direction="{{ $nextDirection }}" aria-label="Sort {{ $label }} {{ $sortLabel }}">
                                        {{ $label }} <span aria-hidden="true">{{ $isSorted ? ($direction === 'asc' ? '↑' : '↓') : '↕' }}</span>
                                    </a>
                                    @if (in_array($column, ['sbs_held', 'renew']))
                                        <label class="sr-only" for="filter-{{ $column }}">Filter {{ $label }}</label>
                                        <select id="filter-{{ $column }}" name="{{ $column }}" class="organisation-filter">
                                            <option value="">All</option>
                                            @foreach (($column === 'sbs_held' ? ['Y' => 'Y', 'N' => 'N'] : ['flag' => 'Flag', 'no_flag' => 'No flag']) as $value => $option)
                                                <option value="{{ $value }}" @selected(($filters[$column] ?? '') === $value)>{{ $option }}</option>
                                            @endforeach
                                        </select>
                                    @else
                                        <label class="sr-only" for="filter-{{ $column }}">Search {{ $label }}</label>
                                        <input id="filter-{{ $column }}" type="search" name="{{ $column }}" value="{{ $filters[$column] ?? '' }}" placeholder="Search {{ strtolower($label) }}" maxlength="255" class="organisation-filter">
                                    @endif
                                </th>
                            @endforeach
                            <th scope="col">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($organisations as $organisation)
                            <tr>
                                <td><a href="{{ route('organisations.show', $organisation) }}" class="whitespace-nowrap font-semibold text-[#1f7890] underline underline-offset-2">{{ $organisation->reference }}</a></td>
                                <td class="break-words text-slate-700">{{ $organisation->trading_name ?: '—' }}</td>
                                <td class="break-words font-medium text-[#101820]">{{ $organisation->name }}</td>
                                <td><span class="inline-flex rounded-full px-2.5 py-1 text-xs font-semibold {{ $organisation->sbs_held ? 'bg-emerald-50 text-emerald-800' : 'bg-slate-100 text-slate-600' }}">{{ $organisation->sbs_held ? 'Y' : 'N' }}</span></td>
                                <td>
                                    @if ($organisation->renew)
                                        <span class="inline-flex items-center gap-1.5 whitespace-nowrap rounded-full bg-amber-50 px-2.5 py-1 text-xs font-semibold text-amber-800" title="Active standard SBS expires within 4 months, or accredited SBS within 2 months">{!! payroll_icon('flag') !!} Renew</span>
                                    @else
                                        <span class="text-slate-400" aria-label="No renewal flag">—</span>
                                    @endif
                                </td>
                                <td><a href="{{ route('organisations.show', $organisation) }}" class="organisation-action inline-flex h-9 w-9 items-center justify-center rounded-md border border-slate-200 text-[#1f7890] hover:bg-slate-100" aria-label="Open organisation {{ $organisation->reference }}" title="Open organisation">{!! payroll_icon('eye') !!}</a></td>
                            </tr>
                        @empty
                            <tr><td colspan="6" class="h-36 text-center text-slate-500">No organisations found.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <p class="border-t border-slate-200 px-5 py-3 text-xs text-slate-500">Renewal flags show active standard SBS expiring within 4 months and active accredited SBS expiring within 2 months.</p>
            <div class="flex flex-wrap items-center justify-between gap-4 border-t border-slate-200 px-5 py-4">
                <label class="flex items-center gap-2 text-sm text-slate-600">Records per page
                    <select name="per_page" id="organisations-per-page" class="rounded-md border border-slate-300 bg-white px-3 py-2">
                        @foreach ([10, 20, 50] as $size)<option value="{{ $size }}" @selected(($filters['per_page'] ?? 10) == $size)>{{ $size }}</option>@endforeach
                    </select>
                </label>
                <div class="min-w-0 flex-1">{{ $organisations->onEachSide(1)->links() }}</div>
            </div>
        </section>
    </form>
    <script>
        (() => {
            const form = document.getElementById('organisations-filters');
            form.querySelectorAll('[data-sort]').forEach(link => link.addEventListener('click', event => {
                if (event.ctrlKey || event.metaKey || event.shiftKey || event.altKey) return;
                event.preventDefault();
                form.elements.namedItem('sort').value = link.dataset.sort;
                form.elements.namedItem('direction').value = link.dataset.direction;
                form.requestSubmit();
            }));
            document.getElementById('organisations-per-page').addEventListener('change', () => form.requestSubmit());
        })();
    </script>
@endsection

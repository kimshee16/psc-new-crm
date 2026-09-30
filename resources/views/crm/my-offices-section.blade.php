@extends('layouts.payroll')

@section('content')
    <header class="mb-7 flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
        <div>
            <div class="text-xs font-bold uppercase tracking-[0.12em] text-[#1f7890]">My Offices</div>
            <h1 class="mt-2 text-[26px] font-bold leading-tight text-[#101820]">{{ $heading }}</h1>
            <p class="mt-2 max-w-3xl text-sm leading-6 text-slate-600">
                Office-scoped {{ strtolower($heading) }} for assigned {{ $userAccountType }} users.
            </p>
        </div>
        <div class="rounded-md border border-slate-200 bg-white px-4 py-3 text-right shadow-sm">
            <div class="text-xs text-slate-500">Assigned offices</div>
            <div class="mt-1 text-xl font-bold text-[#101820]">{{ $totalAssignedOffices }}</div>
        </div>
    </header>

    <section class="mb-5 grid grid-cols-1 gap-5 xl:grid-cols-[360px_1fr]">
        <form method="GET" action="{{ route($config['route']) }}" class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm">
            <label for="offices" class="text-sm font-bold text-[#101820]">Select offices to view</label>
            <select
                id="offices"
                name="offices[]"
                multiple
                size="{{ min(max(count($availableOffices), 4), 8) }}"
                class="mt-3 min-h-40 w-full rounded-md border border-slate-300 bg-slate-50 px-3 py-2 text-sm text-slate-700 outline-none transition focus:border-[#1f7890] focus:ring-2 focus:ring-[#1f7890]/15"
            >
                @forelse ($availableOffices as $code => $label)
                    <option value="{{ $code }}" @selected(in_array($code, $selectedOfficeCodes, true))>{{ $code }} - {{ $label }}</option>
                @empty
                    <option disabled>No offices assigned</option>
                @endforelse
            </select>
            <div class="mt-4 flex flex-wrap gap-2">
                <button type="submit" class="inline-flex min-h-10 items-center gap-2 rounded-md bg-[#1f7890] px-4 py-2 text-sm font-bold text-white shadow-sm transition hover:bg-[#17657a]">
                    {!! payroll_icon('search', 'h-4 w-4') !!}
                    <span>Search</span>
                </button>
                <a href="{{ route($config['route']) }}" class="inline-flex min-h-10 items-center rounded-md border border-slate-300 bg-white px-4 py-2 text-sm font-medium text-slate-700 shadow-sm transition hover:border-[#1f7890] hover:text-[#1f7890]">Reset</a>
            </div>
        </form>

        <div class="grid grid-cols-1 gap-4 md:grid-cols-3">
            <article class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm">
                <div class="text-sm text-slate-600">{{ $config['summaryLabel'] }}</div>
                <div class="mt-5 text-3xl font-bold leading-none text-[#101820]">{{ count($records) }}</div>
                <div class="mt-3 text-xs text-slate-500">Visible for selected offices</div>
            </article>
            <article class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm">
                <div class="text-sm text-slate-600">Selected offices</div>
                <div class="mt-5 text-3xl font-bold leading-none text-[#101820]">{{ count($selectedOfficeCodes) }}</div>
                <div class="mt-3 text-xs text-slate-500">{{ implode(', ', $selectedOfficeCodes) ?: 'None' }}</div>
            </article>
            <article class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm">
                <div class="text-sm text-slate-600">Access level</div>
                <div class="mt-5 text-xl font-bold leading-tight text-[#101820]">{{ $userAccountType }}</div>
                <div class="mt-3 text-xs text-slate-500">Admin, Manager and Regional manager only</div>
            </article>
        </div>
    </section>

    <section class="rounded-lg border border-slate-200 bg-white shadow-sm">
        <div class="flex flex-col gap-3 border-b border-slate-200 px-5 py-4 lg:flex-row lg:items-center lg:justify-between">
            <div>
                <h2 class="text-base font-bold text-[#101820]">{{ $heading }} by Office</h2>
                <p class="mt-1 text-xs text-slate-500">{{ implode(', ', $selectedOfficeLabels) ?: 'No office selection available' }}</p>
            </div>
            <div class="flex flex-wrap gap-2">
                @foreach ($selectedOfficeCodes as $code)
                    <span class="rounded-full bg-[#e8f3ef] px-3 py-1 text-xs font-bold text-[#1f6b61]">{{ $code }}</span>
                @endforeach
            </div>
        </div>

        <div class="overflow-x-auto">
            <table class="min-w-full text-left text-sm">
                <thead class="bg-slate-50 text-[11px] uppercase tracking-[0.08em] text-slate-500">
                    <tr>
                        @foreach ($config['columns'] as $label)
                            <th class="px-5 py-3 font-bold">{{ $label }}</th>
                        @endforeach
                        <th class="px-5 py-3 font-bold">Office</th>
                        <th class="px-5 py-3 text-right font-bold">Action</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse ($records as $record)
                        <tr>
                            @foreach (array_keys($config['columns']) as $column)
                                <td class="px-5 py-4 {{ $column === 'ref' || $column === 'name' ? 'font-bold text-[#101820]' : 'text-slate-600' }}">
                                    {{ $record[$column] }}
                                </td>
                            @endforeach
                            <td class="px-5 py-4">
                                <span class="rounded-md bg-slate-100 px-2 py-1 text-xs font-bold text-slate-700">{{ $record['office'] }}</span>
                            </td>
                            <td class="px-5 py-4 text-right">
                                <button type="button" class="inline-flex min-h-9 items-center gap-2 rounded-md border border-[#b9dce4] bg-white px-3 py-2 text-sm font-medium text-[#1f7890] transition hover:border-[#1f7890]">
                                    {!! payroll_icon('eye', 'h-4 w-4') !!}
                                    <span>View</span>
                                </button>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="{{ count($config['columns']) + 2 }}" class="px-5 py-10 text-center text-sm text-slate-500">
                                No records are available for the selected offices.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>
@endsection

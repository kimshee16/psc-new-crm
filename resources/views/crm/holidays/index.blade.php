@extends('layouts.payroll')

@section('content')
    <div class="mb-6 flex flex-wrap items-start justify-between gap-4">
        <div>
            <h1 class="text-2xl font-bold text-[#101820]">Holidays</h1>
            <p class="mt-2 text-sm text-slate-600">Record your leave to pause round-robin lead allocation during your time away.</p>
        </div>
        <a href="{{ route('holidays.create') }}" class="inline-flex items-center gap-2 rounded-md bg-[#123348] px-4 py-2.5 text-sm font-semibold text-white hover:bg-[#1f7890]">
            {!! payroll_icon('plus') !!} Record leave
        </a>
    </div>

    @if (session('success'))
        <div role="status" class="mb-5 rounded-md border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">{{ session('success') }}</div>
    @endif

    <section class="overflow-hidden rounded-lg border border-slate-200 bg-white shadow-sm">
        <div class="border-b border-slate-200 px-5 py-4">
            @if ($canViewStaff)
                <nav aria-label="Leave views" class="mb-4 flex gap-2">
                    <a href="{{ route('holidays') }}" @if (! $showStaff) aria-current="page" @endif class="rounded-md px-4 py-2 text-sm font-semibold {{ ! $showStaff ? 'bg-[#123348] text-white' : 'bg-slate-100 text-slate-700 hover:bg-slate-200' }}">My leave</a>
                    <a href="{{ route('holidays', ['view' => 'staff']) }}" @if ($showStaff) aria-current="page" @endif class="rounded-md px-4 py-2 text-sm font-semibold {{ $showStaff ? 'bg-[#123348] text-white' : 'bg-slate-100 text-slate-700 hover:bg-slate-200' }}">Office staff leave</a>
                </nav>
            @endif
            <h2 class="text-base font-bold">{{ $showStaff ? 'Office staff leave' : 'My leave' }}</h2>
            @if ($showStaff)
                <p class="mt-1 text-sm text-slate-600">
                    {{ $officeCodes ? 'Showing your leave and staff leave for: '.implode(', ', array_map(fn ($code) => $offices[$code] ?? $code, $officeCodes)).'.' : 'No offices are assigned to you. Only your own leave is shown.' }}
                    Staff leave is view only.
                </p>
            @endif
            <p class="mt-1 text-xs text-slate-500">Leave includes both the start and end dates. Dates use {{ config('app.timezone') }} time.</p>
        </div>
        <div class="overflow-x-auto">
            <table class="min-w-full text-left text-sm">
                <thead class="bg-slate-50 text-xs uppercase text-slate-500">
                    <tr>
                        @if ($showStaff)<th scope="col" class="px-5 py-3">Staff member</th>@endif
                        <th scope="col" class="px-5 py-3">Start date</th>
                        <th scope="col" class="px-5 py-3">End date</th>
                        <th scope="col" class="px-5 py-3">Reason</th>
                        <th scope="col" class="px-5 py-3">Status</th>
                        <th scope="col" class="px-5 py-3 text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse ($leaves as $leave)
                        <tr>
                            @if ($showStaff)
                                <td class="px-5 py-4">
                                    <div class="font-semibold">{{ $leave->user->name }}</div>
                                    <div class="mt-1 text-xs text-slate-500">{{ $leave->user->email }}</div>
                                </td>
                            @endif
                            <td class="whitespace-nowrap px-5 py-4">{{ $leave->starts_on->format('d M Y') }}</td>
                            <td class="whitespace-nowrap px-5 py-4">{{ $leave->ends_on->format('d M Y') }}</td>
                            <td class="max-w-xs break-words px-5 py-4">{{ $leave->reason ?: '—' }}</td>
                            <td class="px-5 py-4">
                                <span class="whitespace-nowrap rounded-full px-2.5 py-1 text-xs font-semibold {{ $leave->status() === 'On leave' ? 'bg-amber-100 text-amber-800' : 'bg-slate-100 text-slate-600' }}">{{ $leave->status() }}</span>
                            </td>
                            <td class="px-5 py-4">
                                @if ($leave->user_id === auth()->id() && $leave->active)
                                    <div class="flex justify-end gap-3">
                                        <a href="{{ route('holidays.edit', $leave) }}" class="font-semibold text-[#1f7890] hover:underline">Edit</a>
                                        <form method="POST" action="{{ route('holidays.cancel', $leave) }}" onsubmit="return confirm('Cancel this leave period? It will no longer pause lead allocation.')">
                                            @csrf
                                            @method('PATCH')
                                            <button type="submit" class="whitespace-nowrap font-semibold text-red-700 hover:underline">Cancel leave</button>
                                        </form>
                                    </div>
                                @else
                                    <span class="block text-right text-slate-400">—</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="{{ $showStaff ? 6 : 5 }}" class="px-5 py-12 text-center text-slate-500">No leave periods recorded{{ $showStaff ? ' for you or your office staff' : '' }}.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if ($leaves->hasPages())
            <div class="border-t border-slate-200 p-5">{{ $leaves->links() }}</div>
        @endif
    </section>
@endsection

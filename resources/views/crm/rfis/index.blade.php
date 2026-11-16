@extends('layouts.payroll')

@section('content')
    @include('crm.rfis.styles')
    <header class="mb-7"><h1 class="text-[26px] font-bold">RFIs</h1><p class="mt-2 text-sm text-slate-600">Requests assigned to you or linked to applications where you are a counsellor or migration agent.</p></header>
    @if(session('success'))<div role="status" class="mb-4 rounded-md bg-green-50 p-4 text-sm text-green-800">{{ session('success') }}</div>@endif
    @if($errors->any() && !old('_rfi_notes_id'))<div role="alert" class="mb-4 rounded-md bg-red-50 p-4 text-sm text-red-800">@foreach($errors->all() as $error)<p>{{ $error }}</p>@endforeach</div>@endif
    <form id="rfi-filters" method="GET" action="{{ route('rfis') }}">
        <input type="hidden" name="sort" value="{{ $filters['sort'] ?? 'due_date' }}"><input type="hidden" name="direction" value="{{ $filters['direction'] ?? 'asc' }}">
        <div class="mb-4 flex flex-wrap items-center justify-between gap-4">
            <label class="flex cursor-pointer items-center gap-3 text-sm font-semibold"><input id="display-closed-rfis" type="checkbox" role="switch" name="display_closed" value="1" @checked(!empty($filters['display_closed'])) class="h-5 w-5 accent-[#1f7890]"> Display Closed RFIs</label>
            <div class="flex flex-wrap gap-2"><button class="rounded-md bg-[#1f7890] px-4 py-2 text-sm font-semibold text-white">Apply filters</button><a href="{{ route('rfis') }}" class="rounded-md border border-slate-300 bg-white px-4 py-2 text-sm">Clear filters</a><button formaction="{{ route('rfis.export') }}" class="rounded-md border border-slate-300 bg-white px-4 py-2 text-sm">Download CSV</button></div>
        </div>
        <section class="min-w-0 rounded-lg border border-slate-200 bg-white shadow-sm" aria-label="RFI list">
            <p class="px-5 py-4 text-sm text-slate-600" role="status">Showing {{ $rfis->firstItem() ?? 0 }}–{{ $rfis->lastItem() ?? 0 }} of {{ $rfis->total() }} RFIs</p>
            <x-rfis.table :rfis="$rfis" :filterable="true" :filters="$filters" :officers="$officers" />
            <div class="flex flex-wrap items-center justify-between gap-4 border-t border-slate-200 px-5 py-4">
                <label class="flex items-center gap-2 text-sm">Records per page<select id="rfis-per-page" name="per_page" class="rounded-md border border-slate-300 px-3 py-2">@foreach([10, 20, 50] as $size)<option value="{{ $size }}" @selected(($filters['per_page'] ?? 10) == $size)>{{ $size }}</option>@endforeach</select></label>
                <div class="min-w-0 flex-1">{{ $rfis->onEachSide(1)->links() }}</div>
            </div>
        </section>
    </form>
    <x-rfis.notes-dialogs :rfis="$rfis" />
    <script>
        (() => {
            const form = document.getElementById('rfi-filters');
            document.querySelectorAll('[data-rfi-sort]').forEach(button => button.addEventListener('click', () => {
                form.elements.direction.value = form.elements.sort.value === button.dataset.rfiSort && form.elements.direction.value === 'asc' ? 'desc' : 'asc';
                form.elements.sort.value = button.dataset.rfiSort;
                form.requestSubmit();
            }));
            document.getElementById('display-closed-rfis').addEventListener('change', () => form.requestSubmit());
            document.getElementById('rfis-per-page').addEventListener('change', () => form.requestSubmit());
        })();
    </script>
@endsection

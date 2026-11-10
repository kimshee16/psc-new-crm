@extends('layouts.payroll')

@section('content')
    <header class="mb-7">
        <h1 class="text-[26px] font-bold text-[#101820]">Counsellor Walk-in Forms</h1>
        <p class="mt-2 text-sm text-slate-600">Open a form for a walk-in client or copy the link to share by email or WhatsApp. Clients can access these forms without signing in.</p>
        <p class="mt-2 text-sm text-slate-600">Each form assigns enquiries directly to the selected staff member and uses their default office.</p>
    </header>

    <section class="overflow-hidden rounded-lg border border-slate-200 bg-white shadow-sm" aria-label="Staff walk-in forms">
        <div class="overflow-x-auto">
            <table class="block w-full text-left text-sm xl:table">
                <thead class="hidden border-b border-slate-200 bg-slate-50 text-slate-600 xl:table-header-group">
                    <tr>
                        <th scope="col" class="px-5 py-4">Counsellor / migration agent</th>
                        <th scope="col" class="px-5 py-4">Default office</th>
                        <th scope="col" class="px-5 py-4">Form link</th>
                        <th scope="col" class="px-5 py-4">Actions</th>
                    </tr>
                </thead>
                <tbody class="block divide-y divide-slate-100 xl:table-row-group">
                    @forelse ($counsellors as $counsellor)
                        @php $formUrl = route('counsellor-intake.show', $counsellor); @endphp
                        <tr data-walk-in-form class="block p-4 xl:table-row xl:p-0">
                            <td class="block py-1 font-semibold xl:table-cell xl:px-5 xl:py-4">
                                {{ $counsellor->name }}
                                @if ($counsellor->is(auth()->user()))<span class="ml-2 rounded bg-[#eaf7fc] px-2 py-1 text-xs text-[#1f7890]">Your form</span>@endif
                            </td>
                            <td class="block py-1 xl:table-cell xl:px-5 xl:py-4"><span class="xl:hidden">Office: </span>{{ $offices[$counsellor->defaultOfficeCode() ?? \App\Models\Office::defaultCode()] ?? $counsellor->defaultOfficeCode() }}</td>
                            <td class="block min-w-0 py-2 xl:table-cell xl:min-w-[260px] xl:px-5 xl:py-4">
                                <input type="text" readonly value="{{ $formUrl }}" aria-label="Walk-in form link for {{ $counsellor->name }}" class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm text-slate-600" data-form-link>
                            </td>
                            <td class="block py-1 xl:table-cell xl:px-5 xl:py-4">
                                <div class="flex flex-wrap gap-2">
                                    <a href="{{ $formUrl }}" target="_blank" rel="noopener" aria-label="Open form for {{ $counsellor->name }} (new tab)" class="whitespace-nowrap rounded-md bg-[#1f7890] px-3 py-2 font-semibold text-white">Open form</a>
                                    <button type="button" data-copy-form-link aria-label="Copy form link for {{ $counsellor->name }}" class="whitespace-nowrap rounded-md border border-slate-300 px-3 py-2 font-semibold text-slate-700">Copy link</button>
                                </div>
                                <p role="status" class="mt-2 text-xs text-slate-600" data-copy-status></p>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="px-5 py-8 text-center text-slate-500">No active staff forms are available.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="px-5 py-4">{{ $counsellors->links() }}</div>
    </section>

    <script>
        document.querySelectorAll('[data-copy-form-link]').forEach(button => {
            button.addEventListener('click', async () => {
                const row = button.closest('[data-walk-in-form]');
                const input = row.querySelector('[data-form-link]');
                const status = row.querySelector('[data-copy-status]');
                try {
                    await navigator.clipboard.writeText(input.value);
                    status.textContent = 'Link copied.';
                } catch {
                    input.focus();
                    input.select();
                    status.textContent = 'Press Ctrl+C (or Command+C) to copy the selected link.';
                }
            });
        });
    </script>
@endsection

<section class="mb-6 rounded-lg border border-slate-200 bg-white p-5 shadow-sm">
    <h2 class="text-base font-bold text-[#101820]">Add Event Referral Option</h2>
    <p class="mt-1 text-sm text-slate-500">Options for “How did you hear about our event?” on event registration forms.</p>
    <form method="POST" action="{{ route('configuration.event-marketing.store') }}" class="mt-4 grid gap-3 lg:grid-cols-[1fr_120px_120px_auto] lg:items-end">
        @csrf
        <input type="hidden" name="option_id" value="new">
        <label class="block">
            <span class="text-xs font-bold text-slate-600">Option name</span>
            <input name="name" value="{{ old('option_id') === 'new' ? old('name') : '' }}" required maxlength="160" class="config-control mt-1">
        </label>
        <label class="block">
            <span class="text-xs font-bold text-slate-600">Sort order</span>
            <input name="sort_order" value="{{ old('option_id') === 'new' ? old('sort_order') : min(999, ($eventReferrals->max('sort_order') ?? 0) + 1) }}" type="number" required min="1" max="999" class="config-control mt-1">
        </label>
        <label class="flex min-h-9 items-center gap-2 text-sm font-bold text-slate-700">
            <input type="hidden" name="active" value="0">
            <input type="checkbox" name="active" value="1" @checked(old('option_id') !== 'new' || old('active'))>
            <span>Active</span>
        </label>
        <button type="submit" class="config-action config-primary">Add</button>
    </form>
</section>

<section class="rounded-lg border border-slate-200 bg-white shadow-sm">
    <div class="border-b border-slate-200 px-5 py-4">
        <h2 class="text-base font-bold text-[#101820]">Event Referral</h2>
        <p class="mt-1 text-xs text-slate-500">Deactivate options to hide them from new registrations. Existing leads keep their recorded answers.</p>
        @if (! $eventReferrals->contains('active', true))
            <p class="mt-2 text-sm font-semibold text-amber-700">Add or activate at least one option to enable event registration.</p>
        @endif
    </div>
    <div class="overflow-x-auto">
        <table class="min-w-full text-left text-sm">
            <thead class="bg-slate-50 text-[11px] uppercase tracking-[0.08em] text-slate-500">
                <tr>
                    <th class="w-28 px-5 py-3">Order</th>
                    <th class="min-w-[240px] px-5 py-3">Option</th>
                    <th class="w-28 px-5 py-3">Active</th>
                    <th class="w-28 px-5 py-3 text-right">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse ($eventReferrals as $referral)
                    @php
                        $formId = 'event-referral-'.$referral->id;
                        $hasOldInput = (string) old('option_id') === (string) $referral->id;
                    @endphp
                    <tr>
                        <td class="px-5 py-4">
                            <input form="{{ $formId }}" name="sort_order" aria-label="Sort order for {{ $referral->name }}" value="{{ $hasOldInput ? old('sort_order') : $referral->sort_order }}" type="number" required min="1" max="999" class="config-control">
                        </td>
                        <td class="px-5 py-4">
                            <input form="{{ $formId }}" name="name" aria-label="Option name for {{ $referral->name }}" value="{{ $hasOldInput ? old('name') : $referral->name }}" required maxlength="160" class="config-control">
                        </td>
                        <td class="px-5 py-4">
                            <input form="{{ $formId }}" type="hidden" name="active" value="0">
                            <input form="{{ $formId }}" type="checkbox" name="active" value="1" aria-label="Active: {{ $referral->name }}" @checked($hasOldInput ? old('active') : $referral->active)>
                        </td>
                        <td class="px-5 py-4 text-right">
                            <button form="{{ $formId }}" type="submit" class="config-action config-primary">Save</button>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="4" class="px-5 py-10 text-center text-slate-500">No event referral options configured.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</section>
@foreach ($eventReferrals as $referral)
    <form id="event-referral-{{ $referral->id }}" method="POST" action="{{ route('configuration.event-marketing.update', $referral) }}">
        @csrf
        @method('PATCH')
        <input type="hidden" name="option_id" value="{{ $referral->id }}">
    </form>
@endforeach

@props(['activities', 'kind', 'showApplication' => false])
@php $financial = in_array($kind, ['payments', 'quotes']); @endphp
<div class="mt-4 overflow-x-auto">
    <table class="w-full text-left text-sm">
        <thead class="bg-slate-50 text-slate-600"><tr>
            <th scope="col" class="px-3 py-3">Record ID</th>
            @if($showApplication)<th scope="col" class="px-3 py-3">Source application</th>@endif
            <th scope="col" class="px-3 py-3">Title</th><th scope="col" class="px-3 py-3">Reference</th><th scope="col" class="px-3 py-3">Status</th><th scope="col" class="px-3 py-3">Due date</th>
            @if($financial)<th scope="col" class="px-3 py-3">Amount</th>@endif
        </tr></thead>
        <tbody class="divide-y divide-slate-100">
            @forelse($activities as $activity)
                <tr>
                    <td class="px-3 py-4">{{ $activity->id }}</td>
                    @if($showApplication)<td class="px-3 py-4"><a href="{{ route('applications.show', ['application' => $activity->application_id, 'tab' => $kind]) }}#application-tabs" class="font-semibold text-[#1f7890] underline">{{ $activity->application->record_reference }}</a></td>@endif
                    <td class="px-3 py-4">{{ $activity->title }}</td><td class="px-3 py-4">{{ $activity->reference ?: '—' }}</td><td class="px-3 py-4">{{ $activity->status ?: '—' }}</td><td class="whitespace-nowrap px-3 py-4">{{ $activity->due_date?->format('d M Y') ?? '—' }}</td>
                    @if($financial)<td class="whitespace-nowrap px-3 py-4">{{ $activity->amount !== null ? $activity->currency.' '.number_format((float) $activity->amount, 2) : '—' }}</td>@endif
                </tr>
            @empty
                <tr><td colspan="{{ 5 + (int) $showApplication + (int) $financial }}" class="px-3 py-8 text-center text-slate-500">No {{ strtolower(\App\Models\ApplicationActivity::TABS[$kind]) }} records yet.</td></tr>
            @endforelse
        </tbody>
    </table>
</div>
<div class="mt-4">{{ $activities->links() }}</div>

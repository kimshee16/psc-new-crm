<section class="mt-6 min-w-0 rounded-xl border border-slate-200 bg-white p-6">
    <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
        <h2 class="text-lg font-bold">Sponsorship</h2>
        <button type="button" id="add-sponsorship" aria-haspopup="dialog" aria-controls="sponsorship-create-dialog" class="inline-flex items-center gap-2 rounded-lg bg-[#15638e] px-5 py-3 text-sm font-semibold text-white"><span aria-hidden="true" class="text-xl leading-none">+</span> Add sponsorship</button>
    </div>
    <div class="overflow-x-auto rounded-lg border border-slate-200"><table class="w-full text-left text-sm">
        <thead class="bg-slate-50 text-xs text-slate-500"><tr>@foreach(['Sponsorship ID', 'Sponsor type', 'Sponsorship status', 'Approval date', 'Expiry date', 'TRN', 'Counsellor / Migration Agent'] as $label)<th scope="col" class="px-3 py-3">{{ $label }}</th>@endforeach</tr></thead>
        <tbody class="divide-y divide-slate-100">
            @forelse($sponsors as $sponsor)
                <tr>
                    <td class="whitespace-nowrap px-3 py-4"><a class="font-semibold text-[#15638e] underline" href="{{ route('applications.show', $sponsor) }}">{{ $sponsor->record_reference }}</a></td>
                    <td class="px-3 py-4">{{ $sponsor->sponsor_type ?: '—' }}</td>
                    <td class="px-3 py-4"><x-applications.status-badge :status="$sponsor->status" /></td>
                    <td class="whitespace-nowrap px-3 py-4">{{ $sponsor->approval_date?->format('d M Y') ?? '—' }}</td><td class="whitespace-nowrap px-3 py-4">{{ $sponsor->expiry_date?->format('d M Y') ?? '—' }}</td>
                    <td class="px-3 py-4">{{ $sponsor->trn ?: '—' }}</td>
                    <td class="px-3 py-4"><div>{{ $sponsor->primaryCounsellor?->name ?? 'Unassigned' }}</div>@if($sponsor->migrationAgent)<div class="mt-1 text-slate-500">{{ $sponsor->migrationAgent->name }}</div>@endif</td>
                </tr>
            @empty
                <tr><td colspan="7" class="px-3 py-8 text-center text-slate-500">No sponsorship records assigned to you.</td></tr>
            @endforelse
        </tbody>
    </table></div>
    @if($sponsors->hasPages())<div class="mt-4">{{ $sponsors->links() }}</div>@endif
</section>
<section class="mt-6 min-w-0 rounded-xl border border-slate-200 bg-white p-6">
    <div class="mb-4 flex flex-wrap items-center justify-between gap-3"><h2 class="text-lg font-bold">Nominations</h2><button type="button" id="create-nomination" aria-haspopup="dialog" aria-controls="nomination-create-dialog" class="inline-flex items-center gap-2 rounded-lg bg-[#15638e] px-5 py-3 text-sm font-semibold text-white"><span aria-hidden="true" class="text-xl leading-none">+</span> Create nomination</button></div>
    <div class="overflow-x-auto rounded-lg border border-slate-200"><table class="w-full text-left text-sm">
        <thead class="bg-slate-50 text-xs text-slate-500"><tr>@foreach(['Nomination ID', 'Nominated position title', 'Status', 'Outcome', 'Nominee', 'Linked visa application ID', 'Linked visa application status'] as $label)<th scope="col" class="px-3 py-3">{{ $label }}</th>@endforeach</tr></thead>
        <tbody class="divide-y divide-slate-100">
            @forelse($nominations as $nomination)
                <tr>
                    <td class="whitespace-nowrap px-3 py-4"><a class="font-semibold text-[#15638e] underline" href="{{ route('applications.show', $nomination) }}">{{ $nomination->record_reference }}</a></td>
                    <td class="px-3 py-4">{{ $nomination->nominated_position_title ?: '—' }}</td><td class="px-3 py-4"><x-applications.status-badge :status="$nomination->status" /></td><td class="px-3 py-4"><x-applications.status-badge :status="$nomination->outcome" /></td>
                    <td class="px-3 py-4">@include('crm.organisations.nominee', ['application' => $nomination])</td>
                    <td class="px-3 py-4">@forelse($nomination->linkedVisas as $visa)<div class="py-1"><a class="text-[#15638e] underline" href="{{ route('applications.show', $visa) }}">{{ $visa->record_reference }}</a></div>@empty &mdash; @endforelse</td>
                    <td class="px-3 py-4">@forelse($nomination->linkedVisas as $visa)<div class="py-1"><x-applications.status-badge :status="$visa->status" /></div>@empty &mdash; @endforelse</td>
                </tr>
            @empty
                <tr><td colspan="7" class="px-3 py-8 text-center text-slate-500">No nominations assigned to you.</td></tr>
            @endforelse
        </tbody>
    </table></div>
    @if($nominations->hasPages())<div class="mt-4">{{ $nominations->links() }}</div>@endif
</section>

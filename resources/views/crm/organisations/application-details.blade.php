<section class="mt-6 rounded-lg border border-slate-200 bg-white p-6 shadow-sm">
    <div class="mb-5 flex items-center justify-between gap-3">
        <h2 class="text-lg font-bold">{{ $application->category === 'SBS' ? (str_starts_with($application->record_reference, 'LAB-') ? 'Labour Agreement' : 'SBS') : 'Nomination' }} Details</h2>
        <a href="{{ route('applications.organisation-details.edit', $application) }}" class="text-sm font-semibold text-[#1f7890]">Edit details</a>
    </div>
    <dl class="grid gap-4 text-sm sm:grid-cols-2 lg:grid-cols-3">
        @if ($application->category === 'SBS')
            @foreach (['sponsor_type' => 'Sponsor type', 'approval_date' => 'Approval date', 'expiry_date' => 'Expiry date', 'home_affairs_reference' => 'Home Affairs reference', 'trn' => 'TRN for the SBS application'] as $field => $label)
                <div><dt class="text-slate-500">{{ $label }}</dt><dd class="mt-1 break-words font-medium">{{ in_array($field, ['approval_date', 'expiry_date']) ? ($application->$field?->format('d M Y') ?? '—') : ($application->$field ?: '—') }}</dd></div>
            @endforeach
        @else
            <div><dt class="text-slate-500">Nominated position title</dt><dd class="mt-1 font-medium">{{ $application->nominated_position_title ?: '—' }}</dd></div>
            <div><dt class="text-slate-500">Outcome</dt><dd class="mt-1 font-medium">{{ $application->outcome ?: '—' }}</dd></div>
            <div><dt class="text-slate-500">Nominee Name + Linked Client ID</dt><dd class="mt-1 font-medium">@include('crm.organisations.nominee')</dd></div>
        @endif
    </dl>
</section>

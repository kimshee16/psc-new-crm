@props(['application', 'tabs', 'activeTab', 'activities' => null, 'nominations' => null, 'rfis' => null])
@php($access = app(\App\Support\PSC\CrmAccess::class))

<section id="application-tabs" class="mt-6 min-w-0 scroll-mt-20 rounded-lg border border-slate-200 bg-white shadow-sm" aria-label="Application details">
    @if ($tabs)
        <nav aria-label="Application tabs" class="flex flex-wrap gap-1 border-b border-slate-200 px-4 pt-3">
            @foreach ($tabs as $key => $label)
                <a id="application-tab-{{ $key }}"
                   href="{{ route('applications.show', ['application' => $application->id, 'tab' => $key]) }}#application-tabs"
                   @if ($activeTab === $key) aria-current="page" @endif
                   class="border-b-2 px-4 py-3 text-sm font-semibold focus-visible:outline-2 focus-visible:outline-[#1f7890] {{ $activeTab === $key ? 'border-[#1f7890] bg-[#eaf7fc] text-[#123348]' : 'border-transparent text-slate-600 hover:bg-slate-50' }}">{{ $label }}@if($key === 'rfi') <span data-outstanding-rfi-count="{{ $application->active_rfi_count ?? 0 }}" class="ml-1 inline-flex min-w-6 items-center justify-center rounded-full px-2 py-0.5 text-xs {{ ($application->active_rfi_count ?? 0) > 0 ? 'bg-[#1f7890] text-white' : 'bg-slate-100 text-slate-600' }}" title="RFIs that are not Closed" aria-label="{{ $application->active_rfi_count ?? 0 }} outstanding {{ ($application->active_rfi_count ?? 0) === 1 ? 'RFI' : 'RFIs' }}">{{ $application->active_rfi_count ?? 0 }}</span>@endif</a>
            @endforeach
        </nav>
        <section class="p-6" aria-labelledby="application-tab-{{ $activeTab }}">
            <div class="flex flex-wrap items-center justify-between gap-2">
                <h2 class="text-lg font-bold">{{ $tabs[$activeTab] }}</h2>
                @if ($activeTab === 'rfi' && $rfis !== null && $access->allows(auth()->user(), 'rfis', 'create'))
                    <a href="{{ route('applications.rfis.create', $application) }}" class="rounded-md bg-[#1f7890] px-4 py-2 text-sm font-semibold text-white">+ Add RFI</a>
                @elseif ($activeTab === 'services' && $access->allows(auth()->user(), 'applications', 'edit'))
                    <button type="button" id="add-application-service" class="rounded-md bg-[#1f7890] px-4 py-2 text-sm font-semibold text-white">+ Add service</button>
                @endif
            </div>
            @if ($activeTab === 'notes')
                <x-related-record-notes :record="$application" module="applications" :record-id="$application->id" />
            @elseif ($activeTab === 'services')
                @include('crm.applications.services', ['canEditServices' => $access->allows(auth()->user(), 'applications', 'edit')])
            @elseif ($activeTab === 'nominations' && $nominations !== null)
                @include('crm.applications.sbs-nominations')
            @elseif ($activeTab === 'rfi' && $rfis !== null)
                @include('crm.rfis.styles')
                <x-rfis.table :rfis="$rfis" return-to="application" />
                <div class="mt-4">{{ $rfis->links() }}</div>
                <x-rfis.notes-dialogs :rfis="$rfis" return-to="application" />
            @elseif ($activities !== null)
                <x-applications.activity-records :activities="$activities" :kind="$activeTab" />
                @if($activeTab === 'tasks' ? $access->allows(auth()->user(), 'tasks', 'create') : $access->allows(auth()->user(), 'applications', 'edit'))
                    @include('crm.organisations.activity-form', ['application' => $application, 'kind' => $activeTab])
                @endif
            @else
                <p class="mt-3 text-sm text-slate-600">{{ $tabs[$activeTab] }} content is work in progress.</p>
            @endif
        </section>
    @else
        <p class="p-6 text-sm text-slate-600">No tabs are enabled for this application type.</p>
    @endif
</section>

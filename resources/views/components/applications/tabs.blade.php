@props(['application', 'tabs', 'activeTab'])

<section id="application-tabs" class="mt-6 min-w-0 rounded-lg border border-slate-200 bg-white shadow-sm" aria-label="Application details">
    @if ($tabs)
        <nav aria-label="Application tabs" class="flex flex-wrap gap-1 border-b border-slate-200 px-4 pt-3">
            @foreach ($tabs as $key => $label)
                <a id="application-tab-{{ $key }}"
                   href="{{ route('applications.show', ['application' => $application->id, 'tab' => $key]) }}#application-tabs"
                   @if ($activeTab === $key) aria-current="page" @endif
                   class="border-b-2 px-4 py-3 text-sm font-semibold focus-visible:outline-2 focus-visible:outline-[#1f7890] {{ $activeTab === $key ? 'border-[#1f7890] bg-[#eaf7fc] text-[#123348]' : 'border-transparent text-slate-600 hover:bg-slate-50' }}">{{ $label }}</a>
            @endforeach
        </nav>
        <section class="p-6" aria-labelledby="application-tab-{{ $activeTab }}">
            <h2 class="text-lg font-bold">{{ $tabs[$activeTab] }}</h2>
            <p class="mt-3 text-sm text-slate-600">{{ $tabs[$activeTab] }} content is work in progress.</p>
        </section>
    @else
        <p class="p-6 text-sm text-slate-600">No tabs are enabled for this application type.</p>
    @endif
</section>

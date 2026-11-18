@props(['application', 'checkpoints', 'embedded' => false])
<form id="application-checkpoints-form" method="POST" action="{{ route('applications.checkpoints.update', $application) }}" @if(!$embedded) class="mt-5 border-t border-slate-200 pt-4" @endif>
    @csrf
    @method('PATCH')
    @unless($embedded)
        <h3 class="font-bold">Checkpoints</h3>
        <fieldset @disabled(!app(\App\Support\PSC\CrmAccess::class)->allows(auth()->user(), 'applications', 'edit'))>
        @include('crm.organisations.errors')
        @foreach (\App\Support\PSC\ApplicationCheckpoints::FIRST_FIELDS as $field => $label)
            @php($values = \App\Support\PSC\SbsRecord::MILESTONES[$field])
            <x-applications.summary-select :name="$field" :label="$label" :options="array_combine($values, $values)" :value="$application->$field" />
        @endforeach
        <x-applications.checkpoint-options :application="$application" :checkpoints="$checkpoints" />
        <x-applications.nomination-field :application="$application" name="lodgement_date" label="Lodgement date" type="date" />
        <x-applications.summary-select name="outcome" label="Outcome" :options="array_combine(\App\Support\PSC\NominationRecord::OUTCOMES, \App\Support\PSC\NominationRecord::OUTCOMES)" :value="$application->outcome" />
        <x-applications.nomination-field :application="$application" name="outcome_date" label="Outcome date" type="date" />
        <button class="mt-3 rounded-md bg-[#15638e] px-4 py-2 text-sm font-semibold text-white">Save checkpoints</button>
        </fieldset>
    @endunless
</form>

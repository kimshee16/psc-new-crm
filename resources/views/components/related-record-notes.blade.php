@props(['record', 'module', 'recordId'])
@php
    $notesService = app(\App\Support\PSC\RecordNotes::class);
    $owner = $notesService->owner($record);
    $sharedClient = ! $owner->is($record);
    $canReadShared = !$sharedClient || (app(\App\Support\PSC\CrmAccess::class)->notes(auth()->user(), 'clients')
        && $owner->newQuery()->visibleTo(auth()->user())->whereKey($owner->id)->exists());
    $editable = ! ($owner instanceof \App\Models\Client)
        || app(\App\Http\Controllers\ClientsController::class)->canManageNotes($owner, auth()->user());
@endphp
@if($canReadShared)
    <x-record-notes :stored="$owner->notes" :module="$module" :record-id="$recordId" :editable="$editable" :shared-client="$sharedClient" />
@endif
@if ($sharedClient && $notesService->all($record->notes) !== [])
    <details class="mt-6 rounded-md border border-slate-200 bg-white p-4">
        <summary class="cursor-pointer text-sm font-semibold">Earlier {{ $module === 'leads' ? 'Lead' : 'Application' }} Notes</summary>
        <p class="mt-3 text-xs text-slate-500">These earlier record-specific notes have been preserved. Add new notes in the shared Client Notes section above.</p>
        <x-record-notes :stored="$record->notes" :module="$module" :record-id="$recordId" :section-id="$module.'-'.$recordId.'-earlier-notes'" :heading="'Earlier '.($module === 'leads' ? 'Lead' : 'Application').' Notes'" />
    </details>
@endif

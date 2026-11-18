@extends('layouts.payroll')

@section('content')
    @include('crm.rfis.styles')
    @php
        $draft = fn($name, $default = '') => is_scalar(old($name, $default)) ? old($name, $default) : $default;
        $back = $returnTo === 'application' && in_array(auth()->id(), [$application->primary_counsellor_id, $application->secondary_counsellor_id, $application->migration_agent_id], true)
            ? route('applications.show', ['application' => $application, 'tab' => 'rfi']).'#application-tabs' : route('rfis');
    @endphp
    <header class="mb-7"><a href="{{ $back }}" class="text-sm font-semibold text-[#1f7890]">&larr; Back to RFIs</a><h1 class="mt-3 text-[26px] font-bold">{{ $rfi->exists ? 'Edit RFI' : 'Add RFI' }}</h1><p class="mt-2 text-sm text-slate-600">{{ $application->record_reference }} · {{ $application->type_label }}</p></header>
    <section class="max-w-4xl rounded-lg border border-slate-200 bg-white p-6 shadow-sm">
        @if($errors->any())<div role="alert" class="mb-5 rounded-md bg-red-50 p-4 text-sm text-red-800">Please correct the highlighted fields.@error('closed_confirmed')<p>{{ $message }}</p>@enderror</div>@endif
        <form id="rfi-editor" class="rfi-editor space-y-5" method="POST" enctype="multipart/form-data" action="{{ $rfi->exists ? route('rfis.update', $rfi) : route('applications.rfis.store', $application) }}" novalidate>
            @csrf @if($rfi->exists) @method('PATCH') @endif
            <input type="hidden" name="return_to" value="{{ $returnTo }}"><input type="hidden" name="closed_confirmed" value="0">
            <div class="grid gap-5 sm:grid-cols-2">
                <label>Request Type *<select id="rfi-request-type" name="request_type" required @error('request_type') aria-invalid="true" @enderror><option value="">Select request type</option>@foreach(\App\Models\ApplicationRfi::TYPES as $type)<option @selected($draft('request_type', $rfi->request_type) === $type)>{{ $type }}</option>@endforeach</select><x-rfis.field-error name="request_type" /></label>
                <label id="rfi-other-field">Other request type *<input name="request_type_other" value="{{ $draft('request_type_other', $rfi->request_type_other) }}" maxlength="255" @error('request_type_other') aria-invalid="true" @enderror><x-rfis.field-error name="request_type_other" /></label>
                <label>Date received *<input type="date" name="received_date" value="{{ $draft('received_date', $rfi->received_date?->format('Y-m-d')) }}" required @error('received_date') aria-invalid="true" @enderror><x-rfis.field-error name="received_date" /></label>
                <label>Response due date *<input type="date" name="due_date" value="{{ $draft('due_date', $rfi->due_date?->format('Y-m-d')) }}" required @error('due_date') aria-invalid="true" @enderror><x-rfis.field-error name="due_date" /></label>
                <label>Status *<select id="rfi-status" name="status" required @error('status') aria-invalid="true" @enderror>@foreach(\App\Models\ApplicationRfi::STATUSES as $status)<option @selected($draft('status', $rfi->status ?? 'Received') === $status)>{{ $status }}</option>@endforeach</select><x-rfis.field-error name="status" /></label>
                <label>Assigned officer *<select name="assigned_to_user_id" required @error('assigned_to_user_id') aria-invalid="true" @enderror>@foreach($officers as $officer)<option value="{{ $officer->id }}" @selected((string) $draft('assigned_to_user_id', $rfi->assigned_to_user_id ?? auth()->id()) === (string) $officer->id)>{{ $officer->name }}</option>@endforeach</select><x-rfis.field-error name="assigned_to_user_id" /></label>
                <label>Request letter *<input type="file" name="request_letter" accept=".pdf,.jpg,.jpeg,.png,.doc,.docx" @required(!$rfi->request_letter_path) @error('request_letter') aria-invalid="true" @enderror>@if($rfi->request_letter_path)<a href="{{ route('rfis.document', ['rfi' => $rfi, 'document' => 'request_letter']) }}" target="_blank" rel="noopener" class="mt-2 block text-sm font-normal text-[#1f7890] underline">View current request letter</a>@endif<x-rfis.field-error name="request_letter" /></label>
                <label>Date lodged<input type="date" name="lodged_date" value="{{ $draft('lodged_date', $rfi->lodged_date?->format('Y-m-d')) }}" @error('lodged_date') aria-invalid="true" @enderror><x-rfis.field-error name="lodged_date" /></label>
                <label>Proof of lodgement<input type="file" name="proof_of_lodgement" accept=".pdf,.jpg,.jpeg,.png,.doc,.docx" @error('proof_of_lodgement') aria-invalid="true" @enderror>@if($rfi->proof_of_lodgement_path)<a href="{{ route('rfis.document', ['rfi' => $rfi, 'document' => 'proof_of_lodgement']) }}" target="_blank" rel="noopener" class="mt-2 block text-sm font-normal text-[#1f7890] underline">View current proof of lodgement</a>@endif<x-rfis.field-error name="proof_of_lodgement" /></label>
            </div>
            <p class="text-xs text-slate-500">Upload PDF, JPG, PNG or Word documents, up to 10 MB each. @if($rfi->exists)Existing documents are retained unless you upload replacements.@endif</p>
            @if(!$rfi->exists && app(\App\Support\PSC\CrmAccess::class)->notes(auth()->user(), 'rfis', write: true))<label>Notes<textarea name="notes" rows="5" maxlength="10000">{{ $draft('notes') }}</textarea><x-rfis.field-error name="notes" /></label>@endif
            <div class="flex justify-end gap-3 border-t border-slate-200 pt-5"><a href="{{ $back }}" class="rounded-md border border-slate-300 px-5 py-2">Cancel</a><button class="rounded-md bg-[#1f7890] px-5 py-2 font-semibold text-white">Save RFI</button></div>
        </form>
        @if($rfi->exists && in_array(auth()->user()->account_type, ['Admin', 'Super Admin'], true))
            <form id="rfi-delete" method="POST" action="{{ route('rfis.destroy', $rfi) }}" class="mt-6 border-t border-slate-200 pt-5">@csrf @method('DELETE')<input type="hidden" name="return_to" value="{{ $returnTo }}"><button class="rounded-md border border-red-300 px-4 py-2 text-sm font-semibold text-red-700">Delete RFI</button></form>
        @endif
    </section>
    <script>
        (() => {
            const form = document.getElementById('rfi-editor');
            const type = document.getElementById('rfi-request-type');
            const other = document.getElementById('rfi-other-field');
            const syncOther = () => { other.hidden = type.value !== 'Other'; other.querySelector('input').required = type.value === 'Other'; };
            type.addEventListener('change', syncOther); syncOther();
            const status = document.getElementById('rfi-status');
            let previous = status.value;
            const confirmClosed = () => {
                const confirmed = window.confirm('Close this RFI? It will no longer count as active and will be hidden from your RFI list by default.');
                form.elements.closed_confirmed.value = confirmed ? '1' : '0';
                return confirmed;
            };
            status.addEventListener('change', () => {
                if (status.value === 'Closed' && previous !== 'Closed' && !confirmClosed()) status.value = previous;
                if (status.value !== 'Closed') form.elements.closed_confirmed.value = '0';
                previous = status.value;
            });
            form.addEventListener('submit', event => {
                if (status.value === 'Closed' && @json(!$rfi->exists || $rfi->status !== 'Closed') && form.elements.closed_confirmed.value !== '1' && !confirmClosed()) event.preventDefault();
            });
            document.getElementById('rfi-delete')?.addEventListener('submit', event => {
                if (!window.confirm('Delete this RFI and its uploaded documents? This cannot be undone.')) event.preventDefault();
            });
            form.querySelector('[aria-invalid="true"]')?.focus();
        })();
    </script>
@endsection

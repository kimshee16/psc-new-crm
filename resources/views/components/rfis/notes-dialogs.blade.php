@props(['rfis', 'returnTo' => 'list'])
@foreach($rfis as $rfi)
    <dialog id="rfi-notes-{{ $rfi->id }}" class="rfi-dialog" aria-labelledby="rfi-notes-heading-{{ $rfi->id }}">
        <form method="POST" action="{{ route('rfis.notes', $rfi) }}" class="rfi-editor space-y-4">
            @csrf @method('PATCH')
            <input type="hidden" name="return_to" value="{{ $returnTo }}"><input type="hidden" name="_rfi_notes_id" value="{{ $rfi->id }}">
            <div class="flex items-start justify-between gap-4"><h2 id="rfi-notes-heading-{{ $rfi->id }}" class="text-xl font-bold">RFI notes</h2><button type="button" data-close-rfi-dialog aria-label="Close RFI notes" class="px-2 text-2xl">&times;</button></div>
            <p class="text-sm text-slate-600">{{ $rfi->application->record_reference }} · {{ $rfi->request_label }}</p>
            @if($errors->any() && (string) old('_rfi_notes_id') === (string) $rfi->id)<div role="alert" class="text-sm text-red-700">@foreach($errors->all() as $error)<p>{{ $error }}</p>@endforeach</div>@endif
            <label>Notes<textarea name="notes" rows="8" maxlength="10000">{{ (string) old('_rfi_notes_id') === (string) $rfi->id ? old('notes', $rfi->notes) : $rfi->notes }}</textarea></label>
            <div class="flex justify-end gap-3"><button type="button" data-close-rfi-dialog class="rounded-md border border-slate-300 px-4 py-2">Cancel</button><button class="rounded-md bg-[#1f7890] px-4 py-2 font-semibold text-white">Save notes</button></div>
        </form>
    </dialog>
@endforeach
<script>
    (() => {
        document.querySelectorAll('[data-rfi-notes]').forEach(button => button.addEventListener('click', () => document.getElementById(button.dataset.rfiNotes)?.showModal()));
        document.querySelectorAll('[data-close-rfi-dialog]').forEach(button => button.addEventListener('click', () => button.closest('dialog').close()));
        @if($errors->any() && old('_rfi_notes_id'))document.getElementById('rfi-notes-{{ (int) old('_rfi_notes_id') }}')?.showModal();@endif
    })();
</script>

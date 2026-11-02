@props(['stored' => '', 'module', 'recordId', 'editable' => false, 'notice' => ''])
@php
    $notesService = app(\App\Support\PSC\RecordNotes::class);
    $notes = $notesService->all($stored);
@endphp
<section class="record-notes mt-8" data-record-notes data-version="{{ $notesService->version($stored) }}" data-url="{{ route('notes.store', ['module' => $module, 'recordId' => $recordId]) }}" data-token="{{ csrf_token() }}" aria-label="{{ ucfirst(rtrim($module, 's')) }} notes">
    <h2 class="mb-2 text-lg font-bold">{{ ['clients' => 'Client', 'leads' => 'Lead', 'applications' => 'Application'][$module] }} Notes</h2>
    <p class="mb-4 text-xs text-slate-500">Newest notes first. Notes save separately from the record.</p>
    @if ($module === 'applications')
        <p class="mb-4 text-xs text-slate-500">Use notes for record-level comments. Keep detailed workflow updates and documentation in the relevant application sections.</p>
    @endif
    <p data-note-status role="status" aria-live="polite" class="mb-3 text-sm">{{ $notice }}</p>
    @if ($editable)
        <details class="mb-4 rounded-md border border-slate-200 bg-white p-3" data-note-editor>
            <summary class="cursor-pointer text-sm font-bold text-[#1f7890]">Add Note</summary>
            <label class="mt-3 block text-sm">New note
                <textarea data-note-body rows="4" maxlength="10000" class="mt-1 block w-full rounded-md border border-slate-300 p-3" placeholder="Write a note..."></textarea>
            </label>
            <button type="button" data-note-action="POST" class="mt-2 rounded-md bg-[#1f7890] px-4 py-2 text-sm font-bold text-white">Save note</button>
        </details>
    @endif
    <div class="grid grid-cols-1 items-start gap-4 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 xl:grid-cols-6">
        @forelse ($notes as $note)
            <article class="record-note min-w-0 break-words rounded-md border p-4" data-note-editor data-note-id="{{ $note['id'] }}">
                <p class="text-xs text-slate-600">Added by {{ $note['author'] }}<br>{{ $notesService->displayDate($note['datetime']) }}</p>
                @if (!empty($note['edited_at']))
                    <p class="mt-1 text-xs text-slate-500">Edited by {{ $note['edited_by'] }} · {{ $notesService->displayDate($note['edited_at']) }}</p>
                @endif
                <p class="mt-3 whitespace-pre-wrap break-words text-sm">{{ $note['body'] }}</p>
                @if ($editable)
                    <details class="mt-3">
                        <summary class="cursor-pointer text-sm font-semibold text-[#1f7890]">Edit note</summary>
                        <label class="mt-2 block text-sm">Note text
                            <textarea data-note-body rows="5" maxlength="{{ max(10000, mb_strlen($note['body'])) }}" class="mt-1 block w-full rounded-md border border-slate-300 bg-white p-2">{{ $note['body'] }}</textarea>
                        </label>
                        <div class="mt-2 flex flex-wrap gap-2">
                            <button type="button" data-note-action="PATCH" class="rounded-md bg-[#1f7890] px-3 py-2 text-sm font-bold text-white">Save note</button>
                            <button type="button" data-note-action="DELETE" class="rounded-md border border-red-300 bg-white px-3 py-2 text-sm text-red-700">Remove</button>
                        </div>
                    </details>
                @endif
            </article>
        @empty
            <p class="text-sm text-slate-500">No notes yet.</p>
        @endforelse
    </div>
</section>
@once
<style>
    .record-note { min-height: 158px; background: #fff7bd; border-color: #eadf98; box-shadow: 0 10px 18px rgb(15 23 42 / 8%); }
    .record-note:nth-child(3n + 2) { background: #dff7ef; border-color: #a8dfca; }
    .record-note:nth-child(3n) { background: #e8f1ff; border-color: #bfd2f2; }
</style>
<script>
if (!window.pscRecordNotesReady) {
    window.pscRecordNotesReady = true;
    window.addEventListener('beforeunload', event => {
        if ([...document.querySelectorAll('[data-note-body]')].some(input => input.value !== input.defaultValue)) {
            event.preventDefault();
            event.returnValue = '';
        }
    });
    document.addEventListener('click', async event => {
        const button = event.target.closest('[data-note-action]');
        if (!button) return;
        const section = button.closest('[data-record-notes]');
        if (section.dataset.saving) return;
        const editor = button.closest('[data-note-editor]');
        const status = section.querySelector('[data-note-status]');
        const method = button.dataset.noteAction;
        const input = editor.querySelector('[data-note-body]');
        if (method === 'DELETE' && !confirm('Remove this note?')) return;
        if (method !== 'DELETE' && !input.value.trim()) { status.textContent = 'Enter a note before saving.'; input.focus(); return; }
        section.dataset.saving = 'true';
        button.disabled = true;
        if (input) input.disabled = true;
        status.textContent = 'Saving...';
        try {
            const response = await fetch(section.dataset.url + (method === 'POST' ? '' : '/' + encodeURIComponent(editor.dataset.noteId)), {
                method, credentials: 'same-origin',
                headers: {'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': section.dataset.token},
                body: JSON.stringify({version: section.dataset.version, body: input?.value})
            });
            if (response.redirected || !response.headers.get('content-type')?.includes('application/json')) {
                throw new Error('Your session may have expired. Copy your draft and sign in again before saving.');
            }
            const result = await response.json();
            if (!response.ok) throw new Error(Object.values(result.errors || {}).flat().join(' ') || result.message || 'Unable to save this note.');
            const template = document.createElement('template');
            template.innerHTML = result.html;
            const replacement = template.content.querySelector('[data-record-notes]');
            // Keep drafts in other cards when this card is saved.
            section.querySelectorAll('[data-note-editor]').forEach(other => {
                const draft = other.querySelector('[data-note-body]');
                if (other === editor || !draft || draft.value === draft.defaultValue) return;
                const next = [...replacement.querySelectorAll('[data-note-editor]')].find(card => card.dataset.noteId === other.dataset.noteId);
                if (!next) return;
                next.querySelector('[data-note-body]').value = draft.value;
                (next.matches('details') ? next : next.querySelector('details')).open = true;
            });
            section.replaceWith(replacement);
            const message = replacement.querySelector('[data-note-status]');
            message.tabIndex = -1;
            message.focus({preventScroll: true});
        } catch (error) {
            status.textContent = error.message || 'Unable to save. Your draft has been kept. Try again.';
        } finally {
            delete section.dataset.saving;
            button.disabled = false;
            if (input) input.disabled = false;
        }
    });
}
</script>
@endonce

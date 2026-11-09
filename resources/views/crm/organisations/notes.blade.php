<h2 class="text-lg font-bold">Notes</h2>
<p class="mt-2 text-sm text-slate-500">Collated across sponsorship and nomination records. Open a linked record to add or edit its notes.</p>
<div class="mt-5 grid gap-4 md:grid-cols-2 xl:grid-cols-3">
    @forelse($notes as $note)
        <article class="min-w-0 rounded-lg border border-slate-200 bg-slate-50 p-4">
            <a href="{{ route('applications.show', $note['application']) }}" class="text-sm font-semibold text-[#15638e] underline">{{ $note['application']->record_reference }}</a>
            <p class="mt-2 text-xs text-slate-500">{{ $note['author'] }} · {{ app(\App\Support\PSC\RecordNotes::class)->displayDate($note['datetime']) }}</p>
            <p class="mt-3 whitespace-pre-wrap break-words text-sm">{{ $note['body'] }}</p>
        </article>
    @empty
        <p class="text-sm text-slate-500">No notes yet.</p>
    @endforelse
</div>

<li class="flex flex-wrap items-center justify-between gap-2 rounded border border-slate-200 px-3 py-2 text-sm">
    <input type="hidden" name="checkpoint_ids[]" value="{{ $checkpoint?->id }}">
    <span data-checkpoint-name>{{ $checkpoint?->name }}</span>
    <span class="flex gap-2">
        <button type="button" data-move="up" class="config-action disabled:opacity-40">Move up</button>
        <button type="button" data-move="down" class="config-action disabled:opacity-40">Move down</button>
        <button type="button" data-remove class="config-action config-danger">Remove</button>
    </span>
</li>

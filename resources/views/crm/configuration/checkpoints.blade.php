<section aria-labelledby="checkpoints-heading">
    <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
        <h2 id="checkpoints-heading" class="text-lg font-bold">Checkpoints</h2>
        <a class="text-sm font-semibold text-[#1f7890] underline" href="{{ route('configuration', ['tab' => 'workflow-checkpoints']) }}">Assign to workflows</a>
    </div>
    <form method="POST" action="{{ $editingCheckpoint ? route('configuration.checkpoints.update', $editingCheckpoint) : route('configuration.checkpoints.store') }}" class="mb-5 rounded-lg border border-slate-200 bg-white p-5">
        @csrf
        @if($editingCheckpoint) @method('PATCH') @endif
        <h3 class="mb-3 font-bold">{{ $editingCheckpoint ? 'Edit checkpoint' : 'Add checkpoint' }}</h3>
        <label for="checkpoint-name" class="block text-sm font-semibold">Name</label>
        <input id="checkpoint-name" class="config-control mt-1" name="name" maxlength="120" value="{{ old('name', $editingCheckpoint?->name) }}" required>
        <label for="checkpoint-statuses" class="mt-3 block text-sm font-semibold">Statuses (one per line, in dropdown order)</label>
        <textarea id="checkpoint-statuses" class="config-control mt-1" name="status_lines" rows="5" maxlength="6100" required>{{ old('status_lines', implode("\n", $editingCheckpoint?->statuses ?? [])) }}</textarea>
        <div class="mt-3 flex items-center gap-3">
            <button class="config-action config-primary">{{ $editingCheckpoint ? 'Save checkpoint' : 'Add checkpoint' }}</button>
            @if($editingCheckpoint)
                <a href="{{ route('configuration', ['tab' => 'checkpoints']) }}" class="config-action">Cancel</a>
            @endif
        </div>
    </form>
    <div class="overflow-x-auto rounded-lg border border-slate-200 bg-white">
        <table class="text-left text-sm">
            <thead><tr><th scope="col">Checkpoint</th><th scope="col">Statuses</th><th scope="col" class="text-right">Actions</th></tr></thead>
            <tbody class="divide-y divide-slate-200">
                @forelse($checkpoints as $checkpoint)
                    <tr>
                        <th scope="row">{{ $checkpoint->name }}</th>
                        <td><ul>@foreach($checkpoint->statuses as $status)<li>{{ $status }}</li>@endforeach</ul></td>
                        <td>
                            <div class="config-row-actions">
                                <a class="config-action config-primary" aria-label="Edit {{ $checkpoint->name }}" href="{{ route('configuration', ['tab' => 'checkpoints', 'edit' => $checkpoint->id]) }}">Edit</a>
                                <form method="POST" action="{{ route('configuration.checkpoints.destroy', $checkpoint) }}" onsubmit="return confirm('Remove this checkpoint from all workflows? Saved application progress will be retained.')">
                                    @csrf
                                    @method('DELETE')
                                    <button class="config-action config-danger" aria-label="Remove {{ $checkpoint->name }}">Remove</button>
                                </form>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="3">No checkpoints configured.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</section>

@php $showNominationForm = $editingNominationSubclass || request('action') === 'create'; @endphp
<section class="rounded-lg border border-slate-200 bg-white shadow-sm">
    <div class="flex flex-wrap items-center justify-between gap-3 border-b border-slate-200 px-5 py-4">
        <div>
            <h2 class="text-base font-bold text-[#101820]">{{ $showNominationForm ? ($editingNominationSubclass ? 'Edit Nomination Subclass' : 'Add Nomination Subclass') : 'Nomination Subclasses' }}</h2>
            <p class="mt-1 text-sm text-slate-600">Visa names follow workflow configuration. Removing an option preserves existing nominations.</p>
        </div>
        @unless ($showNominationForm)
            <a href="{{ route('configuration', ['tab' => 'nomination-subclasses', 'action' => 'create']) }}" class="config-action config-primary">Add Subclass</a>
        @endunless
    </div>
    @if ($showNominationForm)
        <form method="POST" action="{{ $editingNominationSubclass ? route('configuration.nomination-subclasses.update', $editingNominationSubclass) : route('configuration.nomination-subclasses.store') }}" class="p-5">
            @csrf
            @if ($editingNominationSubclass) @method('PATCH') @endif
            <div class="grid gap-4 lg:grid-cols-2">
                <label class="block">
                    <span class="text-xs font-bold text-slate-600">Subclass and visa name</span>
                    <select name="subclass" required class="config-control mt-1 @error('subclass') is-invalid @enderror">
                        <option value="">Select subclass</option>
                        @foreach ($nominationVisaOptions as $code => $label)
                            <option value="{{ $code }}" @selected((string) old('subclass', $editingNominationSubclass?->subclass) === (string) $code)>{{ $label }}</option>
                        @endforeach
                    </select>
                </label>
                <label class="flex items-center gap-2 text-sm font-bold text-slate-700">
                    <input type="hidden" name="sbs_required" value="0">
                    <input type="checkbox" name="sbs_required" value="1" @checked(old('sbs_required', $editingNominationSubclass?->sbs_required ?? false))>
                    <span>SBS required</span>
                </label>
            </div>
            <div class="mt-5 flex gap-2">
                <button type="submit" class="config-action config-primary">Save Subclass</button>
                <a href="{{ route('configuration', ['tab' => 'nomination-subclasses']) }}" class="config-action config-primary">Cancel</a>
            </div>
        </form>
    @else
        <div class="overflow-x-auto">
            <table class="min-w-full text-left text-sm">
                <thead class="bg-slate-50 text-xs uppercase text-slate-500"><tr>
                    <th scope="col">Subclass</th><th scope="col">Visa name</th><th scope="col">SBS required</th><th scope="col" class="text-right">Actions</th>
                </tr></thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse ($nominationSubclasses as $option)
                        <tr>
                            <td>{{ $option->subclass }}</td>
                            <td>{{ isset($nominationVisaOptions[$option->subclass]) ? preg_replace('/^\d{3}\s*[-–]\s*/u', '', $nominationVisaOptions[$option->subclass]) : 'Visa name not configured' }}</td>
                            <td>{{ $option->sbs_required ? 'Y' : '' }}</td>
                            <td><div class="flex justify-end gap-2">
                                <a href="{{ route('configuration', ['tab' => 'nomination-subclasses', 'edit' => $option->id]) }}" class="config-action config-primary" aria-label="Edit subclass {{ $option->subclass }}">Edit</a>
                                <form method="POST" action="{{ route('configuration.nomination-subclasses.destroy', $option) }}" onsubmit="return confirm('Remove this subclass from new nominations?')">
                                    @csrf @method('DELETE')
                                    <button type="submit" class="config-action config-danger" aria-label="Remove subclass {{ $option->subclass }}">Remove</button>
                                </form>
                            </div></td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="py-10 text-center text-slate-500">No nomination subclasses configured. Add a subclass to make it available for nominations.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    @endif
</section>

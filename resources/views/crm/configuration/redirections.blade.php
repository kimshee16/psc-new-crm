<section class="rounded-lg border border-slate-200 bg-white shadow-sm">
    <div class="flex flex-wrap items-center justify-between gap-3 border-b border-slate-200 px-5 py-4">
        <div>
            <h2 class="text-base font-bold text-[#101820]">{{ $showRedirectionForm ? ($editingRedirection ? 'Edit Redirection' : 'Add Redirection') : 'Lead Redirections' }}</h2>
            <p class="mt-1 text-sm text-slate-600">Active rules match both nationality and current location before default-location routing. Otherwise, the location's default office applies.</p>
        </div>
        @unless ($showRedirectionForm)
            <a href="{{ route('configuration', ['tab' => 'redirections', 'action' => 'create']) }}" class="config-action config-primary">Add Redirection</a>
        @endunless
    </div>

    @if ($showRedirectionForm)
        <form method="POST" action="{{ $editingRedirection ? route('configuration.redirections.update', $editingRedirection) : route('configuration.redirections.store') }}" class="p-5">
            @csrf
            @if ($editingRedirection)
                @method('PATCH')
            @endif
            <div class="grid gap-4 lg:grid-cols-2">
                <label class="block">
                    <span class="text-xs font-bold text-slate-600">Nationality</span>
                    <select name="nationality" required class="config-control mt-1 @error('nationality') is-invalid @enderror">
                        <option value="">Select nationality</option>
                        @foreach ($nationalities as $nationality)
                            <option value="{{ $nationality }}" @selected(old('nationality', $editingRedirection?->nationality) === $nationality)>{{ $nationality }}</option>
                        @endforeach
                    </select>
                </label>
                <label class="block">
                    <span class="text-xs font-bold text-slate-600">Current Location</span>
                    <select name="current_location" required class="config-control mt-1 @error('current_location') is-invalid @enderror">
                        <option value="">Select current location</option>
                        @if ($editingRedirection && ! in_array($editingRedirection->current_location, $redirectionLocations, true))
                            <option value="{{ $editingRedirection->current_location }}" @selected(old('current_location', $editingRedirection->current_location) === $editingRedirection->current_location) disabled>{{ $editingRedirection->current_location }} (unavailable; select a current location)</option>
                        @endif
                        @foreach ($redirectionLocations as $location)
                            <option value="{{ $location }}" @selected(old('current_location', $editingRedirection?->current_location) === $location)>{{ $location }}</option>
                        @endforeach
                    </select>
                </label>
                <label class="block">
                    <span class="text-xs font-bold text-slate-600">Destination Office</span>
                    <select name="office_code" required class="config-control mt-1 @error('office_code') is-invalid @enderror">
                        <option value="">Select office</option>
                        @foreach ($offices as $code => $office)
                            <option value="{{ $code }}" @selected(old('office_code', $editingRedirection?->office_code) === $code)>{{ $code }} - {{ $office }}</option>
                        @endforeach
                    </select>
                </label>
                <label class="flex items-center gap-2 text-sm font-bold text-slate-700">
                    <input type="hidden" name="active" value="0">
                    <input type="checkbox" name="active" value="1" @checked(old('active', $editingRedirection?->active ?? true))>
                    <span>Active</span>
                </label>
            </div>
            <p class="mt-4 text-xs text-slate-500">Each nationality and current-location pair can have one rule. Changes apply to future lead allocations.</p>
            <div class="mt-5 flex flex-wrap gap-2">
                <button type="submit" class="config-action config-primary">Save Redirection</button>
                <a href="{{ route('configuration', ['tab' => 'redirections']) }}" class="config-action config-primary">Cancel</a>
            </div>
        </form>
    @else
        <div class="overflow-x-auto">
            <table class="min-w-full text-left text-sm">
                <thead class="bg-slate-50 text-xs uppercase text-slate-500">
                    <tr>
                        <th scope="col" class="px-5 py-3">Nationality</th>
                        <th scope="col" class="px-5 py-3">Current Location</th>
                        <th scope="col" class="px-5 py-3">Destination Office</th>
                        <th scope="col" class="px-5 py-3">Status</th>
                        <th scope="col" class="px-5 py-3 text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse ($redirections as $redirection)
                        <tr>
                            <td class="px-5 py-4">{{ $redirection->nationality }}</td>
                            <td class="px-5 py-4">
                                {{ $redirection->current_location }}
                                @unless (in_array($redirection->current_location, $redirectionLocations, true))
                                    <span class="block text-xs text-amber-700">Location unavailable on lead forms</span>
                                @endunless
                            </td>
                            <td class="px-5 py-4">{{ $redirection->office_code }} - {{ $offices[$redirection->office_code] ?? 'Unavailable office' }}</td>
                            <td class="px-5 py-4">{{ $redirection->active ? 'Active' : 'Inactive' }}</td>
                            <td class="px-5 py-4">
                                <div class="flex justify-end gap-2">
                                    <a href="{{ route('configuration', ['tab' => 'redirections', 'edit' => $redirection->id]) }}" class="config-action config-primary" aria-label="Edit redirection for {{ $redirection->nationality }} in {{ $redirection->current_location }}">Edit</a>
                                    <form method="POST" action="{{ route('configuration.redirections.destroy', $redirection) }}" onsubmit="return confirm('Remove this redirection? Future leads will use default-location routing unless another rule matches.')">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="config-action config-danger" aria-label="Remove redirection for {{ $redirection->nationality }} in {{ $redirection->current_location }}">Remove</button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="px-5 py-10 text-center text-slate-500">No redirections configured. Incoming leads use default-location routing.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    @endif
</section>

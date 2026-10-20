<section class="rounded-lg border border-slate-200 bg-white shadow-sm">
    <div class="flex flex-wrap items-center justify-between gap-3 border-b border-slate-200 px-5 py-4">
        <div>
            <h2 class="text-base font-bold text-[#101820]">{{ $showCountryForm ? ($editingCountry ? 'Edit Country' : 'Add Country') : 'Countries' }}</h2>
            <p class="mt-1 text-sm text-slate-600">Manage country options for nationality fields and lead redirections. Inactive countries remain on existing records but are unavailable for new selections.</p>
        </div>
        @unless ($showCountryForm)
            <a href="{{ route('configuration', ['tab' => 'countries', 'action' => 'create']) }}" class="config-action config-primary">Add Country</a>
        @endunless
    </div>

    @if ($showCountryForm)
        <form method="POST" action="{{ $editingCountry ? route('configuration.countries.update', $editingCountry) : route('configuration.countries.store') }}" class="p-5">
            @csrf
            @if ($editingCountry)
                @method('PATCH')
            @endif
            <div class="grid gap-4 lg:grid-cols-2">
                <label class="block">
                    <span class="text-xs font-bold text-slate-600">Country Name</span>
                    <input name="name" value="{{ old('name', $editingCountry?->name) }}" required maxlength="80" class="config-control mt-1 @error('name') is-invalid @enderror">
                </label>
                <label class="flex items-center gap-2 text-sm font-bold text-slate-700">
                    <input type="hidden" name="active" value="0">
                    <input type="checkbox" name="active" value="1" @checked(old('active', $editingCountry?->active ?? true))>
                    <span>Active</span>
                </label>
            </div>
            @if ($editingCountry)
                <p class="mt-4 text-xs text-slate-500">Renaming updates the country name on linked clients, leads and redirection rules.</p>
                @if ($editingCountry->legacy_nationality)
                    <p class="mt-2 text-xs text-slate-500">The existing CRM nationality label "{{ $editingCountry->legacy_nationality }}" is retained for compatibility.</p>
                @endif
            @endif
            <div class="mt-5 flex gap-2">
                <button type="submit" class="config-action config-primary">Save Country</button>
                <a href="{{ route('configuration', ['tab' => 'countries']) }}" class="config-action config-primary">Cancel</a>
            </div>
        </form>
    @else
        <form method="GET" action="{{ route('configuration') }}" class="flex flex-wrap items-end gap-3 p-5">
            <input type="hidden" name="tab" value="countries">
            <label class="block">
                <span class="text-xs font-bold text-slate-600">Search countries</span>
                <input type="search" name="search" value="{{ $countrySearch }}" class="config-control mt-1">
            </label>
            <button type="submit" class="config-action config-primary">Search</button>
            @if ($countrySearch !== '')
                <a href="{{ route('configuration', ['tab' => 'countries']) }}" class="config-action config-primary">Clear</a>
            @endif
        </form>
        <div class="overflow-x-auto">
            <table class="min-w-full text-left text-sm">
                <thead class="bg-slate-50 text-xs uppercase text-slate-500">
                    <tr>
                        <th scope="col" class="px-5 py-3">Country Name</th>
                        <th scope="col" class="px-5 py-3">Status</th>
                        <th scope="col" class="px-5 py-3 text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse ($countries as $country)
                        <tr>
                            <td class="px-5 py-4">{{ $country->name }}</td>
                            <td class="px-5 py-4">{{ $country->active ? 'Active' : 'Inactive' }}</td>
                            <td class="px-5 py-4">
                                <div class="flex justify-end gap-2">
                                    <a href="{{ route('configuration', ['tab' => 'countries', 'edit' => $country->id]) }}" class="config-action config-primary" aria-label="Edit {{ $country->name }}">Edit</a>
                                    <form method="POST" action="{{ route('configuration.countries.destroy', $country) }}" onsubmit="return confirm('Remove this country option? Countries in use must be deactivated instead.')">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="config-action config-danger" aria-label="Remove {{ $country->name }}">Remove</button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="3" class="px-5 py-10 text-center text-slate-500">{{ $countrySearch !== '' ? 'No countries match your search.' : 'No countries configured. Add a country to make it available on forms.' }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="border-t border-slate-200 p-5">{{ $countries->links() }}</div>
    @endif
</section>

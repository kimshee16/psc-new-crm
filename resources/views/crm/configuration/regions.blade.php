<style>
    .region-panel { overflow: visible; border: 1px solid #dedeea; border-radius: 10px; background: white; }
    .region-heading { display: flex; align-items: center; justify-content: space-between; gap: 16px; margin-bottom: 16px; }
    .region-button { display: inline-flex; align-items: center; min-height: 36px; padding: 8px 14px; border: 0; border-radius: 6px; background: #5035ff; color: white; font-size: 14px; font-weight: 600; cursor: pointer; }
    .region-button:hover { background: #4025e5; }
    .region-link { color: #5035ff; }
    .region-link:hover { text-decoration: underline; }
    .region-fields { display: grid; grid-template-columns: 136px minmax(0, 300px); align-items: start; gap: 16px; padding: 24px 14px 30px; font-size: 14px; }
    .region-fields > label, .region-fields > .region-label { padding-top: 8px; }
    .region-fields .config-control { min-height: 36px; border-color: #c2bfe5; border-radius: 6px; font-size: 14px; }
    .region-footer { padding: 14px 14px 24px 166px; border-top: 1px solid #dedeea; }
    .region-offices { position: relative; }
    .region-offices summary { cursor: pointer; color: #545277; }
    .region-office-options { position: absolute; top: calc(100% + 4px); left: 0; right: 0; z-index: 10; max-height: 240px; overflow-y: auto; padding: 6px; border: 1px solid #c2bfe5; border-radius: 6px; background: white; box-shadow: 0 6px 18px #10182018; }
    .region-office-options label { display: flex; gap: 8px; align-items: center; padding: 8px; cursor: pointer; }
    .region-office-options label:hover { background: #f6f5ff; }
    .region-table { width: 100%; border-collapse: collapse; font-size: 13px; text-align: left; }
    .region-table th { background: #f8f8ff; font-weight: 600; }
    .region-table th, .region-table td { padding: 10px 12px; border: 1px solid #e2e2ee; vertical-align: top; }
    .region-table td { height: 51px; }
    .region-table th a { display: inline-flex; align-items: center; gap: 8px; }
    .region-table th span { color: #8582a5; }
    .region-table td:last-child a { display: block; }
    @media (max-width: 700px) {
        .region-fields { grid-template-columns: minmax(0, 1fr); gap: 8px; }
        .region-footer { padding-left: 14px; }
    }
</style>

@if ($showRegionForm)
    @php
        $selectedOfficeIds = array_map('strval', (array) old('offices', old('_region_form') ? [] : ($editingRegion?->offices->modelKeys() ?? [])));
        $selectedCodes = $officeRecords->filter(fn ($office) => in_array((string) $office->id, $selectedOfficeIds, true))->pluck('code');
    @endphp
    <div class="region-heading">
        <h2 class="text-base font-bold">{{ $editingRegion ? 'Edit Region' : 'Add Region' }}</h2>
        <a class="region-link text-sm" href="{{ route('configuration', ['tab' => 'regions']) }}">Back to Regions</a>
    </div>
    <form method="POST" class="region-panel" action="{{ $editingRegion ? route('configuration.regions.update', $editingRegion) : route('configuration.regions.store') }}">
        @csrf
        <input type="hidden" name="_region_form" value="1">
        @if ($editingRegion) @method('PATCH') @endif
        <div class="region-fields">
            <label for="region-name">Region Name</label>
            <input id="region-name" name="name" value="{{ old('name', $editingRegion?->name) }}" required maxlength="120" class="config-control @error('name') is-invalid @enderror">
            <span id="region-offices-label" class="region-label">Offices</span>
            <details class="region-offices">
                <summary class="config-control" aria-labelledby="region-offices-label region-office-summary"><span id="region-office-summary">{{ $selectedCodes->isEmpty() ? '-Select-' : $selectedCodes->join(', ') }}</span></summary>
                <div class="region-office-options" role="group" aria-labelledby="region-offices-label">
                    @foreach ($officeRecords->sortBy('code') as $office)
                        <label>
                            <input type="checkbox" name="offices[]" value="{{ $office->id }}" data-office-code="{{ $office->code }}" @checked(in_array((string) $office->id, $selectedOfficeIds, true))>
                            <span>{{ $office->code }} — {{ $office->name }}</span>
                        </label>
                    @endforeach
                </div>
            </details>
            <label for="region-manager">Regional Manager</label>
            <select id="region-manager" name="regional_manager_id" class="config-control @error('regional_manager_id') is-invalid @enderror">
                <option value="">-Select-</option>
                @foreach ($regionalManagers as $manager)
                    <option value="{{ $manager->id }}" @selected((string) old('regional_manager_id', $editingRegion?->regional_manager_id) === (string) $manager->id)>{{ $manager->name }} ({{ $manager->email }})</option>
                @endforeach
            </select>
        </div>
        <div class="region-footer"><button type="submit" class="region-button">Submit and Exit</button></div>
    </form>
    <script>
        const regionOfficePicker = document.querySelector('.region-offices');
        regionOfficePicker.addEventListener('change', () => {
            const selected = Array.from(regionOfficePicker.querySelectorAll('input:checked')).map(input => input.dataset.officeCode);
            document.getElementById('region-office-summary').textContent = selected.join(', ') || '-Select-';
        });
        document.addEventListener('click', event => {
            if (!regionOfficePicker.contains(event.target)) regionOfficePicker.open = false;
        });
        regionOfficePicker.addEventListener('keydown', event => {
            if (event.key === 'Escape') {
                regionOfficePicker.open = false;
                regionOfficePicker.querySelector('summary').focus();
            }
        });
    </script>
@else
    <div class="region-heading">
        <h2 class="text-base font-bold">Regions</h2>
        <a class="region-button" href="{{ route('configuration', ['tab' => 'regions', 'action' => 'create']) }}">Add Region</a>
    </div>
    <div class="overflow-x-auto bg-white">
        <table class="region-table">
            <thead>
                <tr>
                    @foreach (['name' => 'Region Name', 'manager' => 'Regional Manager', 'offices' => 'Offices'] as $key => $label)
                        <th scope="col" aria-sort="{{ $regionSort === $key ? ($regionDirection === 'asc' ? 'ascending' : 'descending') : 'none' }}">
                            <a href="{{ route('configuration', ['tab' => 'regions', 'sort' => $key, 'direction' => $regionSort === $key && $regionDirection === 'asc' ? 'desc' : 'asc']) }}">{{ $label }} <span aria-hidden="true">{{ $regionSort === $key && $regionDirection === 'asc' ? '⌃' : '⌄' }}</span></a>
                        </th>
                    @endforeach
                </tr>
            </thead>
            <tbody>
                @forelse ($regions as $region)
                    <tr>
                        <td style="width: 34%"><a href="{{ route('configuration', ['tab' => 'regions', 'edit' => $region->id]) }}" aria-label="Edit {{ $region->name }}">{{ $region->name }}</a></td>
                        <td style="width: 42%">
                            @if ($region->regionalManager)
                                <a class="region-link" href="{{ route('configuration', ['tab' => 'regions', 'edit' => $region->id]) }}">{{ $region->regionalManager->name }}</a>
                            @endif
                        </td>
                        <td>
                            @foreach ($region->offices as $office)
                                <a class="region-link" href="{{ route('configuration', ['tab' => 'offices']) }}">{{ $office->code }}</a>
                            @endforeach
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="3">No regions have been configured. Add a region to get started.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
@endif

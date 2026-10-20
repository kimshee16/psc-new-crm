<style>
    .users-toolbar { display: flex; flex-wrap: wrap; gap: 8px; align-items: center; margin-bottom: 16px; }
    .users-toolbar h2 { margin-right: 6px; font-size: 16px; font-weight: 600; }
    .users-button { display: inline-flex; min-height: 34px; align-items: center; border: 1px solid #dcdbea; border-radius: 4px; padding: 7px 12px; background: white; color: #242438; font-size: 13px; cursor: pointer; }
    .users-primary { background: #5745ff; border-color: #5745ff; color: white; }
    .users-button:hover { filter: brightness(.96); }
    .users-link { color: #5745ff; }
    .users-link:hover { text-decoration: underline; }
    .users-panel { background: white; border: 1px solid #e2e1ed; border-radius: 7px; }
    .users-form-grid { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); column-gap: 64px; row-gap: 20px; max-width: 760px; padding: 24px 12px; }
    .users-form-column { display: flex; flex-direction: column; gap: 14px; min-width: 0; }
    .users-form-column label, .users-form-column legend { font-size: 13px; }
    .users-required { color: #dc2626; }
    .users-form-column .config-control { border-color: #c9c6e5; border-radius: 5px; min-height: 38px; }
    .users-radios { display: flex; flex-wrap: wrap; gap: 14px; margin-top: 10px; font-size: 13px; }
    .users-radios label { display: inline-flex; align-items: center; gap: 7px; }
    .users-panel input[type=radio], .users-panel input[type=checkbox] { accent-color: #6451ff; }
    .users-footer { padding: 14px 12px 24px; border-top: 1px solid #e2e1ed; }
    .users-table { width: 100%; min-width: 1280px; font-size: 12px; text-align: left; border-collapse: collapse; }
    .users-table th { background: #f8f9ff; font-weight: 600; white-space: nowrap; }
    .users-table th, .users-table td { border: 1px solid #eeedf4; padding: 9px 8px; vertical-align: top; }
    .users-table tr.is-dirty td { background: #f0edff; }
    .users-table select { width: 100%; min-width: 85px; max-width: 150px; padding: 2px; border: 1px solid transparent; background: transparent; font-size: 12px; }
    .users-table select:hover, .users-table select:focus { border-color: #c9c6e5; background: white; }
    .users-table .users-name { display: block; min-width: 135px; }
    .users-table .users-name:hover { color: #5745ff; text-decoration: underline; }
    .user-office-picker summary { cursor: pointer; }
    .user-office-options { max-height: 220px; overflow-y: auto; padding: 8px; border: 1px solid #dcdbea; border-radius: 5px; background: white; }
    .user-office-options label { display: flex; gap: 7px; align-items: center; padding: 5px 0; }
    .users-table .user-office-picker summary { color: #5745ff; min-width: 100px; white-space: pre-line; }
    .users-photo { width: 32px; height: 32px; border-radius: 50%; object-fit: cover; }
    @media (max-width: 850px) { .users-form-grid { grid-template-columns: 1fr; gap: 20px; } }
</style>

@if ($showUserForm)
    @php
        $nameParts = preg_split('/\s+/', trim($editingUser?->name ?? ''), 2);
        $selectedOffices = (array) old('offices', old('_user_form') ? [] : ($editingUser?->assignedOfficeCodes() ?? []));
        $defaultOffice = old('default_office_code', $editingUser?->defaultOfficeCode());
    @endphp
    <div class="users-toolbar">
        <h2>{{ $editingUser ? 'Edit User' : 'User' }}</h2>
        <a class="users-link ml-auto text-sm" href="{{ route('configuration', ['tab' => 'users']) }}">Back to Users</a>
    </div>
    <form class="users-panel" method="POST" enctype="multipart/form-data" action="{{ $editingUser ? route('configuration.users.update', $editingUser) : route('configuration.users.store') }}">
        @csrf
        @if ($editingUser) @method('PATCH') @endif
        <input type="hidden" name="_user_form" value="1">
        <div class="users-form-grid" data-user-fields>
            <div class="users-form-column">
                <fieldset>
                    <legend>Name <span class="users-required">*</span></legend>
                    <div class="mt-2 flex gap-2">
                        <input class="config-control" name="first_name" aria-label="First Name" placeholder="First Name" required maxlength="120" autocomplete="given-name" value="{{ old('first_name', $editingUser?->first_name ?? $nameParts[0]) }}">
                        <input class="config-control" name="last_name" aria-label="Last Name" placeholder="Last Name" required maxlength="120" autocomplete="family-name" value="{{ old('last_name', $editingUser?->last_name ?? ($nameParts[1] ?? '')) }}">
                    </div>
                </fieldset>
                <label><span class="sr-only">Email</span><input class="config-control" type="email" name="email" placeholder="Email *" required maxlength="255" autocomplete="email" value="{{ old('email', $editingUser?->email) }}"></label>
                <div class="flex gap-2">
                    <input class="config-control" style="width: 80px; flex-shrink: 0" name="phone_country_code" aria-label="Phone country code" list="user-phone-codes" required pattern="\+[1-9][0-9]{0,3}" value="{{ old('phone_country_code', $editingUser?->phone_country_code ?? '+61') }}">
                    <datalist id="user-phone-codes"><option value="+61">Australia</option><option value="+63">Philippines</option><option value="+62">Indonesia</option><option value="+55">Brazil</option><option value="+84">Vietnam</option></datalist>
                    <input class="config-control" name="phone_number" aria-label="Phone" placeholder="Phone *" type="tel" required maxlength="40" autocomplete="tel-national" value="{{ old('phone_number', $editingUser?->phone_number) }}">
                </div>
                <label><span class="sr-only">User Role</span>
                    <select class="config-control" name="account_type" required aria-label="User Role">
                        <option value="">User Role *</option>
                        @foreach (\App\Models\User::CONFIGURABLE_ROLES as $role)<option value="{{ $role }}" @selected(old('account_type', $editingUser?->account_type) === $role)>{{ $role === 'Regional manager' ? 'Regional Manager' : $role }}</option>@endforeach
                    </select>
                </label>
                <label>User Picture
                    <input class="config-control mt-1" type="file" name="picture" accept="image/jpeg,image/png,image/webp" aria-describedby="user-picture-help">
                    <span id="user-picture-help" class="mt-1 block text-xs text-slate-500">JPG, PNG or WebP, up to 2 MB.</span>
                </label>
                @if ($editingUser?->picture_path)<img class="users-photo" src="{{ route('configuration.users.picture', $editingUser) }}" alt="{{ $editingUser->name }}">@endif
                <fieldset>
                    <legend>Portal Invite Status</legend>
                    <div class="users-radios">
                        @foreach (\App\Models\User::INVITE_STATUSES as $status)
                            <label><input type="radio" name="portal_invite_status" value="{{ $status }}" required @checked(old('portal_invite_status', $editingUser?->portal_invite_status ?? 'Send Invite') === $status)> {{ $status }}</label>
                        @endforeach
                    </div>
                </fieldset>
            </div>
            <div class="users-form-column">
                <fieldset>
                    <legend>User Status <span class="users-required">*</span></legend>
                    <div class="users-radios">
                        @foreach ([1 => 'Active', 0 => 'Inactive'] as $value => $label)<label><input type="radio" name="is_active" value="{{ $value }}" required @checked((int) old('is_active', $editingUser?->is_active ?? true) === $value)> {{ $label }}</label>@endforeach
                    </div>
                </fieldset>
                <fieldset>
                    <legend>User Round Robin Status <span class="users-required">*</span></legend>
                    <div class="users-radios">
                        @foreach ([1 => 'Include', 0 => 'Skip'] as $value => $label)<label><input type="radio" name="include_in_round_robin" value="{{ $value }}" required @checked((int) old('include_in_round_robin', $editingUser?->include_in_round_robin ?? true) === $value)> {{ $label }}</label>@endforeach
                    </div>
                </fieldset>
                <details class="user-office-picker">
                    <summary class="config-control" aria-label="Office"><span data-office-summary>{{ $selectedOffices ? implode(', ', $selectedOffices) : 'Office *' }}</span></summary>
                    <div class="user-office-options">
                        @foreach ($officeRecords->sortBy('code') as $office)
                            <label><input type="checkbox" data-office-choice name="offices[]" value="{{ $office->code }}" @checked(in_array($office->code, $selectedOffices, true))> {{ $office->code }} — {{ $office->name }}</label>
                        @endforeach
                    </div>
                </details>
                <label><span class="sr-only">Default Office</span>
                    <select class="config-control" data-default-office name="default_office_code" required aria-label="Default Office">
                        <option value="">Default Office *</option>
                        @foreach ($officeRecords->sortBy('code') as $office)<option value="{{ $office->code }}" @disabled(! in_array($office->code, $selectedOffices, true)) @selected($defaultOffice === $office->code)>{{ $office->code }}</option>@endforeach
                    </select>
                </label>
                <p class="text-xs leading-5 text-slate-500">Included users receive auto-assigned leads through their Default Office.</p>
            </div>
        </div>
        <div class="users-footer"><button type="submit" class="users-button users-primary">Submit and Exit</button></div>
    </form>
@else
    <form method="POST" action="{{ route('configuration.users.save') }}" id="users-list-form">
        @csrf
        @method('PATCH')
        <div class="users-toolbar">
            <h2>Users<span class="users-required" id="users-dirty-indicator" hidden> *</span></h2>
            <button class="users-button" type="submit" id="users-save" @disabled($users->isEmpty())>Save Changes</button>
            <a class="users-button" href="{{ route('configuration', ['tab' => 'users', 'sort' => $userSort, 'direction' => $userDirection]) }}">Remove Changes</a>
            <a class="users-button users-primary ml-auto" href="{{ route('configuration', ['tab' => 'users', 'action' => 'create']) }}">Add User</a>
        </div>
        <div class="users-panel overflow-x-auto">
            <table class="users-table">
                <thead><tr>
                    @foreach (['name' => 'Name', 'email' => 'Email', 'account_type' => 'User Role', 'picture' => 'User Picture', 'is_active' => 'User Status', 'offices' => 'Office', 'default_office_code' => 'Default Office', 'include_in_round_robin' => 'User Round Robin Status', 'id' => 'User ID', 'portal_invite_status' => 'Portal Invite Status'] as $key => $label)
                        <th scope="col" @if ($key !== 'picture') aria-sort="{{ $userSort === $key ? ($userDirection === 'asc' ? 'ascending' : 'descending') : 'none' }}" @endif>
                            @if ($key === 'picture') {{ $label }} @else
                                <a href="{{ route('configuration', ['tab' => 'users', 'sort' => $key, 'direction' => $userSort === $key && $userDirection === 'asc' ? 'desc' : 'asc']) }}">{{ $label }} <span aria-hidden="true">{{ $userSort === $key && $userDirection === 'asc' ? '⌃' : '⌄' }}</span></a>
                            @endif
                        </th>
                    @endforeach
                </tr></thead>
                <tbody>
                    @forelse ($users as $configuredUser)
                        @php
                            $key = $configuredUser->id;
                            $postedRow = old('users.'.$key);
                            $rowOffices = (array) old('users.'.$key.'.offices', $postedRow ? [] : $configuredUser->assignedOfficeCodes());
                            $rowDefault = old('users.'.$key.'.default_office_code', $configuredUser->defaultOfficeCode());
                        @endphp
                        <tr data-user-fields data-pending-change="{{ $postedRow ? '1' : '0' }}">
                            <td><input type="hidden" name="users[{{ $key }}][id]" value="{{ $key }}"><a class="users-name" href="{{ route('configuration', ['tab' => 'users', 'edit' => $key]) }}">{{ $configuredUser->name }}</a></td>
                            <td><a class="users-link" href="{{ route('configuration', ['tab' => 'users', 'edit' => $key]) }}">{{ $configuredUser->email }}</a></td>
                            <td><select name="users[{{ $key }}][account_type]" aria-label="{{ $configuredUser->name }} User Role" required>
                                @foreach (\App\Models\User::CONFIGURABLE_ROLES as $role)<option value="{{ $role }}" @selected(old('users.'.$key.'.account_type', $configuredUser->account_type) === $role)>{{ $role === 'Regional manager' ? 'Regional Manager' : $role }}</option>@endforeach
                            </select></td>
                            <td>@if ($configuredUser->picture_path)<img class="users-photo" loading="lazy" src="{{ route('configuration.users.picture', $configuredUser) }}" alt="{{ $configuredUser->name }}">@endif</td>
                            <td><select name="users[{{ $key }}][is_active]" aria-label="{{ $configuredUser->name }} User Status" required>
                                @foreach ([1 => 'Active', 0 => 'Inactive'] as $value => $label)<option value="{{ $value }}" @selected((int) old('users.'.$key.'.is_active', $configuredUser->is_active) === $value)>{{ $label }}</option>@endforeach
                            </select></td>
                            <td><details class="user-office-picker">
                                <summary aria-label="{{ $configuredUser->name }} Office"><span data-office-summary>{{ implode("\n", $rowOffices) ?: 'Select offices' }}</span></summary>
                                <div class="user-office-options">
                                    @foreach ($officeRecords->sortBy('code') as $office)<label><input type="checkbox" data-office-choice name="users[{{ $key }}][offices][]" value="{{ $office->code }}" @checked(in_array($office->code, $rowOffices, true))> {{ $office->code }}</label>@endforeach
                                </div>
                            </details></td>
                            <td><select name="users[{{ $key }}][default_office_code]" data-default-office aria-label="{{ $configuredUser->name }} Default Office" required>
                                <option value="">-Select-</option>
                                @foreach ($officeRecords->sortBy('code') as $office)<option value="{{ $office->code }}" @disabled(! in_array($office->code, $rowOffices, true)) @selected($rowDefault === $office->code)>{{ $office->code }}</option>@endforeach
                            </select></td>
                            <td><select name="users[{{ $key }}][include_in_round_robin]" aria-label="{{ $configuredUser->name }} User Round Robin Status" required>
                                @foreach ([1 => 'Include', 0 => 'Skip'] as $value => $label)<option value="{{ $value }}" @selected((int) old('users.'.$key.'.include_in_round_robin', $configuredUser->include_in_round_robin) === $value)>{{ $label }}</option>@endforeach
                            </select></td>
                            <td class="text-right">{{ $key }}</td>
                            <td><select name="users[{{ $key }}][portal_invite_status]" aria-label="{{ $configuredUser->name }} Portal Invite Status" required>
                                @foreach (\App\Models\User::INVITE_STATUSES as $status)<option value="{{ $status }}" @selected(old('users.'.$key.'.portal_invite_status', $configuredUser->portal_invite_status) === $status)>{{ $status }}</option>@endforeach
                            </select></td>
                        </tr>
                    @empty
                        <tr><td colspan="10">No users have been configured.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </form>
@endif
<script>
    const userListForm = document.getElementById('users-list-form');
    const userFieldValues = fields => JSON.stringify(Array.from(fields.querySelectorAll('input, select')).map(input => [input.name, input.value, input.type === 'checkbox' ? input.checked : null]));
    const updateUserSaveButton = () => {
        if (!userListForm) return;
        const dirty = !!userListForm.querySelector('tr.is-dirty');
        document.getElementById('users-dirty-indicator').hidden = !dirty;
        document.getElementById('users-save').disabled = !dirty;
    };
    document.querySelectorAll('[data-user-fields]').forEach(fields => {
        const originalValues = userFieldValues(fields);
        if (fields.dataset.pendingChange === '1') fields.classList.add('is-dirty');
        fields.addEventListener('change', event => {
            if (event.target.matches('[data-office-choice]')) {
                const selected = Array.from(fields.querySelectorAll('[data-office-choice]:checked')).map(input => input.value);
                fields.querySelector('[data-office-summary]').textContent = selected.join(fields.tagName === 'TR' ? '\n' : ', ') || 'Select offices';
                const defaultOffice = fields.querySelector('[data-default-office]');
                Array.from(defaultOffice.options).forEach(option => { option.disabled = !!option.value && !selected.includes(option.value); });
                if (!selected.includes(defaultOffice.value)) defaultOffice.value = '';
            }
            if (fields.tagName === 'TR') {
                fields.classList.toggle('is-dirty', fields.dataset.pendingChange === '1' || userFieldValues(fields) !== originalValues);
                updateUserSaveButton();
            }
        });
    });
    updateUserSaveButton();
    if (userListForm) {
        // Only changed rows are submitted, so unrelated legacy records cannot block a save.
        // Validate after disabling unchanged rows, including their required Default Office fields.
        userListForm.noValidate = true;
        userListForm.addEventListener('submit', event => {
            const unchanged = userListForm.querySelectorAll('tr[data-user-fields]:not(.is-dirty) input, tr[data-user-fields]:not(.is-dirty) select');
            unchanged.forEach(input => { input.disabled = true; });
            if (!userListForm.reportValidity()) {
                event.preventDefault();
                unchanged.forEach(input => { input.disabled = false; });
            }
        });
    }
    document.querySelectorAll('.user-office-picker').forEach(picker => {
        picker.addEventListener('keydown', event => {
            if (event.key === 'Escape') { picker.open = false; picker.querySelector('summary').focus(); }
        });
    });
</script>

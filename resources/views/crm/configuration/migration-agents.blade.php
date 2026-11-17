@php
    $showMigrationAgentForm = $editingMigrationAgent !== null || request('action') === 'create';
    $agentDraft = fn($key, $default = '') => is_scalar(old($key, $default)) ? old($key, $default) : $default;
@endphp
<section aria-labelledby="migration-agents-heading" class="overflow-hidden rounded-lg border border-slate-200 bg-white shadow-sm">
    <div class="flex flex-wrap items-center justify-between gap-3 border-b border-slate-200 p-5">
        <div>
            <h2 id="migration-agents-heading" class="text-base font-bold">{{ $showMigrationAgentForm ? ($editingMigrationAgent ? 'Edit Migration Agent' : 'Add Migration Agent') : 'Migration Agents' }}</h2>
            <p class="mt-1 text-sm text-slate-600">Registration, contact details and signature images for migration agents.</p>
        </div>
        @unless($showMigrationAgentForm)<a href="{{ route('configuration', ['tab' => 'migration-agents', 'action' => 'create']) }}" class="config-action config-primary">Add Migration Agent</a>@endunless
    </div>
    @if($showMigrationAgentForm)
        <form method="POST" enctype="multipart/form-data" action="{{ $editingMigrationAgent ? route('configuration.migration-agents.update', $editingMigrationAgent) : route('configuration.migration-agents.store') }}" class="space-y-5 p-5">
            @csrf
            @if($editingMigrationAgent) @method('PATCH') @endif
            <fieldset><legend class="mb-2 text-sm font-semibold">Agent Name *</legend><div class="grid gap-4 sm:grid-cols-2">
                @foreach(['first_name' => 'First Name', 'last_name' => 'Last Name'] as $field => $label)
                    <label class="block text-sm">{{ $label }}<input name="{{ $field }}" value="{{ $agentDraft($field, $editingMigrationAgent?->$field) }}" required maxlength="120" autocomplete="{{ $field === 'first_name' ? 'given-name' : 'family-name' }}" class="config-control mt-1" @error($field) aria-invalid="true" @enderror></label>
                @endforeach
            </div></fieldset>
            <label class="block text-sm font-semibold">Signature {{ $editingMigrationAgent ? '' : '*' }}
                <input id="agent-signature" name="signature" type="file" accept="image/png,image/jpeg,image/webp" @required(!$editingMigrationAgent) class="config-control mt-2" aria-describedby="agent-signature-help" @error('signature') aria-invalid="true" @enderror>
                <span id="agent-signature-help" class="mt-2 block text-xs font-normal text-slate-500">Upload a PNG, JPG or WebP image, up to 2 MB. {{ $editingMigrationAgent ? 'Leave blank to keep the current signature.' : '' }}</span>
            </label>
            <div class="rounded-md border border-slate-200 bg-white p-3" id="agent-signature-preview" @if(!$editingMigrationAgent) hidden @endif>
                <img id="agent-signature-image" @if($editingMigrationAgent) src="{{ route('configuration.migration-agents.signature', $editingMigrationAgent) }}" @endif alt="Signature preview" class="max-h-28 max-w-full object-contain">
            </div>
            <div class="grid gap-4 sm:grid-cols-2">
                <label class="block text-sm font-semibold">Registration Number *<input name="registration_number" value="{{ $agentDraft('registration_number', $editingMigrationAgent?->registration_number) }}" required maxlength="40" class="config-control mt-1" @error('registration_number') aria-invalid="true" @enderror></label>
                <label class="block text-sm font-semibold">Email<input name="email" type="email" value="{{ $agentDraft('email', $editingMigrationAgent?->email) }}" maxlength="255" autocomplete="email" class="config-control mt-1" @error('email') aria-invalid="true" @enderror></label>
            </div>
            <label class="block text-sm font-semibold">Linked CRM user (optional)
                <select name="user_id" class="config-control mt-1" @error('user_id') aria-invalid="true" @enderror>
                    <option value="">No linked user</option>
                    @foreach($users as $member)<option value="{{ $member->id }}" @selected((string)$agentDraft('user_id', $editingMigrationAgent?->user_id) === (string)$member->id)>{{ $member->name }} ({{ $member->email }})</option>@endforeach
                </select>
                <span class="mt-2 block text-xs font-normal text-slate-500">Link a CRM user to make these details available to their workflow and application records.</span>
            </label>
            <div class="flex gap-2 border-t border-slate-200 pt-5"><button type="submit" class="config-action config-primary">Submit and Exit</button><a href="{{ route('configuration', ['tab' => 'migration-agents']) }}" class="config-action config-secondary">Cancel</a></div>
        </form>
        <script>
            (() => {
                const input = document.getElementById('agent-signature');
                const image = document.getElementById('agent-signature-image');
                const preview = document.getElementById('agent-signature-preview');
                const original = image.getAttribute('src');
                let objectUrl;
                input.addEventListener('change', () => {
                    if (objectUrl) URL.revokeObjectURL(objectUrl);
                    const file = input.files[0];
                    if (file && ['image/png', 'image/jpeg', 'image/webp'].includes(file.type)) {
                        objectUrl = URL.createObjectURL(file);
                        image.src = objectUrl;
                        preview.hidden = false;
                    } else {
                        if (original) image.src = original; else image.removeAttribute('src');
                        preview.hidden = !original;
                    }
                });
                window.addEventListener('pagehide', () => { if (objectUrl) URL.revokeObjectURL(objectUrl); });
            })();
        </script>
    @else
        <div class="overflow-x-auto" tabindex="0" role="region" aria-label="Migration agents table">
            <table class="min-w-[800px] text-left text-sm"><thead class="bg-slate-50 text-xs text-slate-600"><tr>
                <th scope="col">Agent Name</th><th scope="col">Signature</th><th scope="col">Registration Number</th><th scope="col">Email</th><th scope="col" class="text-right">Actions</th>
            </tr></thead><tbody class="divide-y divide-slate-100">
                @forelse($migrationAgents as $agent)<tr data-migration-agent-row="{{ $agent->id }}">
                    <td><span class="font-semibold">{{ $agent->name }}</span>@if($agent->user)<span class="mt-1 block text-xs text-slate-500">CRM user: {{ $agent->user->name }}</span>@endif</td>
                    <td><img src="{{ route('configuration.migration-agents.signature', $agent) }}" alt="Signature of {{ $agent->name }}" class="h-12 w-36 rounded bg-white object-contain" loading="lazy"></td>
                    <td>{{ $agent->registration_number }}</td><td>{{ $agent->email ?: '—' }}</td>
                    <td><div class="flex justify-end gap-2"><a href="{{ route('configuration', ['tab' => 'migration-agents', 'edit' => $agent->id]) }}" class="config-action config-primary" aria-label="Edit {{ $agent->name }}">Edit</a>
                        <form method="POST" action="{{ route('configuration.migration-agents.destroy', $agent) }}" onsubmit="return confirm('Remove this migration agent and their signature image?')">@csrf @method('DELETE')<button type="submit" class="config-action config-danger" aria-label="Remove {{ $agent->name }}">Remove</button></form>
                    </div></td>
                </tr>@empty<tr><td colspan="5" class="py-10 text-center text-slate-500">No migration agents configured. Add an agent to get started.</td></tr>@endforelse
            </tbody></table>
        </div>
    @endif
</section>

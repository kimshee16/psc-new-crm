@extends('layouts.payroll')

@section('content')
    <header class="mb-6">
        <h1 class="text-[26px] font-bold">User Access &amp; Privileges</h1>
        <p class="mt-2 text-sm text-slate-600">Choose a role to control its CRM permissions. Changes apply to every user with that account type.</p>
    </header>
    @if(session('success'))
        <p role="status" class="mb-4 rounded-md bg-emerald-50 p-3 text-sm text-emerald-900">{{ session('success') }}</p>
    @endif
    @include('crm.organisations.errors')
    <nav aria-label="CRM roles" class="mb-5 flex flex-wrap gap-2">
        @foreach(\App\Support\PSC\CrmAccess::ROLES as $value => $label)
            <a href="{{ route('role-privileges', ['role' => $value]) }}" @if($value === $role) aria-current="page" @endif class="rounded-md border border-slate-300 bg-white px-3 py-2 text-sm font-semibold text-slate-700 aria-[current=page]:border-[#1f7890] aria-[current=page]:bg-[#eaf7fc]">{{ $label }}</a>
        @endforeach
    </nav>
    <form method="POST" action="{{ route('role-privileges.update', $role) }}" class="rounded-lg border border-slate-200 bg-white p-5">
        @csrf
        @method('PATCH')
        <h2 class="text-lg font-bold">{{ \App\Support\PSC\CrmAccess::ROLES[$role] }}</h2>
        @if($role === 'Super Admin')
            <p class="mt-2 text-sm text-slate-600">System Administrator access is fixed for account recovery.</p>
        @elseif(!$canEditRole)
            <p class="mt-2 text-sm text-slate-600">A System Administrator can edit this role.</p>
        @elseif(!$setting)
            <p class="mt-2 text-sm text-slate-600">This role currently uses the CRM's existing access rules.</p>
        @endif
        @php($selected = old('record_scope') !== null ? old('permissions', []) : ($setting?->permissions ?? $options))
        <fieldset class="mt-4" @disabled(!$canEditRole)>
            <legend class="font-semibold">Modules and actions</legend>
            <p class="mt-1 text-sm text-slate-600">Clear View to disable a module and its actions.</p>
            <div class="mt-3 grid gap-3 sm:grid-cols-2 xl:grid-cols-3">
                @foreach(\App\Support\PSC\CrmAccess::MODULES as $module => [$label, $actions])
                    @continue(!in_array($module.'.view', $options, true))
                    <fieldset class="rounded border border-slate-200 px-3 pb-3">
                        <legend class="px-1 text-sm font-semibold">{{ $label }}</legend>
                        <div class="flex flex-wrap gap-3 pt-1">
                            @foreach($actions as $action)
                                @php($permission = $module.'.'.$action)
                                @continue(!in_array($permission, $options, true))
                                <label class="flex items-center gap-1 text-sm">
                                    <input type="checkbox" name="permissions[]" class="accent-[#1f7890]" value="{{ $permission }}" @checked(in_array($permission, (array) $selected, true))>
                                    {{ ucfirst($action) }}
                                </label>
                            @endforeach
                        </div>
                    </fieldset>
                @endforeach
            </div>
            <div class="mt-5 grid gap-4 sm:grid-cols-2">
                <label class="block text-sm font-semibold">Record visibility
                    <select name="record_scope" class="mt-1 block w-full rounded-md border border-slate-300 p-2">
                        @foreach($scopes as $scope => $label)
                            <option value="{{ $scope }}" @selected(old('record_scope', $setting?->record_scope ?? 'existing') === $scope)>{{ $label }}</option>
                        @endforeach
                    </select>
                </label>
                <label class="block text-sm font-semibold">Notes access
                    <select name="notes_access" class="mt-1 block w-full rounded-md border border-slate-300 p-2">
                        @foreach(['hidden' => 'Hidden', 'read' => 'Read only', 'write' => 'Read and edit'] as $mode => $label)
                            <option value="{{ $mode }}" @selected(old('notes_access', $setting?->notes_access ?? 'write') === $mode)>{{ $label }}</option>
                        @endforeach
                    </select>
                </label>
            </div>
        </fieldset>
        @if($canEditRole)
            <button class="mt-5 rounded-md bg-[#15638e] px-4 py-2 text-sm font-semibold text-white">Save privileges</button>
        @endif
    </form>
@endsection

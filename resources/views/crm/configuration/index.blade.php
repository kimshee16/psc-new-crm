@extends('layouts.payroll')

@section('content')
    @php
        $activeConfigurationTab = $activeConfigurationTab ?? 'locations';
        $configurationTabs = [
            [
                'id' => 'locations',
                'label' => 'Locations',
                'description' => 'Current Location options and their default office routing.',
                'count' => $locations->count(),
            ],
            [
                'id' => 'education-levels',
                'label' => 'Highest Education Level',
                'description' => 'Education dropdown options shown on lead forms.',
                'count' => $educationLevels->count(),
            ],
        ];
        $activeTabMeta = collect($configurationTabs)->firstWhere('id', $activeConfigurationTab) ?? $configurationTabs[0];
    @endphp

    <style>
        .config-control {
            width: 100%;
            min-height: 36px;
            border: 1px solid #aeb7c4;
            border-radius: 4px;
            background: #fff;
            padding: 7px 10px;
            color: #334155;
            font-size: 13px;
            outline: none;
            transition: border-color 160ms ease, box-shadow 160ms ease;
        }

        .config-control:focus {
            border-color: #1f7890;
            box-shadow: 0 0 0 3px rgb(31 120 144 / 14%);
        }

        .config-control[readonly] {
            background: #f8fafc;
            color: #475569;
        }

        .config-control.is-invalid {
            border-color: #dc2626;
        }

        .config-action {
            display: inline-flex;
            min-height: 36px;
            align-items: center;
            justify-content: center;
            gap: 7px;
            border-radius: 6px;
            padding: 7px 12px;
            font-size: 13px;
            font-weight: 800;
            transition: background-color 160ms ease, border-color 160ms ease, color 160ms ease;
        }

        .config-primary {
            border: 1px solid #b9dce4;
            background: #eaf7fc;
            color: #1f5f74;
        }

        .config-primary:hover {
            border-color: #1f7890;
            background: #dff2f8;
        }

        .config-danger {
            border: 1px solid #fecaca;
            background: #fff;
            color: #b91c1c;
        }

        .config-danger:hover {
            border-color: #dc2626;
            background: #fef2f2;
        }
    </style>

    <header class="mb-7 flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
        <div>
            <div class="text-xs font-bold uppercase tracking-[0.12em] text-[#1f7890]">Configuration</div>
            <h1 class="mt-2 text-[26px] font-bold leading-tight text-[#101820]">Configurations</h1>
            <p class="mt-2 max-w-3xl text-sm leading-6 text-slate-600">
                Manage dropdown options and routing defaults used by lead forms and CRM records.
            </p>
        </div>
        <div class="rounded-md border border-slate-200 bg-white px-4 py-3 text-right shadow-sm">
            <div class="text-xs text-slate-500">{{ $activeTabMeta['label'] }}</div>
            <div class="mt-1 text-xl font-bold text-[#101820]">{{ $activeTabMeta['count'] }}</div>
        </div>
    </header>

    @if (session('success'))
        <div class="mb-5 rounded-md border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-semibold text-emerald-700">
            {{ session('success') }}
        </div>
    @endif

    @if (session('error'))
        <div class="mb-5 rounded-md border border-rose-200 bg-rose-50 px-4 py-3 text-sm font-semibold text-rose-700">
            {{ session('error') }}
        </div>
    @endif

    @if ($errors->any())
        <div class="mb-5 rounded-md border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-700">
            <div class="font-bold">The configuration was not saved. Complete the invalid fields.</div>
            <ul class="mt-2 list-disc pl-5">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <div class="grid gap-6 lg:grid-cols-[250px_minmax(0,1fr)] lg:items-start">
        <aside class="rounded-lg border border-slate-200 bg-white p-2 shadow-sm">
            @foreach ($configurationTabs as $tab)
                @php $isTabActive = $activeConfigurationTab === $tab['id']; @endphp
                <a href="{{ route('configuration', ['tab' => $tab['id']]) }}"
                   class="mb-1 block rounded-md px-4 py-3 transition {{ $isTabActive ? 'bg-[#eaf7fc] text-[#123348]' : 'text-slate-600 hover:bg-slate-50 hover:text-[#123348]' }}">
                    <span class="flex items-center justify-between gap-3">
                        <span class="text-sm font-bold">{{ $tab['label'] }}</span>
                        <span class="rounded-full bg-white px-2 py-0.5 text-xs font-bold text-slate-500">{{ $tab['count'] }}</span>
                    </span>
                    <span class="mt-1 block text-xs leading-5 {{ $isTabActive ? 'text-[#1f5f74]' : 'text-slate-500' }}">{{ $tab['description'] }}</span>
                </a>
            @endforeach
        </aside>

        <div>
            @if ($activeConfigurationTab === 'locations')
                <section class="mb-6 rounded-lg border border-slate-200 bg-white p-5 shadow-sm">
                    <h2 class="text-base font-bold text-[#101820]">Add Location</h2>
                    <form method="POST" action="{{ route('configuration.locations.store') }}" class="mt-4 grid grid-cols-1 gap-3 lg:grid-cols-[1fr_240px_auto] lg:items-end">
                        @csrf
                        <label class="block">
                            <span class="text-xs font-bold text-slate-600">Location</span>
                            <input name="name" value="{{ old('name') }}" required maxlength="120" class="config-control mt-1 @error('name') is-invalid @enderror">
                        </label>
                        <label class="block">
                            <span class="text-xs font-bold text-slate-600">Default Office</span>
                            <select name="office_code" required class="config-control mt-1 @error('office_code') is-invalid @enderror">
                                <option value="">Select office</option>
                                @foreach ($offices as $code => $office)
                                    <option value="{{ $code }}" @selected(old('office_code') === $code)>{{ $code }} - {{ $office }}</option>
                                @endforeach
                            </select>
                        </label>
                        <button type="submit" class="config-action config-primary">
                            {!! payroll_icon('plus', 'h-4 w-4') !!}
                            <span>Add</span>
                        </button>
                    </form>
                </section>

                <section class="rounded-lg border border-slate-200 bg-white shadow-sm">
                    <div class="flex flex-col gap-2 border-b border-slate-200 px-5 py-4 lg:flex-row lg:items-center lg:justify-between">
                        <div>
                            <h2 class="text-base font-bold text-[#101820]">Location Routing</h2>
                            <p class="mt-1 text-xs text-slate-500">These locations populate Current Location dropdowns and route new leads to the selected default office.</p>
                        </div>
                        <div class="text-xs font-bold text-slate-500">{{ $locations->count() }} total locations</div>
                    </div>

                    <div class="overflow-x-auto">
                        <table class="min-w-full text-left text-sm">
                            <thead class="bg-slate-50 text-[11px] uppercase tracking-[0.08em] text-slate-500">
                                <tr>
                                    <th class="min-w-[320px] px-5 py-3 font-bold">Location</th>
                                    <th class="w-72 px-5 py-3 font-bold">Default Office</th>
                                    <th class="w-56 px-5 py-3 text-right font-bold">Actions</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100">
                                @forelse ($locations as $location)
                                    @php
                                        $updateFormId = 'location-update-'.$location->id;
                                        $deleteFormId = 'location-delete-'.$location->id;
                                        $isRequiredLocation = $location->name === $requiredLocationName;
                                    @endphp
                                    <tr>
                                        <td class="px-5 py-4 align-top">
                                            <div class="flex flex-col gap-2">
                                                <input form="{{ $updateFormId }}" name="name" value="{{ $location->name }}" required maxlength="120" class="config-control" @readonly($isRequiredLocation)>
                                                @if ($isRequiredLocation)
                                                    <span class="text-xs font-semibold text-slate-500">Required catch-all option</span>
                                                @endif
                                            </div>
                                        </td>
                                        <td class="px-5 py-4 align-top">
                                            <select form="{{ $updateFormId }}" name="office_code" required class="config-control">
                                                @foreach ($offices as $code => $office)
                                                    <option value="{{ $code }}" @selected($location->office_code === $code)>{{ $code }} - {{ $office }}</option>
                                                @endforeach
                                            </select>
                                        </td>
                                        <td class="px-5 py-4 align-top">
                                            <div class="flex flex-wrap justify-end gap-2">
                                                <button form="{{ $updateFormId }}" type="submit" class="config-action config-primary">
                                                    {!! payroll_icon('check', 'h-4 w-4') !!}
                                                    <span>Save</span>
                                                </button>
                                                @if (! $isRequiredLocation)
                                                    <button form="{{ $deleteFormId }}" type="submit" class="config-action config-danger" onclick="return confirm('Remove this location option?')">
                                                        {!! payroll_icon('trash', 'h-4 w-4') !!}
                                                        <span>Remove</span>
                                                    </button>
                                                @endif
                                            </div>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="3" class="px-5 py-10 text-center text-sm text-slate-500">
                                            No location options have been configured.
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </section>
            @else
                <section class="mb-6 rounded-lg border border-slate-200 bg-white p-5 shadow-sm">
                    <h2 class="text-base font-bold text-[#101820]">Add Education Level</h2>
                    <form method="POST" action="{{ route('configuration.education-levels.store') }}" class="mt-4 grid grid-cols-1 gap-3 lg:grid-cols-[1fr_120px_120px_auto] lg:items-end">
                        @csrf
                        <label class="block">
                            <span class="text-xs font-bold text-slate-600">Option name</span>
                            <input name="name" value="{{ old('name') }}" maxlength="160" class="config-control mt-1 @error('name') is-invalid @enderror">
                        </label>
                        <label class="block">
                            <span class="text-xs font-bold text-slate-600">Sort order</span>
                            <input name="sort_order" value="{{ old('sort_order') }}" type="number" min="1" max="999" class="config-control mt-1 @error('sort_order') is-invalid @enderror">
                        </label>
                        <label class="flex min-h-9 items-center gap-2 pb-1 text-sm font-bold text-slate-700">
                            <input type="hidden" name="active" value="0">
                            <input type="checkbox" name="active" value="1" class="h-4 w-4 rounded border-slate-300 text-[#1f7890]" checked>
                            <span>Active</span>
                        </label>
                        <button type="submit" class="config-action config-primary">
                            {!! payroll_icon('plus', 'h-4 w-4') !!}
                            <span>Add</span>
                        </button>
                    </form>
                </section>

                <section class="rounded-lg border border-slate-200 bg-white shadow-sm">
                    <div class="flex flex-col gap-2 border-b border-slate-200 px-5 py-4 lg:flex-row lg:items-center lg:justify-between">
                        <div>
                            <h2 class="text-base font-bold text-[#101820]">Dropdown Options</h2>
                            <p class="mt-1 text-xs text-slate-500">Inactive rows stay saved here but are hidden from dropdown fields.</p>
                        </div>
                        <div class="text-xs font-bold text-slate-500">{{ $educationLevels->count() }} total options</div>
                    </div>

                    <div class="overflow-x-auto">
                        <table class="min-w-full text-left text-sm">
                            <thead class="bg-slate-50 text-[11px] uppercase tracking-[0.08em] text-slate-500">
                                <tr>
                                    <th class="w-28 px-5 py-3 font-bold">Order</th>
                                    <th class="min-w-[320px] px-5 py-3 font-bold">Option</th>
                                    <th class="w-28 px-5 py-3 font-bold">Active</th>
                                    <th class="w-56 px-5 py-3 text-right font-bold">Actions</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100">
                                @forelse ($educationLevels as $educationLevel)
                                    @php
                                        $updateFormId = 'education-level-update-'.$educationLevel->id;
                                        $deleteFormId = 'education-level-delete-'.$educationLevel->id;
                                    @endphp
                                    <tr>
                                        <td class="px-5 py-4 align-top">
                                            <input form="{{ $updateFormId }}" name="sort_order" value="{{ $educationLevel->sort_order }}" type="number" min="1" max="999" class="config-control">
                                        </td>
                                        <td class="px-5 py-4 align-top">
                                            <input form="{{ $updateFormId }}" name="name" value="{{ $educationLevel->name }}" maxlength="160" class="config-control">
                                        </td>
                                        <td class="px-5 py-4 align-top">
                                            <label class="inline-flex min-h-9 items-center gap-2 text-sm font-bold text-slate-700">
                                                <input form="{{ $updateFormId }}" type="hidden" name="active" value="0">
                                                <input form="{{ $updateFormId }}" type="checkbox" name="active" value="1" class="h-4 w-4 rounded border-slate-300 text-[#1f7890]" @checked($educationLevel->active)>
                                                <span>{{ $educationLevel->active ? 'Yes' : 'No' }}</span>
                                            </label>
                                        </td>
                                        <td class="px-5 py-4 align-top">
                                            <div class="flex flex-wrap justify-end gap-2">
                                                <button form="{{ $updateFormId }}" type="submit" class="config-action config-primary">
                                                    {!! payroll_icon('check', 'h-4 w-4') !!}
                                                    <span>Save</span>
                                                </button>
                                                <button form="{{ $deleteFormId }}" type="submit" class="config-action config-danger" onclick="return confirm('Remove this education level option?')">
                                                    {!! payroll_icon('trash', 'h-4 w-4') !!}
                                                    <span>Remove</span>
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="4" class="px-5 py-10 text-center text-sm text-slate-500">
                                            No education level options have been configured.
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </section>
            @endif
        </div>
    </div>

    @foreach ($locations as $location)
        <form id="location-update-{{ $location->id }}" method="POST" action="{{ route('configuration.locations.update', $location) }}">
            @csrf
            @method('PATCH')
        </form>
        <form id="location-delete-{{ $location->id }}" method="POST" action="{{ route('configuration.locations.destroy', $location) }}">
            @csrf
            @method('DELETE')
        </form>
    @endforeach

    @foreach ($educationLevels as $educationLevel)
        <form id="education-level-update-{{ $educationLevel->id }}" method="POST" action="{{ route('configuration.education-levels.update', $educationLevel) }}">
            @csrf
            @method('PATCH')
        </form>
        <form id="education-level-delete-{{ $educationLevel->id }}" method="POST" action="{{ route('configuration.education-levels.destroy', $educationLevel) }}">
            @csrf
            @method('DELETE')
        </form>
    @endforeach
@endsection

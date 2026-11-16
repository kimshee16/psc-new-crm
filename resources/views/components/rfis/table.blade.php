@props(['rfis', 'filterable' => false, 'filters' => [], 'officers' => [], 'returnTo' => 'list'])
@php
    $columns = \App\Support\PSC\RfiList::COLUMNS;
    if (!$filterable) unset($columns['application_id']);
@endphp
<div class="mt-4 overflow-x-auto" tabindex="0" role="region" aria-label="RFI table; scroll horizontally for all columns">
    <table class="rfi-table">
        <caption class="sr-only">Requests for information and their response progress</caption>
        <thead><tr>
            @foreach($columns as $column => $label)
                @php
                    $sorted = ($filters['sort'] ?? 'due_date') === $column;
                @endphp
                <th scope="col" @if($filterable && in_array($column, \App\Support\PSC\RfiList::SORTABLE)) aria-sort="{{ $sorted ? (($filters['direction'] ?? 'asc') === 'asc' ? 'ascending' : 'descending') : 'none' }}" @endif>
                    @if($filterable && in_array($column, \App\Support\PSC\RfiList::SORTABLE))
                        <button type="button" data-rfi-sort="{{ $column }}" class="text-left font-bold" aria-label="Sort {{ $label }}">{{ $label }} <span aria-hidden="true">{{ $sorted ? (($filters['direction'] ?? 'asc') === 'asc' ? '↑' : '↓') : '↕' }}</span></button>
                    @else {{ $label }} @endif
                    @if($filterable)
                        @if($column === 'application_id')
                            <input class="rfi-filter" type="search" name="application_id" value="{{ $filters['application_id'] ?? '' }}" maxlength="80" placeholder="Search ID" aria-label="Search Application ID">
                        @elseif(in_array($column, ['request_type', 'status', 'officer']))
                            @php
                                $key = $column === 'officer' ? 'assigned_to_user_id' : $column;
                                $options = $column === 'officer' ? collect($officers)->mapWithKeys(fn($user) => [$user->id => $user->name])->all() : array_combine($column === 'status' ? \App\Models\ApplicationRfi::STATUSES : \App\Models\ApplicationRfi::TYPES, $column === 'status' ? \App\Models\ApplicationRfi::STATUSES : \App\Models\ApplicationRfi::TYPES);
                            @endphp
                            <details class="rfi-filter"><summary class="cursor-pointer" aria-label="Filter {{ $label }}">{{ empty($filters[$key]) ? 'All' : count($filters[$key]).' selected' }}</summary><div class="mt-3 max-h-52 space-y-2 overflow-y-auto">
                                @foreach($options as $value => $option)<label class="flex items-start gap-2"><input type="checkbox" name="{{ $key }}[]" value="{{ $value }}" @checked(in_array((string) $value, array_map('strval', $filters[$key] ?? []), true))> {{ $option }}</label>@endforeach
                            </div></details>
                        @elseif(in_array($column, ['received_date', 'due_date', 'lodged_date']))
                            <label class="mt-2 block font-normal">From<input class="rfi-filter rfi-date-filter" type="date" name="{{ $column }}_from" value="{{ $filters[$column.'_from'] ?? '' }}" aria-label="{{ $label }} from"></label>
                            <label class="mt-2 block font-normal">To<input class="rfi-filter rfi-date-filter" type="date" name="{{ $column }}_to" value="{{ $filters[$column.'_to'] ?? '' }}" aria-label="{{ $label }} to"></label>
                        @endif
                    @endif
                </th>
            @endforeach
            <th scope="col">Actions</th>
        </tr></thead>
        <tbody>
            @forelse($rfis as $rfi)
                <tr data-rfi-row="{{ $rfi->id }}">
                    @if($filterable)<td>
                        @if(in_array(auth()->id(), [$rfi->application->primary_counsellor_id, $rfi->application->secondary_counsellor_id, $rfi->application->migration_agent_id], true))
                            <a href="{{ route('applications.show', ['application' => $rfi->application_id, 'tab' => 'rfi']) }}#application-tabs" class="font-semibold text-[#1f7890] underline">{{ str_pad((string) $rfi->application_id, 5, '0', STR_PAD_LEFT) }}</a>
                        @else {{ str_pad((string) $rfi->application_id, 5, '0', STR_PAD_LEFT) }} @endif
                    </td>@endif
                    <td class="font-medium">{{ $rfi->request_type === 'Other' ? 'Other: ' : '' }}{{ $rfi->request_label }}</td>
                    <td class="whitespace-nowrap">{{ $rfi->received_date?->format('d/m/Y') ?? '—' }}</td>
                    <td class="whitespace-nowrap {{ $rfi->status !== 'Closed' && $rfi->due_date?->isBefore(today()) ? 'font-semibold text-red-700' : '' }}">{{ $rfi->due_date?->format('d/m/Y') ?? '—' }}</td>
                    <td><span class="rounded-full px-2 py-1 text-xs {{ $rfi->status === 'Closed' ? 'bg-green-100 text-green-800' : 'bg-slate-100 text-slate-700' }}">{{ $rfi->status }}</span></td>
                    <td>{{ $rfi->officer?->name ?? 'Unassigned' }}</td>
                    <td>@if($rfi->request_letter_path)<a href="{{ route('rfis.document', ['rfi' => $rfi, 'document' => 'request_letter']) }}" target="_blank" rel="noopener" class="font-semibold text-[#1f7890] underline" aria-label="View request letter for RFI {{ $rfi->id }}">View</a>@else — @endif</td>
                    <td class="whitespace-nowrap">{{ $rfi->lodged_date?->format('d/m/Y') ?? '—' }}</td>
                    <td>@if($rfi->proof_of_lodgement_path)<a href="{{ route('rfis.document', ['rfi' => $rfi, 'document' => 'proof_of_lodgement']) }}" target="_blank" rel="noopener" class="font-semibold text-[#1f7890] underline" aria-label="View proof of lodgement for RFI {{ $rfi->id }}">View</a>@else — @endif</td>
                    <td><button type="button" data-rfi-notes="rfi-notes-{{ $rfi->id }}" class="flex items-center gap-1 rounded-md border border-slate-300 px-3 py-2 text-[#1f7890]" aria-label="Open notes for RFI {{ $rfi->id }}">{!! payroll_icon('note', 'h-4 w-4') !!}<span>{{ filled($rfi->notes) ? 'Notes' : 'Add notes' }}</span></button></td>
                    <td><a href="{{ route('rfis.edit', ['rfi' => $rfi, 'return_to' => $returnTo]) }}" class="font-semibold text-[#1f7890] underline">Edit</a></td>
                </tr>
            @empty
                <tr><td colspan="{{ count($columns) + 1 }}" class="h-32 text-center text-slate-500">No RFI records yet.</td></tr>
            @endforelse
        </tbody>
    </table>
</div>

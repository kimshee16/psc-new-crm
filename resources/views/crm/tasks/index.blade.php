@extends('layouts.payroll')

@section('content')
    @php($taskDraft = fn($key, $default = '') => is_scalar(old($key, $default)) ? old($key, $default) : $default)
    <style>
        .tasks-table { width: 100%; min-width: 1050px; table-layout: fixed; }
        .tasks-table th, .tasks-table td { padding: 12px 14px; vertical-align: top; overflow-wrap: anywhere; }
        .tasks-table .task-description-column { width: 28%; }
        .tasks-table .task-related-column { width: 26%; }
        .tasks-table .task-date-column, .tasks-table .task-status-column { width: 12%; }
        .tasks-table .task-person-column { width: 11%; }
        .task-related-link { display: block; border-radius: 3px; color: #1f7890; }
        .task-related-link:hover .task-related-context { text-decoration: underline; }
        .task-related-context { display: block; font-weight: 600; }
        .task-related-reference { display: block; margin-top: 3px; font-size: 12px; color: #64748b; }
        .tasks-table tbody tr { border-top: 1px solid #e2e8f0; }
        .tasks-table tbody tr:nth-child(even) { background: #f8fafc; }
        .task-filter { width: 100%; margin-top: 10px; border: 1px solid #cbd5e1; border-radius: 6px; padding: 8px; background: white; font-weight: 400; }
        .task-dialog { width: min(600px, calc(100vw - 32px)); max-height: calc(100dvh - 32px); margin: auto; border: 0; border-radius: 12px; padding: 24px; box-shadow: 0 24px 50px #0004; }
        .task-dialog::backdrop { background: #20282e99; }
        .task-dialog label { display: block; font-size: 14px; font-weight: 600; }
        .task-dialog input, .task-dialog select { display: block; width: 100%; margin-top: 6px; border: 1px solid #cbd5e1; border-radius: 6px; padding: 10px; font-weight: 400; }
        .task-dialog input[readonly] { background: #f1f5f9; }
        .task-dialog :focus-visible, #tasks-filters :focus-visible { outline: 2px solid #15638e; outline-offset: 3px; }
    </style>
    <header class="mb-7 flex flex-wrap items-center justify-between gap-4">
        <div><h1 class="text-[26px] font-bold text-[#101820]">Tasks</h1><p class="mt-2 text-sm text-slate-600">Assigned work across the CRM within your access.</p></div>
        <button type="button" id="add-task" class="rounded-md bg-[#1f7890] px-4 py-2.5 text-sm font-bold text-white">+ Add new task</button>
    </header>
    @if(session('success'))<div role="status" class="mb-4 rounded-md bg-green-50 p-4 text-sm text-green-800">{{ session('success') }}</div>@endif
    @if($errors->any() && !old('_add_task'))
        <div role="alert" class="mb-4 rounded-md bg-red-50 p-4 text-sm text-red-800">@foreach($errors->all() as $error)<p>{{ $error }}</p>@endforeach</div>
    @endif
    <form id="tasks-filters" method="GET" action="{{ route('tasks') }}">
        <input type="hidden" name="direction" value="{{ $filters['direction'] ?? 'asc' }}">
        <div class="mb-4 flex flex-wrap gap-2">
            <button class="rounded-md bg-[#1f7890] px-4 py-2 text-sm font-semibold text-white">Apply filters</button>
            <a href="{{ route('tasks') }}" class="rounded-md border border-slate-300 bg-white px-4 py-2 text-sm">Clear filters</a>
            <button formaction="{{ route('tasks.export') }}" class="rounded-md border border-slate-300 bg-white px-4 py-2 text-sm">Download CSV</button>
        </div>
        <section class="rounded-lg border border-slate-200 bg-white shadow-sm" aria-label="Tasks list">
            <p class="px-5 py-4 text-sm text-slate-600" role="status">Showing {{ $tasks->firstItem() ?? 0 }}–{{ $tasks->lastItem() ?? 0 }} of {{ $tasks->total() }} tasks</p>
            <div class="overflow-x-auto" tabindex="0" role="region" aria-label="Tasks table; scroll horizontally for all columns">
                <table class="tasks-table text-left text-sm">
                    <caption class="sr-only">Tasks visible to you. Search descriptions and related IDs, select filters or sort by complete by date.</caption>
                    <colgroup><col class="task-description-column"><col class="task-related-column"><col class="task-date-column"><col class="task-status-column"><col class="task-person-column"><col class="task-person-column"></colgroup>
                    <thead class="bg-slate-50 text-xs text-slate-600"><tr>
                        <th scope="col"><label for="task-description">Task Description</label><input id="task-description" class="task-filter" type="search" name="description" maxlength="255" value="{{ $filters['description'] ?? '' }}" placeholder="Type a few characters"></th>
                        <th scope="col"><label for="task-relates">Relates to</label><input id="task-relates" class="task-filter" type="search" name="relates_to" maxlength="80" value="{{ $filters['relates_to'] ?? '' }}" placeholder="Lead / application ID"></th>
                        <th scope="col" aria-sort="{{ ($filters['direction'] ?? 'asc') === 'asc' ? 'ascending' : 'descending' }}"><button type="button" id="task-date-sort" class="text-left font-bold" aria-label="Sort complete by date {{ ($filters['direction'] ?? 'asc') === 'asc' ? 'latest first' : 'earliest first' }}">Complete by date <span aria-hidden="true">{{ ($filters['direction'] ?? 'asc') === 'asc' ? '↑' : '↓' }}</span></button></th>
                        @foreach(['status' => ['Status', collect(\App\Models\ApplicationActivity::TASK_STATUSES)->map(fn($status) => (object) ['id' => $status, 'name' => $status])], 'assignee' => ['Assignee', $assignees], 'creator' => ['Task created by', $creators]] as $key => [$label, $options])
                            <th scope="col">{{ $label }}
                                <details class="task-filter"><summary class="cursor-pointer">{{ empty($filters[$key]) ? 'All' : count($filters[$key]).' selected' }}</summary>
                                    <div class="mt-3 max-h-52 space-y-2 overflow-y-auto">
                                        @forelse($options as $option)<label class="flex items-start gap-2"><input type="checkbox" name="{{ $key }}[]" value="{{ $option->id }}" @checked(in_array((string) $option->id, array_map('strval', $filters[$key] ?? []), true))> {{ $option->name }}</label>@empty<p>No options</p>@endforelse
                                    </div>
                                </details>
                            </th>
                        @endforeach
                    </tr></thead>
                    <tbody>
                        @forelse($tasks as $task)
                            <tr data-task-row="{{ $task->id }}">
                                <td class="font-medium text-[#101820]">{{ $task->title }}</td>
                                <td>
                                    @if($task->application)
                                        <a href="{{ route('applications.show', $task->application_id) }}" class="task-related-link" aria-label="Open {{ $task->related_reference }}{{ $task->related_context ? ' for '.$task->related_context : '' }}">
                                            <span class="task-related-context">{{ $task->related_context ?: $task->related_reference }}</span>
                                            <span class="task-related-reference">{{ $task->related_reference }} · {{ $task->application->type_label }}</span>
                                        </a>
                                    @elseif($task->lead)
                                        <span class="task-related-context">{{ $task->related_context ?: $task->related_reference }}</span>
                                        <span class="task-related-reference">{{ $task->related_reference }} · Lead</span>
                                    @else <span class="text-slate-500">—</span> @endif
                                </td>
                                <td class="whitespace-nowrap {{ $task->due_date?->isBefore(today()) && $task->task_status !== 'Done' ? 'font-semibold text-red-700' : '' }}">{{ $task->due_date?->format('d/m/Y') ?? '—' }}</td>
                                <td><span class="inline-block rounded-full px-2.5 py-1 text-xs font-medium {{ $task->task_status === 'Done' ? 'bg-green-100 text-green-800' : 'bg-slate-100 text-slate-700' }}">{{ $task->task_status }}</span></td>
                                <td>{{ $task->assignee?->name ?? 'Unassigned' }}</td>
                                <td>{{ $task->creator?->name ?? 'Unknown (legacy task)' }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="6" class="h-36 text-center text-slate-500">No tasks match your access and filters.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="flex flex-wrap items-center justify-between gap-4 border-t border-slate-200 px-5 py-4">
                <label class="flex items-center gap-2 text-sm text-slate-600">Records per page<select id="tasks-per-page" name="per_page" class="rounded-md border border-slate-300 px-3 py-2">@foreach([10, 20, 50] as $size)<option value="{{ $size }}" @selected(($filters['per_page'] ?? 10) == $size)>{{ $size }}</option>@endforeach</select></label>
                <div class="min-w-0 flex-1">{{ $tasks->onEachSide(1)->links() }}</div>
            </div>
        </section>
    </form>
    <dialog id="task-dialog" class="task-dialog" aria-labelledby="task-dialog-title">
        <form method="POST" action="{{ route('tasks.store') }}" class="space-y-5">
            @csrf<input type="hidden" name="_add_task" value="1">
            <div class="flex items-center justify-between"><h2 id="task-dialog-title" class="text-xl font-bold">Add new task</h2><button type="button" data-close-task aria-label="Close Add new task" class="px-2 text-2xl">&times;</button></div>
            @if($errors->any() && old('_add_task'))<div role="alert" class="rounded-md bg-red-50 p-3 text-sm text-red-800">@foreach($errors->all() as $error)<p>{{ $error }}</p>@endforeach</div>@endif
            <label for="new-task-title">Task Description *<input id="new-task-title" name="title" value="{{ $taskDraft('title') }}" maxlength="255" required></label>
            <label for="new-task-date">Complete by *<input id="new-task-date" name="due_date" type="date" min="{{ today()->format('Y-m-d') }}" value="{{ $taskDraft('due_date', today()->format('Y-m-d')) }}" required></label>
            <label for="new-task-assignee">Assignee *<select id="new-task-assignee" name="assigned_to_user_id" required><option value="">Select user</option>@foreach($staff as $member)<option value="{{ $member->id }}" @selected((string) $taskDraft('assigned_to_user_id', auth()->id()) === (string) $member->id)>{{ $member->name }}</option>@endforeach</select></label>
            <label for="new-task-creator">Task created by<input id="new-task-creator" readonly value="{{ auth()->user()->name }}"></label>
            <label for="new-task-status">Status *<select id="new-task-status" name="status" required>@foreach(\App\Models\ApplicationActivity::TASK_STATUSES as $status)<option @selected(old('status', 'To Do') === $status)>{{ $status }}</option>@endforeach</select></label>
            <div class="grid gap-4 sm:grid-cols-2">
                <label for="new-task-related-type">Relates to (optional)<select id="new-task-related-type" name="related_type"><option value="">No linked record</option><option value="application" @selected(old('related_type') === 'application')>Application</option><option value="lead" @selected(old('related_type') === 'lead')>Lead</option></select></label>
                <label for="new-task-related-id">Lead / application ID<input id="new-task-related-id" name="related_id" value="{{ $taskDraft('related_id') }}" maxlength="80" placeholder="e.g. PSC-0001"></label>
            </div>
            <div class="flex justify-end gap-3 border-t border-slate-200 pt-4"><button type="button" data-close-task class="rounded-md border border-slate-300 px-5 py-2">Cancel</button><button type="submit" class="rounded-md bg-[#1f7890] px-5 py-2 font-semibold text-white">Add</button></div>
        </form>
    </dialog>
    <script>
        (() => {
            const dialog = document.getElementById('task-dialog');
            const form = document.getElementById('tasks-filters');
            document.getElementById('add-task').addEventListener('click', () => dialog.showModal());
            dialog.querySelectorAll('[data-close-task]').forEach(button => button.addEventListener('click', () => dialog.close()));
            dialog.querySelector('form').addEventListener('submit', event => {
                if (event.target.checkValidity()) event.target.querySelector('[type="submit"]').disabled = true;
            });
            document.getElementById('task-date-sort').addEventListener('click', () => {
                form.elements.direction.value = form.elements.direction.value === 'asc' ? 'desc' : 'asc';
                form.requestSubmit();
            });
            document.getElementById('tasks-per-page').addEventListener('change', () => form.requestSubmit());
            @if($errors->any() && old('_add_task'))dialog.showModal();@endif
        })();
    </script>
@endsection

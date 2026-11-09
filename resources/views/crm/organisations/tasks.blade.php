<div class="overflow-x-auto">
    <table class="w-full text-left text-sm">
        <thead class="border-b border-slate-200 text-xs text-slate-500"><tr>@foreach(['Task description*', 'Linked record', 'Complete by', 'Assigned to*', 'Status*', 'Actions'] as $label)<th scope="col" class="px-3 py-3">{{ $label }}</th>@endforeach</tr></thead>
        <tbody class="divide-y divide-slate-100">
            @forelse($activities as $task)
                <tr>
                    <td class="px-3 py-4 font-medium">{{ $task->title }}</td>
                    <td class="px-3 py-4"><a href="{{ route('applications.show', ['application' => $task->application_id, 'tab' => 'tasks']) }}#application-tabs" class="text-[#15638e] underline">{{ $task->application->record_reference }}</a></td>
                    <td class="whitespace-nowrap px-3 py-4">{{ $task->due_date?->format('d M Y') ?? '—' }}</td>
                    <td class="px-3 py-4">{{ $task->assignee?->name ?? 'Unassigned' }}</td>
                    <td class="px-3 py-4">
                        <form method="POST" action="{{ route('organisations.tasks.update', [$organisation, $task]) }}">
                            @csrf @method('PATCH')
                            <input type="hidden" name="_task_status" value="{{ $task->id }}">
                            <select name="status" aria-label="Status for {{ $task->title }}" onchange="this.form.requestSubmit()" class="min-w-32 rounded-md border border-slate-600 bg-white px-3 py-2.5">
                                @if(!in_array($task->status, \App\Models\ApplicationActivity::TASK_STATUSES))<option value="{{ $task->status }}" selected disabled>{{ $task->status ?: 'Choose status' }}</option>@endif
                                @foreach(\App\Models\ApplicationActivity::TASK_STATUSES as $status)<option @selected($task->status === $status)>{{ $status }}</option>@endforeach
                            </select>
                            <noscript><button class="mt-2 text-[#15638e] underline">Save status</button></noscript>
                        </form>
                    </td>
                    <td class="px-3 py-4">
                        <div class="flex items-center justify-end gap-3">
                            <button type="button" aria-label="Edit task {{ $task->title }}" aria-controls="task-editor-{{ $task->id }}" aria-expanded="{{ old('_task_form') === (string) $task->id ? 'true' : 'false' }}" onclick="const editor = document.getElementById('task-editor-{{ $task->id }}'); editor.hidden = !editor.hidden; this.setAttribute('aria-expanded', !editor.hidden);" class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-[#0d817c] text-white">{!! payroll_icon('edit', 'h-4 w-4') !!}</button>
                            <form method="POST" action="{{ route('organisations.tasks.destroy', [$organisation, $task]) }}" onsubmit="return confirm('Delete this task?')">
                                @csrf @method('DELETE')
                                <button aria-label="Delete task {{ $task->title }}" class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-[#bf241b] text-white">{!! payroll_icon('trash', 'h-4 w-4') !!}</button>
                            </form>
                        </div>
                    </td>
                </tr>
                <tr id="task-editor-{{ $task->id }}" @if(old('_task_form') !== (string) $task->id) hidden @endif><td colspan="6" class="bg-slate-50 p-4">@include('crm.organisations.task-form', ['task' => $task])</td></tr>
            @empty
                <tr><td colspan="6" class="px-3 py-8 text-center text-slate-500">No tasks records yet.</td></tr>
            @endforelse
        </tbody>
    </table>
</div>
@if($activities->hasPages())<div class="mt-4">{{ $activities->links() }}</div>@endif
@if($taskApplications->isNotEmpty())
    <details class="mt-6" @if(old('_task_form') === 'new') open @endif>
        <summary class="w-fit cursor-pointer text-sm font-semibold text-[#15638e]"><span class="mr-2 text-xl" aria-hidden="true">+</span> Add task</summary>
        <div class="mt-4">@include('crm.organisations.task-form', ['task' => null])</div>
    </details>
@else
    <p class="mt-5 text-sm text-slate-500">Add a sponsorship or nomination with Tasks enabled to create a task.</p>
@endif

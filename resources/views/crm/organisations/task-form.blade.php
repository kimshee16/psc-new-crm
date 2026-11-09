@php
    $taskFormId = (string) ($task?->id ?? 'new');
    $taskDraft = old('_task_form') === $taskFormId;
@endphp
<form method="POST" action="{{ $task ? route('organisations.tasks.update', [$organisation, $task]) : route('organisations.tasks.store', $organisation) }}" class="grid max-w-4xl gap-4 text-sm sm:grid-cols-2">
    @csrf @if($task) @method('PATCH') @endif
    <input type="hidden" name="_task_form" value="{{ $taskFormId }}">
    <label class="sm:col-span-2" for="task-{{ $taskFormId }}-title">Task description *
        <input id="task-{{ $taskFormId }}-title" name="title" required maxlength="255" value="{{ $taskDraft ? old('title') : $task?->title }}" class="mt-1 block w-full rounded-md border border-slate-300 px-3 py-2">
    </label>
    @if(!$task)
        <label for="task-new-application">Linked record *
            <select id="task-new-application" name="application_id" required class="mt-1 block w-full rounded-md border border-slate-300 bg-white px-3 py-2">
                <option value="">Select sponsorship or nomination</option>
                @foreach($taskApplications as $source)<option value="{{ $source->id }}" @selected($taskDraft && (string) old('application_id') === (string) $source->id)>{{ $source->record_reference }} — {{ $source->category === 'SBS' ? ($source->sponsor_type ?: 'Sponsorship') : ($source->nominated_position_title ?: 'Nomination') }}</option>@endforeach
            </select>
        </label>
    @endif
    <label for="task-{{ $taskFormId }}-due">Complete by
        <input id="task-{{ $taskFormId }}-due" name="due_date" type="date" value="{{ $taskDraft ? old('due_date') : $task?->due_date?->format('Y-m-d') }}" class="mt-1 block w-full rounded-md border border-slate-300 px-3 py-2">
    </label>
    <label for="task-{{ $taskFormId }}-assignee">Assigned to *
        <select id="task-{{ $taskFormId }}-assignee" name="assigned_to_user_id" required class="mt-1 block w-full rounded-md border border-slate-300 bg-white px-3 py-2">
            <option value="">Select staff member</option>
            @foreach($staff as $member)<option value="{{ $member->id }}" @selected((string) ($taskDraft ? old('assigned_to_user_id') : $task?->assigned_to_user_id) === (string) $member->id)>{{ $member->name }}</option>@endforeach
        </select>
    </label>
    <label for="task-{{ $taskFormId }}-status">Status *
        <select id="task-{{ $taskFormId }}-status" name="status" required class="mt-1 block w-full rounded-md border border-slate-300 bg-white px-3 py-2">
            @foreach(\App\Models\ApplicationActivity::TASK_STATUSES as $status)<option @selected(($taskDraft ? old('status') : ($task?->status ?? 'Pending')) === $status)>{{ $status }}</option>@endforeach
        </select>
    </label>
    <div class="sm:col-span-2"><button type="submit" class="rounded-md bg-[#15638e] px-4 py-2 font-semibold text-white">{{ $task ? 'Save task' : 'Add task' }}</button></div>
</form>

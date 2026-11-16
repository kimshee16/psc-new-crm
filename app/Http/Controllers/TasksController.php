<?php

namespace App\Http\Controllers;

use App\Models\Application;
use App\Models\ApplicationActivity;
use App\Models\User;
use App\Support\PSC\TaskList;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class TasksController extends Controller
{
    public function __construct(private readonly TaskList $list) {}

    public function index(Request $request): View
    {
        $filters = $this->filters($request);
        $visible = $this->list->visible($request->user());

        return view('crm.tasks.index', [
            'title' => 'PSC CRM - Tasks', 'filters' => $filters,
            'tasks' => $this->list->filtered($request->user(), $filters)->paginate($filters['per_page'] ?? 10)->withQueryString(),
            'assignees' => $this->list->staff($request->user())->orderBy('name')->get(),
            'creators' => User::query()->whereIn('id', (clone $visible)->select('created_by_user_id'))->orderBy('name')->get(),
            'staff' => User::query()->where('is_active', true)->where('portal_invite_status', '!=', 'Restrict Access')->orderBy('name')->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'due_date' => ['required', 'date_format:Y-m-d', 'after_or_equal:'.now(config('app.timezone'))->toDateString()],
            'assigned_to_user_id' => ['required', 'integer', Rule::exists('users', 'id')->where('is_active', true)->whereNot('portal_invite_status', 'Restrict Access')],
            'status' => ['required', Rule::in(ApplicationActivity::TASK_STATUSES)],
            'related_type' => ['nullable', Rule::in(['application', 'lead'])],
            'related_id' => ['nullable', 'required_with:related_type', 'string', 'max:80'],
        ]);
        $task = new ApplicationActivity(collect($data)->only(['title', 'due_date', 'assigned_to_user_id', 'status'])->all() + ['kind' => 'tasks']);
        if (filled($data['related_id'] ?? null)) {
            if (($data['related_type'] ?? '') === 'application') {
                $reference = strtoupper(trim($data['related_id']));
                $id = preg_replace('/^(PSC|NOM|SBS|LAB)-/', '', $reference);
                $application = ctype_digit($id) ? Application::query()->assignedTo($request->user())->find((int) $id) : null;
                if (! $application || (str_contains($reference, '-') && $reference !== $application->record_reference)) {
                    throw ValidationException::withMessages(['related_id' => 'Choose an application you have permission to view.']);
                }
                $task->application()->associate($application);
            } elseif (($data['related_type'] ?? '') === 'lead') {
                try {
                    $task->lead()->associate(app(LeadsController::class)->noteRecord(trim($data['related_id']), $request->user()));
                } catch (ModelNotFoundException) {
                    throw ValidationException::withMessages(['related_id' => 'Choose a lead you have permission to view.']);
                }
            } else {
                throw ValidationException::withMessages(['related_type' => 'Choose the related record type.']);
            }
        }
        $task->save();

        return redirect()->route('tasks')->with('success', 'Task added.');
    }

    public function export(Request $request): StreamedResponse
    {
        $query = $this->list->filtered($request->user(), $this->filters($request));

        return response()->streamDownload(function () use ($query): void {
            $handle = fopen('php://output', 'w');
            fwrite($handle, "\xEF\xBB\xBF");
            fputcsv($handle, TaskList::COLUMNS, ',', '"', '');
            foreach ($query->lazy(200) as $task) {
                $row = [$task->title, $task->due_date?->format('Y-m-d') ?? '', $task->related_reference,
                    $task->assignee?->name ?? '', $task->creator?->name ?? '', $task->task_status];
                $row = array_map(fn ($value) => preg_match('/^[\s\x{FEFF}]*[=+@-]|^[\t\r\n]/u', $value) ? "'".$value : $value, $row);
                fputcsv($handle, $row, ',', '"', '');
            }
            fclose($handle);
        }, 'Tasks -'.now()->format('YmdHis').'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    private function filters(Request $request): array
    {
        return $request->validate([
            'description' => ['nullable', 'string', 'max:255'], 'relates_to' => ['nullable', 'string', 'max:80'],
            'assignee' => ['nullable', 'array', 'max:200'], 'assignee.*' => ['integer'],
            'creator' => ['nullable', 'array', 'max:200'], 'creator.*' => ['integer'],
            'status' => ['nullable', 'array'], 'status.*' => [Rule::in(ApplicationActivity::TASK_STATUSES)],
            'direction' => ['nullable', Rule::in(['asc', 'desc'])], 'per_page' => ['nullable', Rule::in([10, 20, 50])],
        ]);
    }
}

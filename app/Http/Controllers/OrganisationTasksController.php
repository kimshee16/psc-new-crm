<?php

namespace App\Http\Controllers;

use App\Models\Application;
use App\Models\ApplicationActivity;
use App\Models\ApplicationTypeSetting;
use App\Models\Organisation;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class OrganisationTasksController extends Controller
{
    private function application(Request $request, int $organisation, int $application): Application
    {
        Organisation::query()->visibleTo($request->user())->findOrFail($organisation);

        return Application::query()->assignedTo($request->user())->where('organisation_id', $organisation)
            ->whereIn('category', ['SBS', 'Nomination'])->findOrFail($application);
    }

    private function task(Request $request, int $organisation, int $task): ApplicationActivity
    {
        $record = ApplicationActivity::query()->where('kind', 'tasks')->findOrFail($task);
        $this->application($request, $organisation, $record->application_id);

        return $record;
    }

    private function details(Request $request, bool $creating = false): array
    {
        return $request->validate([
            'title' => [$creating ? 'required' : 'sometimes', 'required', 'string', 'max:255'],
            'due_date' => ['nullable', 'date_format:Y-m-d'],
            'assigned_to_user_id' => [$creating ? 'required' : 'sometimes', 'required', 'integer',
                Rule::exists('users', 'id')->where('is_active', true)->where('portal_invite_status', '!=', 'Restrict Access')],
            'status' => ['required', Rule::in(ApplicationActivity::TASK_STATUSES)],
        ]);
    }

    public function store(Request $request, int $organisation): RedirectResponse
    {
        $identity = $request->validate(['application_id' => ['required', 'integer']]);
        $application = $this->application($request, $organisation, $identity['application_id']);
        abort_unless(isset(ApplicationTypeSetting::tabsFor($application->category)['tasks']), 422, 'Tasks are disabled for this application type.');
        $application->activities()->create(['kind' => 'tasks'] + $this->details($request, true));

        return $this->back($organisation, 'Task added.');
    }

    public function update(Request $request, int $organisation, int $task): RedirectResponse
    {
        $this->task($request, $organisation, $task)->update($this->details($request));

        return $this->back($organisation, 'Task saved.');
    }

    public function destroy(Request $request, int $organisation, int $task): RedirectResponse
    {
        $this->task($request, $organisation, $task)->delete();

        return $this->back($organisation, 'Task deleted.');
    }

    private function back(int $organisation, string $message): RedirectResponse
    {
        return redirect()->to(route('organisations.show', ['organisation' => $organisation, 'tab' => 'tasks']).'#organisation-tabs')
            ->with('success', $message);
    }
}

<?php

namespace App\Http\Controllers;

use App\Models\ApplicationTypeSetting;
use App\Models\ApplicationWorkflow;
use App\Models\Checkpoint;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class CheckpointConfigurationController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        return $this->save($request, new Checkpoint);
    }

    public function update(Request $request, Checkpoint $checkpoint): RedirectResponse
    {
        return $this->save($request, $checkpoint);
    }

    public function destroy(Request $request, Checkpoint $checkpoint): RedirectResponse
    {
        $this->authorizeAdmin($request);
        $checkpoint->delete();

        return $this->back('Checkpoint removed.');
    }

    public function assign(Request $request): RedirectResponse
    {
        $this->authorizeAdmin($request);
        $target = $request->validate([
            'category' => ['nullable', 'required_without:workflow_id', 'prohibits:workflow_id', Rule::in(array_keys(ApplicationTypeSetting::types()))],
            'workflow_id' => ['nullable', 'integer', 'exists:application_workflows,id'],
            'inherit' => ['sometimes', 'boolean'],
            'checkpoint_ids' => ['sometimes', 'array', 'max:50'],
            'checkpoint_ids.*' => ['nullable', 'integer', 'distinct', Rule::exists('checkpoints', 'id')->whereNull('deleted_at')],
        ]);

        $ids = array_values(array_filter($target['checkpoint_ids'] ?? [], fn ($id) => $id !== null));
        if (! empty($target['workflow_id'])) {
            $workflow = ApplicationWorkflow::findOrFail($target['workflow_id']);
            $workflow->forceFill(['checkpoint_ids' => ! empty($target['inherit']) ? null : $ids])->save();
        } else {
            $category = $target['category'];
            $setting = ApplicationTypeSetting::query()->firstOrCreate(['category' => $category], [
                'tabs' => ApplicationTypeSetting::defaultTabs($category),
            ]);
            $setting->forceFill(['checkpoint_ids' => $ids])->save();
        }

        return $this->back('Workflow checkpoints saved.', 'workflow-checkpoints');
    }

    private function save(Request $request, Checkpoint $checkpoint): RedirectResponse
    {
        $this->authorizeAdmin($request);
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'status_lines' => ['required', 'string', 'max:6100'],
        ]);

        $statuses = array_values(array_filter(array_map('trim', preg_split('/\R/u', $data['status_lines'])), fn ($status) => $status !== ''));
        $uniqueStatuses = array_unique(array_map('mb_strtolower', $statuses));
        if ($statuses === [] || count($statuses) > 50 || count($uniqueStatuses) !== count($statuses)
            || collect($statuses)->contains(fn ($status) => mb_strlen($status) > 120)) {
            throw ValidationException::withMessages(['status_lines' => 'Enter 1 to 50 unique statuses, up to 120 characters each.']);
        }

        DB::transaction(function () use ($checkpoint, $data, $statuses): void {
            $record = $checkpoint->exists ? Checkpoint::query()->lockForUpdate()->findOrFail($checkpoint->id) : $checkpoint;
            $record->fill(['name' => $data['name'], 'statuses' => $statuses])->save();
        });

        return $this->back('Checkpoint saved.');
    }

    private function authorizeAdmin(Request $request): void
    {
        abort_unless(in_array($request->user()?->account_type, [User::ACCOUNT_TYPE_ADMIN, 'Super Admin'], true), 403);
    }

    private function back(string $message, string $tab = 'checkpoints'): RedirectResponse
    {
        return redirect()->route('configuration', ['tab' => $tab])->with('success', $message);
    }
}

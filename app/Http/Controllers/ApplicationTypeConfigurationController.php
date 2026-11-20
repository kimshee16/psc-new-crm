<?php

namespace App\Http\Controllers;

use App\Models\ApplicationTypeSetting;
use App\Models\ApplicationWorkflow;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class ApplicationTypeConfigurationController extends Controller
{
    public function update(Request $request): RedirectResponse
    {
        abort_unless(in_array($request->user()?->account_type, [User::ACCOUNT_TYPE_ADMIN, 'Super Admin'], true), 403);

        $category = $request->validate([
            'category' => ['required', 'string', Rule::in(array_keys(ApplicationTypeSetting::types()))],
        ])['category'];

        $mode = $request->validate(['mode' => ['nullable', Rule::in(['type', 'create', 'subtype'])]])['mode'] ?? 'type';

        if ($mode === 'create') {
            DB::transaction(function () use ($request, $category): void {
                ApplicationTypeSetting::query()->firstOrCreate(['category' => $category], [
                    'tabs' => ApplicationTypeSetting::defaultTabs($category),
                ]);
                $setting = ApplicationTypeSetting::query()->where('category', $category)->lockForUpdate()->firstOrFail();
                $name = $request->validate([
                    'name' => ['required', 'string', 'max:255', Rule::unique('application_workflows')->where('category', $category)],
                ])['name'];
                $defaults = $setting?->defaults ?? [];
                $workflow = new ApplicationWorkflow(['category' => $category, 'name' => $name]);
                $workflow->migration_agent_id = $defaults['migration_agent_id'] ?? null;
                unset($defaults['migration_agent_id']);
                $workflow->defaults = $defaults;
                $workflow->checkpoint_ids = $setting?->checkpoint_ids ?? [];
                $workflow->save();
            });

            return $this->saved('Application subtype was created.');
        }

        $rules = [
            'checkpoint_ids' => ['sometimes', 'array'],
            'checkpoint_ids.*' => ['required', 'integer', 'distinct', Rule::exists('checkpoints', 'id')->whereNull('deleted_at')],
            'migration_agent_id' => ['nullable', 'integer', 'exists:migration_agents,user_id'],
            'service_agreement_id' => ['nullable', 'integer', 'min:1'],
        ];

        foreach (ApplicationTypeSetting::LIST_FIELDS as $key => $label) {
            $rules[$key] = ['nullable', 'string', 'max:10000'];
        }

        if ($mode === 'type') {
            $tabs = array_keys(ApplicationTypeSetting::TABS);
            $rules['tabs'] = ['sometimes', 'array', 'max:'.count($tabs)];
            $rules['tabs.*'] = ['required', 'string', 'distinct', Rule::in(
                $category === 'SBS' ? $tabs : array_diff($tabs, ['nominations'])
            )];
        } else {
            $rules['workflow_id'] = ['required', 'integer', Rule::exists('application_workflows', 'id')->where('category', $category)];
            $rules['tabs'] = ['missing'];
        }

        $data = $request->validate($rules);
        $defaults = [
            'service_agreement_id' => isset($data['service_agreement_id']) ? (int) $data['service_agreement_id'] : null,
        ];

        foreach (ApplicationTypeSetting::LIST_FIELDS as $key => $label) {
            $lines = preg_split('/\R/u', $data[$key] ?? '');
            $defaults[$key] = array_values(array_unique(array_filter(array_map('trim', $lines), 'strlen')));
        }

        if ($mode === 'subtype') {
            $workflow = ApplicationWorkflow::query()->where('category', $category)->findOrFail($data['workflow_id']);
            $workflow->migration_agent_id = $data['migration_agent_id'] ?? null;
            $workflow->defaults = $defaults;
            $workflow->checkpoint_ids = array_map('intval', $data['checkpoint_ids'] ?? []);
            $workflow->save();

            return $this->saved('Application subtype settings were saved.');
        }

        $attributes = ['tabs' => array_keys(ApplicationTypeSetting::availableTabs($data['tabs'] ?? []))];
        if ($request->filled('mode')) {
            $attributes['defaults'] = $defaults + ['migration_agent_id' => isset($data['migration_agent_id']) ? (int) $data['migration_agent_id'] : null];
            $attributes['checkpoint_ids'] = array_map('intval', $data['checkpoint_ids'] ?? []);
        }
        ApplicationTypeSetting::query()->updateOrCreate(['category' => $category], $attributes);

        return $this->saved('Application type settings were saved.');
    }

    private function saved(string $message): RedirectResponse
    {
        return redirect()->route('configuration', ['tab' => 'application-types'])->with('success', $message);
    }
}

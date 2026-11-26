<?php

namespace App\Http\Controllers;

use App\Models\ApplicationCatalogEntry;
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

        $mode = $request->validate(['mode' => ['nullable', Rule::in(['type', 'create', 'subtype', 'reset'])]])['mode'] ?? 'type';

        if ($mode === 'reset') {
            $data = $request->validate([
                'workflow_id' => ['required', 'integer'],
                'section' => ['required', Rule::in([
                    'checkpoint_ids', 'migration_agent_id', 'service_agreement_id', 'tabs',
                    ...array_keys(ApplicationTypeSetting::LIST_FIELDS),
                ])],
            ]);

            DB::transaction(function () use ($category, $data): void {
                $setting = ApplicationTypeSetting::query()->where('category', $category)->first();
                $workflow = ApplicationWorkflow::query()->where('category', $category)
                    ->lockForUpdate()->findOrFail($data['workflow_id']);
                $section = $data['section'];

                if ($section === 'checkpoint_ids') {
                    $workflow->checkpoint_ids = $setting?->checkpoint_ids ?? [];
                } elseif ($section === 'migration_agent_id') {
                    $workflow->migration_agent_id = $setting?->defaults['migration_agent_id'] ?? null;
                } else {
                    $defaults = $workflow->defaults ?? $setting?->defaults ?? [];
                    if ($workflow->defaults === null) {
                        $workflow->migration_agent_id ??= $defaults['migration_agent_id'] ?? null;
                    }
                    unset($defaults['migration_agent_id']);
                    $defaults[$section] = $section === 'tabs'
                        ? ($setting?->tabs ?? ApplicationTypeSetting::defaultTabs($category))
                        : ($setting?->defaults[$section] ?? ($section === 'service_agreement_id' ? null : []));
                    $workflow->defaults = $defaults;
                }

                $workflow->save();
            });

            return $this->saved('The subtype section was reset to the type default.');
        }

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
                $defaults['tabs'] = $setting->tabs ?? ApplicationTypeSetting::defaultTabs($category);
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
            if (isset(ApplicationCatalogEntry::LABELS[$key])) {
                $rules[$key] = ['sometimes', 'array'];
                $rules["$key.*"] = ['required', 'string', 'distinct'];
            } else {
                $rules[$key] = ['nullable', 'string', 'max:10000'];
            }
        }
        $rules['catalogs_present'] = ['sometimes', Rule::in(['1'])];

        $tabs = array_keys(ApplicationTypeSetting::TABS);
        $rules['tabs'] = ['sometimes', 'array', 'max:'.count($tabs)];
        $rules['tabs.*'] = ['required', 'string', 'distinct', Rule::in(
            $category === 'SBS' ? $tabs : array_diff($tabs, ['nominations'])
        )];
        if ($mode === 'subtype') {
            $rules['workflow_id'] = ['required', 'integer', Rule::exists('application_workflows', 'id')->where('category', $category)];
            $rules['name'] = ['sometimes', 'required', 'string', 'max:255'];
            $rules['tabs_present'] = ['sometimes', Rule::in(['1'])];
        }

        $data = $request->validate($rules);
        $typeDefaults = ApplicationTypeSetting::query()->where('category', $category)->first()?->defaults ?? [];
        $existingDefaults = $mode === 'subtype'
            ? (ApplicationWorkflow::findOrFail($data['workflow_id'])->defaults ?? $typeDefaults)
            : $typeDefaults;
        $catalogs = ApplicationCatalogEntry::catalogs();
        $defaults = [
            'service_agreement_id' => isset($data['service_agreement_id']) ? (int) $data['service_agreement_id'] : null,
        ];

        foreach (ApplicationTypeSetting::LIST_FIELDS as $key => $label) {
            if (isset(ApplicationCatalogEntry::LABELS[$key])) {
                $existing = $existingDefaults[$key] ?? [];
                $available = $catalogs->get($key, collect())->map->selectionValue()->all();
                $request->validate(["$key.*" => [Rule::in([...$available, ...$existing])]]);
                $defaults[$key] = $data[$key] ?? (isset($data['catalogs_present']) ? [] : $existing);
                continue;
            }
            $lines = preg_split('/\R/u', $data[$key] ?? '');
            $defaults[$key] = array_values(array_unique(array_filter(array_map('trim', $lines), 'strlen')));
        }

        if ($mode === 'subtype') {
            DB::transaction(function () use ($request, $category, $data, $defaults): void {
                ApplicationTypeSetting::query()->firstOrCreate(['category' => $category], [
                    'tabs' => ApplicationTypeSetting::defaultTabs($category),
                ]);
                ApplicationTypeSetting::query()->where('category', $category)->lockForUpdate()->firstOrFail();
                $workflow = ApplicationWorkflow::query()->where('category', $category)
                    ->lockForUpdate()->findOrFail($data['workflow_id']);
                if (isset($data['name'])) {
                    $workflow->name = $request->validate([
                        'name' => [Rule::unique('application_workflows')->where('category', $category)->ignore($workflow)],
                    ])['name'];
                }

                $workflow->migration_agent_id = $data['migration_agent_id'] ?? null;
                $workflow->defaults = $defaults + array_intersect_key($workflow->defaults ?? [], ['tabs' => true]);
                if (isset($data['tabs_present']) || isset($data['tabs'])) {
                    $workflow->defaults = array_replace($workflow->defaults, [
                        'tabs' => array_keys(ApplicationTypeSetting::availableTabs($data['tabs'] ?? [])),
                    ]);
                }
                $workflow->checkpoint_ids = array_map('intval', $data['checkpoint_ids'] ?? []);
                $workflow->save();
            });

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

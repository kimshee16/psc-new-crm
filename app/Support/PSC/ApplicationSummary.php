<?php

namespace App\Support\PSC;

use App\Models\Application;
use App\Models\ApplicationWorkflow;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class ApplicationSummary
{
    public const TYPE_FIELDS = [
        'Assessment' => ['assessment_body', 'Assessment'],
        'Visa' => ['visa_subclass', 'Subclass'],
        'ART Appeal' => ['psc_client', 'PSC Client'],
        'Nomination' => ['nomination_pathway', 'Nomination subclass'],
    ];

    public function eligibleStaff(Application $application): Builder
    {
        $query = User::query()->where('is_active', true)
            ->where('portal_invite_status', '!=', 'Restrict Access')
            ->whereIn('account_type', User::CONFIGURABLE_ROLES);

        if ($application->workflow) {
            // A workflow explicitly grants case handling permission. Existing
            // assignments and its default agent also carry case access.
            $permitted = $application->workflow->caseHandlers()->pluck('users.id')->all();
            $permitted = array_filter(array_merge($permitted, [
                $application->primary_counsellor_id, $application->secondary_counsellor_id,
                $application->migration_agent_id, $application->workflow->migration_agent_id,
            ]));
            $query->whereIn('id', $permitted);
        }

        return $query->orderBy('name')->orderBy('id');
    }

    public function options(Application $application, string $field): array
    {
        $options = $application->workflow?->summary_options[$field]
            ?? config('applications.creation_options.'.$field, []);
        $options = array_values(array_unique(array_filter($options, fn ($value) => is_string($value) && trim($value) !== '')));
        // Retired choices can be retained, but cannot be selected on other cases.
        if ($application->exists && filled($application->$field)) {
            $options[] = $application->$field;
        }

        return array_values(array_unique($options));
    }

    public function viewData(Application $application): array
    {
        return [
            'staff' => $this->eligibleStaff($application)->get(),
            'typeField' => self::TYPE_FIELDS[$application->category] ?? null,
            'typeOptions' => isset(self::TYPE_FIELDS[$application->category])
                && $application->category !== 'ART Appeal'
                    ? $this->options($application, self::TYPE_FIELDS[$application->category][0]) : [],
        ];
    }

    public function validateUpdate(Application $application, array $input): array
    {
        $rules = $this->editableRules($application);
        foreach (['id', 'client_id', 'organisation_id', 'category', 'application_workflow_id', 'created_at', 'updated_at', 'psc_client', 'nomination_pathway', 'course'] as $field) {
            $rules[$field] = ['missing'];
        }

        return Validator::make($input, $rules, [], $this->labels())->validate();
    }

    /** Shared creation boundary for PSC-33; selections are preserved unchanged. */
    public function create(User $creator, array $input): Application
    {
        $identity = Validator::make($input, [
            'client_id' => ['nullable', 'required_without:organisation_id', 'prohibits:organisation_id', 'integer', 'exists:clients,id'],
            'organisation_id' => ['nullable', 'required_without:client_id', 'prohibits:client_id', 'integer', 'exists:organisations,id'],
            'category' => ['required', Rule::in(array_keys(Application::TYPES))],
            'application_workflow_id' => ['nullable', 'integer', 'exists:application_workflows,id'],
        ])->validate();

        $workflow = isset($identity['application_workflow_id']) ? ApplicationWorkflow::findOrFail($identity['application_workflow_id']) : null;
        Validator::make($identity, [
            'category' => [Rule::in($workflow ? [$workflow->category] : array_keys(Application::TYPES))],
        ])->validate();

        $application = new Application($identity);
        $application->setRelation('workflow', $workflow);
        $application->primary_counsellor_id = $creator->id;
        $application->migration_agent_id = $workflow?->migration_agent_id;
        $input += [
            'status' => 'Not Started', 'priority' => 'M',
            'primary_counsellor_id' => $creator->id,
            'migration_agent_id' => $workflow?->migration_agent_id,
        ];
        $rules = $this->editableRules($application);
        $rules['psc_client'] = $application->category === 'ART Appeal' ? ['required', 'boolean'] : ['missing'];
        $rules['nomination_pathway'] = $application->category === 'Nomination'
            ? ['required', 'string', Rule::in($this->options($application, 'nomination_pathway'))] : ['missing'];
        $rules['course'] = $application->category === 'Study' ? ['nullable', 'string', 'max:255'] : ['missing'];
        // PSC-33 leaves assessment body optional at creation. The Summary form
        // can still require it before saving a completed summary (PSC-29).
        if ($application->category === 'Assessment') {
            $rules['assessment_body'] = ['nullable', 'string', 'max:255', Rule::in($this->options($application, 'assessment_body'))];
        }
        if ($application->category === 'ART Appeal') {
            $rules['visa_subclass'] = ['required', 'string', 'max:255', Rule::in($this->options($application, 'visa_subclass'))];
        }
        $validated = Validator::make($input, $rules, [], $this->labels())->validate();
        $application->fill($validated)->save();

        return $application;
    }

    private function editableRules(Application $application): array
    {
        $staffIds = $this->eligibleStaff($application)->pluck('id')->all();

        return [
            'status' => ['required', Rule::in(Application::STATUSES)],
            'priority' => ['required', Rule::in(array_keys(Application::PRIORITIES))],
            'critical_date' => ['nullable', 'date_format:Y-m-d'],
            'primary_counsellor_id' => ['required', 'integer', Rule::in($staffIds)],
            'secondary_counsellor_id' => ['nullable', 'integer', Rule::in($staffIds)],
            'migration_agent_id' => [$application->category === 'Visa' ? 'required' : 'nullable', 'integer', Rule::in($staffIds)],
            'notes' => ['nullable', 'string', 'max:60000'],
            'assessment_body' => $application->category === 'Assessment'
                ? ['required', 'string', 'max:255', Rule::in($this->options($application, 'assessment_body'))] : ['missing'],
            'visa_subclass' => $application->category === 'Visa'
                ? ['required', 'string', 'max:255', Rule::in($this->options($application, 'visa_subclass'))] : ['missing'],
        ];
    }

    private function labels(): array
    {
        return [
            'primary_counsellor_id' => 'Counsellor 1', 'secondary_counsellor_id' => 'Counsellor 2',
            'migration_agent_id' => 'Migration Agent', 'assessment_body' => 'Assessment',
            'visa_subclass' => 'Subclass', 'nomination_pathway' => 'Nomination subclass', 'psc_client' => 'PSC Client',
        ];
    }
}

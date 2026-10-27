<?php

namespace App\Support\PSC;

use App\Models\Application;
use App\Models\ApplicationWorkflow;
use App\Models\Client;
use App\Models\Lead;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class ApplicationCreation
{
    public const TYPES = [
        'Study and 500 Visa' => 'Study and 500 Visa',
        'Study' => 'Study Application',
        'Visa' => 'Visa Application',
        'Assessment' => 'Assessment',
        'ART Appeal' => 'ART Appeal',
        'Citizenship' => 'Citizenship Application',
    ];

    public function __construct(private readonly ApplicationSummary $summary) {}

    public function viewData(User $user): array
    {
        $workflows = ApplicationWorkflow::whereIn('category', array_keys(self::TYPES))->orderBy('name')->get();

        return [
            'types' => self::TYPES,
            'options' => config('applications.creation_options'),
            'workflows' => $workflows->map(function (ApplicationWorkflow $workflow) use ($user) {
                $draft = new Application(['primary_counsellor_id' => $user->id]);
                $draft->setRelation('workflow', $workflow);

                return [
                    'id' => $workflow->id, 'name' => $workflow->name, 'category' => $workflow->category,
                    'options' => $workflow->summary_options ?? [],
                    'migration_agent_id' => $workflow->migration_agent_id,
                    'staff' => $this->summary->eligibleStaff($draft)->get(['id', 'name'])->toArray(),
                ];
            })->all(),
            'staff' => $this->summary->eligibleStaff(new Application)->get(['id', 'name'])->toArray(),
        ];
    }

    public function create(User $user, Client $client, array $input, ?Lead $lead = null): Application
    {
        $data = Validator::make($input, [
            'category' => ['required', Rule::in(array_keys(self::TYPES))],
            'application_workflow_id' => ['nullable', 'integer'],
            'visa_workflow_id' => ['nullable', 'integer'],
            'migration_agent_id' => ['nullable', 'integer'],
            'visa_subclass' => ['exclude_unless:category,Visa,ART Appeal', 'required', 'string', 'max:255'],
            'assessment_body' => ['exclude_unless:category,Assessment', 'nullable', 'string', 'max:255'],
            'psc_client' => ['exclude_unless:category,ART Appeal', 'required', 'boolean'],
        ])->validate();

        return DB::transaction(function () use ($user, $client, $lead, $data) {
            // Serialize conversion of the same lead, including simultaneous requests.
            if ($lead) {
                $lead = Lead::whereKey($lead->id)->lockForUpdate()->firstOrFail();
                abort_unless($lead->client_id === $client->id, 404);
                if ($existing = Application::where('source_lead_id', $lead->id)->orderBy('id')->first()) {
                    abort_unless(Application::assignedTo($user)->whereKey($existing->id)->exists(), 403);

                    return $existing;
                }
                Validator::make(['status' => $lead->status], ['status' => [Rule::notIn(['Converted'])]], [
                    'status.not_in' => 'This Lead has already been converted.',
                ])->validate();
            }

            $combined = $data['category'] === 'Study and 500 Visa';
            $category = $combined ? 'Study' : $data['category'];
            $workflow = $this->workflow($category, $data['application_workflow_id'] ?? null, 'application_workflow_id');
            $attributes = ['client_id' => $client->id, 'category' => $category, 'application_workflow_id' => $workflow?->id];
            foreach (['visa_subclass', 'assessment_body', 'psc_client'] as $field) {
                if (array_key_exists($field, $data)) {
                    $attributes[$field] = $data[$field];
                }
            }
            if ($category === 'Visa' && ! $workflow?->migration_agent_id) {
                $attributes['migration_agent_id'] = $data['migration_agent_id'] ?? null;
            }
            $application = $this->summary->create($user, $attributes);
            $application->source_lead_id = $lead?->id;

            if ($combined) {
                $visaWorkflow = $this->workflow('Visa', $data['visa_workflow_id'] ?? null, 'visa_workflow_id');
                $visa = $this->summary->create($user, [
                    'client_id' => $client->id, 'category' => 'Visa',
                    'application_workflow_id' => $visaWorkflow?->id,
                    'visa_subclass' => '500 - Student Visa',
                    'migration_agent_id' => $visaWorkflow?->migration_agent_id ?? $data['migration_agent_id'] ?? null,
                ]);
                $visa->linked_application_id = $application->id;
                $visa->source_lead_id = $lead?->id;
                $visa->save();
                $application->linked_application_id = $visa->id;
            }
            $application->save();
            $lead?->update(['status' => 'Converted']);

            return $application;
        });
    }

    private function workflow(string $category, ?int $id, string $field): ?ApplicationWorkflow
    {
        $choices = ApplicationWorkflow::where('category', $category)->pluck('id')->all();
        Validator::make([$field => $id], [$field => [
            $choices === [] ? 'nullable' : 'required', Rule::in($choices),
        ]], [$field.'.required' => 'Select a workflow template.'])->validate();

        return $id ? ApplicationWorkflow::findOrFail($id) : null;
    }
}

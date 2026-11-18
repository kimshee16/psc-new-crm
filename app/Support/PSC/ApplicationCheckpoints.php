<?php

namespace App\Support\PSC;

use App\Models\Application;
use App\Models\ApplicationTypeSetting;
use App\Models\Checkpoint;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class ApplicationCheckpoints
{
    public const FIRST_FIELDS = [
        'initial_consultation' => 'Initial consultation',
        'service_agreement' => 'Service agreement',
    ];

    public function forApplication(Application $application): Collection
    {
        $ids = $application->workflow?->checkpoint_ids
            ?? ApplicationTypeSetting::query()->where('category', $application->category)->first()?->checkpoint_ids
            ?? [];

        $checkpoints = Checkpoint::query()->whereKey($ids)->get()->keyBy('id');

        return collect($ids)->map(fn ($id) => $checkpoints->get($id))->filter()->values();
    }

    public function save(Application $application, array $input): void
    {
        $checkpoints = $this->forApplication($application)->keyBy('id');
        abort_if($checkpoints->isEmpty(), 404);

        $rules = ['checkpoint_statuses' => ['sometimes', 'array']];
        $specialised = $application->organisation_id && in_array($application->category, ['SBS', 'Nomination'], true);
        if (! $specialised) {
            foreach (self::FIRST_FIELDS as $field => $label) {
                $rules[$field] = ['sometimes', 'nullable', Rule::in(SbsRecord::MILESTONES[$field])];
            }
            $rules += [
                'lodgement_date' => ['sometimes', 'nullable', 'date_format:Y-m-d'],
                'outcome_date' => ['sometimes', 'nullable', 'date_format:Y-m-d'],
                'outcome' => ['sometimes', 'nullable', Rule::in(NominationRecord::OUTCOMES)],
            ];
        }

        $validated = Validator::make($input, $rules + [
            'checkpoint_statuses.*' => ['nullable', 'string', 'max:120'],
        ])->validate();

        $saved = $application->checkpoint_statuses ?? [];
        foreach ($validated['checkpoint_statuses'] ?? [] as $id => $status) {
            $checkpoint = $checkpoints->get($id);
            if (! $checkpoint || ($status !== null && $status !== ($saved[$id] ?? null)
                && ! in_array($status, $checkpoint->statuses, true))) {
                throw ValidationException::withMessages(['checkpoint_statuses.'.$id => 'Select a status available for this checkpoint.']);
            }
            if ($status === null) {
                unset($saved[$id]);
            } else {
                $saved[$id] = $status;
            }
        }

        unset($validated['checkpoint_statuses']);
        $application->fill($validated)->forceFill(['checkpoint_statuses' => $saved])->save();
    }
}

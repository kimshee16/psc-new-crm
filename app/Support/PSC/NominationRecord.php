<?php

namespace App\Support\PSC;

use App\Models\Application;
use App\Models\Client;
use App\Models\NominationSubclass;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class NominationRecord
{
    public const OUTCOMES = ['Awaiting decision', 'Approved', 'Refused', 'Withdrawn'];

    public const FINAL_OUTCOMES = ['Approved', 'Refused', 'Withdrawn'];

    public const APPEAL_STATUSES = ['Not reviewable', 'Considering', 'Not appealing', 'Lodged',
        'Awaiting hearing', 'Hearing scheduled', 'Awaiting decision', 'Affirmed',
        'Set aside - remitted', 'Set aside - substituted', 'Dismissed', 'Withdrawn', 'Out of time'];

    public const DETAIL_FIELDS = ['nominee_name', 'nomination_pathway', 'nominated_position_title',
        'sponsorship_id', 'anzsco_code', 'annual_salary', 'lodgement_date', 'reference_number',
        'outcome', 'outcome_date', 'expiry_date', 'appeal_deadline', 'appeal_status',
        'art_case_number', 'final_outcome', 'final_outcome_date', 'google_drive_url'];

    public function linkedVisa(Application $application): ?Application
    {
        // Do not scope this check by viewer: a hidden link must still lock the fields.
        return Application::query()->linkedTo($application)->where('category', 'Visa')->with('client')->first();
    }

    public function sponsorships(Application $application, User $user)
    {
        return Application::query()->assignedTo($user)->where('organisation_id', $application->organisation_id)
            ->where('category', 'SBS')->orderByDesc('approval_date')->orderByDesc('id')->get();
    }

    public function requiresSbs(?string $subclass): bool
    {
        return preg_match('/^(\d{3})/', $subclass ?? '', $matches)
            && NominationSubclass::query()->where('subclass', $matches[1])->where('sbs_required', true)->exists();
    }

    public function activeSbs(Application $application, User $user): ?Application
    {
        return $this->sponsorships($application, $user)->first(fn (Application $sbs) => $this->isActiveSbs($sbs));
    }

    public function isActiveSbs(Application $application): bool
    {
        return strtolower(trim($application->status)) === 'active'
            && strtolower(trim((string) $application->sponsor_type)) !== 'labour agreement'
            && (! $application->approval_date || $application->approval_date->lte(today()))
            && (! $application->expiry_date || $application->expiry_date->gt(today()));
    }

    public function defaultSponsorship(Application $application, User $user): ?int
    {
        return $this->requiresSbs($application->nomination_pathway) ? $this->activeSbs($application, $user)?->id : null;
    }

    public function viewData(Application $application, User $user): array
    {
        $visa = $this->linkedVisa($application);
        if ($visa) {
            $application->nominee_client_id = $visa->client_id;
            $application->nominee_name = null;
            $application->nomination_pathway = $visa->visa_subclass;
            $application->setRelation('nomineeClient', $visa->client);
        }
        $reference = old('nominee_client_reference', $application->nomineeClient?->client_id);
        $sponsorships = $this->sponsorships($application, $user);
        $activeSbs = $sponsorships->first(fn (Application $sbs) => $this->isActiveSbs($sbs));

        return [
            'nomineeLocked' => $visa !== null,
            'selectedNominee' => is_scalar($reference) && filled($reference)
                ? Client::query()->where('client_id', $reference)->first() : null,
            'sponsorships' => $sponsorships,
            'sbsRequiredSubclasses' => NominationSubclass::query()->where('sbs_required', true)->pluck('subclass')->all(),
            'hasActiveSbs' => $activeSbs !== null,
            'activeSbsId' => $activeSbs?->id,
        ];
    }

    public function update(User $user, int $id, array $input, bool $withSummary = true): Application
    {
        return DB::transaction(function () use ($user, $id, $input, $withSummary) {
            $application = Application::query()->assignedTo($user)->where('category', 'Nomination')
                ->whereNotNull('organisation_id')->lockForUpdate()->findOrFail($id);
            $summary = [];
            if ($withSummary) {
                $summaryInput = array_diff_key($input, array_flip(self::DETAIL_FIELDS));
                $summary = app(ApplicationSummary::class)->validateUpdate($application, $summaryInput);
            }
            $application->fill($summary + $this->validateDetails($application, $user, $input, $withSummary))->save();

            return $application;
        });
    }

    public function validateDetails(Application $application, User $user, array $input, bool $complete): array
    {
        $data = [];
        foreach (self::DETAIL_FIELDS as $field) {
            $value = $application->$field;
            $data[$field] = $value instanceof \DateTimeInterface ? $value->format('Y-m-d') : $value;
        }
        $data['nominee_client_reference'] = $application->nomineeClient?->client_id;
        $data = array_replace($data, array_intersect_key($input, $data));
        $visa = $this->linkedVisa($application);
        $identityChanged = $complete || count(array_intersect(array_keys($input), ['external_nominee', 'nominee_name', 'nominee_client_reference', 'nomination_pathway'])) > 0;
        $external = array_key_exists('external_nominee', $input)
            ? filter_var($input['external_nominee'], FILTER_VALIDATE_BOOLEAN) : filled($data['nominee_name']) && ! $data['nominee_client_reference'];
        if ($external && ! $visa) {
            $data['nominee_client_reference'] = null;
        } elseif (! $visa) {
            $data['nominee_name'] = null;
        }
        $rules = [
            'nominated_position_title' => ['nullable', 'string', 'max:255'],
            'sponsorship_id' => ['nullable', 'integer', Rule::in($this->sponsorships($application, $user)->pluck('id')->all())],
            'anzsco_code' => ['nullable', 'regex:/^[0-9]{6}$/'],
            'annual_salary' => ['nullable', 'numeric', 'min:0', 'max:9999999999.99', 'decimal:0,2'],
            'reference_number' => ['nullable', 'string', 'max:255'],
            'outcome' => ['nullable', Rule::in(self::OUTCOMES)],
            'appeal_status' => ['nullable', Rule::in(self::APPEAL_STATUSES)],
            'art_case_number' => ['nullable', 'string', 'max:255'],
            'final_outcome' => ['nullable', Rule::in(self::FINAL_OUTCOMES)],
            'google_drive_url' => ['nullable', 'string', 'max:2048', 'url:https', 'regex:~^https://drive\.google\.com(?:/|$)~i'],
        ];
        foreach (['lodgement_date', 'outcome_date', 'expiry_date', 'appeal_deadline', 'final_outcome_date'] as $date) {
            $rules[$date] = ['nullable', 'date_format:Y-m-d'];
        }
        $refused = ($data['outcome'] ?? null) === 'Refused';
        if (in_array($data['outcome'], self::FINAL_OUTCOMES, true)) {
            $rules['outcome_date'][0] = 'required';
        }
        if ($refused && filled($data['final_outcome'])) {
            $rules['final_outcome_date'][0] = 'required';
            if ($data['final_outcome'] === 'Approved') {
                $rules['expiry_date'][0] = 'required';
            }
        }
        if ($refused && $data['appeal_status'] === 'Not reviewable') {
            $rules['appeal_deadline'] = ['prohibited'];
        }
        if (! $refused) {
            foreach (['appeal_status', 'appeal_deadline', 'art_case_number', 'final_outcome', 'final_outcome_date'] as $field) {
                $data[$field] = null;
            }
        }
        if ($data['outcome'] !== 'Approved' && ! ($refused && $data['final_outcome'] === 'Approved')) {
            $data['expiry_date'] = null;
        }
        if (! $visa) {
            $rules['nominee_name'] = [$identityChanged && $external ? 'required' : 'nullable', 'string', 'max:255'];
            $rules['nominee_client_reference'] = [$identityChanged && ! $external ? 'required' : 'nullable', 'integer', 'exists:clients,client_id'];
            $rules['nomination_pathway'] = [$identityChanged ? 'required' : 'nullable', 'string', 'max:255',
                Rule::in(app(ApplicationSummary::class)->options($application, 'nomination_pathway'))];
        }
        $validator = Validator::make($data, $rules);
        if (array_key_exists('external_nominee', $input)) {
            Validator::make($input, ['external_nominee' => ['boolean']])->validate();
        }
        // Even identical submitted values are rejected: linked identity is owned by the visa.
        $validator->after(function ($validator) use ($visa, $input) {
            if ($visa) {
                foreach (['nominee_name', 'nominee_client_reference', 'external_nominee', 'nomination_pathway', 'nominee_client_id', 'visa_subclass'] as $field) {
                    if (array_key_exists($field, $input)) {
                        $validator->errors()->add($field, 'Unlink the visa application before changing the nominee or visa subclass.');
                    }
                }
            }
        });
        $validated = $validator->validate();
        if (! $visa) {
            $validated['nominee_client_id'] = filled($data['nominee_client_reference'])
                ? Client::query()->where('client_id', $data['nominee_client_reference'])->value('id') : null;
            unset($validated['nominee_client_reference']);
        } else {
            $validated += ['nominee_client_id' => $visa->client_id, 'nominee_name' => null, 'nomination_pathway' => $visa->visa_subclass];
        }
        if (! array_key_exists('sponsorship_id', $input) && $application->nomination_pathway !== ($validated['nomination_pathway'] ?? null)) {
            $preview = clone $application;
            $preview->fill($validated);
            $validated['sponsorship_id'] = $this->defaultSponsorship($preview, $user);
        }

        return $validated;
    }
}

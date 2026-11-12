<?php

namespace App\Support\PSC;

use App\Models\Application;
use App\Models\Organisation;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class SbsRecord
{
    public const MILESTONES = [
        'initial_consultation' => ['To do', 'Booked', 'Done', 'Not required'],
        'service_agreement' => ['To do', 'In progress', 'Sent - Pending Client', 'Signed', 'Withdrawn'],
        'supporting_documents' => ['To do', 'Pending client', 'In review', 'Done'],
        'application_stage' => ['To do', 'In progress', 'In review', 'Lodged'],
    ];

    public const DETAIL_FIELDS = ['initial_consultation', 'service_agreement', 'supporting_documents',
        'application_stage', 'lodgement_date', 'trn', 'approval_date', 'outcome', 'outcome_date',
        'expiry_date', 'appeal_deadline', 'appeal_status', 'art_case_number', 'final_outcome', 'final_outcome_date'];

    public static function supports(Application $application): bool
    {
        return $application->category === 'SBS' && $application->organisation_id;
    }

    private function query(): Builder
    {
        return Application::query()->where('category', 'SBS')->whereNotNull('organisation_id');
    }

    public function expireDue(?int $organisationId = null): int
    {
        return $this->query()->when($organisationId, fn ($query) => $query->where('organisation_id', $organisationId))
            ->where('expiry_date', '<=', today()->toDateString())->where('status', '!=', 'Expired')
            ->update(['status' => 'Expired', 'updated_at' => now()]);
    }

    public function update(User $user, int $id, array $input, bool $withSummary = true): Application
    {
        $visible = $this->query()->assignedTo($user)->findOrFail($id);

        return DB::transaction(function () use ($user, $id, $input, $withSummary, $visible) {
            // Lock the parent first so two different SBS records cannot become active together.
            Organisation::query()->whereKey($visible->organisation_id)->lockForUpdate()->firstOrFail();
            $this->expireDue($visible->organisation_id);
            $application = $this->query()->assignedTo($user)->lockForUpdate()->findOrFail($id);
            $labour = $application->is_labour_agreement;
            $summary = $withSummary ? app(ApplicationSummary::class)->validateUpdate($application,
                array_diff_key($input, array_flip(self::DETAIL_FIELDS))) : [];
            Validator::make($input, ['sponsor_type' => ['missing'], 'home_affairs_reference' => ['missing']]
                + ($labour ? ['trn' => ['missing']] : []))->validate();

            $details = [];
            foreach (self::DETAIL_FIELDS as $field) {
                $value = $application->$field;
                $details[$field] = $value instanceof \DateTimeInterface ? $value->format('Y-m-d') : $value;
            }
            foreach (self::MILESTONES as $field => $options) {
                $details[$field] ??= 'To do';
            }
            $details = array_replace($details, array_intersect_key($input, $details));
            if ($labour) {
                $details['trn'] = null;
            }
            $refused = $details['outcome'] === 'Refused';
            if (! $refused) {
                foreach (['appeal_deadline', 'appeal_status', 'art_case_number', 'final_outcome', 'final_outcome_date'] as $field) {
                    $details[$field] = null;
                }
            }
            $approved = $details['outcome'] === 'Approved' || ($refused && $details['final_outcome'] === 'Approved');
            if (! $approved) {
                $details['expiry_date'] = null;
                $details['approval_date'] = null;
            }
            $status = $summary['status'] ?? $application->status;
            if ($details['application_stage'] === 'Lodged' && $application->application_stage !== 'Lodged') {
                $status = 'Pending Decision';
                $details['outcome'] = 'Awaiting decision';
                $details['outcome_date'] = $details['approval_date'] = $details['expiry_date'] = null;
                foreach (['appeal_deadline', 'appeal_status', 'art_case_number', 'final_outcome', 'final_outcome_date'] as $field) {
                    $details[$field] = null;
                }
                $approved = $refused = false;
            }

            $rules = [
                'trn' => [$status === 'Active' && ! $labour ? 'required' : 'nullable', 'string', 'regex:/^[A-Za-z0-9]{13}$/'],
                'outcome' => ['nullable', Rule::in(NominationRecord::OUTCOMES)],
                'appeal_status' => ['nullable', Rule::in(NominationRecord::APPEAL_STATUSES)],
                'art_case_number' => ['nullable', 'string', 'max:255'],
                'final_outcome' => ['nullable', Rule::in(NominationRecord::FINAL_OUTCOMES)],
            ];
            foreach (self::MILESTONES as $field => $options) {
                $rules[$field] = ['nullable', Rule::in($options)];
            }
            foreach (['lodgement_date', 'approval_date', 'outcome_date', 'expiry_date', 'appeal_deadline', 'final_outcome_date'] as $field) {
                $rules[$field] = ['nullable', 'date_format:Y-m-d'];
            }
            if (in_array($details['outcome'], NominationRecord::FINAL_OUTCOMES, true)) {
                $rules['outcome_date'][0] = 'required';
            }
            if ($refused && filled($details['final_outcome'])) {
                $rules['final_outcome_date'][0] = 'required';
            }
            if ($refused && $details['appeal_status'] === 'Not reviewable') {
                $rules['appeal_deadline'] = ['prohibited'];
            }
            if ($approved) {
                $rules['expiry_date'][0] = 'required';
                $rules['expiry_date'][] = 'after_or_equal:'.($refused ? 'final_outcome_date' : 'outcome_date');
            }
            $validated = Validator::make($details, $rules, [
                'trn.regex' => 'TRN must contain exactly 13 alphanumeric characters.',
            ])->validate();
            foreach (self::MILESTONES as $field => $options) {
                $validated[$field] ??= 'To do';
            }
            // Keep the approval date used by organisation tables in sync with the effective outcome.
            $validated['approval_date'] = $approved
                ? ($refused ? $validated['final_outcome_date'] : $validated['outcome_date']) : null;
            if ($validated['expiry_date'] && $validated['expiry_date'] <= today()->toDateString()) {
                $status = 'Expired';
            }
            if ($status === 'Active' && $this->query()->where('organisation_id', $application->organisation_id)
                ->whereRaw("LOWER(TRIM(COALESCE(sponsor_type, ''))) ".($labour ? '=' : '!=')." 'labour agreement'")
                ->whereKeyNot($id)->whereRaw("LOWER(TRIM(status)) = 'active'")->exists()) {
                $type = $labour ? 'Labour Agreement' : 'SBS';
                throw ValidationException::withMessages(['status' => "This organisation already has an active {$type}. Expire or change that record before activating another."]);
            }
            $application->fill(array_replace($summary, $validated, ['status' => $status]))->save();

            return $application;
        });
    }

    public function nominations(Application $application, User $user)
    {
        return Application::query()->assignedTo($user)->where('category', 'Nomination')
            ->where('organisation_id', $application->organisation_id)->where('sponsorship_id', $application->id)
            ->with('nomineeClient')->orderByDesc('id')->paginate(15, ['*'], 'nominations_page')->withQueryString();
    }
}

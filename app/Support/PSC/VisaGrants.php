<?php

namespace App\Support\PSC;

use App\Models\Application;
use App\Models\User;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class VisaGrants
{
    public function updateApplication(User $user, int $id, array $input, ApplicationSummary $summary): Application
    {
        return DB::transaction(function () use ($user, $id, $input, $summary) {
            $application = Application::query()->assignedTo($user)->lockForUpdate()->findOrFail($id);
            $fields = $summary->validateUpdate($application, $input);
            $granting = $application->category === 'Visa' && $fields['status'] === 'Granted';
            $transition = $granting && $application->status !== 'Granted';
            $hasDates = array_key_exists('grant_effective_from', $input) || array_key_exists('grant_expires_on', $input);
            $dates = Validator::make($input, [
                'grant_effective_from' => $granting && ($transition || $hasDates) ? ['required', 'date_format:Y-m-d'] : ['missing'],
                'grant_expires_on' => $granting && ($transition || $hasDates) ? ['required', 'date_format:Y-m-d'] : ['missing'],
                'grant_visa_subclass' => ['missing'],
            ], [], ['grant_effective_from' => 'Effective from', 'grant_expires_on' => 'Expires on'])->validate();

            // Compare only after both dates pass validation, including malformed array input.
            if (isset($dates['grant_expires_on']) && $dates['grant_expires_on'] < $dates['grant_effective_from']) {
                throw ValidationException::withMessages(['grant_expires_on' => 'Expires on must be on or after Effective from.']);
            }

            if ($granting && ! $application->client_id) {
                throw ValidationException::withMessages(['status' => 'A granted visa must belong to a client.']);
            }
            if ($granting && ! $transition && $hasDates) {
                // A retry of the same submission must not create another grant.
                $previous = DB::table('visa_grants')->where('application_id', $id)->whereNull('cancelled_at')->latest('id')->first();
                if (! $previous || $previous->effective_from !== $dates['grant_effective_from']
                    || $previous->expires_on !== ($dates['grant_expires_on'] ?? null)
                    || $previous->visa_subclass !== $fields['visa_subclass']) {
                    throw ValidationException::withMessages(['status' => 'This application is already granted. Refresh before making further changes.']);
                }
            }
            // Serialize grant creation, cancellation and effective-date processing for a client.
            if ($application->client_id) {
                DB::table('clients')->where('id', $application->client_id)->lockForUpdate()->first();
            }
            $application->update($fields);
            if ($transition) {
                DB::table('visa_grants')->insert([
                    'application_id' => $id, 'client_id' => $application->client_id,
                    'visa_subclass' => $application->visa_subclass,
                    'effective_from' => $dates['grant_effective_from'],
                    'expires_on' => $dates['grant_expires_on'] ?? null, 'created_at' => now(),
                ]);
            } elseif (! $granting) {
                DB::table('visa_grants')->where('application_id', $id)->whereNull('processed_at')->whereNull('cancelled_at')
                    ->update(['cancelled_at' => now()]);
            }
            if ($application->client_id) {
                $this->applyForClient($application->client_id);
            }

            return $application;
        });
    }

    private function due(): Builder
    {
        return DB::table('visa_grants')->whereNull('cancelled_at')->where('effective_from', '<=', today()->toDateString());
    }

    public function applyForClient(int $clientId): bool
    {
        if (! $this->due()->where('client_id', $clientId)->whereNull('processed_at')->exists()) {
            return false;
        }

        return DB::transaction(function () use ($clientId) {
            $client = DB::table('clients')->where('id', $clientId)->lockForUpdate()->first();
            if (! $client) {
                return false;
            }
            // Backdated or overdue grants must not overwrite a later effective grant.
            // For the same effective date, the most recently saved grant wins.
            $latest = $this->due()->where('client_id', $clientId)->orderByDesc('effective_from')->orderByDesc('id')->first();
            $changed = $latest && $latest->processed_at === null;
            if ($changed) {
                DB::table('clients')->where('id', $clientId)->update([
                    'current_visa' => $latest->visa_subclass, 'visa_expiry' => $latest->expires_on, 'updated_at' => now(),
                ]);
            }
            $this->due()->where('client_id', $clientId)->whereNull('processed_at')->update(['processed_at' => now()]);

            return $changed;
        });
    }

    public function applyDue(): int
    {
        $updated = 0;
        DB::table('clients')->whereIn('id', $this->due()->whereNull('processed_at')->select('client_id'))
            ->orderBy('id')->chunkById(100, function ($clients) use (&$updated) {
                foreach ($clients as $client) {
                    $updated += (int) $this->applyForClient($client->id);
                }
            });

        return $updated;
    }
}

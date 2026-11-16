<?php

namespace App\Support\PSC;

use App\Models\ApplicationRfi;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class RfiList
{
    public const COLUMNS = ['application_id' => 'Application ID', 'request_type' => 'Request Type',
        'received_date' => 'Date received', 'due_date' => 'Response due date', 'status' => 'Status',
        'officer' => 'Assigned officer', 'request_letter' => 'Request letter', 'lodged_date' => 'Date lodged',
        'proof_of_lodgement' => 'Proof of lodgement', 'notes' => 'Notes'];

    public const SORTABLE = ['application_id', 'request_type', 'received_date', 'due_date', 'status', 'officer', 'lodged_date'];

    public function visible(User $user): Builder
    {
        return ApplicationRfi::query()->whereHas('application')->where(fn (Builder $query) => $query
            ->where('assigned_to_user_id', $user->id)
            ->orWhereHas('application', fn (Builder $application) => $application->assignedTo($user)));
    }

    public function filtered(User $user, array $filters): Builder
    {
        $query = $this->visible($user)->with(['application', 'officer']);
        if (empty($filters['display_closed'])) {
            $query->where(fn (Builder $active) => $active->where('status', '!=', 'Closed')->orWhereNull('status'));
        }
        if (filled($filters['application_id'] ?? null)) {
            $term = $this->pattern($filters['application_id']);
            $numeric = ctype_digit(trim($filters['application_id'])) ? (int) trim($filters['application_id']) : null;
            $query->whereHas('application', function (Builder $application) use ($term, $numeric) {
                $prefix = "CASE category WHEN 'SBS' THEN CASE WHEN LOWER(TRIM(sponsor_type)) = 'labour agreement' THEN 'LAB-' ELSE 'SBS-' END WHEN 'Nomination' THEN 'NOM-' ELSE 'PSC-' END";
                $reference = DB::getDriverName() === 'sqlite'
                    ? "($prefix || printf('%04d', applications.id))"
                    : "CONCAT($prefix, LPAD(applications.id, GREATEST(4, LENGTH(applications.id)), '0'))";
                $application->where(function (Builder $id) use ($reference, $term, $numeric) {
                    $id->whereRaw("LOWER($reference) LIKE ? ESCAPE '!'", [$term])
                        ->orWhereRaw("CAST(applications.id AS CHAR) LIKE ? ESCAPE '!'", [$term]);
                    if ($numeric !== null) {
                        $id->orWhere('applications.id', $numeric);
                    }
                });
            });
        }
        foreach (['request_type', 'status', 'assigned_to_user_id'] as $column) {
            if (! empty($filters[$column])) {
                $query->whereIn($column, $filters[$column]);
            }
        }
        foreach (['received_date', 'due_date', 'lodged_date'] as $column) {
            if (! empty($filters[$column.'_from'])) {
                $query->whereDate($column, '>=', $filters[$column.'_from']);
            }
            if (! empty($filters[$column.'_to'])) {
                $query->whereDate($column, '<=', $filters[$column.'_to']);
            }
        }
        $sort = $filters['sort'] ?? 'due_date';
        $direction = $filters['direction'] ?? 'asc';
        if ($sort === 'officer') {
            $query->orderBy(User::select('name')->whereColumn('users.id', 'application_activities.assigned_to_user_id'), $direction);
        } else {
            $query->orderByRaw('CASE WHEN '.$sort.' IS NULL THEN 1 ELSE 0 END')->orderBy($sort, $direction);
        }

        return $query->orderBy('application_activities.id');
    }

    private function pattern(string $value): string
    {
        return '%'.str_replace(['!', '%', '_'], ['!!', '!%', '!_'], mb_strtolower(trim($value))).'%';
    }
}

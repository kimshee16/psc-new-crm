<?php

namespace App\Support\PSC;

use App\Models\ApplicationActivity;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class TaskList
{
    public const COLUMNS = ['Task Description', 'Complete by date', 'Relates to', 'Assignee', 'Task created by', 'Status'];

    public function staff(User $user): Builder
    {
        $query = User::query();
        if (in_array($user->account_type, ['Admin', 'Super Admin'], true)) {
            return $query;
        }

        return $query->where(function (Builder $staff) use ($user) {
            $staff->whereKey($user->id);
            if (in_array($user->account_type, [User::ACCOUNT_TYPE_MANAGER, User::ACCOUNT_TYPE_REGIONAL_MANAGER], true)) {
                foreach ($user->accessibleOfficeCodes() as $code) {
                    $staff->orWhereJsonContains('offices', $code);
                }
            }
        });
    }

    public function visible(User $user): Builder
    {
        $query = ApplicationActivity::query()->where('kind', 'tasks');

        return in_array($user->account_type, ['Admin', 'Super Admin'], true)
            ? $query : $query->whereIn('assigned_to_user_id', $this->staff($user)->select('id'));
    }

    public function filtered(User $user, array $filters): Builder
    {
        $query = $this->visible($user)->with(['application', 'lead', 'assignee', 'creator']);
        if (filled($filters['description'] ?? null)) {
            $this->contains($query, 'title', $filters['description']);
        }
        if (filled($filters['relates_to'] ?? null)) {
            $term = $filters['relates_to'];
            $query->where(function (Builder $related) use ($term) {
                $related->whereHas('lead', fn (Builder $lead) => $this->contains($lead, 'lead_id', $term))
                    ->orWhereHas('application', function (Builder $application) use ($term) {
                        $prefix = "CASE category WHEN 'SBS' THEN CASE WHEN LOWER(TRIM(sponsor_type)) = 'labour agreement' THEN 'LAB-' ELSE 'SBS-' END WHEN 'Nomination' THEN 'NOM-' ELSE 'PSC-' END";
                        $reference = DB::getDriverName() === 'sqlite'
                            ? "($prefix || printf('%04d', applications.id))"
                            : "CONCAT($prefix, LPAD(applications.id, GREATEST(4, LENGTH(applications.id)), '0'))";
                        $application->where(function (Builder $id) use ($reference, $term) {
                            $this->contains($id, $reference, $term);
                            if (ctype_digit(trim($term))) {
                                $id->orWhere('applications.id', (int) trim($term));
                            }
                        });
                    });
            });
        }
        foreach (['assignee' => 'assigned_to_user_id', 'creator' => 'created_by_user_id'] as $filter => $column) {
            if (! empty($filters[$filter])) {
                $query->whereIn($column, $filters[$filter]);
            }
        }
        if (! empty($filters['status'])) {
            $statuses = $filters['status'];
            if (in_array('To Do', $statuses)) {
                $statuses[] = 'Pending';
            }
            if (in_array('In Progress', $statuses)) {
                $statuses[] = 'In progress';
            }
            $query->where(function (Builder $status) use ($statuses, $filters) {
                $status->whereIn('status', $statuses);
                if (in_array('To Do', $filters['status'])) {
                    $status->orWhereNull('status')->orWhere('status', '');
                }
            });
        }

        return $query->orderByRaw('CASE WHEN due_date IS NULL THEN 1 ELSE 0 END')
            ->orderBy('due_date', $filters['direction'] ?? 'asc')->orderByDesc('id');
    }

    private function contains(Builder $query, string $column, string $term): void
    {
        $term = str_replace(['!', '%', '_'], ['!!', '!%', '!_'], mb_strtolower(trim($term)));
        $query->whereRaw("LOWER($column) LIKE ? ESCAPE '!'", ['%'.$term.'%']);
    }
}

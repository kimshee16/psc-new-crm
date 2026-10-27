<?php

namespace App\Support\PSC;

use App\Models\Application;
use App\Models\User;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

class ApplicationList
{
    public const COLUMNS = [
        'id' => 'ID', 'client_name' => 'Client name', 'category' => 'Category',
        'description' => 'Application', 'critical_date' => 'Critical date',
        'priority' => 'Priority', 'status' => 'Status',
        'primary_counsellor' => 'Primary counsellor',
        'secondary_counsellor' => 'Secondary counsellor', 'migration_agent' => 'Migration agent',
    ];

    public function rows(User $user, bool $activeOnly = true): Builder
    {
        $nameParts = ['TRIM(clients.first_name)', "NULLIF(TRIM(clients.middle_name), '')", "NULLIF(TRIM(clients.surname), '')"];
        $name = DB::getDriverName() === 'sqlite'
            ? "TRIM({$nameParts[0]} || COALESCE(' ' || {$nameParts[1]}, '') || COALESCE(' ' || {$nameParts[2]}, ''))"
            : 'CONCAT_WS(\' \', '.implode(', ', $nameParts).')';

        $query = Application::query()->assignedTo($user)
            ->leftJoin('clients', 'clients.id', '=', 'applications.client_id')
            ->leftJoin('organisations', 'organisations.id', '=', 'applications.organisation_id')
            ->leftJoin('users as primary_staff', 'primary_staff.id', '=', 'applications.primary_counsellor_id')
            ->leftJoin('users as secondary_staff', 'secondary_staff.id', '=', 'applications.secondary_counsellor_id')
            ->leftJoin('users as agent', 'agent.id', '=', 'applications.migration_agent_id')
            ->select('applications.id', 'applications.client_id', 'applications.category', 'applications.critical_date', 'applications.priority', 'applications.status', 'applications.updated_at')
            ->selectRaw("COALESCE(organisations.name, $name) AS client_name")
            ->selectRaw("COALESCE(primary_staff.name, '') AS primary_counsellor, COALESCE(secondary_staff.name, '') AS secondary_counsellor, COALESCE(agent.name, '') AS migration_agent")
            ->selectRaw("CASE applications.category
                WHEN 'Study' THEN COALESCE(NULLIF(TRIM(applications.course), ''), 'Course TBD')
                WHEN 'Visa' THEN COALESCE(applications.visa_subclass, '')
                WHEN 'Citizenship' THEN 'Australian Citizenship Application'
                WHEN 'SBS' THEN 'Standard Business Sponsorship'
                WHEN 'ART Appeal' THEN 'ART Appeal'
                WHEN 'Nomination' THEN COALESCE(applications.nomination_pathway, '')
                WHEN 'Assessment' THEN COALESCE(applications.assessment_body, '')
                END AS description");

        if ($activeOnly) {
            $query->active();
        }

        return DB::query()->fromSub($query, 'application_list');
    }

    public function filtered(User $user, array $filters): Builder
    {
        $query = $this->rows($user);
        foreach (array_diff(array_keys(self::COLUMNS), ['critical_date', 'priority']) as $column) {
            if (isset($filters[$column]) && trim($filters[$column]) !== '') {
                // Search is a literal, case-insensitive substring, including % and _.
                $term = str_replace(['!', '%', '_'], ['!!', '!%', '!_'], mb_strtolower(trim($filters[$column])));
                $query->whereRaw("LOWER($column) LIKE ? ESCAPE '!'", ['%'.$term.'%']);
            }
        }
        if (! empty($filters['priority'])) {
            $query->whereIn('priority', $filters['priority']);
        }
        if (! empty($filters['date_from'])) {
            $query->where('critical_date', '>=', $filters['date_from']);
        }
        if (! empty($filters['date_to'])) {
            $query->where('critical_date', '<=', $filters['date_to']);
        }

        $sort = $filters['sort'] ?? 'id';
        $direction = $filters['direction'] ?? 'desc';
        if ($sort === 'priority') {
            $query->orderByRaw("CASE priority WHEN 'H' THEN 3 WHEN 'M' THEN 2 ELSE 1 END $direction");
        } elseif ($sort === 'critical_date') {
            $query->orderByRaw('CASE WHEN critical_date IS NULL THEN 1 ELSE 0 END')->orderBy($sort, $direction);
        } elseif ($sort === 'id') {
            $query->orderBy($sort, $direction);
        } else {
            $query->orderByRaw("LOWER($sort) $direction");
        }

        return $sort === 'id' ? $query : $query->orderByDesc('id');
    }
}

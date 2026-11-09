<?php

namespace App\Support\PSC;

use App\Models\Organisation;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

class OrganisationList
{
    public const COLUMNS = [
        'id' => 'Organisation ID', 'trading_name' => 'Trading name', 'legal_name' => 'Legal name',
        'sbs_held' => 'SBS held', 'renew' => 'Renew',
    ];

    public function filtered(User $user, array $filters): Builder
    {
        $today = today();
        $activeSbs = fn (Builder $query) => $query->where('category', 'SBS')
            ->whereRaw("LOWER(TRIM(status)) = 'active'");
        $rows = Organisation::query()->visibleTo($user)->select('organisations.*')->withExists([
            'applications as sbs_held' => $activeSbs,
            'applications as renew' => function (Builder $query) use ($activeSbs, $today) {
                $activeSbs($query)->where('expiry_date', '>=', $today->toDateString())
                    ->where(function (Builder $types) use ($today) {
                        $types->where(fn (Builder $standard) => $standard
                            ->whereRaw("LOWER(TRIM(sponsor_type)) IN ('standard', 'standard business sponsor', 'standard business sponsorship')")
                            ->where('expiry_date', '<=', $today->copy()->addMonthsNoOverflow(4)->toDateString()))
                            ->orWhere(fn (Builder $accredited) => $accredited
                                ->whereRaw("LOWER(TRIM(sponsor_type)) IN ('accredited', 'accredited sponsor', 'accredited business sponsor', 'accredited business sponsorship')")
                                ->where('expiry_date', '<=', $today->copy()->addMonthsNoOverflow(2)->toDateString()));
                    });
            },
        ]);
        // Wrap the computed indicators so filtering, sorting and CSV share the same values.
        $query = Organisation::query()->fromSub($rows, 'organisations')->select('organisations.*');
        foreach (['trading_name' => 'trading_name', 'legal_name' => 'name'] as $filter => $column) {
            if (filled($filters[$filter] ?? null)) {
                $query->whereRaw("LOWER($column) LIKE ? ESCAPE '!'", [$this->term($filters[$filter])]);
            }
        }
        if (filled($filters['id'] ?? null)) {
            $this->searchId($query, trim($filters['id']));
        }
        // Preserve the existing general search, including ABN and public references.
        if (filled($filters['search'] ?? null)) {
            $query->where(function (Builder $search) use ($filters) {
                foreach (['name', 'trading_name', 'abn'] as $column) {
                    $search->orWhereRaw("LOWER($column) LIKE ? ESCAPE '!'", [$this->term($filters['search'])]);
                }
                $search->orWhere(fn (Builder $id) => $this->searchId($id, trim($filters['search'])));
            });
        }
        foreach (['sbs_held' => ['Y' => 1, 'N' => 0], 'renew' => ['flag' => 1, 'no_flag' => 0]] as $column => $values) {
            if (isset($values[$filters[$column] ?? ''])) {
                $query->where($column, $values[$filters[$column]]);
            }
        }

        $sort = $filters['sort'] ?? 'trading_name';
        $direction = $filters['direction'] ?? 'asc';
        if (in_array($sort, ['trading_name', 'legal_name'], true)) {
            $column = $sort === 'legal_name' ? 'name' : $sort;
            $query->orderByRaw("CASE WHEN NULLIF(TRIM($column), '') IS NULL THEN 1 ELSE 0 END")
                ->orderByRaw("LOWER($column) $direction");
        } else {
            $query->orderBy($sort, $direction);
        }

        return $sort === 'id' ? $query : $query->orderBy('id');
    }

    private function term(string $value): string
    {
        return '%'.str_replace(['!', '%', '_'], ['!!', '!%', '!_'], mb_strtolower(trim($value))).'%';
    }

    private function searchId(Builder $query, string $value): void
    {
        if (preg_match('/^ORG-([0-9]+)$/i', $value, $matches)) {
            $query->where('id', ltrim($matches[1], '0') ?: '0');
        } else {
            $query->whereRaw("CAST(id AS CHAR) LIKE ? ESCAPE '!'", [$this->term($value)]);
        }
    }
}

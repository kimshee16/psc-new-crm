<?php

namespace App\Support\PSC;

use App\Models\Application;
use App\Models\User;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

class ApplicationLinks
{
    public function __construct(private readonly ApplicationList $list) {}

    public function rows(Application $application, User $user): Builder
    {
        return $this->list->rows($user, false)
            ->whereIn('id', Application::query()->linkedTo($application)->select('applications.id'))
            ->orderBy('id');
    }

    public function candidates(Application $application, User $user, string $search): Builder
    {
        $query = $this->list->rows($user, false)->where('id', '!=', $application->id)
            ->whereNotIn('id', Application::query()->linkedTo($application)->select('applications.id'));

        if ($search !== '') {
            $term = '%'.str_replace(['!', '%', '_'], ['!!', '!%', '!_'], mb_strtolower($search)).'%';
            $query->where(function (Builder $query) use ($search, $term) {
                foreach (['description', 'client_name', 'category', 'status'] as $column) {
                    $query->orWhereRaw("LOWER($column) LIKE ? ESCAPE '!'", [$term]);
                }
                if (ctype_digit($search)) {
                    $query->orWhere('id', $search);
                }
            });
        }

        return $query->orderByDesc('id');
    }

    public function change(User $user, int $applicationId, int $linkedId, bool $link): void
    {
        abort_if($applicationId === $linkedId, 422, 'An application cannot link to itself.');

        DB::transaction(function () use ($user, $applicationId, $linkedId, $link) {
            // Lock both records in a consistent order, including for reciprocal requests.
            $records = Application::query()->assignedTo($user)
                ->whereIn('id', [$applicationId, $linkedId])->orderBy('id')->lockForUpdate()->get();
            abort_unless($records->count() === 2, 404);

            $pair = [
                'application_id' => min($applicationId, $linkedId),
                'linked_application_id' => max($applicationId, $linkedId),
            ];
            $query = DB::table('application_links')->where($pair);

            if ($link) {
                if (! $query->exists()) {
                    DB::table('application_links')->insert($pair + ['created_at' => now()]);
                }
            } else {
                $query->delete();
                // Clear only the requested legacy pair, preserving any other links.
                foreach ($records as $record) {
                    $otherId = $record->id === $applicationId ? $linkedId : $applicationId;
                    if ((int) $record->linked_application_id === $otherId) {
                        $record->linked_application_id = null;
                        $record->save();
                    }
                }
            }
        });
    }

    public function choices(Application $application, User $user, ?int $id = null): Builder
    {
        $query = $this->list->rows($user, false)->where('id', '!=', $application->id)
            ->whereNotIn('id', Application::query()->linkedTo($application)->select('applications.id'));

        // An organisation with no client must not suggest every clientless application.
        return $id !== null
            ? $query->where('id', $id)
            : $query->where('client_id', $application->client_id)->whereNotNull('client_id')->orderByDesc('id');
    }

    public function save(User $user, int $applicationId, array $add, array $remove): void
    {
        DB::transaction(function () use ($user, $applicationId, $add, $remove) {
            $ids = array_values(array_unique([$applicationId, ...$add, ...$remove]));
            // Lock the whole batch before changing any pair; a failed target rolls back everything.
            $records = Application::query()->assignedTo($user)->whereIn('id', $ids)
                ->orderBy('id')->lockForUpdate()->get();
            abort_unless($records->count() === count($ids), 404);
            foreach ($add as $id) {
                $this->change($user, $applicationId, (int) $id, true);
            }
            foreach ($remove as $id) {
                $this->change($user, $applicationId, (int) $id, false);
            }
        });
    }
}

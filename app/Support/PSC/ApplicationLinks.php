<?php

namespace App\Support\PSC;

use App\Models\Application;
use App\Models\User;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

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
            $nomination = $records->firstWhere('category', 'Nomination');
            $visa = $records->firstWhere('category', 'Visa');
            $nominationVisa = $nomination && $visa;
            $wasLinked = $nominationVisa && Application::query()->linkedTo($nomination)->whereKey($visa->id)->exists();

            if ($link) {
                if ($nominationVisa) {
                    // Both endpoints are locked, so concurrent requests sharing either
                    // endpoint cannot assign a second visa or a second nomination.
                    $otherVisa = Application::query()->linkedTo($nomination)->where('category', 'Visa')->whereKeyNot($visa->id)->lockForUpdate()->first();
                    $otherNomination = Application::query()->linkedTo($visa)->where('category', 'Nomination')->whereKeyNot($nomination->id)->lockForUpdate()->first();
                    if ($otherVisa || $otherNomination) {
                        throw ValidationException::withMessages(['linked_application_id' => 'Only one visa application can be linked to a nomination, and each visa can link to only one nomination.']);
                    }
                }
                if (! $query->exists()) {
                    DB::table('application_links')->insert($pair + ['created_at' => now()]);
                }
                if ($nominationVisa) {
                    $nomination->fill(['nominee_client_id' => $visa->client_id, 'nominee_name' => null, 'nomination_pathway' => $visa->visa_subclass]);
                    if (! $wasLinked) {
                        $nomination->sponsorship_id = app(NominationRecord::class)->defaultSponsorship($nomination, $user);
                    }
                    $nomination->save();
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
                if ($nominationVisa && $wasLinked) {
                    $nomination->fill(['nominee_client_id' => null, 'nominee_name' => null, 'nomination_pathway' => null])->save();
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
            : $query->where('client_id', $application->category === 'Nomination' ? $application->nominee_client_id : $application->client_id)
                ->whereNotNull('client_id')
                ->when($application->category === 'Nomination', fn ($q) => $q->where('category', 'Visa')
                    ->whereNotIn('status', ['Finalised', 'Withdrawn']))->orderByDesc('id');
    }

    public function save(User $user, int $applicationId, array $add, array $remove): void
    {
        DB::transaction(function () use ($user, $applicationId, $add, $remove) {
            $ids = array_values(array_unique([$applicationId, ...$add, ...$remove]));
            // Lock the whole batch before changing any pair; a failed target rolls back everything.
            $records = Application::query()->assignedTo($user)->whereIn('id', $ids)
                ->orderBy('id')->lockForUpdate()->get();
            abort_unless($records->count() === count($ids), 404);
            foreach ($remove as $id) {
                $this->change($user, $applicationId, (int) $id, false);
            }
            foreach ($add as $id) {
                $this->change($user, $applicationId, (int) $id, true);
            }
        });
    }
}

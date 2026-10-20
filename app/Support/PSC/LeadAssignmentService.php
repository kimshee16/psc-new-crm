<?php

namespace App\Support\PSC;

use App\Models\Lead;
use App\Models\Office;
use App\Models\User;
use Illuminate\Support\Collection;

class LeadAssignmentService
{
    public function __construct(private readonly WebsiteLeadReferences $references) {}

    /**
     * @return array{user: User|null, name: string, office_code: string, office: string, reason: string}
     */
    public function assign(string $nationality, string $currentLocation): array
    {
        $redirectedOfficeCode = $this->references->officeCodeForRedirection($nationality, $currentLocation);
        $officeCode = $redirectedOfficeCode ?? $this->references->officeCodeForLocation($currentLocation);
        $offices = WebsiteLeadReferences::offices();
        if (! isset($offices[$officeCode])) {
            $officeCode = Office::defaultCode();
        }
        $office = $offices[$officeCode];
        $user = $this->nextRoundRobinUserForOffice($officeCode);
        $routeSource = $redirectedOfficeCode !== null
            ? "Nationality {$nationality} and current location {$currentLocation} redirected to {$office}"
            : "Current location {$currentLocation} mapped to {$office}";

        if ($user !== null) {
            return [
                'user' => $user,
                'name' => $this->assigneeName($user),
                'office_code' => $officeCode,
                'office' => $office,
                'reason' => "{$routeSource}; assigned by office round robin.",
            ];
        }

        return [
            'user' => null,
            'name' => "{$office} Web Queue",
            'office_code' => $officeCode,
            'office' => $office,
            'reason' => "{$routeSource}; no eligible round robin user is configured.",
        ];
    }

    private function nextRoundRobinUserForOffice(string $officeCode): ?User
    {
        /** @var Collection<int, User> $users */
        $users = User::query()
            ->whereDoesntHave('staffLeaves', fn ($query) => $query->onDate(now()->toDateString()))
            ->orderBy('id')
            ->get()
            ->filter(fn (User $user): bool => $this->isEligibleForOffice($user, $officeCode))
            ->values();

        if ($users->isEmpty()) {
            return null;
        }

        $eligibleUserIds = $users->map(fn (User $user): int => (int) $user->getKey())->all();
        $lastAssignedUserId = Lead::query()
            ->where('assigned_office_code', $officeCode)
            ->whereIn('assigned_user_id', $eligibleUserIds)
            ->latest('created_at')
            ->latest('id')
            ->value('assigned_user_id');

        if ($lastAssignedUserId === null) {
            return $users->first();
        }

        $lastIndex = $users->search(fn (User $user): bool => (int) $user->getKey() === (int) $lastAssignedUserId);

        if ($lastIndex === false) {
            return $users->first();
        }

        return $users->get(((int) $lastIndex + 1) % $users->count());
    }

    private function isEligibleForOffice(User $user, string $officeCode): bool
    {
        return $user->include_in_round_robin
            && $user->hasPortalAccess()
            && $user->defaultOfficeCode() === $officeCode;
    }

    private function assigneeName(User $user): string
    {
        return trim($user->name) !== '' ? $user->name : $user->email;
    }
}

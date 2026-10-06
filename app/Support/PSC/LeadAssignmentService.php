<?php

namespace App\Support\PSC;

use App\Models\Lead;
use App\Models\User;
use Illuminate\Support\Collection;

class LeadAssignmentService
{
    public function __construct(private readonly WebsiteLeadReferences $references)
    {
    }

    /**
     * @return array{user: User|null, name: string, office_code: string, office: string, reason: string}
     */
    public function assign(string $currentLocation): array
    {
        $officeCode = $this->references->officeCodeForLocation($currentLocation);
        $offices = WebsiteLeadReferences::offices();
        $office = $offices[$officeCode] ?? $offices['MEL'];
        $user = $this->leastLoadedUserForOffice($officeCode);

        if ($user !== null) {
            return [
                'user' => $user,
                'name' => $this->assigneeName($user),
                'office_code' => $officeCode,
                'office' => $office,
                'reason' => "Current location {$currentLocation} mapped to {$office}; assigned to the lowest active lead load.",
            ];
        }

        return [
            'user' => null,
            'name' => "{$office} Web Queue",
            'office_code' => $officeCode,
            'office' => $office,
            'reason' => "Current location {$currentLocation} mapped to {$office}; no user with that office is configured.",
        ];
    }

    private function leastLoadedUserForOffice(string $officeCode): ?User
    {
        /** @var Collection<int, User> $users */
        $users = User::query()
            ->orderBy('id')
            ->get()
            ->filter(fn (User $user): bool => in_array($officeCode, $user->assignedOfficeCodes(), true));

        return $users
            ->sortBy([
                fn (User $user): int => Lead::query()->where('assigned_user_id', $user->getKey())->count(),
                fn (User $user): int => (int) $user->getKey(),
            ])
            ->first();
    }

    private function assigneeName(User $user): string
    {
        return trim($user->name) !== '' ? $user->name : $user->email;
    }
}

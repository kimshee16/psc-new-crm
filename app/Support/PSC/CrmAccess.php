<?php

namespace App\Support\PSC;

use App\Models\RolePrivilege;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

class CrmAccess
{
    public const ROLES = [
        User::ACCOUNT_TYPE_ADMIN => 'Administrator',
        'Super Admin' => 'System Administrator',
        User::ACCOUNT_TYPE_REGIONAL_MANAGER => 'Regional Manager',
        User::ACCOUNT_TYPE_MANAGER => 'Office Manager',
        'Counsellor' => 'Staff',
    ];

    public const MODULES = [
        'applications' => ['Applications', ['view', 'create', 'edit', 'export']],
        'leads' => ['Leads', ['view', 'create', 'edit', 'export']],
        'clients' => ['Clients', ['view', 'create', 'edit', 'export']],
        'organisations' => ['Organisations', ['view', 'create', 'edit', 'export']],
        'tasks' => ['Tasks', ['view', 'create', 'edit', 'delete', 'export']],
        'rfis' => ['RFI', ['view', 'create', 'edit', 'delete', 'export']],
        'counsellor-intake' => ['Walk-in Forms', ['view']],
        'my-offices' => ['My Offices', ['view']],
        'reports' => ['Reports', ['view']],
        'holidays' => ['Holidays', ['view', 'create', 'edit']],
        'configuration' => ['Configuration', ['view', 'edit']],
    ];

    private array $settings = [];

    public static function permissionOptions(string $role): array
    {
        $options = [];
        foreach (self::MODULES as $module => [, $actions]) {
            if ($module === 'configuration' && ! in_array($role, [User::ACCOUNT_TYPE_ADMIN, 'Super Admin'], true)) {
                continue;
            }
            if ($module === 'my-offices' && ! in_array($role, User::MY_OFFICES_ACCOUNT_TYPES, true)) {
                continue;
            }
            foreach ($actions as $action) {
                if ($module === 'rfis' && $action === 'delete' && ! in_array($role, [User::ACCOUNT_TYPE_ADMIN, 'Super Admin'], true)) {
                    continue;
                }
                $options[] = $module.'.'.$action;
            }
        }

        return $options;
    }

    public static function scopeOptions(string $role): array
    {
        $scopes = ['existing' => 'Current CRM rules', 'assigned' => 'Assigned records only'];
        if (in_array($role, User::MY_OFFICES_ACCOUNT_TYPES, true)) {
            $scopes['office'] = 'Assigned offices';
        }
        if ($role === User::ACCOUNT_TYPE_REGIONAL_MANAGER) {
            $scopes['region'] = 'Assigned offices and managed regions';
        }

        return $scopes;
    }

    public function allows(User $user, string $module, string $action = 'view'): bool
    {
        $role = (string) $user->account_type;
        $permission = $module.'.'.$action;
        if (! isset(self::ROLES[$role]) || ! in_array($permission, self::permissionOptions($role), true)) {
            return false;
        }

        $permissions = $this->settingsFor($user)?->permissions;

        return $permissions === null || (in_array($module.'.view', $permissions, true)
            && in_array($permission, $permissions, true));
    }

    public function notes(User $user, string $module, bool $write = false): bool
    {
        $access = $this->settingsFor($user)?->notes_access ?? 'write';

        return $this->allows($user, $module) && ($write ? $access === 'write' : $access !== 'hidden');
    }

    public function scope(User $user): string
    {
        return $this->settingsFor($user)?->record_scope ?? 'existing';
    }

    public function offices(User $user): array
    {
        return $this->scope($user) === 'region' ? $user->accessibleOfficeCodes() : $user->assignedOfficeCodes();
    }

    public function staffInScope(User $user): Builder
    {
        $codes = in_array($this->scope($user), ['office', 'region'], true) ? $this->offices($user) : [];

        return User::query()->where(function (Builder $staff) use ($user, $codes): void {
            $staff->whereKey($user->id);
            foreach ($codes as $code) {
                $staff->orWhereJsonContains('offices', $code);
            }
        });
    }

    public function officeNamesAndCodes(User $user): array
    {
        $codes = $this->offices($user);
        $names = array_intersect_key(WebsiteLeadReferences::offices(), array_flip($codes));

        return [...$codes, ...array_values($names)];
    }

    private function settingsFor(User $user): ?RolePrivilege
    {
        $role = (string) $user->account_type;
        if ($role === 'Super Admin' || ! isset(self::ROLES[$role])) {
            return null;
        }
        if (! array_key_exists($role, $this->settings)) {
            $this->settings[$role] = RolePrivilege::query()->where('account_type', $role)->first();
        }

        return $this->settings[$role];
    }
}

<?php

namespace App\Http\Controllers;

use App\Models\RolePrivilege;
use App\Models\User;
use App\Support\PSC\CrmAccess;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class RolePrivilegesController extends Controller
{
    public function index(Request $request): View
    {
        $this->authorizeAdmin($request);
        $role = $request->query('role', 'Counsellor');
        abort_unless(is_string($role) && isset(CrmAccess::ROLES[$role]), 404);

        return view('crm.configuration.privileges', [
            'title' => 'PSC CRM - User Access & Privileges',
            'role' => $role,
            'setting' => RolePrivilege::query()->where('account_type', $role)->first(),
            'options' => CrmAccess::permissionOptions($role),
            'scopes' => CrmAccess::scopeOptions($role),
            'canEditRole' => $role !== 'Super Admin'
                && ($role !== User::ACCOUNT_TYPE_ADMIN || $request->user()->account_type === 'Super Admin'),
        ]);
    }

    public function update(Request $request, string $role): RedirectResponse
    {
        $this->authorizeAdmin($request);
        abort_unless(isset(CrmAccess::ROLES[$role]), 404);
        abort_if($role === 'Super Admin', 403);
        abort_if($role === User::ACCOUNT_TYPE_ADMIN && $request->user()->account_type !== 'Super Admin', 403);

        $data = $request->validate([
            'permissions' => ['sometimes', 'array', 'max:60'],
            'permissions.*' => ['required', 'string', 'distinct', Rule::in(CrmAccess::permissionOptions($role))],
            'record_scope' => ['required', Rule::in(array_keys(CrmAccess::scopeOptions($role)))],
            'notes_access' => ['required', Rule::in(['hidden', 'read', 'write'])],
        ]);
        $permissions = $data['permissions'] ?? [];
        $permissions = array_values(array_filter($permissions, fn ($permission) =>
            in_array(strstr($permission, '.', true).'.view', $permissions, true)));

        $policy = new RolePrivilege;
        DB::table($policy->getTable())->upsert([[
            'account_type' => $role,
            'permissions' => json_encode($permissions, JSON_THROW_ON_ERROR),
            'record_scope' => $data['record_scope'],
            'notes_access' => $data['notes_access'],
            'created_at' => now(),
            'updated_at' => now(),
        ]], ['account_type'], ['permissions', 'record_scope', 'notes_access', 'updated_at']);

        return redirect()->route('role-privileges', ['role' => $role])->with('success', 'Role privileges saved.');
    }

    private function authorizeAdmin(Request $request): void
    {
        abort_unless(app(CrmAccess::class)->allows($request->user(), 'configuration', 'edit'), 403);
    }
}

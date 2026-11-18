<?php

namespace App\Http\Middleware;

use App\Support\PSC\CrmAccess;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class EnsureCrmPrivilege
{
    public function __construct(private readonly CrmAccess $access) {}

    public function handle(Request $request, Closure $next): Response
    {
        $access = $this->access;
        $name = $request->route()?->getName() ?? '';
        $module = Str::before($name, '.');
        $user = $request->user();

        if ($module === 'notes') {
            abort_unless($access->notes($user, $request->route('module'), write: true), 403);
        } elseif ($name === 'rfis.notes') {
            abort_unless($access->notes($user, 'rfis', write: true), 403);
        } elseif (isset(CrmAccess::MODULES[$module])) {
            $action = match (true) {
                str_ends_with($name, '.export') => 'export',
                $request->isMethod('DELETE') => $module === 'configuration' ? 'edit' : 'delete',
                str_ends_with($name, '.edit'), ! $request->isMethodSafe() => 'edit',
                default => 'view',
            };

            if (in_array($name, ['organisations.create', 'organisations.store', 'clients.create', 'clients.store',
                'holidays.create', 'holidays.store', 'tasks.store'], true)) {
                $action = 'create';
            }

            $target = match ($name) {
                'clients.applications.create' => $request->boolean('create_lead') ? 'leads' : 'applications',
                'leads.applications.create', 'organisations.applications.create', 'organisations.applications.store',
                'organisations.nominees' => 'applications',
                'applications.rfis.create', 'applications.rfis.store' => 'rfis',
                'organisations.tasks.store', 'organisations.tasks.update', 'organisations.tasks.destroy' => 'tasks',
                'applications.activities.store' => $request->input('kind') === 'tasks' ? 'tasks' : null,
                default => null,
            };

            if ($target !== null) {
                $targetAction = match ($name) {
                    'organisations.tasks.update' => 'edit',
                    'organisations.tasks.destroy' => 'delete',
                    'organisations.nominees' => 'view',
                    default => 'create',
                };
                abort_unless($access->allows($user, $module) && $access->allows($user, $target, $targetAction), 403);
            } else {
                abort_unless($access->allows($user, $module, $action), 403);
            }

            if ($module === 'my-offices' && $name !== 'my-offices.index') {
                $related = Str::after($name, '.');
                abort_unless($access->allows($user, $related === 'rfi' ? 'rfis' : $related), 403);
            }
        }

        if ($name === 'leads.applications.create' && ! $request->boolean('create_lead')) {
            abort_unless($access->allows($user, 'leads', 'edit'), 403);
        }
        if ($name === 'organisations.nominees') {
            abort_unless($access->allows($user, 'clients'), 403);
        }
        if ($name === 'tasks.store') {
            $related = match ($request->input('related_type')) {
                'application' => 'applications',
                'lead' => 'leads',
                default => null,
            };
            abort_if($related !== null && ! $access->allows($user, $related), 403);
        }
        if ($name === 'clients.store' || $name === 'rfis.notes' || $name === 'applications.rfis.store') {
            $notesModule = $name === 'clients.store' ? 'clients' : 'rfis';
            abort_if(($request->filled('notes') || $request->filled('note_bodies'))
                && ! $access->notes($user, $notesModule, write: true), 403);
        }

        return $next($request);
    }
}

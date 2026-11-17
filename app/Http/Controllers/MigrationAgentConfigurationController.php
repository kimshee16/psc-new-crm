<?php

namespace App\Http\Controllers;

use App\Models\MigrationAgent;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\StreamedResponse;

class MigrationAgentConfigurationController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        return $this->save($request, new MigrationAgent);
    }

    public function update(Request $request, MigrationAgent $migrationAgent): RedirectResponse
    {
        return $this->save($request, $migrationAgent);
    }

    private function save(Request $request, MigrationAgent $agent): RedirectResponse
    {
        $this->authorizeAdmin($request);
        $data = $request->validate([
            'first_name' => ['required', 'string', 'max:120'],
            'last_name' => ['required', 'string', 'max:120'],
            'registration_number' => ['required', 'string', 'max:40', Rule::unique('migration_agents')->ignore($agent)],
            'email' => ['nullable', 'email', 'max:255'],
            'user_id' => ['nullable', 'integer', Rule::exists('users', 'id'), Rule::unique('migration_agents')->ignore($agent)],
            'signature' => [$agent->exists ? 'nullable' : 'required', 'image', 'mimes:png,jpg,jpeg,webp', 'max:2048'],
        ]);
        unset($data['signature']);
        $data['email'] = filled($data['email'] ?? null) ? strtolower($data['email']) : null;
        $data['user_id'] = array_key_exists('user_id', $data)
            ? ($data['user_id'] !== null ? (int) $data['user_id'] : null) : $agent->user_id;
        $newSignature = null;
        $oldSignature = null;
        try {
            DB::transaction(function () use ($request, &$agent, $data, &$newSignature, &$oldSignature) {
                if ($agent->exists) {
                    $agent = MigrationAgent::whereKey($agent->id)->lockForUpdate()->firstOrFail();
                    if ($agent->isInUse() && $agent->user_id !== $data['user_id']) {
                        throw ValidationException::withMessages(['user_id' => 'This agent is used by a workflow or application. Keep the existing CRM user link.']);
                    }
                }
                $oldSignature = $agent->signature_path;
                if ($request->hasFile('signature')) {
                    $newSignature = $request->file('signature')->store('migration-agent-signatures', 'local');
                    abort_unless($newSignature, 500, 'The signature image could not be saved.');
                }
                $agent->fill($data);
                if ($newSignature) {
                    $agent->signature_path = $newSignature;
                }
                $agent->save();
            });
        } catch (\Throwable $exception) {
            if ($newSignature) {
                Storage::disk('local')->delete($newSignature);
            }
            throw $exception;
        }
        if ($newSignature && $oldSignature) {
            Storage::disk('local')->delete($oldSignature);
        }

        return redirect()->route('configuration', ['tab' => 'migration-agents'])->with('success', 'Migration agent was saved.');
    }

    public function signature(Request $request, MigrationAgent $migrationAgent): StreamedResponse
    {
        $this->authorizeAdmin($request);
        abort_unless(Storage::disk('local')->exists($migrationAgent->signature_path), 404);

        return Storage::disk('local')->response($migrationAgent->signature_path, null, [
            'X-Content-Type-Options' => 'nosniff', 'Cache-Control' => 'private, no-store',
        ]);
    }

    public function destroy(Request $request, MigrationAgent $migrationAgent): RedirectResponse
    {
        $this->authorizeAdmin($request);
        $signature = DB::transaction(function () use ($migrationAgent) {
            $agent = MigrationAgent::whereKey($migrationAgent->id)->lockForUpdate()->firstOrFail();
            if ($agent->isInUse()) {
                throw ValidationException::withMessages(['migration_agent' => 'This agent is used by a workflow or application and cannot be removed.']);
            }
            $agent->delete();

            return $agent->signature_path;
        });
        Storage::disk('local')->delete($signature);

        return redirect()->route('configuration', ['tab' => 'migration-agents'])->with('success', 'Migration agent was removed.');
    }

    private function authorizeAdmin(Request $request): void
    {
        abort_unless(in_array($request->user()?->account_type, [User::ACCOUNT_TYPE_ADMIN, 'Super Admin'], true), 403);
    }
}

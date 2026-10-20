<?php

namespace App\Http\Controllers;

use App\Models\Country;
use App\Models\User;
use Closure;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CountryConfigurationController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        return $this->save($request, new Country);
    }

    public function update(Request $request, Country $country): RedirectResponse
    {
        return $this->save($request, $country);
    }

    private function save(Request $request, Country $country): RedirectResponse
    {
        $this->authorizeConfiguration($request);
        $validated = $request->validate([
            'name' => ['bail', 'required', 'string', 'max:80',
                function (string $attribute, mixed $value, Closure $fail) use ($country): void {
                    $name = mb_strtolower($value);
                    $duplicate = Country::query()
                        ->where(function ($query) use ($country, $name) {
                            $query->where(function ($query) use ($country, $name) {
                                $query->whereRaw('LOWER(name) = ?', [$name]);
                                if ($country->exists) {
                                    $query->where('id', '!=', $country->id);
                                }
                            })->orWhereRaw('LOWER(legacy_nationality) = ?', [$name]);
                        })->exists();
                    if ($duplicate) {
                        $fail('This country name or nationality label already exists.');
                    }
                }],
            'active' => ['required', 'boolean'],
        ], [], ['name' => 'country name']);

        DB::transaction(function () use ($country, $validated) {
            $oldName = $country->exists ? $country->name : null;
            $country->fill($validated)->save();

            // These fields store names, so keep references and routing in sync on rename.
            if ($oldName !== null && $oldName !== $country->name) {
                foreach (['clients', 'leads', 'lead_redirections'] as $table) {
                    DB::table($table)->where('nationality', $oldName)
                        ->update(['nationality' => $country->name, 'updated_at' => now()]);
                }
            }
        });

        return redirect()->route('configuration', ['tab' => 'countries'])
            ->with('success', 'Country was saved.');
    }

    public function destroy(Request $request, Country $country): RedirectResponse
    {
        $this->authorizeConfiguration($request);
        foreach (['clients', 'leads', 'lead_redirections'] as $table) {
            if (DB::table($table)->whereIn('nationality', $country->nationalityValues())->exists()) {
                return redirect()->route('configuration', ['tab' => 'countries'])
                    ->with('error', 'This country is used by clients, leads or redirections. Deactivate it instead.');
            }
        }

        $country->delete();

        return redirect()->route('configuration', ['tab' => 'countries'])
            ->with('success', 'Country was removed.');
    }

    private function authorizeConfiguration(Request $request): void
    {
        abort_unless(in_array($request->user()?->account_type, [User::ACCOUNT_TYPE_ADMIN, 'Super Admin'], true), 403);
    }
}

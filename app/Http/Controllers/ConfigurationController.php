<?php

namespace App\Http\Controllers;

use App\Models\EducationLevel;
use App\Models\Location;
use App\Models\User;
use App\Support\PSC\WebsiteLeadReferences;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ConfigurationController extends Controller
{
    private const REQUIRED_LOCATION_NAME = 'Other Location';

    public function index(Request $request): View
    {
        $this->authorizeConfiguration($request->user());
        $this->ensureRequiredLocation();

        return view('crm.configuration.index', [
            'title' => 'PSC CRM - Configuration',
            'activeConfigurationTab' => $this->activeConfigurationTab($request),
            'educationLevels' => EducationLevel::query()
                ->orderBy('sort_order')
                ->orderBy('name')
                ->get(),
            'locations' => Location::query()
                ->orderBy('sort_order')
                ->orderBy('name')
                ->get(),
            'offices' => WebsiteLeadReferences::offices(),
            'requiredLocationName' => self::REQUIRED_LOCATION_NAME,
        ]);
    }

    public function storeEducationLevel(Request $request): RedirectResponse
    {
        $this->authorizeConfiguration($request->user());

        $validated = $request->validate($this->educationLevelRules(), [], $this->educationLevelAttributes());

        EducationLevel::create([
            'name' => trim((string) $validated['name']),
            'active' => $request->boolean('active', true),
            'sort_order' => (int) ($validated['sort_order'] ?? $this->nextEducationSortOrder()),
        ]);

        return redirect()
            ->route('configuration', ['tab' => 'education-levels'])
            ->with('success', 'Education level option was added.');
    }

    public function updateEducationLevel(Request $request, EducationLevel $educationLevel): RedirectResponse
    {
        $this->authorizeConfiguration($request->user());

        $validated = $request->validate(
            $this->educationLevelRules($educationLevel),
            [],
            $this->educationLevelAttributes()
        );

        $educationLevel->fill([
            'name' => trim((string) $validated['name']),
            'active' => $request->boolean('active'),
            'sort_order' => (int) ($validated['sort_order'] ?? $educationLevel->sort_order),
        ])->save();

        return redirect()
            ->route('configuration', ['tab' => 'education-levels'])
            ->with('success', 'Education level option was updated.');
    }

    public function destroyEducationLevel(Request $request, EducationLevel $educationLevel): RedirectResponse
    {
        $this->authorizeConfiguration($request->user());

        $educationLevel->delete();

        return redirect()
            ->route('configuration', ['tab' => 'education-levels'])
            ->with('success', 'Education level option was removed.');
    }

    public function storeLocation(Request $request): RedirectResponse
    {
        $this->authorizeConfiguration($request->user());
        $this->ensureRequiredLocation();

        $validated = $request->validate($this->locationRules(), [], $this->locationAttributes());

        Location::create([
            'name' => trim((string) $validated['name']),
            'office_code' => strtoupper(trim((string) $validated['office_code'])),
            'active' => true,
            'sort_order' => $this->nextLocationSortOrder(),
        ]);

        return redirect()
            ->route('configuration', ['tab' => 'locations'])
            ->with('success', 'Location option was added.');
    }

    public function updateLocation(Request $request, Location $location): RedirectResponse
    {
        $this->authorizeConfiguration($request->user());
        $this->ensureRequiredLocation();

        $validated = $request->validate(
            $this->locationRules($location),
            [],
            $this->locationAttributes()
        );

        if ($this->isRequiredLocation($location) && trim((string) $validated['name']) !== self::REQUIRED_LOCATION_NAME) {
            return redirect()
                ->route('configuration', ['tab' => 'locations'])
                ->withErrors(['name' => self::REQUIRED_LOCATION_NAME.' must always remain available.'])
                ->withInput();
        }

        $location->fill([
            'name' => trim((string) $validated['name']),
            'office_code' => strtoupper(trim((string) $validated['office_code'])),
            'active' => true,
        ])->save();

        return redirect()
            ->route('configuration', ['tab' => 'locations'])
            ->with('success', 'Location option was updated.');
    }

    public function destroyLocation(Request $request, Location $location): RedirectResponse
    {
        $this->authorizeConfiguration($request->user());
        $this->ensureRequiredLocation();

        if ($this->isRequiredLocation($location)) {
            return redirect()
                ->route('configuration', ['tab' => 'locations'])
                ->with('error', self::REQUIRED_LOCATION_NAME.' cannot be removed.');
        }

        $location->delete();

        return redirect()
            ->route('configuration', ['tab' => 'locations'])
            ->with('success', 'Location option was removed.');
    }

    private function authorizeConfiguration(?User $user): void
    {
        abort_unless(in_array($user?->account_type, [User::ACCOUNT_TYPE_ADMIN, 'Super Admin'], true), 403);
    }

    /**
     * @return array<string, list<mixed>>
     */
    private function educationLevelRules(?EducationLevel $educationLevel = null): array
    {
        return [
            'name' => [
                'required',
                'string',
                'max:160',
                Rule::unique('education_levels', 'name')->ignore($educationLevel),
            ],
            'sort_order' => ['nullable', 'integer', 'min:1', 'max:999'],
            'active' => ['nullable', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    private function educationLevelAttributes(): array
    {
        return [
            'name' => 'education level',
            'sort_order' => 'sort order',
        ];
    }

    private function nextEducationSortOrder(): int
    {
        return ((int) EducationLevel::query()->max('sort_order')) + 1;
    }

    /**
     * @return array<string, list<mixed>>
     */
    private function locationRules(?Location $location = null): array
    {
        return [
            'name' => [
                'required',
                'string',
                'max:120',
                Rule::unique('locations', 'name')->ignore($location),
            ],
            'office_code' => [
                'required',
                'string',
                Rule::in(array_keys(WebsiteLeadReferences::offices())),
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    private function locationAttributes(): array
    {
        return [
            'name' => 'location',
            'office_code' => 'default office',
        ];
    }

    private function nextLocationSortOrder(): int
    {
        return ((int) Location::query()->max('sort_order')) + 1;
    }

    private function ensureRequiredLocation(): void
    {
        $location = Location::query()->firstOrNew(['name' => self::REQUIRED_LOCATION_NAME]);

        if (! $location->exists) {
            $location->office_code = 'MEL';
            $location->sort_order = $this->nextLocationSortOrder();
        }

        $location->active = true;
        $location->save();
    }

    private function isRequiredLocation(Location $location): bool
    {
        return $location->name === self::REQUIRED_LOCATION_NAME;
    }

    private function activeConfigurationTab(Request $request): string
    {
        $tab = (string) $request->query('tab', 'locations');

        return in_array($tab, ['locations', 'education-levels'], true) ? $tab : 'locations';
    }
}

<?php

namespace App\Http\Controllers;

use App\Models\Country;
use App\Models\EducationLevel;
use App\Models\LeadRedirection;
use App\Models\Location;
use App\Models\Office;
use App\Models\PscEvent;
use App\Models\Region;
use App\Models\User;
use App\Support\PSC\WebsiteLeadReferences;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ConfigurationController extends Controller
{
    private const REQUIRED_LOCATION_NAME = 'Other Location';

    public function index(Request $request): View
    {
        $this->authorizeConfiguration($request->user());
        $this->ensureRequiredLocation();

        $editingEvent = $request->query('tab') === 'events' && $request->query('edit')
            ? PscEvent::findOrFail($request->query('edit')) : null;

        $editingCountry = $request->query('tab') === 'countries' && $request->query('edit')
            ? Country::findOrFail($request->query('edit')) : null;
        $countrySearch = trim((string) $request->query('search', ''));

        $editingRedirection = $request->query('tab') === 'redirections' && $request->query('edit')
            ? LeadRedirection::findOrFail($request->query('edit')) : null;

        $editingUser = $request->query('tab') === 'users' && $request->query('edit')
            ? User::findOrFail($request->query('edit')) : null;
        $userSort = in_array($request->query('sort'), ['name', 'email', 'account_type', 'is_active', 'offices', 'default_office_code', 'include_in_round_robin', 'id', 'portal_invite_status'], true)
            ? $request->query('sort') : 'name';
        $userDirection = $request->query('direction') === 'desc' ? 'desc' : 'asc';

        $editingRegion = $request->query('tab') === 'regions' && $request->query('edit')
            ? Region::with('offices')->findOrFail($request->query('edit')) : null;
        $regionSort = in_array($request->query('sort'), ['name', 'manager', 'offices'], true)
            ? $request->query('sort') : 'name';
        $regionDirection = $request->query('direction') === 'desc' ? 'desc' : 'asc';
        $regions = Region::with(['regionalManager', 'offices'])->get()->sortBy(
            fn (Region $region) => mb_strtolower(match ($regionSort) {
                'manager' => $region->regionalManager?->name ?? '',
                'offices' => $region->offices->pluck('code')->join(', '),
                default => $region->name,
            }), SORT_NATURAL, $regionDirection === 'desc'
        );

        return view('crm.configuration.index', [
            'title' => 'PSC CRM - Configuration',
            'events' => PscEvent::query()->orderByDesc('starts_at')->orderByDesc('id')
                ->paginate(20, ['*'], 'event_page')->appends(['tab' => 'events']),
            'editingEvent' => $editingEvent,
            'showEventForm' => $editingEvent !== null || $request->query('action') === 'create',
            'countries' => Country::query()
                ->when($countrySearch !== '', fn ($query) => $query->where('name', 'like', '%'.$countrySearch.'%'))
                ->orderBy('name')->paginate(25, ['*'], 'country_page')
                ->appends(['tab' => 'countries', 'search' => $countrySearch]),
            'countryCount' => Country::query()->count(),
            'countrySearch' => $countrySearch,
            'editingCountry' => $editingCountry,
            'showCountryForm' => $editingCountry !== null || $request->query('action') === 'create',
            'redirections' => LeadRedirection::query()->orderBy('nationality')->orderBy('current_location')->get(),
            'editingRedirection' => $editingRedirection,
            'showRedirectionForm' => $editingRedirection !== null || $request->query('action') === 'create',
            'nationalities' => array_values(array_unique(array_filter([
                ...WebsiteLeadReferences::countries(), $editingRedirection?->nationality,
            ]))),
            'redirectionLocations' => app(WebsiteLeadReferences::class)->locationNames(),
            'users' => User::query()->orderBy($userSort, $userDirection)->orderBy('id')->get(),
            'editingUser' => $editingUser,
            'showUserForm' => $editingUser !== null || $request->query('action') === 'create',
            'userSort' => $userSort,
            'userDirection' => $userDirection,
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
            'officeRecords' => Office::query()->orderByDesc('office_id')->orderBy('code')->get(),
            'requiredLocationName' => self::REQUIRED_LOCATION_NAME,
            'regions' => $regions,
            'editingRegion' => $editingRegion,
            'showRegionForm' => $editingRegion !== null || $request->query('action') === 'create',
            'regionSort' => $regionSort,
            'regionDirection' => $regionDirection,
            'regionalManagers' => User::query()->where('account_type', User::ACCOUNT_TYPE_REGIONAL_MANAGER)
                ->orderBy('name')->get(['id', 'name', 'email']),
        ]);
    }

    public function storeRedirection(Request $request): RedirectResponse
    {
        return $this->saveRedirection($request, new LeadRedirection);
    }

    public function updateRedirection(Request $request, LeadRedirection $redirection): RedirectResponse
    {
        return $this->saveRedirection($request, $redirection);
    }

    private function saveRedirection(Request $request, LeadRedirection $redirection): RedirectResponse
    {
        $this->authorizeConfiguration($request->user());

        $validated = $request->validate([
            'nationality' => ['required', 'string', Rule::in(array_filter([
                ...WebsiteLeadReferences::countries(), $redirection->nationality,
            ])),
                Rule::unique('lead_redirections', 'nationality')
                    ->where('current_location', $request->input('current_location'))->ignore($redirection)],
            'current_location' => ['required', 'string', Rule::in(app(WebsiteLeadReferences::class)->locationNames())],
            'office_code' => ['required', 'string', Rule::in(array_keys(WebsiteLeadReferences::offices()))],
            'active' => ['required', 'boolean'],
        ], [
            'nationality.unique' => 'A redirection already exists for this nationality and current location. Edit the existing rule instead.',
        ], ['office_code' => 'destination office']);

        $redirection->fill($validated)->save();

        return redirect()->route('configuration', ['tab' => 'redirections'])
            ->with('success', 'Lead redirection was saved.');
    }

    public function destroyRedirection(Request $request, LeadRedirection $redirection): RedirectResponse
    {
        $this->authorizeConfiguration($request->user());
        $redirection->delete();

        return redirect()->route('configuration', ['tab' => 'redirections'])
            ->with('success', 'Lead redirection was removed.');
    }

    public function storeRegion(Request $request): RedirectResponse
    {
        return $this->saveRegion($request, new Region);
    }

    public function updateRegion(Request $request, Region $region): RedirectResponse
    {
        return $this->saveRegion($request, $region);
    }

    private function saveRegion(Request $request, Region $region): RedirectResponse
    {
        $this->authorizeConfiguration($request->user());
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:120', Rule::unique('regions', 'name')->ignore($region)],
            'offices' => ['required', 'array', 'min:1'],
            'offices.*' => ['required', 'integer', 'distinct', Rule::exists('offices', 'id')],
            'regional_manager_id' => ['nullable', 'integer', Rule::exists('users', 'id')
                ->where('account_type', User::ACCOUNT_TYPE_REGIONAL_MANAGER)],
        ], [], ['name' => 'region name', 'regional_manager_id' => 'regional manager', 'offices.*' => 'office']);

        DB::transaction(function () use ($region, $validated) {
            $region->fill([
                'name' => $validated['name'],
                'regional_manager_id' => $validated['regional_manager_id'] ?? null,
            ])->save();
            $region->offices()->sync($validated['offices']);
        });

        return redirect()->route('configuration', ['tab' => 'regions'])
            ->with('success', 'Region was saved.');
    }

    public function updateOffices(Request $request): RedirectResponse
    {
        $this->authorizeConfiguration($request->user());

        $validated = $request->validate([
            'default_office_code' => ['required', 'string', Rule::exists('offices', 'code')],
            'offices' => ['required', 'array', 'size:'.Office::query()->count()],
            'offices.*' => ['required', 'array:code,sender_name,sender_email'],
            'offices.*.code' => ['required', 'string', 'distinct', Rule::exists('offices', 'code')],
            'offices.*.sender_name' => ['nullable', 'string', 'max:160', 'not_regex:/[\r\n]/'],
            'offices.*.sender_email' => ['nullable', 'email', 'max:255'],
        ]);

        DB::transaction(function () use ($validated) {
            // Serialize changes to the entire office configuration, including the single default.
            Office::query()->orderBy('id')->lockForUpdate()->get();
            Office::query()->where('is_default', true)->update(['is_default' => false]);
            Office::query()->where('code', $validated['default_office_code'])->update(['is_default' => true]);

            foreach ($validated['offices'] as $office) {
                Office::query()->where('code', $office['code'])->update([
                    'sender_name' => $office['sender_name'] ?? null,
                    'sender_email' => $office['sender_email'] ?? null,
                ]);
            }

            Location::query()->get()->each(function (Location $location) use ($validated) {
                if (Location::isOtherLocation($location->name)) {
                    $location->update(['office_code' => $validated['default_office_code']]);
                }
            });
        });

        return redirect()->route('configuration', ['tab' => 'offices'])
            ->with('success', 'Office changes were saved.');
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
            'office_code' => Location::isOtherLocation($validated['name'])
                ? Office::defaultCode() : strtoupper(trim((string) $validated['office_code'])),
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
            'office_code' => Location::isOtherLocation($validated['name'])
                ? Office::defaultCode() : strtoupper(trim((string) $validated['office_code'])),
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
            $location->sort_order = $this->nextLocationSortOrder();
        }

        $location->office_code = Office::defaultCode();
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

        return in_array($tab, ['locations', 'education-levels', 'offices', 'regions', 'users', 'redirections', 'countries', 'events'], true) ? $tab : 'locations';
    }
}

<?php

namespace App\Http\Controllers;

use App\Models\Client;
use App\Models\User;
use App\Support\PSC\WebsiteLeadReferences;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ClientsController extends Controller
{
    private const PER_PAGE_OPTIONS = [10, 20, 50];
    private const EDIT_LOCK_MINUTES = 15;

    /**
     * @var list<string>
     */
    private array $sortableColumns = [
        'client_id',
        'first_name',
        'middle_name',
        'surname',
    ];

    public function index(Request $request): View
    {
        return view('crm.clients.index', [
            'title' => 'PSC CRM - My Clients',
            'clients' => $this->assignedClients($request->user()),
            'statuses' => $this->statuses(),
            'selectedStatuses' => $this->selectedStatuses($request),
            'sort' => $this->sortColumn($request),
            'direction' => $this->sortDirection($request),
            'perPageOptions' => self::PER_PAGE_OPTIONS,
            'perPage' => $this->perPage($request),
        ]);
    }

    public function export(Request $request): StreamedResponse
    {
        $clients = $this->filteredClients($request, $request->user());
        $filename = 'my-clients-'.now()->format('Ymd-His').'.csv';

        return response()->streamDownload(function () use ($clients): void {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, ['Client ID', 'First Name', 'Middle Name', 'Surname', 'Mobile Number', 'Status', 'Tag']);

            foreach ($clients as $client) {
                fputcsv($handle, [
                    $client['client_id'],
                    $client['first_name'],
                    $client['middle_name'],
                    $client['surname'],
                    $client['mobile'],
                    $client['status'],
                    $client['tag'],
                ]);
            }

            fclose($handle);
        }, $filename, ['Content-Type' => 'text/csv']);
    }

    public function create(Request $request): View
    {
        $user = $request->user();

        return view('crm.clients.create', [
            'title' => 'PSC CRM - New Client',
            'client' => $this->blankClientFor($user),
            'nationalities' => $this->nationalities(),
            'states' => $this->states(),
            'offices' => $this->offices(),
            'clientStatuses' => $this->statuses(),
            'phoneCountryCodes' => WebsiteLeadReferences::phoneCountryCodes(),
            'duplicateClient' => session('duplicateClient'),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'first_name' => ['required', 'string', 'max:80'],
            'middle_name' => ['nullable', 'string', 'max:80'],
            'surname' => ['nullable', 'string', 'max:80'],
            'dob' => ['nullable', 'date'],
            'phone_country_code' => ['required', 'string', Rule::in(array_keys(WebsiteLeadReferences::phoneCountryCodes()))],
            'phone_number' => ['required', 'string', 'max:40', 'regex:/^[0-9 ()-]{6,24}$/'],
            'email' => ['required', 'email', 'max:120'],
            'nationality' => ['required', 'string', 'max:80'],
            'current_location' => ['required', 'string', 'max:120'],
            'street' => ['nullable', 'string', 'max:160'],
            'suburb' => ['nullable', 'string', 'max:120'],
            'state' => ['nullable', 'string', 'max:40'],
            'postcode' => ['nullable', 'string', 'max:20'],
            'overseas_address' => ['nullable', 'string', 'max:1200'],
            'admin_office' => ['required', 'string', 'max:80'],
            'client_status' => ['required', 'string', 'max:40'],
            'current_visa' => ['nullable', 'string', 'max:120'],
            'visa_expiry' => ['nullable', 'date'],
            'notes' => ['nullable', 'string', 'max:4000'],
            'note_bodies' => ['nullable', 'array', 'max:50'],
            'note_bodies.*' => ['nullable', 'string', 'max:1000'],
            'note_authors' => ['nullable', 'array', 'max:50'],
            'note_authors.*' => ['nullable', 'string', 'max:120'],
            'note_datetimes' => ['nullable', 'array', 'max:50'],
            'note_datetimes.*' => ['nullable', 'string', 'max:40'],
            'proceed_duplicate' => ['nullable', 'boolean'],
        ], [], [
            'first_name' => 'first name',
            'dob' => 'DOB',
            'phone_country_code' => 'country code',
            'phone_number' => 'phone number',
            'email' => 'email address',
            'current_location' => 'current location',
            'admin_office' => 'admin office',
            'client_status' => 'client status',
        ]);

        $user = $request->user();
        $duplicate = $this->duplicateClientFor($validated, $user);

        if ($duplicate !== null && ! $request->boolean('proceed_duplicate')) {
            return back()
                ->withInput($request->except('proceed_duplicate'))
                ->with('duplicateClient', $duplicate);
        }

        $clientId = $this->nextClientIdFor($user);
        $client = Client::create($this->storedClientAttributesFrom($validated, $user, $clientId));

        $fullName = $this->displayName($this->storedClientToArray($client));

        return redirect()
            ->route('clients.show', $clientId)
            ->with('success', "Success! Client ID {$clientId} created for {$fullName}");
    }

    public function search(Request $request): View
    {
        return view('crm.clients.search', [
            'title' => 'PSC CRM - Client Search',
            'clients' => $this->globalSearchResults($request, $request->user()),
            'events' => $this->events(),
            'hasSearch' => $this->hasGlobalSearch($request),
            'statuses' => $this->statuses(),
        ]);
    }

    public function show(Request $request, string $clientId): View
    {
        $client = $this->clientProfileFor($clientId, $request->user());
        $clientName = $this->displayName($client);
        $ownsLock = $this->ownsEditLock($request, $clientId);
        $isEditing = $request->boolean('editing') && $ownsLock;

        if ($isEditing) {
            $this->refreshEditLock($request, $clientId);
        }

        $editLock = $this->editLockFor($clientId);

        return view('crm.clients.show', [
            'title' => 'PSC CRM - '.$clientName,
            'client' => $client,
            'nationalities' => $this->nationalities(),
            'currentLocations' => $this->locations(),
            'states' => $this->states(),
            'offices' => $this->offices(),
            'clientStatuses' => $this->statuses(),
            'visas' => $this->visas(),
            'phoneCountryCodes' => WebsiteLeadReferences::phoneCountryCodes(),
            'isEditing' => $isEditing,
            'isLockedByAnother' => $editLock !== null && ! $ownsLock,
            'editLock' => $editLock,
            'canManageClientNotes' => $this->canManageClientNotes($request->user(), $client),
        ]);
    }

    public function edit(Request $request, string $clientId): RedirectResponse
    {
        $this->clientProfileFor($clientId, $request->user());

        if (! $this->acquireEditLock($request, $clientId)) {
            $lock = $this->editLockFor($clientId);

            return redirect()
                ->route('clients.show', $clientId)
                ->with('warning', 'This client record is currently being edited by '.($lock['user_name'] ?? 'another user').'.');
        }

        return redirect()->route('clients.show', [
            'clientId' => $clientId,
            'editing' => 1,
        ]);
    }

    public function update(Request $request, string $clientId): RedirectResponse
    {
        if (! $this->ownsEditLock($request, $clientId)) {
            return redirect()
                ->route('clients.show', $clientId)
                ->with('warning', 'This client record is locked for editing. Try again after the current edit session ends.');
        }

        $validated = $request->validate($this->clientRecordRules(), [], $this->clientRecordAttributes());
        $user = $request->user();
        $existing = $this->clientListRecordFor($clientId, $user);
        $existingPhotoPath = (string) ($existing['passport_photo_path'] ?? '');
        $passportPhotoPath = $this->passportPhotoPathFrom($request, $existingPhotoPath);

        $client = Client::updateOrCreate(
            ['client_id' => (int) $clientId],
            $this->updatedClientAttributesFrom($validated, $user, (int) $clientId, $existing, $passportPhotoPath)
        );

        if ($existingPhotoPath !== '' && $existingPhotoPath !== $client->passport_photo_path) {
            Storage::disk('public')->delete($existingPhotoPath);
        }

        $this->releaseEditLock($request, $clientId);

        return redirect()
            ->route('clients.show', $clientId)
            ->with('success', 'Success! Client changes were saved.');
    }

    public function discard(Request $request, string $clientId): RedirectResponse
    {
        $this->releaseEditLock($request, $clientId);

        return redirect()->route('clients.show', $clientId);
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function filteredClients(Request $request, User $user): array
    {
        $clients = $this->assignedClients($user);

        $clients = array_values(array_filter($clients, function (array $client) use ($request): bool {
            foreach (['first_name', 'middle_name', 'surname', 'mobile'] as $field) {
                $value = trim((string) $request->query($field, ''));

                if ($value !== '' && ! str_contains(strtolower((string) $client[$field]), strtolower($value))) {
                    return false;
                }
            }

            $statuses = $this->selectedStatuses($request);

            return $statuses === [] || in_array($client['status'], $statuses, true);
        }));

        $sort = $this->sortColumn($request);
        $direction = $this->sortDirection($request);

        usort($clients, function (array $left, array $right) use ($sort, $direction): int {
            $result = $sort === 'client_id'
                ? $left[$sort] <=> $right[$sort]
                : strnatcasecmp((string) $left[$sort], (string) $right[$sort]);

            return $direction === 'desc' ? -$result : $result;
        });

        return $clients;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function assignedClients(User $user): array
    {
        return array_values(array_filter(
            $this->clientsFor($user),
            fn (array $client) => $this->isAssignedTo($client, $user)
        ));
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function globalSearchResults(Request $request, User $user): array
    {
        if (! $this->hasGlobalSearch($request)) {
            return [];
        }

        return array_values(array_filter($this->searchableClientsFor($user), function (array $client) use ($request): bool {
            foreach (['first_name', 'surname', 'mobile', 'email'] as $field) {
                $value = trim((string) $request->query($field, ''));

                if ($value !== '' && ! str_contains(strtolower((string) $client[$field]), strtolower($value))) {
                    return false;
                }
            }

            $clientId = trim((string) $request->query('client_id', ''));

            if ($clientId !== '' && ! str_contains((string) $client['client_id'], $clientId)) {
                return false;
            }

            foreach (['status', 'event'] as $field) {
                $value = trim((string) $request->query($field, ''));

                if ($value !== '' && $client[$field] !== $value) {
                    return false;
                }
            }

            return true;
        }));
    }

    private function hasGlobalSearch(Request $request): bool
    {
        foreach (['client_id', 'first_name', 'surname', 'mobile', 'email', 'status', 'event'] as $field) {
            if (trim((string) $request->query($field, '')) !== '') {
                return true;
            }
        }

        return false;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function searchableClientsFor(User $user): array
    {
        return array_map(function (array $client): array {
            $emailFirst = strtolower(preg_replace('/[^a-z0-9]+/i', '.', (string) $client['first_name']));
            $emailSurname = strtolower(preg_replace('/[^a-z0-9]+/i', '.', (string) $client['surname']));

            return $client + [
                'email' => trim($emailFirst.'.'.$emailSurname, '.').'@example.test',
                'event' => $this->eventFor($client),
            ];
        }, $this->clientsFor($user));
    }

    private function isAssignedTo(array $client, User $user): bool
    {
        $assignee = $this->assigneeName($user);

        return in_array($assignee, [
            $client['primary_counsellor'],
            $client['secondary_counsellor'],
            $client['migration_agent'],
        ], true);
    }

    private function assigneeName(User $user): string
    {
        return trim($user->name) !== '' ? $user->name : $user->email;
    }

    private function sortColumn(Request $request): string
    {
        $sort = (string) $request->query('sort', 'client_id');

        return in_array($sort, $this->sortableColumns, true) ? $sort : 'client_id';
    }

    private function sortDirection(Request $request): string
    {
        return $request->query('direction') === 'desc' ? 'desc' : 'asc';
    }

    private function perPage(Request $request): int
    {
        $perPage = (int) $request->query('per_page', 10);

        return in_array($perPage, self::PER_PAGE_OPTIONS, true) ? $perPage : 10;
    }

    /**
     * @return list<string>
     */
    private function selectedStatuses(Request $request): array
    {
        $statuses = $request->query('statuses', []);

        if (is_string($statuses)) {
            $statuses = [$statuses];
        }

        if (! is_array($statuses)) {
            return [];
        }

        return array_values(array_intersect($this->statuses(), array_map('strval', $statuses)));
    }

    /**
     * @return list<string>
     */
    private function statuses(): array
    {
        return ['Active', 'Prospect', 'On hold', 'Inactive', 'Archived'];
    }

    /**
     * @return list<string>
     */
    private function nationalities(): array
    {
        return ['Australian', 'Brazilian', 'Chinese', 'Indian', 'Indonesian', 'Nepalese', 'Philippine'];
    }

    /**
     * @return list<string>
     */
    private function locations(): array
    {
        return ['Melbourne', 'Sydney', 'Brisbane', 'Perth', 'Adelaide', 'Manila', 'Jakarta', 'Kathmandu', 'Mumbai', 'Beijing', 'Offshore'];
    }

    /**
     * @return list<string>
     */
    private function states(): array
    {
        return ['ACT', 'NSW', 'NT', 'QLD', 'SA', 'TAS', 'VIC', 'WA'];
    }

    /**
     * @return list<string>
     */
    private function offices(): array
    {
        return ['Melbourne', 'Sydney', 'Brisbane', 'Perth', 'Adelaide', 'Manila', 'Philippines Offshore', 'LATAM'];
    }

    /**
     * @return list<string>
     */
    private function visas(): array
    {
        return ['Student visa', 'Visitor visa', 'Temporary Graduate visa', 'Partner visa', 'Skilled visa', 'Bridging visa', 'Permanent resident'];
    }

    /**
     * @return list<string>
     */
    private function events(): array
    {
        return ['Consultation', 'Document request', 'Application review', 'Visa follow up'];
    }

    private function eventFor(array $client): string
    {
        if ($client['status'] === 'Prospect') {
            return 'Consultation';
        }

        if ($client['status'] === 'On hold') {
            return 'Document request';
        }

        if ($client['tag'] === 'Visa') {
            return 'Visa follow up';
        }

        return 'Application review';
    }

    /**
     * @return array<string, mixed>
     */
    private function clientProfileFor(string $clientId, User $user): array
    {
        if ($clientId === '152') {
            return [
                'client_id' => 152,
                'first_name' => 'Rika',
                'middle_name' => '',
                'surname' => 'Supardi',
                'dob' => '',
                'age' => '',
                'mobile' => '',
                'phone_country_code' => '+61',
                'phone_number' => '',
                'email' => '',
                'nationality' => '',
                'current_location' => '',
                'australian_address' => [
                    'street' => '',
                    'suburb' => '',
                    'state' => '',
                    'postcode' => '',
                ],
                'overseas_address' => '',
                'admin_office' => '',
                'client_status' => '',
                'current_visa' => '',
                'visa_expiry' => '',
                'passport_photo_path' => '',
                'passport_photo_url' => '',
                'applications' => [
                    ['id' => 191, 'summary' => 'Advanced Diploma of Building [...]', 'counsellor' => 'Harris Gunawan', 'status' => 'In Progress', 'critical_date' => '10/10/2026', 'last_modified' => '10/10/2025'],
                    ['id' => 192, 'summary' => 'Visa Application - sc500 - Student', 'counsellor' => 'Harris Gunawan', 'status' => 'Not Started', 'critical_date' => '25/10/2026', 'last_modified' => '25/10/2025'],
                ],
                'notes' => ['Sample note'],
                'primary_counsellor' => 'Harris Gunawan',
                'secondary_counsellor' => '',
                'migration_agent' => '',
            ];
        }

        $client = collect($this->searchableClientsFor($user))
            ->first(fn (array $client): bool => (string) $client['client_id'] === $clientId);

        if (! $client) {
            $client = [
                'client_id' => $clientId,
                'first_name' => 'Client',
                'middle_name' => '',
                'surname' => $clientId,
                'mobile' => '',
                'phone_country_code' => '+61',
                'phone_number' => '',
                'email' => '',
                'status' => 'Prospect',
                'tag' => 'Student',
                'event' => 'Consultation',
            ];
        }

        $tag = $client['tag'] ?? 'Student';

        if (($client['source'] ?? null) === 'database') {
            return [
                'client_id' => $client['client_id'],
                'first_name' => $client['first_name'],
                'middle_name' => $client['middle_name'] ?? '',
                'surname' => $client['surname'] ?? '',
                'dob' => $client['dob'] ?? '',
                'age' => $this->ageFor($client['dob'] ?? ''),
                'mobile' => $client['mobile'] ?? '',
                'phone_country_code' => $client['phone_country_code'] ?? '+61',
                'phone_number' => $client['phone_number'] ?? '',
                'email' => $client['email'] ?? '',
                'nationality' => $client['nationality'] ?? '',
                'current_location' => $client['current_location'] ?? '',
                'australian_address' => $client['australian_address'] ?? [
                    'street' => '',
                    'suburb' => '',
                    'state' => '',
                    'postcode' => '',
                ],
                'overseas_address' => $client['overseas_address'] ?? '',
                'admin_office' => $client['admin_office'] ?? '',
                'client_status' => $client['client_status'] ?? 'Active',
                'current_visa' => $client['current_visa'] ?? '',
                'visa_expiry' => $client['visa_expiry'] ?? '',
                'passport_photo_path' => $client['passport_photo_path'] ?? '',
                'passport_photo_url' => $this->passportPhotoUrlFor($client['passport_photo_path'] ?? ''),
                'applications' => [],
                'notes' => $client['notes'] ?? [],
                'primary_counsellor' => $client['primary_counsellor'] ?? '',
                'secondary_counsellor' => $client['secondary_counsellor'] ?? '',
                'migration_agent' => $client['migration_agent'] ?? '',
            ];
        }

        return [
            'client_id' => $client['client_id'],
            'first_name' => $client['first_name'],
            'middle_name' => $client['middle_name'] ?? '',
            'surname' => $client['surname'],
            'dob' => '2000-04-12',
            'age' => 26,
            'mobile' => $client['mobile'] ?? '',
            'phone_country_code' => $client['phone_country_code'] ?? '+61',
            'phone_number' => $client['phone_number'] ?? '',
            'email' => $client['email'] ?? '',
            'nationality' => $tag === 'Student' ? 'Philippine' : 'Indian',
            'current_location' => 'Melbourne',
            'australian_address' => [
                'street' => '22 Collins Street',
                'suburb' => 'Melbourne',
                'state' => 'VIC',
                'postcode' => '3000',
            ],
            'overseas_address' => "Family residence\nHome country",
            'admin_office' => 'Melbourne',
            'client_status' => $client['status'],
            'current_visa' => $tag === 'Student' ? 'Student visa' : 'Visitor visa',
            'visa_expiry' => '2026-10-25',
            'passport_photo_path' => '',
            'passport_photo_url' => '',
            'applications' => [
                ['id' => 191, 'summary' => 'Advanced Diploma of Building [...]', 'counsellor' => $client['primary_counsellor'] ?? $this->assigneeName($user), 'status' => 'In Progress', 'critical_date' => '10/10/2026', 'last_modified' => '10/10/2025'],
                ['id' => 192, 'summary' => 'Visa Application - sc500 - Student', 'counsellor' => $client['secondary_counsellor'] ?: $this->assigneeName($user), 'status' => 'Not Started', 'critical_date' => '25/10/2026', 'last_modified' => '25/10/2025'],
            ],
            'notes' => ['Sample note'],
            'primary_counsellor' => $client['primary_counsellor'] ?? '',
            'secondary_counsellor' => $client['secondary_counsellor'] ?? '',
            'migration_agent' => $client['migration_agent'] ?? '',
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function clientsFor(User $user): array
    {
        $currentUser = $this->assigneeName($user);
        $otherCounsellor = 'Priya Raman';
        $otherAgent = 'Nina Patel';

        $seedClients = [
            ['client_id' => 1001, 'first_name' => 'Ana', 'middle_name' => 'Maria', 'surname' => 'Santos', 'mobile' => '+61 412 345 001', 'status' => 'Active', 'tag' => 'Student', 'primary_counsellor' => $currentUser, 'secondary_counsellor' => $otherCounsellor, 'migration_agent' => $otherAgent],
            ['client_id' => 1002, 'first_name' => 'Daniel', 'middle_name' => 'Min', 'surname' => 'Park', 'mobile' => '+61 412 345 002', 'status' => 'Prospect', 'tag' => 'Visa', 'primary_counsellor' => $otherCounsellor, 'secondary_counsellor' => $currentUser, 'migration_agent' => $otherAgent],
            ['client_id' => 1003, 'first_name' => 'Maria', 'middle_name' => 'Luisa', 'surname' => 'Cruz', 'mobile' => '+61 412 345 003', 'status' => 'Active', 'tag' => 'Student', 'primary_counsellor' => $otherCounsellor, 'secondary_counsellor' => 'Liam OConnor', 'migration_agent' => $currentUser],
            ['client_id' => 1004, 'first_name' => 'Ravi', 'middle_name' => 'Arun', 'surname' => 'Kumar', 'mobile' => '+61 412 345 004', 'status' => 'On hold', 'tag' => 'PR', 'primary_counsellor' => $currentUser, 'secondary_counsellor' => 'Marco Silva', 'migration_agent' => $otherAgent],
            ['client_id' => 1005, 'first_name' => 'Lucia', 'middle_name' => 'Elena', 'surname' => 'Herrera', 'mobile' => '+61 412 345 005', 'status' => 'Inactive', 'tag' => 'Visa', 'primary_counsellor' => 'Sofia Reyes', 'secondary_counsellor' => $currentUser, 'migration_agent' => 'Marco Silva'],
            ['client_id' => 1006, 'first_name' => 'Chen', 'middle_name' => 'Wei', 'surname' => 'Lin', 'mobile' => '+61 412 345 006', 'status' => 'Active', 'tag' => 'Student', 'primary_counsellor' => $currentUser, 'secondary_counsellor' => '', 'migration_agent' => $otherAgent],
            ['client_id' => 1007, 'first_name' => 'Isabella', 'middle_name' => 'Rosa', 'surname' => 'Rossi', 'mobile' => '+61 412 345 007', 'status' => 'Archived', 'tag' => 'PR', 'primary_counsellor' => 'Priya Raman', 'secondary_counsellor' => 'Liam OConnor', 'migration_agent' => $currentUser],
            ['client_id' => 1008, 'first_name' => 'Mateo', 'middle_name' => 'Jose', 'surname' => 'Garcia', 'mobile' => '+61 412 345 008', 'status' => 'Prospect', 'tag' => 'Student', 'primary_counsellor' => $currentUser, 'secondary_counsellor' => 'Sofia Reyes', 'migration_agent' => $otherAgent],
            ['client_id' => 1009, 'first_name' => 'Aisha', 'middle_name' => 'Noor', 'surname' => 'Khan', 'mobile' => '+61 412 345 009', 'status' => 'Active', 'tag' => 'Visa', 'primary_counsellor' => $otherCounsellor, 'secondary_counsellor' => $currentUser, 'migration_agent' => $otherAgent],
            ['client_id' => 1010, 'first_name' => 'Paulo', 'middle_name' => 'Andre', 'surname' => 'Mendes', 'mobile' => '+61 412 345 010', 'status' => 'On hold', 'tag' => 'Student', 'primary_counsellor' => $currentUser, 'secondary_counsellor' => '', 'migration_agent' => 'Marco Silva'],
            ['client_id' => 1011, 'first_name' => 'Mei', 'middle_name' => 'Ling', 'surname' => 'Zhang', 'mobile' => '+61 412 345 011', 'status' => 'Active', 'tag' => 'PR', 'primary_counsellor' => 'Sofia Reyes', 'secondary_counsellor' => 'Priya Raman', 'migration_agent' => $currentUser],
            ['client_id' => 1012, 'first_name' => 'Omar', 'middle_name' => 'Hassan', 'surname' => 'Ali', 'mobile' => '+61 412 345 012', 'status' => 'Inactive', 'tag' => 'Visa', 'primary_counsellor' => $currentUser, 'secondary_counsellor' => $otherCounsellor, 'migration_agent' => $otherAgent],
            ['client_id' => 1013, 'first_name' => 'Grace', 'middle_name' => 'Anne', 'surname' => 'Taylor', 'mobile' => '+61 412 345 013', 'status' => 'Active', 'tag' => 'Student', 'primary_counsellor' => 'Liam OConnor', 'secondary_counsellor' => $currentUser, 'migration_agent' => 'Marco Silva'],
            ['client_id' => 1014, 'first_name' => 'Hiro', 'middle_name' => 'Kenji', 'surname' => 'Tanaka', 'mobile' => '+61 412 345 014', 'status' => 'Prospect', 'tag' => 'Visa', 'primary_counsellor' => $currentUser, 'secondary_counsellor' => '', 'migration_agent' => $otherAgent],
            ['client_id' => 1015, 'first_name' => 'Fatima', 'middle_name' => 'Zahra', 'surname' => 'Rahman', 'mobile' => '+61 412 345 015', 'status' => 'Active', 'tag' => 'PR', 'primary_counsellor' => 'Liam OConnor', 'secondary_counsellor' => 'Sofia Reyes', 'migration_agent' => $currentUser],
            ['client_id' => 1016, 'first_name' => 'Noah', 'middle_name' => 'James', 'surname' => 'Wilson', 'mobile' => '+61 412 345 016', 'status' => 'Active', 'tag' => 'Student', 'primary_counsellor' => 'Priya Raman', 'secondary_counsellor' => 'Marco Silva', 'migration_agent' => $otherAgent],
            ['client_id' => 1017, 'first_name' => 'Sofia', 'middle_name' => 'Camila', 'surname' => 'Torres', 'mobile' => '+61 412 345 017', 'status' => 'Archived', 'tag' => 'Visa', 'primary_counsellor' => 'Nina Patel', 'secondary_counsellor' => 'Liam OConnor', 'migration_agent' => 'Marco Silva'],
        ];

        $clientsById = [];

        foreach ([...$seedClients, ...$this->storedClients()] as $client) {
            $clientsById[(string) $client['client_id']] = $this->clientWithPhoneParts($client);
        }

        return array_values($clientsById);
    }

    /**
     * @return array<string, mixed>
     */
    private function blankClientFor(User $user): array
    {
        return [
            'client_id' => $this->nextClientIdFor($user),
            'first_name' => '',
            'middle_name' => '',
            'surname' => '',
            'dob' => '',
            'age' => '',
            'mobile' => '',
            'phone_country_code' => '+61',
            'phone_number' => '',
            'email' => '',
            'nationality' => '',
            'current_location' => '',
            'australian_address' => [
                'street' => '',
                'suburb' => '',
                'state' => '',
                'postcode' => '',
            ],
            'overseas_address' => '',
            'admin_office' => $this->currentOfficeFor($user),
            'client_status' => 'Active',
            'current_visa' => '',
            'visa_expiry' => '',
            'passport_photo_path' => '',
            'passport_photo_url' => '',
            'notes' => [],
        ];
    }

    /**
     * @param array<string, mixed> $validated
     * @return array<string, mixed>
     */
    private function storedClientAttributesFrom(array $validated, User $user, int $clientId): array
    {
        $phoneNumber = $this->normalisePhoneNumber((string) $validated['phone_number']);
        $phoneCountryCode = (string) $validated['phone_country_code'];

        return [
            'client_id' => $clientId,
            'created_by_user_id' => $user->getKey(),
            'first_name' => (string) $validated['first_name'],
            'middle_name' => (string) ($validated['middle_name'] ?? ''),
            'surname' => (string) ($validated['surname'] ?? ''),
            'dob' => $validated['dob'] ?? null,
            'phone_country_code' => $phoneCountryCode,
            'phone_number' => $phoneNumber,
            'mobile' => $this->mobileFromPhoneParts($phoneCountryCode, $phoneNumber),
            'email' => (string) $validated['email'],
            'client_status' => (string) $validated['client_status'],
            'tag' => 'Manual',
            'nationality' => (string) $validated['nationality'],
            'current_location' => (string) $validated['current_location'],
            'street' => (string) ($validated['street'] ?? ''),
            'suburb' => (string) ($validated['suburb'] ?? ''),
            'state' => (string) ($validated['state'] ?? ''),
            'postcode' => (string) ($validated['postcode'] ?? ''),
            'overseas_address' => (string) ($validated['overseas_address'] ?? ''),
            'admin_office' => (string) $validated['admin_office'],
            'current_visa' => (string) ($validated['current_visa'] ?? ''),
            'visa_expiry' => $validated['visa_expiry'] ?? null,
            'passport_photo_path' => null,
            'notes' => $this->notesToStorage($validated, $user),
            'primary_counsellor' => $this->assigneeName($user),
            'secondary_counsellor' => '',
            'migration_agent' => '',
        ];
    }

    /**
     * @param array<string, mixed> $validated
     * @param array<string, mixed>|null $existing
     * @return array<string, mixed>
     */
    private function updatedClientAttributesFrom(array $validated, User $user, int $clientId, ?array $existing, ?string $passportPhotoPath): array
    {
        $phoneNumber = $this->normalisePhoneNumber((string) $validated['phone_number']);
        $phoneCountryCode = (string) $validated['phone_country_code'];

        return [
            'client_id' => $clientId,
            'created_by_user_id' => $existing['created_by_user_id'] ?? $user->getKey(),
            'first_name' => (string) $validated['first_name'],
            'middle_name' => (string) ($validated['middle_name'] ?? ''),
            'surname' => (string) ($validated['surname'] ?? ''),
            'dob' => $validated['dob'],
            'phone_country_code' => $phoneCountryCode,
            'phone_number' => $phoneNumber,
            'mobile' => $this->mobileFromPhoneParts($phoneCountryCode, $phoneNumber),
            'email' => (string) $validated['email'],
            'client_status' => (string) $validated['client_status'],
            'tag' => (string) ($existing['tag'] ?? 'Manual'),
            'nationality' => (string) $validated['nationality'],
            'current_location' => (string) $validated['current_location'],
            'street' => (string) ($validated['street'] ?? ''),
            'suburb' => (string) ($validated['suburb'] ?? ''),
            'state' => (string) ($validated['state'] ?? ''),
            'postcode' => (string) ($validated['postcode'] ?? ''),
            'overseas_address' => (string) ($validated['overseas_address'] ?? ''),
            'admin_office' => (string) ($validated['admin_office'] ?? ''),
            'current_visa' => (string) ($validated['current_visa'] ?? ''),
            'visa_expiry' => $validated['visa_expiry'] ?? null,
            'passport_photo_path' => $passportPhotoPath,
            'notes' => $this->canManageClientNotes($user, $existing ?? [])
                ? $this->notesToStorage($validated, $user)
                : $this->notesToStorage(['note_bodies' => $existing['notes'] ?? []], $user),
            'primary_counsellor' => (string) ($existing['primary_counsellor'] ?? $this->assigneeName($user)),
            'secondary_counsellor' => (string) ($existing['secondary_counsellor'] ?? ''),
            'migration_agent' => (string) ($existing['migration_agent'] ?? ''),
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function storedClients(): array
    {
        return Client::query()
            ->orderBy('client_id')
            ->get()
            ->map(fn (Client $client): array => $this->storedClientToArray($client))
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    private function storedClientToArray(Client $client): array
    {
        return [
            'source' => 'database',
            'client_id' => $client->client_id,
            'created_by_user_id' => $client->created_by_user_id,
            'first_name' => $client->first_name,
            'middle_name' => $client->middle_name ?? '',
            'surname' => $client->surname ?? '',
            'dob' => $client->dob?->format('Y-m-d') ?? '',
            'mobile' => $client->mobile,
            'phone_country_code' => $client->phone_country_code ?? '+61',
            'phone_number' => $client->phone_number ?? '',
            'email' => $client->email,
            'status' => $client->client_status,
            'client_status' => $client->client_status,
            'tag' => $client->tag,
            'nationality' => $client->nationality,
            'current_location' => $client->current_location,
            'australian_address' => [
                'street' => $client->street ?? '',
                'suburb' => $client->suburb ?? '',
                'state' => $client->state ?? '',
                'postcode' => $client->postcode ?? '',
            ],
            'overseas_address' => $client->overseas_address ?? '',
            'admin_office' => $client->admin_office,
            'current_visa' => $client->current_visa ?? '',
            'visa_expiry' => $client->visa_expiry?->format('Y-m-d') ?? '',
            'passport_photo_path' => $client->passport_photo_path ?? '',
            'notes' => $this->notesFrom($client->notes ?? ''),
            'primary_counsellor' => $client->primary_counsellor,
            'secondary_counsellor' => $client->secondary_counsellor ?? '',
            'migration_agent' => $client->migration_agent ?? '',
        ];
    }

    /**
     * @param array<string, mixed> $client
     * @return array<string, mixed>
     */
    private function clientWithPhoneParts(array $client): array
    {
        $countryCode = trim((string) ($client['phone_country_code'] ?? ''));
        $phoneNumber = $this->normalisePhoneNumber((string) ($client['phone_number'] ?? ''));

        if ($countryCode === '' || $phoneNumber === '') {
            [$countryCode, $phoneNumber] = $this->phonePartsFromMobile((string) ($client['mobile'] ?? ''));
        }

        $client['phone_country_code'] = $countryCode;
        $client['phone_number'] = $phoneNumber;
        $client['mobile'] = $phoneNumber !== ''
            ? $this->mobileFromPhoneParts($countryCode, $phoneNumber)
            : (string) ($client['mobile'] ?? '');

        return $client;
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function phonePartsFromMobile(string $mobile): array
    {
        $mobile = trim($mobile);

        if (preg_match('/^(\+\d{1,3})\s*(.+)$/', $mobile, $matches) === 1) {
            return [$matches[1], $this->normalisePhoneNumber($matches[2])];
        }

        return ['+61', $this->normalisePhoneNumber($mobile)];
    }

    private function mobileFromPhoneParts(string $countryCode, string $phoneNumber): string
    {
        return trim($countryCode.' '.$this->normalisePhoneNumber($phoneNumber));
    }

    private function normalisePhoneNumber(string $phoneNumber): string
    {
        return trim((string) preg_replace('/\s+/', ' ', $phoneNumber));
    }

    private function nextClientIdFor(User $user): int
    {
        return ((int) max(array_map(
            fn (array $client): int => (int) $client['client_id'],
            $this->clientsFor($user)
        ))) + 1;
    }

    private function currentOfficeFor(User $user): string
    {
        $officeNames = [
            'MEL' => 'Melbourne',
            'SYD' => 'Sydney',
            'BNE' => 'Brisbane',
            'PER' => 'Perth',
            'ADL' => 'Adelaide',
            'MANILA' => 'Manila',
            'PHIL-OFFSHORE' => 'Philippines Offshore',
            'LATAM' => 'LATAM',
        ];
        $officeCode = $user->assignedOfficeCodes()[0] ?? 'MEL';

        return $officeNames[$officeCode] ?? 'Melbourne';
    }

    /**
     * @return array<string, list<mixed>>
     */
    private function clientRecordRules(): array
    {
        return [
            'first_name' => ['required', 'string', 'max:80'],
            'middle_name' => ['nullable', 'string', 'max:80'],
            'surname' => ['nullable', 'string', 'max:80'],
            'dob' => ['required', 'date', 'before_or_equal:today'],
            'phone_country_code' => ['required', 'string', Rule::in(array_keys(WebsiteLeadReferences::phoneCountryCodes()))],
            'phone_number' => ['required', 'string', 'max:40', 'regex:/^[0-9 ()-]{6,24}$/'],
            'email' => ['required', 'email', 'max:120'],
            'nationality' => ['required', 'string', Rule::in($this->nationalities())],
            'current_location' => ['required', 'string', Rule::in($this->locations())],
            'street' => ['nullable', 'string', 'max:160'],
            'suburb' => ['nullable', 'string', 'max:120'],
            'state' => ['nullable', 'string', Rule::in($this->states())],
            'postcode' => ['nullable', 'regex:/^\d{4}$/'],
            'overseas_address' => ['nullable', 'string', 'max:1200'],
            'admin_office' => ['nullable', 'string', Rule::in($this->offices())],
            'client_status' => ['required', 'string', Rule::in($this->statuses())],
            'current_visa' => ['nullable', 'string', Rule::in($this->visas())],
            'visa_expiry' => ['nullable', 'date'],
            'passport_photo' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp,gif', 'max:5120'],
            'delete_passport_photo' => ['nullable', 'boolean'],
            'notes' => ['nullable', 'string', 'max:4000'],
            'note_bodies' => ['nullable', 'array', 'max:50'],
            'note_bodies.*' => ['nullable', 'string', 'max:1000'],
            'note_authors' => ['nullable', 'array', 'max:50'],
            'note_authors.*' => ['nullable', 'string', 'max:120'],
            'note_datetimes' => ['nullable', 'array', 'max:50'],
            'note_datetimes.*' => ['nullable', 'string', 'max:40'],
        ];
    }

    /**
     * @return array<string, string>
     */
    private function clientRecordAttributes(): array
    {
        return [
            'first_name' => 'first name',
            'dob' => 'DOB',
            'phone_country_code' => 'country code',
            'phone_number' => 'phone number',
            'email' => 'email address',
            'current_location' => 'current location',
            'client_status' => 'client status',
            'current_visa' => 'current visa',
            'visa_expiry' => 'visa expiry',
            'passport_photo' => 'passport photo',
        ];
    }

    private function passportPhotoPathFrom(Request $request, string $existingPhotoPath): ?string
    {
        $passportPhotoPath = $existingPhotoPath !== '' ? $existingPhotoPath : null;

        if ($request->boolean('delete_passport_photo')) {
            $passportPhotoPath = null;
        }

        if ($request->hasFile('passport_photo')) {
            $passportPhotoPath = $request->file('passport_photo')->store('client-passport-photos', 'public');
        }

        return $passportPhotoPath;
    }

    private function passportPhotoUrlFor(string $passportPhotoPath): string
    {
        return $passportPhotoPath !== '' ? Storage::disk('public')->url($passportPhotoPath) : '';
    }

    /**
     * @return array<string, mixed>|null
     */
    private function clientListRecordFor(string $clientId, User $user): ?array
    {
        return collect($this->clientsFor($user))
            ->first(fn (array $client): bool => (string) $client['client_id'] === $clientId);
    }

    /**
     * @return array<string, mixed>|null
     */
    private function editLockFor(string $clientId): ?array
    {
        $lock = Cache::get($this->editLockKey($clientId));

        return is_array($lock) ? $lock : null;
    }

    private function acquireEditLock(Request $request, string $clientId): bool
    {
        $key = $this->editLockKey($clientId);
        $lock = $this->newEditLock($request);

        if (Cache::add($key, $lock, now()->addMinutes(self::EDIT_LOCK_MINUTES))) {
            return true;
        }

        if ($this->ownsEditLock($request, $clientId)) {
            Cache::put($key, $lock, now()->addMinutes(self::EDIT_LOCK_MINUTES));

            return true;
        }

        return false;
    }

    private function refreshEditLock(Request $request, string $clientId): void
    {
        Cache::put($this->editLockKey($clientId), $this->newEditLock($request), now()->addMinutes(self::EDIT_LOCK_MINUTES));
    }

    private function releaseEditLock(Request $request, string $clientId): void
    {
        if ($this->ownsEditLock($request, $clientId)) {
            Cache::forget($this->editLockKey($clientId));
        }
    }

    private function ownsEditLock(Request $request, string $clientId): bool
    {
        $lock = $this->editLockFor($clientId);

        return $lock !== null
            && (int) ($lock['user_id'] ?? 0) === (int) $request->user()->getKey();
    }

    /**
     * @return array<string, mixed>
     */
    private function newEditLock(Request $request): array
    {
        return [
            'owner' => $request->session()->getId(),
            'user_id' => $request->user()->getKey(),
            'user_name' => $this->assigneeName($request->user()),
            'expires_at' => Carbon::now()->addMinutes(self::EDIT_LOCK_MINUTES)->toDateTimeString(),
        ];
    }

    private function editLockKey(string $clientId): string
    {
        return 'clients.edit_lock.'.$clientId;
    }

    /**
     * @param array<string, mixed> $input
     * @return array<string, mixed>|null
     */
    private function duplicateClientFor(array $input, User $user): ?array
    {
        $dob = trim((string) ($input['dob'] ?? ''));

        if ($dob === '') {
            return null;
        }

        $fullName = $this->normalisedFullName(
            (string) ($input['first_name'] ?? ''),
            (string) ($input['middle_name'] ?? ''),
            (string) ($input['surname'] ?? '')
        );

        if ($fullName === '') {
            return null;
        }

        foreach ($this->clientsFor($user) as $client) {
            $clientDob = (string) ($client['dob'] ?? '2000-04-12');

            if (
                $clientDob === $dob
                && $this->normalisedFullName(
                    (string) ($client['first_name'] ?? ''),
                    (string) ($client['middle_name'] ?? ''),
                    (string) ($client['surname'] ?? '')
                ) === $fullName
            ) {
                return [
                    'client_id' => $client['client_id'],
                    'name' => $this->displayName($client),
                    'dob' => $clientDob,
                ];
            }
        }

        return null;
    }

    private function normalisedFullName(string $firstName, string $middleName, string $surname): string
    {
        return strtolower(trim(preg_replace('/\s+/', ' ', trim($firstName.' '.$middleName.' '.$surname)) ?? ''));
    }

    /**
     * @param array<string, mixed> $client
     */
    private function displayName(array $client): string
    {
        return trim(preg_replace('/\s+/', ' ', trim(($client['first_name'] ?? '').' '.($client['middle_name'] ?? '').' '.($client['surname'] ?? ''))) ?? '');
    }

    /**
     * @return list<array{body: string, author: string, datetime: string}>
     */
    private function notesFrom(string $notes): array
    {
        if (trim($notes) === '') {
            return [];
        }

        $decoded = json_decode($notes, true);

        if (is_array($decoded)) {
            return array_values(array_filter(array_map(function ($note): ?array {
                if (! is_array($note)) {
                    return null;
                }

                $body = trim((string) ($note['body'] ?? ''));

                if ($body === '') {
                    return null;
                }

                return [
                    'body' => $body,
                    'author' => trim((string) ($note['author'] ?? 'Unknown user')) ?: 'Unknown user',
                    'datetime' => trim((string) ($note['datetime'] ?? 'Date not recorded')) ?: 'Date not recorded',
                    'datetime_display' => $this->displayNoteDatetime(trim((string) ($note['datetime'] ?? ''))),
                ];
            }, $decoded)));
        }

        return array_map(fn (string $note): array => [
            'body' => $note,
            'author' => 'Existing record',
            'datetime' => 'Date not recorded',
            'datetime_display' => 'Date not recorded',
        ], preg_split("/\R{2,}/", trim($notes)) ?: []);
    }

    private function displayNoteDatetime(string $datetime): string
    {
        if ($datetime === '' || $datetime === 'Date not recorded' || $datetime === 'Pending save') {
            return $datetime !== '' ? $datetime : 'Date not recorded';
        }

        try {
            return Carbon::parse($datetime)->locale('en')->isoFormat('MMMM D, YYYY [at] h:mm A');
        } catch (\Throwable) {
            return $datetime;
        }
    }

    /**
     * @param array<string, mixed> $input
     */
    private function notesToStorage(array $input, User $user): string
    {
        $noteBodies = $input['note_bodies'] ?? null;
        $noteAuthors = $input['note_authors'] ?? [];
        $noteDatetimes = $input['note_datetimes'] ?? [];

        if (is_array($noteBodies)) {
            $notes = [];

            foreach (array_values($noteBodies) as $index => $note) {
                $existingNote = is_array($note) ? $note : [];
                $body = trim(is_array($note) ? (string) ($note['body'] ?? '') : (string) $note);

                if ($body === '') {
                    continue;
                }

                $author = trim((string) ($noteAuthors[$index] ?? $existingNote['author'] ?? ''));
                $datetime = trim((string) ($noteDatetimes[$index] ?? $existingNote['datetime'] ?? ''));

                $notes[] = [
                    'body' => $body,
                    'author' => $author !== '' ? $author : $this->assigneeName($user),
                    'datetime' => $datetime !== '' ? $datetime : now()->format('Y-m-d H:i:s'),
                ];
            }

            return $notes === [] ? '' : json_encode($notes, JSON_THROW_ON_ERROR);
        }

        $legacyNotes = array_map(fn (string $note): array => [
            'body' => $note,
            'author' => $this->assigneeName($user),
            'datetime' => now()->format('Y-m-d H:i:s'),
        ], preg_split("/\R{2,}/", trim((string) ($input['notes'] ?? ''))) ?: []);

        $legacyNotes = array_values(array_filter($legacyNotes, fn (array $note): bool => trim($note['body']) !== ''));

        return $legacyNotes === [] ? '' : json_encode($legacyNotes, JSON_THROW_ON_ERROR);
    }

    /**
     * @param array<string, mixed> $client
     */
    private function canManageClientNotes(User $user, array $client): bool
    {
        if (in_array($user->account_type, User::MY_OFFICES_ACCOUNT_TYPES, true)) {
            return true;
        }

        return $this->isAssignedTo($client + [
            'primary_counsellor' => '',
            'secondary_counsellor' => '',
            'migration_agent' => '',
        ], $user);
    }

    private function ageFor(string $dob): string
    {
        if ($dob === '') {
            return '';
        }

        try {
            return (string) now()->diffInYears(\Carbon\Carbon::parse($dob));
        } catch (\Throwable) {
            return '';
        }
    }
}

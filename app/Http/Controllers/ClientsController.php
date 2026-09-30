<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ClientsController extends Controller
{
    private const PER_PAGE_OPTIONS = [10, 20, 50];

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

    public function create(): View
    {
        return view('crm.section', [
            'title' => 'PSC CRM - Add Client',
            'heading' => 'Add Client',
            'context' => 'Manual client record creation workspace.',
        ]);
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

        return view('crm.clients.show', [
            'title' => 'PSC CRM - '.$client['first_name'].' '.$client['surname'],
            'client' => $client,
            'nationalities' => ['Australian', 'Brazilian', 'Chinese', 'Indian', 'Indonesian', 'Nepalese', 'Philippine'],
            'states' => ['ACT', 'NSW', 'NT', 'QLD', 'SA', 'TAS', 'VIC', 'WA'],
            'offices' => ['Melbourne', 'Sydney', 'Brisbane', 'Perth'],
            'clientStatuses' => $this->statuses(),
        ]);
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
                'applications' => [
                    ['id' => 191, 'summary' => 'Advanced Diploma of Building [...]', 'counsellor' => 'Harris Gunawan', 'status' => 'In Progress', 'critical_date' => '10/10/2026', 'last_modified' => '10/10/2025'],
                    ['id' => 192, 'summary' => 'Visa Application - sc500 - Student', 'counsellor' => 'Harris Gunawan', 'status' => 'Not Started', 'critical_date' => '25/10/2026', 'last_modified' => '25/10/2025'],
                ],
                'notes' => ['Sample note'],
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
                'email' => '',
                'status' => 'Prospect',
                'tag' => 'Student',
                'event' => 'Consultation',
            ];
        }

        $tag = $client['tag'] ?? 'Student';

        return [
            'client_id' => $client['client_id'],
            'first_name' => $client['first_name'],
            'middle_name' => $client['middle_name'] ?? '',
            'surname' => $client['surname'],
            'dob' => '2000-04-12',
            'age' => 26,
            'mobile' => $client['mobile'] ?? '',
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
            'applications' => [
                ['id' => 191, 'summary' => 'Advanced Diploma of Building [...]', 'counsellor' => $client['primary_counsellor'] ?? $this->assigneeName($user), 'status' => 'In Progress', 'critical_date' => '10/10/2026', 'last_modified' => '10/10/2025'],
                ['id' => 192, 'summary' => 'Visa Application - sc500 - Student', 'counsellor' => $client['secondary_counsellor'] ?: $this->assigneeName($user), 'status' => 'Not Started', 'critical_date' => '25/10/2026', 'last_modified' => '25/10/2025'],
            ],
            'notes' => ['Sample note'],
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

        return [
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
    }
}

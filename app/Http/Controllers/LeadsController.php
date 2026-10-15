<?php

namespace App\Http\Controllers;

use App\Models\Lead;
use App\Models\PscEvent;
use App\Models\User;
use App\Support\PSC\WebsiteLeadReferences;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class LeadsController extends Controller
{
    private const PER_PAGE_OPTIONS = [10, 20, 50];

    /**
     * @var list<string>
     */
    private array $sortableColumns = [
        'client_full_name',
        'status',
        'priority',
        'assigned_name',
        'assigned_office',
        'updated_at',
        'days_in_queue',
    ];

    public function __construct(private readonly WebsiteLeadReferences $references)
    {
    }

    public function index(Request $request): View
    {
        $user = $request->user();
        $canViewAdminColumns = $this->canViewAdminColumns($user);

        return view('crm.leads.index', [
            'title' => 'PSC CRM - Leads',
            'leads' => $this->leadRowsForView($this->visibleLeadRows($user), $canViewAdminColumns),
            'statuses' => $this->statuses(),
            'priorities' => $this->priorities(),
            'offices' => $this->availableOfficeFilters($user),
            'selectedStatus' => $this->selectedStatus($request),
            'selectedPriority' => $this->selectedPriority($request),
            'selectedOffice' => $this->selectedOffice($request, $this->availableOfficeFilters($user)),
            'sort' => $this->sortColumn($request),
            'direction' => $this->sortDirection($request),
            'perPageOptions' => self::PER_PAGE_OPTIONS,
            'perPage' => $this->perPage($request),
            'page' => $this->page($request),
            'canViewAdminColumns' => $canViewAdminColumns,
        ]);
    }

    public function export(Request $request): StreamedResponse
    {
        $user = $request->user();
        $canViewAdminColumns = $this->canViewAdminColumns($user);
        $leads = $this->filteredLeadRows($request, $user);
        $filename = 'leads-'.now()->format('Ymd-His').'.csv';

        return response()->streamDownload(function () use ($leads, $canViewAdminColumns): void {
            $handle = fopen('php://output', 'w');
            $headers = ['Client', 'Status', 'Priority'];

            if ($canViewAdminColumns) {
                $headers[] = 'Assigned To';
                $headers[] = 'Admin Office';
            }

            fputcsv($handle, [...$headers, 'Date Last Modified', 'Days in Queue']);

            foreach ($leads as $lead) {
                $row = [
                    $lead['client_full_name'],
                    $lead['status'],
                    $lead['priority'],
                ];

                if ($canViewAdminColumns) {
                    $row[] = $lead['assigned_name'];
                    $row[] = $lead['assigned_office'];
                }

                fputcsv($handle, [...$row, $lead['modified_at'], $lead['days_in_queue_label']]);
            }

            fclose($handle);
        }, $filename, ['Content-Type' => 'text/csv']);
    }

    public function show(Request $request, string $leadId): View
    {
        return $this->recordView($request, $leadId);
    }

    public function edit(Request $request, string $leadId): RedirectResponse
    {
        return redirect()->route('leads.show', $leadId);
    }

    public function update(Request $request, string $leadId): RedirectResponse
    {
        $lead = $this->visibleLeadQuery($request->user())
            ->with('client')
            ->where('lead_id', $leadId)
            ->firstOrFail();
        $validated = $request->validate($this->leadRecordRules(), [], $this->leadRecordAttributes());
        $assignedUser = $this->userForName((string) $validated['assigned_name']);
        $officeCode = $assignedUser?->assignedOfficeCodes()[0] ?? $lead->assigned_office_code;
        $offices = WebsiteLeadReferences::offices();
        $source = (string) $validated['source'];
        $eventName = $source === 'Event' ? (string) ($validated['event_name'] ?? '') : '';

        $lead->fill([
            'assigned_user_id' => $assignedUser?->getKey() ?? $lead->assigned_user_id,
            'assigned_name' => (string) $validated['assigned_name'],
            'assigned_office_code' => $officeCode,
            'assigned_office' => $offices[$officeCode] ?? $lead->assigned_office,
            'prospective_service' => (string) $validated['prospective_service'],
            'status' => (string) $validated['status'],
            'priority' => (string) $validated['priority'],
            'critical_date' => $validated['critical_date'] ?? null,
            'source' => $source,
            'event_name' => $eventName,
            'highest_education_level' => (string) ($validated['highest_education_level'] ?? ''),
            'secondary_counsellor' => (string) ($validated['secondary_counsellor'] ?? ''),
            'migration_agent' => (string) ($validated['migration_agent'] ?? ''),
            'notes' => (string) ($validated['notes'] ?? ''),
        ]);
        $lead->save();

        return redirect()
            ->route('leads.show', $lead->lead_id)
            ->with('success', 'Lead changes were saved.');
    }

    private function recordView(Request $request, string $leadId): View
    {
        $lead = $this->visibleLeadQuery($request->user())
            ->with('client')
            ->where('lead_id', $leadId)
            ->firstOrFail();

        return view('crm.leads.show', [
            'title' => 'PSC CRM - Lead '.$lead->lead_id,
            'lead' => $this->leadRecord($lead),
            'statuses' => $this->statuses(),
            'priorities' => $this->priorities(),
            'prospectiveServices' => $this->prospectiveServices(),
            'sources' => $this->sources(),
            'educationLevels' => $this->references->educationLevelNames(),
            'counsellorOptions' => $this->counsellorOptions($lead),
            'eventOptions' => $this->eventOptions($lead),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function leadRecord(Lead $lead): array
    {
        $client = $lead->client;
        $clientFullName = $client
            ? $this->displayName($client->first_name, $client->middle_name ?? '', $client->surname ?? '')
            : $this->displayName($lead->first_name, $lead->middle_name ?? '', $lead->surname ?? '');
        $phoneCountryCode = $client?->phone_country_code ?? $lead->phone_country_code;
        $phoneNumber = $client?->phone_number ?? $lead->phone_number;
        $phoneDisplay = trim($phoneCountryCode.' '.$phoneNumber);
        $notes = (string) ($lead->notes ?? '');

        return [
            'lead_id' => $lead->lead_id,
            'client_id' => $client?->client_id ?? $lead->client_id,
            'client_full_name' => $clientFullName,
            'client_dob' => $client?->dob?->format('Y-m-d') ?? $lead->dob?->format('Y-m-d') ?? '',
            'client_current_location' => $client?->current_location ?? $lead->current_location,
            'client_nationality' => $client?->nationality ?? $lead->nationality,
            'phone_display' => $phoneDisplay,
            'phone_href' => preg_replace('/[^0-9+]/', '', $phoneDisplay) ?? '',
            'email' => $client?->email ?? $lead->email,
            'prospective_service' => $lead->prospective_service ?: 'Study',
            'status' => $this->displayStatus((string) $lead->status),
            'priority' => $this->displayPriority((string) ($lead->priority ?? 'medium')),
            'critical_date' => $lead->critical_date?->format('Y-m-d') ?? '',
            'source' => $lead->source,
            'event_name' => $lead->event_name ?? '',
            'highest_education_level' => $lead->highest_education_level ?? '',
            'assigned_name' => $lead->assigned_name,
            'secondary_counsellor' => $lead->secondary_counsellor ?? $client?->secondary_counsellor ?? '',
            'migration_agent' => $lead->migration_agent ?? $client?->migration_agent ?? '',
            'created_at' => $lead->created_at?->format('d M Y h:i A') ?? '',
            'updated_at' => $lead->updated_at?->format('d M Y h:i A') ?? '',
            'notes' => $notes !== '' ? $notes : (string) ($lead->enquiry ?? ''),
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function filteredLeadRows(Request $request, User $user): array
    {
        $availableOffices = $this->availableOfficeFilters($user);
        $rows = $this->visibleLeadRows($user);
        $clientName = trim((string) $request->query('client_name', ''));

        $rows = array_values(array_filter($rows, function (array $lead) use ($request, $availableOffices, $clientName): bool {
            if ($clientName !== '' && ! str_contains(strtolower((string) $lead['client_full_name']), strtolower($clientName))) {
                return false;
            }

            $status = $this->selectedStatus($request);
            $priority = $this->selectedPriority($request);
            $office = $this->selectedOffice($request, $availableOffices);

            return ($status === '' || $lead['status'] === $status)
                && ($priority === '' || $lead['priority'] === $priority)
                && ($office === '' || $lead['assigned_office_code'] === $office);
        }));

        $sort = $this->sortColumn($request);
        $direction = $this->sortDirection($request);

        usort($rows, function (array $left, array $right) use ($sort, $direction): int {
            $result = in_array($sort, ['days_in_queue'], true)
                ? ((int) $left[$sort] <=> (int) $right[$sort])
                : strnatcasecmp((string) $left[$sort], (string) $right[$sort]);

            return $direction === 'desc' ? -$result : $result;
        });

        return $rows;
    }

    /**
     * @param list<array<string, mixed>> $rows
     * @return list<array<string, mixed>>
     */
    private function leadRowsForView(array $rows, bool $canViewAdminColumns): array
    {
        if ($canViewAdminColumns) {
            return $rows;
        }

        return array_map(function (array $row): array {
            unset($row['assigned_name'], $row['assigned_office'], $row['assigned_office_code']);

            return $row;
        }, $rows);
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function visibleLeadRows(User $user): array
    {
        $leads = $this->visibleLeadQuery($user)
            ->with('client')
            ->orderByDesc('updated_at')
            ->get();

        $duplicateEmails = $leads
            ->map(fn (Lead $lead): string => strtolower(trim((string) $lead->email)))
            ->filter()
            ->countBy();
        $duplicateNames = $leads
            ->map(fn (Lead $lead): string => $this->duplicateNameKey($lead))
            ->filter()
            ->countBy();

        return $leads
            ->map(fn (Lead $lead): array => $this->leadRow($lead, $duplicateEmails, $duplicateNames))
            ->all();
    }

    /**
     * @param \Illuminate\Support\Collection<string, int> $duplicateEmails
     * @param \Illuminate\Support\Collection<string, int> $duplicateNames
     * @return array<string, mixed>
     */
    private function leadRow(Lead $lead, $duplicateEmails, $duplicateNames): array
    {
        $submittedAt = $lead->submitted_at ?? $lead->created_at ?? now();
        $updatedAt = $lead->updated_at ?? $submittedAt;
        $daysInQueue = max(0, (int) Carbon::parse($submittedAt)->startOfDay()->diffInDays(now()->startOfDay()));
        $status = $this->displayStatus((string) $lead->status);
        $priority = $this->displayPriority((string) ($lead->priority ?? 'medium'));
        $emailKey = strtolower(trim((string) $lead->email));
        $nameKey = $this->duplicateNameKey($lead);
        $clientFullName = $lead->client
            ? $this->displayName($lead->client->first_name, $lead->client->middle_name ?? '', $lead->client->surname ?? '')
            : $this->displayName($lead->first_name, $lead->middle_name ?? '', $lead->surname ?? '');

        return [
            'lead_id' => $lead->lead_id,
            'client_full_name' => $clientFullName,
            'client_url' => route('clients.show', $lead->client?->client_id ?? $lead->client_id),
            'status' => $status,
            'priority' => $priority,
            'assigned_name' => $lead->assigned_name,
            'assigned_office_code' => $lead->assigned_office_code,
            'assigned_office' => $lead->assigned_office,
            'modified_at' => Carbon::parse($updatedAt)->format('d M Y'),
            'updated_at' => Carbon::parse($updatedAt)->toDateTimeString(),
            'days_in_queue' => $daysInQueue,
            'days_in_queue_label' => $daysInQueue >= 10 ? '10+' : (string) max(1, $daysInQueue),
            'queue_age_level' => $status === 'To be contacted' ? $this->queueAgeLevel($daysInQueue) : 'none',
            'has_duplicate' => ($emailKey !== '' && (int) ($duplicateEmails[$emailKey] ?? 0) > 1)
                || ($nameKey !== '' && (int) ($duplicateNames[$nameKey] ?? 0) > 1),
        ];
    }

    private function visibleLeadQuery(User $user): Builder
    {
        $query = Lead::query();
        $officeCodes = $user->assignedOfficeCodes();

        if ($this->canViewAllLeadOffices($user)) {
            if ($officeCodes !== []) {
                $query->whereIn('assigned_office_code', $officeCodes);
            }

            return $query;
        }

        if (in_array($user->account_type, [User::ACCOUNT_TYPE_MANAGER, User::ACCOUNT_TYPE_REGIONAL_MANAGER], true)) {
            return $officeCodes === []
                ? $query->whereRaw('1 = 0')
                : $query->whereIn('assigned_office_code', $officeCodes);
        }

        return $query->where('assigned_user_id', $user->getKey());
    }

    /**
     * @return array<string, list<mixed>>
     */
    private function leadRecordRules(): array
    {
        return [
            'prospective_service' => ['required', 'string', 'max:80', Rule::in($this->prospectiveServices())],
            'status' => ['required', 'string', 'max:40', Rule::in($this->statuses())],
            'priority' => ['required', 'string', 'max:20', Rule::in($this->priorities())],
            'critical_date' => ['nullable', 'date'],
            'source' => ['required', 'string', 'max:80', Rule::in($this->sources())],
            'event_name' => ['nullable', 'string', 'max:160'],
            'highest_education_level' => ['nullable', 'string', Rule::in($this->references->educationLevelNames())],
            'assigned_name' => ['required', 'string', 'max:120'],
            'secondary_counsellor' => ['nullable', 'string', 'max:120'],
            'migration_agent' => ['nullable', 'string', 'max:120'],
            'notes' => ['nullable', 'string', 'max:4000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    private function leadRecordAttributes(): array
    {
        return [
            'prospective_service' => 'prospective service',
            'critical_date' => 'critical date',
            'event_name' => 'event',
            'highest_education_level' => 'highest education level',
            'assigned_name' => 'counsellor',
            'secondary_counsellor' => 'secondary counsellor',
            'migration_agent' => 'migration agent',
        ];
    }

    private function userForName(string $name): ?User
    {
        return User::query()
            ->where('name', $name)
            ->orWhere('email', $name)
            ->orderBy('id')
            ->first();
    }

    /**
     * @return list<string>
     */
    private function counsellorOptions(Lead $lead): array
    {
        $names = User::query()
            ->orderBy('name')
            ->get()
            ->map(fn (User $user): string => trim($user->name) !== '' ? $user->name : $user->email)
            ->all();

        return array_values(array_unique(array_filter([
            $lead->assigned_name,
            $lead->secondary_counsellor,
            $lead->migration_agent,
            $lead->client?->primary_counsellor,
            $lead->client?->secondary_counsellor,
            $lead->client?->migration_agent,
            ...$names,
        ], fn ($name): bool => is_string($name) && trim($name) !== '')));
    }

    /**
     * @return list<string>
     */
    private function eventOptions(Lead $lead): array
    {
        $events = PscEvent::query()
            ->where('active', true)
            ->orderBy('starts_at')
            ->pluck('name')
            ->all();

        return array_values(array_unique(array_filter([
            $lead->event_name,
            ...array_map('strval', $events),
        ], fn ($name): bool => is_string($name) && trim($name) !== '')));
    }

    private function canViewAllLeadOffices(User $user): bool
    {
        return in_array($user->account_type, [User::ACCOUNT_TYPE_ADMIN, 'Super Admin'], true);
    }

    private function canViewAdminColumns(User $user): bool
    {
        return in_array($user->account_type, [User::ACCOUNT_TYPE_ADMIN, 'Super Admin'], true);
    }

    /**
     * @return array<string, string>
     */
    private function availableOfficeFilters(User $user): array
    {
        $offices = WebsiteLeadReferences::offices();

        if ($this->canViewAllLeadOffices($user)) {
            $codes = $user->assignedOfficeCodes();

            return $codes === [] ? $offices : array_intersect_key($offices, array_flip($codes));
        }

        if (in_array($user->account_type, [User::ACCOUNT_TYPE_MANAGER, User::ACCOUNT_TYPE_REGIONAL_MANAGER], true)) {
            return array_intersect_key($offices, array_flip($user->assignedOfficeCodes()));
        }

        return [];
    }

    private function sortColumn(Request $request): string
    {
        $sort = (string) $request->query('sort', 'updated_at');

        return in_array($sort, $this->sortableColumns, true) ? $sort : 'updated_at';
    }

    private function sortDirection(Request $request): string
    {
        return $request->query('direction') === 'asc' ? 'asc' : 'desc';
    }

    private function perPage(Request $request): int
    {
        $perPage = (int) $request->query('per_page', 10);

        return in_array($perPage, self::PER_PAGE_OPTIONS, true) ? $perPage : 10;
    }

    private function page(Request $request): int
    {
        return max(1, (int) $request->query('page', 1));
    }

    private function selectedStatus(Request $request): string
    {
        $status = (string) $request->query('status', '');

        return in_array($status, $this->statuses(), true) ? $status : '';
    }

    private function selectedPriority(Request $request): string
    {
        $priority = strtolower((string) $request->query('priority', ''));

        return in_array($priority, $this->priorities(), true) ? $priority : '';
    }

    /**
     * @param array<string, string> $availableOffices
     */
    private function selectedOffice(Request $request, array $availableOffices): string
    {
        $office = strtoupper((string) $request->query('office', ''));

        return array_key_exists($office, $availableOffices) ? $office : '';
    }

    /**
     * @return list<string>
     */
    private function statuses(): array
    {
        return ['To be contacted', 'Contacted', 'Consulting', 'Converted', 'Withdrawn', 'No response'];
    }

    /**
     * @return list<string>
     */
    private function priorities(): array
    {
        return ['low', 'medium', 'high'];
    }

    /**
     * @return list<string>
     */
    private function prospectiveServices(): array
    {
        return ['Study', 'Visa', 'Migration', 'Consultation', 'Other'];
    }

    /**
     * @return list<string>
     */
    private function sources(): array
    {
        return ['Contact us', 'Book a free consultation', 'PSC-7 counsellor intake', 'Event', 'Manual'];
    }

    private function displayStatus(string $status): string
    {
        return $status === '' || $status === 'New' ? 'To be contacted' : $status;
    }

    private function displayPriority(string $priority): string
    {
        $priority = strtolower($priority);

        return in_array($priority, $this->priorities(), true) ? $priority : 'medium';
    }

    private function queueAgeLevel(int $days): string
    {
        if ($days >= 7) {
            return 'red';
        }

        if ($days >= 3) {
            return 'amber';
        }

        return 'none';
    }

    private function duplicateNameKey(Lead $lead): string
    {
        $name = strtolower(trim(preg_replace('/\s+/', ' ', trim($lead->first_name.' '.($lead->middle_name ?? '').' '.($lead->surname ?? ''))) ?? ''));
        $dob = $lead->dob?->format('Y-m-d') ?? '';

        return $name !== '' && $dob !== '' ? $name.'|'.$dob : '';
    }

    private function displayName(string $firstName, string $middleName, string $surname): string
    {
        return trim(preg_replace('/\s+/', ' ', trim($firstName.' '.$middleName.' '.$surname)) ?? '');
    }
}

<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class MyOfficesController extends Controller
{
    /**
     * @var array<string, string>
     */
    private array $offices = [
        'MEL' => 'Melbourne',
        'SYD' => 'Sydney',
        'BNE' => 'Brisbane',
        'PER' => 'Perth',
        'ADL' => 'Adelaide',
        'MANILA' => 'Manila',
        'PHIL-OFFSHORE' => 'Philippines Offshore',
        'LATAM' => 'LATAM',
    ];

    public function index(Request $request): RedirectResponse
    {
        $this->authorizeAccess($request->user());

        return redirect()->route('my-offices.clients');
    }

    public function clients(Request $request): View
    {
        return $this->section($request, 'clients');
    }

    public function applications(Request $request): View
    {
        return $this->section($request, 'applications');
    }

    public function leads(Request $request): View
    {
        return $this->section($request, 'leads');
    }

    public function tasks(Request $request): View
    {
        return $this->section($request, 'tasks');
    }

    public function rfi(Request $request): View
    {
        return $this->section($request, 'rfi');
    }

    private function section(Request $request, string $section): View
    {
        $user = $request->user();
        $this->authorizeAccess($user);

        $availableOffices = $this->availableOfficesFor($user);
        $selectedOfficeCodes = $this->selectedOfficeCodes($request, array_keys($availableOffices));
        $records = array_values(array_filter(
            $this->records()[$section],
            fn (array $record) => in_array($record['office'], $selectedOfficeCodes, true)
        ));

        return view('crm.my-offices-section', [
            'title' => 'PSC CRM - My Offices - '.$this->sectionConfig()[$section]['heading'],
            'heading' => $this->sectionConfig()[$section]['heading'],
            'section' => $section,
            'config' => $this->sectionConfig()[$section],
            'availableOffices' => $availableOffices,
            'selectedOfficeCodes' => $selectedOfficeCodes,
            'selectedOfficeLabels' => $this->officeLabels($selectedOfficeCodes),
            'records' => $records,
            'totalAssignedOffices' => count($availableOffices),
            'userAccountType' => $user->account_type ?? 'Unknown',
        ]);
    }

    private function authorizeAccess(?User $user): void
    {
        abort_unless($user?->canAccessMyOffices(), 403);
    }

    /**
     * @return array<string, string>
     */
    private function availableOfficesFor(User $user): array
    {
        $assigned = array_intersect_key($this->offices, array_flip($user->assignedOfficeCodes()));

        if ($assigned !== []) {
            return $assigned;
        }

        if ($user->account_type === User::ACCOUNT_TYPE_ADMIN) {
            return $this->offices;
        }

        return [];
    }

    /**
     * @return list<string>
     */
    private function selectedOfficeCodes(Request $request, array $availableCodes): array
    {
        $requested = $request->input('offices', []);

        if (is_string($requested)) {
            $requested = [$requested];
        }

        if (! is_array($requested) || $requested === []) {
            return $availableCodes;
        }

        $requested = array_map(fn ($office) => is_string($office) ? strtoupper($office) : '', $requested);
        $selected = array_values(array_intersect($availableCodes, $requested));

        return $selected === [] ? $availableCodes : $selected;
    }

    /**
     * @return list<string>
     */
    private function officeLabels(array $codes): array
    {
        return array_values(array_map(
            fn (string $code) => $this->offices[$code] ?? $code,
            $codes
        ));
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private function sectionConfig(): array
    {
        return [
            'clients' => [
                'heading' => 'Clients',
                'route' => 'my-offices.clients',
                'summaryLabel' => 'Office clients',
                'columns' => ['ref' => 'Client ID', 'name' => 'Client', 'description' => 'Organisation', 'status' => 'Status', 'owner' => 'Owner', 'updated' => 'Updated'],
            ],
            'applications' => [
                'heading' => 'Applications',
                'route' => 'my-offices.applications',
                'summaryLabel' => 'Office applications',
                'columns' => ['ref' => 'Application ID', 'name' => 'Applicant', 'description' => 'Program', 'status' => 'Stage', 'owner' => 'Counsellor', 'updated' => 'Critical date'],
            ],
            'leads' => [
                'heading' => 'Leads',
                'route' => 'my-offices.leads',
                'summaryLabel' => 'Office leads',
                'columns' => ['ref' => 'Lead ID', 'name' => 'Lead', 'description' => 'Source', 'status' => 'Stage', 'owner' => 'Owner', 'updated' => 'Last contact'],
            ],
            'tasks' => [
                'heading' => 'Tasks',
                'route' => 'my-offices.tasks',
                'summaryLabel' => 'Office tasks',
                'columns' => ['ref' => 'Task ID', 'name' => 'Task', 'description' => 'Related record', 'status' => 'Priority', 'owner' => 'Assignee', 'updated' => 'Due'],
            ],
            'rfi' => [
                'heading' => 'RFIs',
                'route' => 'my-offices.rfi',
                'summaryLabel' => 'Office RFIs',
                'columns' => ['ref' => 'RFI ID', 'name' => 'Request', 'description' => 'Related application', 'status' => 'Status', 'owner' => 'Owner', 'updated' => 'Due'],
            ],
        ];
    }

    /**
     * @return array<string, list<array<string, string>>>
     */
    private function records(): array
    {
        return [
            'clients' => [
                ['ref' => 'CL-1044', 'name' => 'Ana Santos', 'description' => 'Northbridge College', 'status' => 'Active', 'owner' => 'Jessica Morgan', 'updated' => '24 Sep 2026', 'office' => 'MEL'],
                ['ref' => 'CL-1049', 'name' => 'Daniel Park', 'description' => 'Southern Cross University', 'status' => 'Document review', 'owner' => 'Priya Raman', 'updated' => '22 Sep 2026', 'office' => 'SYD'],
                ['ref' => 'CL-1052', 'name' => 'Maria Cruz', 'description' => 'TAFE Queensland', 'status' => 'Active', 'owner' => 'Liam OConnor', 'updated' => '20 Sep 2026', 'office' => 'BNE'],
                ['ref' => 'CL-1057', 'name' => 'Ravi Kumar', 'description' => 'Edith Cowan University', 'status' => 'Awaiting payment', 'owner' => 'Nina Patel', 'updated' => '18 Sep 2026', 'office' => 'PER'],
                ['ref' => 'CL-1063', 'name' => 'Lucia Herrera', 'description' => 'Adelaide Institute', 'status' => 'Active', 'owner' => 'Marco Silva', 'updated' => '17 Sep 2026', 'office' => 'ADL'],
            ],
            'applications' => [
                ['ref' => 'APP-10482', 'name' => 'Ana Santos', 'description' => 'Student Visa 500', 'status' => 'Preparing', 'owner' => 'Jessica Morgan', 'updated' => '09 Oct 2026', 'office' => 'MEL'],
                ['ref' => 'APP-10511', 'name' => 'Daniel Park', 'description' => 'Graduate Visa 485', 'status' => 'Documents', 'owner' => 'Priya Raman', 'updated' => '24 Oct 2026', 'office' => 'SYD'],
                ['ref' => 'APP-10398', 'name' => 'Maria Cruz', 'description' => 'Diploma of Nursing', 'status' => 'Review', 'owner' => 'Liam OConnor', 'updated' => '14 Oct 2026', 'office' => 'BNE'],
                ['ref' => 'APP-10544', 'name' => 'Ravi Kumar', 'description' => 'Master of IT', 'status' => 'Submitted', 'owner' => 'Nina Patel', 'updated' => '03 Nov 2026', 'office' => 'PER'],
                ['ref' => 'APP-10566', 'name' => 'Lucia Herrera', 'description' => 'Business Package', 'status' => 'Offer pending', 'owner' => 'Marco Silva', 'updated' => '12 Nov 2026', 'office' => 'ADL'],
            ],
            'leads' => [
                ['ref' => 'LEAD-2201', 'name' => 'Chen Wei', 'description' => 'Web enquiry', 'status' => 'New', 'owner' => 'Jessica Morgan', 'updated' => 'Today 10:18', 'office' => 'MEL'],
                ['ref' => 'LEAD-2206', 'name' => 'Isabella Rossi', 'description' => 'Education expo', 'status' => 'Contacted', 'owner' => 'Priya Raman', 'updated' => 'Yesterday 16:05', 'office' => 'SYD'],
                ['ref' => 'LEAD-2212', 'name' => 'Mateo Garcia', 'description' => 'Referral', 'status' => 'Qualified', 'owner' => 'Liam OConnor', 'updated' => '21 Sep 2026', 'office' => 'BNE'],
                ['ref' => 'LEAD-2219', 'name' => 'Aisha Khan', 'description' => 'Facebook campaign', 'status' => 'Nurture', 'owner' => 'Nina Patel', 'updated' => '20 Sep 2026', 'office' => 'PER'],
                ['ref' => 'LEAD-2224', 'name' => 'Paulo Mendes', 'description' => 'LATAM webinar', 'status' => 'Contacted', 'owner' => 'Sofia Reyes', 'updated' => '18 Sep 2026', 'office' => 'LATAM'],
            ],
            'tasks' => [
                ['ref' => 'TSK-7104', 'name' => 'Review supporting documents', 'description' => 'APP-10482 - Ana Santos', 'status' => 'High', 'owner' => 'Jessica Morgan', 'updated' => 'Tomorrow', 'office' => 'MEL'],
                ['ref' => 'TSK-7111', 'name' => 'Follow up payment evidence', 'description' => 'APP-10511 - Daniel Park', 'status' => 'Medium', 'owner' => 'Priya Raman', 'updated' => '30 Sep 2026', 'office' => 'SYD'],
                ['ref' => 'TSK-7118', 'name' => 'Prepare GTE notes', 'description' => 'APP-10398 - Maria Cruz', 'status' => 'High', 'owner' => 'Liam OConnor', 'updated' => '02 Oct 2026', 'office' => 'BNE'],
                ['ref' => 'TSK-7122', 'name' => 'Update visa checklist', 'description' => 'APP-10544 - Ravi Kumar', 'status' => 'Low', 'owner' => 'Nina Patel', 'updated' => '06 Oct 2026', 'office' => 'PER'],
                ['ref' => 'TSK-7130', 'name' => 'Book counsellor consult', 'description' => 'LEAD-2224 - Paulo Mendes', 'status' => 'Medium', 'owner' => 'Sofia Reyes', 'updated' => '07 Oct 2026', 'office' => 'LATAM'],
            ],
            'rfi' => [
                ['ref' => 'RFI-3401', 'name' => 'Updated passport bio page', 'description' => 'APP-10482 - Ana Santos', 'status' => 'Open', 'owner' => 'Jessica Morgan', 'updated' => '01 Oct 2026', 'office' => 'MEL'],
                ['ref' => 'RFI-3407', 'name' => 'Bank statement clarification', 'description' => 'APP-10511 - Daniel Park', 'status' => 'Waiting client', 'owner' => 'Priya Raman', 'updated' => '04 Oct 2026', 'office' => 'SYD'],
                ['ref' => 'RFI-3415', 'name' => 'English test result', 'description' => 'APP-10398 - Maria Cruz', 'status' => 'Open', 'owner' => 'Liam OConnor', 'updated' => '05 Oct 2026', 'office' => 'BNE'],
                ['ref' => 'RFI-3421', 'name' => 'Course gap explanation', 'description' => 'APP-10544 - Ravi Kumar', 'status' => 'Resolved', 'owner' => 'Nina Patel', 'updated' => '22 Sep 2026', 'office' => 'PER'],
                ['ref' => 'RFI-3429', 'name' => 'Sponsor declaration', 'description' => 'APP-10566 - Lucia Herrera', 'status' => 'Waiting client', 'owner' => 'Marco Silva', 'updated' => '08 Oct 2026', 'office' => 'ADL'],
            ],
        ];
    }
}

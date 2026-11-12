<?php

namespace App\Http\Controllers;

use App\Models\Application;
use App\Models\ApplicationActivity;
use App\Models\ApplicationTypeSetting;
use App\Support\PSC\ApplicationLinks;
use App\Support\PSC\ApplicationList;
use App\Support\PSC\ApplicationSummary;
use App\Support\PSC\CriticalDateFormatting;
use App\Support\PSC\NominationRecord;
use App\Support\PSC\SbsRecord;
use App\Support\PSC\VisaGrants;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ApplicationsController extends Controller
{
    public function __construct(private readonly ApplicationList $list) {}

    public function index(Request $request): View
    {
        $filters = $this->filters($request);
        $applications = $this->list->filtered($request->user(), $filters)
            ->paginate($filters['per_page'] ?? 10)->withQueryString();

        return view('crm.applications.index', [
            'title' => 'PSC CRM - Applications',
            'applications' => $applications,
            'columns' => ApplicationList::COLUMNS,
            'filters' => $filters,
            'dateFormatting' => app(CriticalDateFormatting::class),
        ]);
    }

    public function export(Request $request): StreamedResponse
    {
        $query = $this->list->filtered($request->user(), $this->filters($request));

        return response()->streamDownload(function () use ($query): void {
            $handle = fopen('php://output', 'w');
            fwrite($handle, "\xEF\xBB\xBF");
            fputcsv($handle, array_values(ApplicationList::COLUMNS), ',', '"', '');
            foreach ($query->cursor() as $application) {
                $row = [];
                foreach (array_keys(ApplicationList::COLUMNS) as $column) {
                    $value = (string) ($application->$column ?? '');
                    // Prevent spreadsheet formula execution in user-entered values.
                    $row[] = preg_match('/^[\s\x{FEFF}]*[=+@-]|^[\t\r\n]/u', $value) ? "'".$value : $value;
                }
                fputcsv($handle, $row, ',', '"', '');
            }
            fclose($handle);
        }, 'applications-'.now()->format('Ymd-His').'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    public function show(Request $request, int $application, ApplicationSummary $summary, ApplicationLinks $links): View
    {
        $record = Application::query()->assignedTo($request->user())
            ->with(['client', 'organisation', 'workflow', 'primaryCounsellor', 'secondaryCounsellor', 'migrationAgent', 'nomineeClient'])
            ->findOrFail($application);

        $tabs = ApplicationTypeSetting::tabsFor($record->category, $record->sponsor_type);
        $requestedTab = $request->query('tab');
        $activeTab = is_string($requestedTab) && array_key_exists($requestedTab, $tabs)
            ? $requestedTab : array_key_first($tabs);

        $nomination = $record->category === 'Nomination' && $record->organisation_id;
        $nominationData = $nomination ? app(NominationRecord::class)->viewData($record, $request->user()) : [];
        $sbs = SbsRecord::supports($record);

        return view($nomination ? 'crm.applications.nomination' : ($sbs ? 'crm.applications.sbs' : 'crm.applications.show'), [
            'title' => 'PSC CRM - Application '.$application,
            'application' => $record,
            'applicationTabs' => $tabs,
            'activeApplicationTab' => $activeTab,
            'nominations' => $sbs && $activeTab === 'nominations' ? app(SbsRecord::class)->nominations($record, $request->user()) : null,
            'linkedItems' => $links->rows($record, $request->user())->get(),
            'activities' => $record->organisation_id && in_array($record->category, ['SBS', 'Nomination'], true)
                && isset(ApplicationActivity::TABS[$activeTab ?? ''])
                    ? $record->activities()->where('kind', $activeTab)->latest('id')->paginate(15)->withQueryString() : null,
        ] + $summary->viewData($record) + $nominationData);
    }

    public function update(Request $request, int $application, ApplicationSummary $summary, VisaGrants $grants): RedirectResponse
    {
        $visible = Application::query()->assignedTo($request->user())->findOrFail($application);
        $isNomination = $visible->category === 'Nomination' && $visible->organisation_id;
        $isSbs = SbsRecord::supports($visible);
        $record = $isNomination
            ? app(NominationRecord::class)->update($request->user(), $application, $request->all())
            : ($isSbs ? app(SbsRecord::class)->update($request->user(), $application, $request->all())
                : $grants->updateApplication($request->user(), $application, $request->all(), $summary));

        // Reassigning the final role away also removes access to this record.
        $route = Application::query()->assignedTo($request->user())->whereKey($record->id)->exists()
            ? redirect()->route('applications.show', $record->id)
            : redirect()->route('applications');

        return $route->with('success', $isNomination ? 'Nomination application saved.' : ($isSbs ? ($record->is_labour_agreement ? 'Labour Agreement application saved.' : 'SBS application saved.') : 'Application summary saved.'));
    }

    private function filters(Request $request): array
    {
        $rules = [];
        foreach (array_diff(array_keys(ApplicationList::COLUMNS), ['critical_date', 'priority']) as $column) {
            $rules[$column] = ['nullable', 'string', 'max:255'];
        }

        return $request->validate($rules + [
            'priority' => ['nullable', 'array', 'max:3'],
            'priority.*' => ['required', Rule::in(['H', 'M', 'L']), 'distinct'],
            'date_from' => ['nullable', 'date_format:Y-m-d'],
            'date_to' => ['nullable', 'date_format:Y-m-d', ...($request->filled('date_from') ? ['after_or_equal:date_from'] : [])],
            'sort' => ['sometimes', Rule::in(array_keys(ApplicationList::COLUMNS))],
            'direction' => ['sometimes', Rule::in(['asc', 'desc'])],
            'per_page' => ['sometimes', 'integer', Rule::in([10, 20, 50])],
            'page' => ['sometimes', 'integer', 'min:1'],
        ]);
    }
}

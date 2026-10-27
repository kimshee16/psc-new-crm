<?php

namespace App\Http\Controllers;

use App\Models\Application;
use App\Models\Organisation;
use App\Support\PSC\ApplicationList;
use App\Support\PSC\ApplicationSummary;
use App\Support\PSC\CriticalDateFormatting;
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

    public function show(Request $request, int $application, ApplicationSummary $summary): View
    {
        $record = Application::query()->assignedTo($request->user())
            ->with(['client', 'organisation', 'workflow', 'primaryCounsellor', 'secondaryCounsellor', 'migrationAgent'])
            ->findOrFail($application);

        return view('crm.applications.show', [
            'title' => 'PSC CRM - Application '.$application,
            'application' => $record,
            'linkedApplication' => $record->linkedApplication()->assignedTo($request->user())->first(),
        ] + $summary->viewData($record));
    }

    public function update(Request $request, int $application, ApplicationSummary $summary): RedirectResponse
    {
        $record = Application::query()->assignedTo($request->user())->findOrFail($application);
        $record->update($summary->validateUpdate($record, $request->all()));

        // Reassigning the final role away also removes access to this record.
        $route = Application::query()->assignedTo($request->user())->whereKey($record->id)->exists()
            ? redirect()->route('applications.show', $record->id)
            : redirect()->route('applications');

        return $route->with('success', 'Application summary saved.');
    }

    public function organisation(Request $request, int $organisation): View
    {
        $record = Organisation::query()->whereHas('applications', fn ($query) => $query->assignedTo($request->user()))
            ->findOrFail($organisation);

        return view('crm.organisations.show', [
            'title' => 'PSC CRM - '.$record->name,
            'organisation' => $record,
            'applications' => $record->applications()->assignedTo($request->user())->latest()->get(),
        ]);
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

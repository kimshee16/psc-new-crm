<?php

namespace App\Http\Controllers;

use App\Models\Application;
use App\Models\ApplicationActivity;
use App\Models\ApplicationTypeSetting;
use App\Models\Client;
use App\Models\Organisation;
use App\Models\User;
use App\Support\PSC\NominationCreation;
use App\Support\PSC\OrganisationList;
use App\Support\PSC\RecordNotes;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class OrganisationsController extends Controller
{
    private const SEARCH_FIELDS = ['id', 'organisation_name', 'contact_name', 'contact_mobile', 'contact_email'];

    public function search(Request $request, OrganisationList $list): View
    {
        $rules = array_fill_keys(self::SEARCH_FIELDS, ['nullable', 'string', 'max:255']);
        $filters = $request->validate($rules + ['page' => ['sometimes', 'integer', 'min:1']]);
        $hasSearch = collect($filters)->only(self::SEARCH_FIELDS)->contains(fn ($value) => filled($value));

        return view('crm.organisations.search', [
            'title' => 'PSC CRM - Organisation Search', 'filters' => $filters, 'hasSearch' => $hasSearch,
            'organisations' => $hasSearch ? $list->filtered($request->user(), $filters, global: true)
                ->with('contacts')->paginate(20)->withQueryString() : null,
        ]);
    }

    public function searchShow(Request $request, Organisation $organisation): View
    {
        return view('crm.organisations.search-record', [
            'title' => 'PSC CRM - '.$organisation->name, 'organisation' => $organisation->load('contacts'),
            'canOpenRecord' => Organisation::query()->visibleTo($request->user())->whereKey($organisation->id)->exists(),
        ]);
    }

    public function index(Request $request, OrganisationList $list): View
    {
        $filters = $this->listFilters($request);

        return view('crm.organisations.index', [
            'title' => 'PSC CRM - Organisations', 'filters' => $filters, 'columns' => OrganisationList::COLUMNS,
            'organisations' => $list->filtered($request->user(), $filters)
                ->paginate($filters['per_page'] ?? 10)->withQueryString(),
        ]);
    }

    public function export(Request $request, OrganisationList $list): StreamedResponse
    {
        $query = $list->filtered($request->user(), $this->listFilters($request));

        return response()->streamDownload(function () use ($query): void {
            $handle = fopen('php://output', 'w');
            fwrite($handle, "\xEF\xBB\xBF");
            fputcsv($handle, array_values(OrganisationList::COLUMNS), ',', '"', '');
            foreach ($query->cursor() as $organisation) {
                $values = [$organisation->reference, $organisation->trading_name ?? '', $organisation->name,
                    $organisation->sbs_held ? 'Y' : 'N', $organisation->renew ? 'Flag' : 'No flag'];
                $values = array_map(function ($value) {
                    $value = (string) $value;

                    return preg_match('/^[\s\x{FEFF}]*[=+@-]|^[\t\r\n]/u', $value) ? "'".$value : $value;
                }, $values);
                fputcsv($handle, $values, ',', '"', '');
            }
            fclose($handle);
        }, 'organisations-'.now()->format('Ymd-His').'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    private function listFilters(Request $request): array
    {
        return $request->validate([
            'search' => ['nullable', 'string', 'max:255'],
            'id' => ['nullable', 'string', 'max:255'],
            'trading_name' => ['nullable', 'string', 'max:255'],
            'legal_name' => ['nullable', 'string', 'max:255'],
            'sbs_held' => ['nullable', Rule::in(['Y', 'N'])],
            'renew' => ['nullable', Rule::in(['flag', 'no_flag'])],
            'sort' => ['sometimes', Rule::in(array_keys(OrganisationList::COLUMNS))],
            'direction' => ['sometimes', Rule::in(['asc', 'desc'])],
            'per_page' => ['sometimes', 'integer', Rule::in([10, 20, 50])],
            'page' => ['sometimes', 'integer', 'min:1'],
        ]);
    }

    public function create(): View
    {
        return view('crm.organisations.form', ['title' => 'PSC CRM - New Organisation', 'organisation' => new Organisation]);
    }

    public function store(Request $request): RedirectResponse
    {
        $organisation = Organisation::create($this->details($request) + ['created_by_user_id' => $request->user()->id]);

        return redirect()->route('organisations.show', $organisation)->with('success', 'Organisation created.');
    }

    public function show(Request $request, int $organisation): View
    {
        $record = Organisation::query()->visibleTo($request->user())->with('contacts')->findOrFail($organisation);
        $applications = $record->applications()->assignedTo($request->user());
        $sponsors = (clone $applications)->where('category', 'SBS')->with(['primaryCounsellor', 'migrationAgent'])
            ->latest('id')->paginate(10, ['*'], 'sbs_page')->withQueryString();
        $nominations = (clone $applications)->where('category', 'Nomination')->with('nomineeClient')
            ->latest('id')->paginate(10, ['*'], 'nomination_page')->withQueryString();
        // Reuse PSC-33 links, including legacy links and links made from the visa record.
        foreach ($nominations as $nomination) {
            $nomination->setRelation('linkedVisas', Application::query()->assignedTo($request->user())
                ->linkedTo($nomination)->where('category', 'Visa')->orderBy('id')->get());
        }
        $requested = $request->query('tab');
        $activeTab = is_string($requested) && isset(ApplicationActivity::ORGANISATION_TABS[$requested]) ? $requested : 'tasks';
        $activityQuery = ApplicationActivity::query()->whereHas('application', fn ($query) => $query
            ->where('organisation_id', $record->id)->whereIn('category', ['SBS', 'Nomination'])->assignedTo($request->user()));
        $sourceApplications = (clone $applications)->whereIn('category', ['SBS', 'Nomination'])->orderBy('id')->get();
        $notes = $sourceApplications->flatMap(function (Application $application) {
            return collect(app(RecordNotes::class)->all($application->notes))->map(fn ($note) => [...$note, 'application' => $application]);
        })->sortByDesc('datetime')->values();
        $counts = (clone $activityQuery)->selectRaw('kind, count(*) as total')->groupBy('kind')->pluck('total', 'kind');
        $counts['notes'] = $notes->count();
        $taskCategories = collect(['SBS', 'Nomination'])->filter(fn ($category) => isset(ApplicationTypeSetting::tabsFor($category)['tasks']));

        return view('crm.organisations.show', [
            'title' => 'PSC CRM - '.$record->name, 'organisation' => $record,
            'sponsors' => $sponsors, 'nominations' => $nominations,
            'nominationSubclasses' => app(NominationCreation::class)->subclasses(),
            'selectedNominee' => is_scalar(old('nominee_client_reference')) && old('_create_nomination') === '1'
                ? Client::query()->where('client_id', old('nominee_client_reference'))->first() : null,
            'tabs' => ApplicationActivity::ORGANISATION_TABS, 'activeTab' => $activeTab,
            'counts' => $counts, 'notes' => $notes,
            'taskApplications' => $sourceApplications->whereIn('category', $taskCategories->all()),
            'staff' => $activeTab === 'tasks' ? User::query()->where('is_active', true)
                ->where('portal_invite_status', '!=', 'Restrict Access')->orderBy('name')->get(['id', 'name']) : collect(),
            'activities' => $activityQuery->where('kind', $activeTab)->with(['application', 'assignee'])->latest('id')
                ->paginate(15, ['*'], 'activity_page')->withQueryString(),
        ]);
    }

    public function edit(Request $request, int $organisation): View
    {
        return view('crm.organisations.form', [
            'title' => 'PSC CRM - Edit Organisation',
            'organisation' => Organisation::query()->visibleTo($request->user())->findOrFail($organisation),
        ]);
    }

    public function update(Request $request, int $organisation): RedirectResponse
    {
        $record = Organisation::query()->visibleTo($request->user())->findOrFail($organisation);
        $record->update($this->details($request));

        return redirect()->route('organisations.show', $record)->with('success', 'Organisation details saved.');
    }

    public function saveContact(Request $request, int $organisation, ?int $contact = null): RedirectResponse
    {
        $record = Organisation::query()->visibleTo($request->user())->findOrFail($organisation);
        $person = $contact === null ? $record->contacts()->make() : $record->contacts()->findOrFail($contact);
        $person->fill($request->validate([
            'name' => ['required', 'string', 'max:255'], 'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:40'], 'role' => ['nullable', 'string', 'max:255'],
        ]))->save();

        return redirect()->route('organisations.show', $record)->with('success', 'Contact person saved.');
    }

    private function details(Request $request): array
    {
        if (is_string($request->input('abn'))) {
            $request->merge(['abn' => preg_replace('/\s+/', '', $request->input('abn'))]);
        }
        $validated = $request->validate([
            'legal_name' => ['required', 'string', 'max:255'], 'trading_name' => ['nullable', 'string', 'max:255'],
            'abn' => ['nullable', 'regex:/^[0-9]{11}$/'], 'address' => ['nullable', 'string', 'max:2000'],
        ]);
        $validated['name'] = $validated['legal_name'];
        unset($validated['legal_name']);

        return $validated;
    }
}

<?php

namespace App\Http\Controllers;

use App\Models\Application;
use App\Models\ApplicationWorkflow;
use App\Models\Client;
use App\Models\Organisation;
use App\Support\PSC\ApplicationLinks;
use App\Support\PSC\ApplicationSummary;
use App\Support\PSC\NominationCreation;
use App\Support\PSC\NominationRecord;
use App\Support\PSC\SbsRecord;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class OrganisationApplicationsController extends Controller
{
    public function create(Request $request, int $organisation): View|RedirectResponse
    {
        $record = Organisation::query()->visibleTo($request->user())->findOrFail($organisation);
        $validated = $request->validate(['category' => ['required', Rule::in(['SBS', 'Nomination'])]]);

        if ($validated['category'] === 'Nomination') {
            return redirect()->route('organisations.show', ['organisation' => $record, 'create_nomination' => 1]);
        }

        return redirect()->route('organisations.show', ['organisation' => $record, 'add_sponsorship' => 1]);
    }

    public function nominees(Request $request, int $organisation): JsonResponse
    {
        Organisation::query()->visibleTo($request->user())->findOrFail($organisation);
        $data = $request->validate(['q' => ['required', 'string', 'max:255']]);
        $term = trim($data['q']);
        $clients = Client::query()->where(function ($query) use ($term) {
            $query->when(ctype_digit($term), fn ($ids) => $ids->where('client_id', $term))->orWhere(function ($names) use ($term) {
                foreach (preg_split('/\s+/u', $term) as $word) {
                    $names->where(fn ($part) => $part->whereLike('first_name', '%'.$word.'%')
                        ->orWhereLike('middle_name', '%'.$word.'%')->orWhereLike('surname', '%'.$word.'%'));
                }
            });
        })->orderBy('first_name')->orderBy('surname')->orderBy('client_id')->limit(20)
            ->get(['client_id', 'first_name', 'middle_name', 'surname']);

        // Global client search is already available to CRM users. Only return
        // the name and public ID needed to choose a nominee here.
        return response()->json(['clients' => $clients->map(fn (Client $client) => [
            'client_id' => $client->client_id,
            'name' => trim(implode(' ', array_filter([$client->first_name, $client->middle_name, $client->surname]))),
        ])]);
    }

    public function store(Request $request, int $organisation, ApplicationLinks $links): RedirectResponse
    {
        $record = Organisation::query()->visibleTo($request->user())->findOrFail($organisation);
        $identity = $request->validate(['category' => ['required', Rule::in(['SBS', 'Nomination'])]]);
        $data = $this->details($request, $identity['category']);
        if ($identity['category'] === 'SBS') {
            $workflow = ApplicationWorkflow::query()->where('category', 'SBS')->orderBy('id')->first();
            $data += ['application_workflow_id' => $workflow?->id, 'migration_agent_id' => $workflow?->migration_agent_id];
        }
        $visa = $data['visa_application_id'] ?? null;
        unset($data['visa_application_id']);
        $application = DB::transaction(function () use ($record, $request, $identity, $data, $visa, $links) {
            $application = $record->applications()->create($data + $identity + ['primary_counsellor_id' => $request->user()->id]);
            if ($application->category === 'Nomination') {
                $nominationRecord = app(NominationRecord::class);
                $application->fill($nominationRecord->validateDetails($application, $request->user(), $request->only(NominationRecord::DETAIL_FIELDS), false));
                if (! $request->exists('sponsorship_id')) {
                    $application->sponsorship_id = $nominationRecord->defaultSponsorship($application, $request->user());
                }
                $application->save();
            }
            if ($visa) {
                $links->change($request->user(), $application->id, (int) $visa, true);
            }

            return $application;
        });

        return redirect()->route('applications.show', $application)->with('success', $application->is_labour_agreement ? 'Labour Agreement application created.' : $application->category.' application created.');
    }

    public function edit(Request $request, int $application): View
    {
        $record = $this->record($request, $application);

        if ($record->category === 'Nomination' || SbsRecord::supports($record)) {
            return app(ApplicationsController::class)->show($request, $application, app(ApplicationSummary::class), app(ApplicationLinks::class));
        }

        return view('crm.organisations.application-form', [
            'title' => 'PSC CRM - Edit '.$record->category, 'organisation' => $record->organisation, 'application' => $record,
        ]);
    }

    public function update(Request $request, int $application): RedirectResponse
    {
        $record = $this->record($request, $application);
        if (SbsRecord::supports($record)) {
            app(SbsRecord::class)->update($request->user(), $application, $request->all(), false);

            return redirect()->route('applications.show', $record)->with('success', $record->is_labour_agreement ? 'Labour Agreement details saved.' : 'SBS details saved.');
        }
        if ($record->category === 'Nomination') {
            app(NominationRecord::class)->update($request->user(), $application, $request->all(), false);

            return redirect()->route('applications.show', $record)->with('success', 'Nomination details saved.');
        }
        $record->update($this->details($request, $record->category, $record));

        return redirect()->route('applications.show', $record)->with('success', $record->category.' details saved.');
    }

    private function record(Request $request, int $application): Application
    {
        return Application::query()->assignedTo($request->user())->whereNotNull('organisation_id')
            ->whereIn('category', ['SBS', 'Nomination'])->with('organisation')->findOrFail($application);
    }

    private function details(Request $request, string $category, ?Application $record = null): array
    {
        $creating = $record === null;
        $sbs = $category === 'SBS';
        if ($sbs && $record) {
            // Validate the resulting date pair even for a partial update.
            $request->merge([
                'approval_date' => $request->input('approval_date', $record->approval_date?->format('Y-m-d')),
                'expiry_date' => $request->input('expiry_date', $record->expiry_date?->format('Y-m-d')),
            ]);
        }
        $rules = [];
        foreach (['sponsor_type', 'home_affairs_reference', 'trn'] as $field) {
            $rules[$field] = $sbs ? [$field === 'sponsor_type' ? 'required' : 'nullable', 'string', 'max:255'] : ['prohibited'];
        }
        if ($sbs && $creating) {
            if ($request->input('sponsor_type') === 'Labour Agreement') {
                $rules['trn'] = ['missing'];
            }
            $rules['sponsor_type'][] = Rule::in(['Standard Business Sponsor', 'Labour Agreement']);
            if ($request->input('sponsor_type') === 'Standard Business Sponsor') {
                $rules['trn'][] = 'regex:/^[A-Za-z0-9]{13}$/';
            }
        }
        if ($sbs && ! $creating) {
            $rules['sponsor_type'][] = Rule::in([$record->sponsor_type]);
        }
        $rules['approval_date'] = $sbs ? ['nullable', 'date_format:Y-m-d'] : ['prohibited'];
        $rules['expiry_date'] = $sbs ? ['nullable', 'date_format:Y-m-d', ...($request->filled('approval_date') ? ['after_or_equal:approval_date'] : [])] : ['prohibited'];
        foreach (['nominated_position_title', 'outcome', 'nominee_name', 'nomination_pathway'] as $field) {
            $rules[$field] = $sbs ? ['prohibited'] : [$field === 'nominated_position_title' && ! $creating ? 'required' : 'nullable', 'string', 'max:255'];
        }
        // The user-facing Client ID differs from clients.id. Resolve it explicitly.
        $rules['nominee_client_reference'] = $sbs ? ['prohibited'] : ['nullable', 'integer', Rule::exists('clients', 'client_id')];
        $rules['visa_application_id'] = ! $sbs && $creating ? ['nullable', 'integer', Rule::in(
            Application::query()->assignedTo($request->user())->where('category', 'Visa')->pluck('id')->all()
        )] : ['prohibited'];
        if (! $sbs && $creating) {
            $rules['external_nominee'] = ['sometimes', 'boolean'];
            $rules['nominee_name'] = ['required_if:external_nominee,1', 'nullable', 'string', 'max:255'];
            $rules['nominee_client_reference'] = ['required_unless:external_nominee,1', 'nullable', 'integer', Rule::exists('clients', 'client_id')];
            $rules['visa_subclass'] = ['required', 'string', 'max:255', Rule::in(app(NominationCreation::class)->subclasses())];
            // The selected subclass is stored in the existing nomination field.
            $rules['nomination_pathway'] = ['prohibited'];
        }
        $data = $request->validate($rules, [], [
            'nominee_client_reference' => 'nominee', 'nominee_name' => 'nominee name', 'visa_subclass' => 'visa subclass',
        ]);
        if ($sbs && $creating && filled($data['approval_date'] ?? null)) {
            $request->validate(['expiry_date' => ['required', 'date_format:Y-m-d', 'after_or_equal:approval_date']]);
            $data += ['outcome' => 'Approved', 'outcome_date' => $data['approval_date']];
        }
        if (! $sbs && $creating) {
            $external = $request->boolean('external_nominee');
            $data['nominee_client_reference'] = $external ? null : $data['nominee_client_reference'];
            $data['nominee_name'] = $external ? $data['nominee_name'] : null;
            $data['nomination_pathway'] = $data['visa_subclass'];
            unset($data['external_nominee'], $data['visa_subclass']);
        }
        if (! $sbs && ($creating || array_key_exists('nominee_client_reference', $data))) {
            $data['nominee_client_id'] = filled($data['nominee_client_reference'] ?? null)
                ? Client::query()->where('client_id', $data['nominee_client_reference'])->value('id') : null;
            unset($data['nominee_client_reference']);
        }

        return $data;
    }
}

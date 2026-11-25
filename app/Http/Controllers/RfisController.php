<?php

namespace App\Http\Controllers;

use App\Models\Application;
use App\Models\ApplicationRfi;
use App\Models\ApplicationTypeSetting;
use App\Models\User;
use App\Support\PSC\RfiList;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class RfisController extends Controller
{
    public function __construct(private readonly RfiList $list) {}

    public function index(Request $request): View
    {
        $filters = $this->filters($request);

        return view('crm.rfis.index', [
            'title' => 'PSC CRM - RFIs', 'filters' => $filters, 'columns' => RfiList::COLUMNS,
            'rfis' => $this->list->filtered($request->user(), $filters)->paginate($filters['per_page'] ?? 10)->withQueryString(),
            'officers' => User::whereIn('id', $this->list->visible($request->user())->select('assigned_to_user_id'))->orderBy('name')->get(),
        ]);
    }

    public function create(Request $request, int $application): View
    {
        $record = Application::assignedTo($request->user())->findOrFail($application);
        abort_unless(isset(ApplicationTypeSetting::tabsFor($record->category, $record->sponsor_type, $record->workflow)['rfi']), 422, 'RFI is disabled for this application type.');
        $rfi = new ApplicationRfi(['assigned_to_user_id' => $request->user()->id]);
        $rfi->application()->associate($record);

        return $this->form($rfi, 'application');
    }

    public function edit(Request $request, int $rfi): View
    {
        return $this->form($this->record($request, $rfi), $request->query('return_to') === 'application' ? 'application' : 'list');
    }

    private function form(ApplicationRfi $rfi, string $returnTo): View
    {
        return view('crm.rfis.form', ['title' => 'PSC CRM - '.($rfi->exists ? 'Edit RFI' : 'Add RFI'),
            'rfi' => $rfi, 'application' => $rfi->application, 'returnTo' => $returnTo,
            'officers' => User::orderBy('name')->orderBy('id')->get()]);
    }

    public function store(Request $request, int $application): RedirectResponse
    {
        $record = Application::assignedTo($request->user())->findOrFail($application);
        abort_unless(isset(ApplicationTypeSetting::tabsFor($record->category, $record->sponsor_type, $record->workflow)['rfi']), 422, 'RFI is disabled for this application type.');
        $request->mergeIfMissing(['status' => 'Received', 'assigned_to_user_id' => $request->user()->id]);
        $rfi = new ApplicationRfi;
        $rfi->application()->associate($record);
        $this->save($request, $rfi);

        return $this->back($request, $rfi, 'RFI added.');
    }

    public function update(Request $request, int $rfi): RedirectResponse
    {
        $record = $this->record($request, $rfi);
        $this->save($request, $record);

        return $this->back($request, $record, 'RFI saved.');
    }

    private function save(Request $request, ApplicationRfi $rfi): void
    {
        $data = $request->validate([
            'request_type' => ['required', Rule::in(ApplicationRfi::TYPES)],
            'request_type_other' => ['exclude_unless:request_type,Other', 'required_if:request_type,Other', 'string', 'max:255'],
            'received_date' => ['required', 'date_format:Y-m-d'],
            'due_date' => ['required', 'date_format:Y-m-d', 'after_or_equal:received_date'],
            'status' => ['required', Rule::in(ApplicationRfi::STATUSES)],
            'assigned_to_user_id' => ['required', 'integer', Rule::exists('users', 'id')],
            'request_letter' => [$rfi->request_letter_path ? 'nullable' : 'required', 'file', 'mimes:pdf,jpg,jpeg,png,doc,docx', 'max:10240'],
            'lodged_date' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:received_date'],
            'proof_of_lodgement' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png,doc,docx', 'max:10240'],
            'notes' => [$rfi->exists ? 'prohibited' : 'nullable', 'string', 'max:10000'],
            'closed_confirmed' => [$request->input('status') === 'Closed' && (! $rfi->exists || $rfi->status !== 'Closed') ? 'accepted' : 'nullable'],
            'return_to' => ['nullable', Rule::in(['list', 'application'])],
        ], ['closed_confirmed.accepted' => 'Please confirm before closing this RFI.'], [
            'received_date' => 'date received', 'due_date' => 'response due date',
            'assigned_to_user_id' => 'assigned officer', 'request_type_other' => 'other request type',
        ]);
        unset($data['request_letter'], $data['proof_of_lodgement'], $data['closed_confirmed'], $data['return_to']);
        if ($rfi->exists) {
            unset($data['notes']);
        }
        $data['request_type_other'] = $data['request_type'] === 'Other' ? $data['request_type_other'] : null;
        $newFiles = [];
        $replacedFiles = [];
        try {
            foreach (['request_letter', 'proof_of_lodgement'] as $field) {
                if ($request->hasFile($field)) {
                    $path = $request->file($field)->store('rfis/'.$rfi->application_id, 'local');
                    if ($path === false) {
                        throw new \RuntimeException('The RFI document could not be stored.');
                    }
                    $newFiles[] = $path;
                    if ($rfi->{$field.'_path'}) {
                        $replacedFiles[] = $rfi->{$field.'_path'};
                    }
                    $data[$field.'_path'] = $path;
                    $data[$field.'_name'] = $request->file($field)->getClientOriginalName();
                }
            }
            DB::transaction(fn () => $rfi->fill($data)->save());
        } catch (\Throwable $exception) {
            Storage::disk('local')->delete($newFiles);
            throw $exception;
        }
        Storage::disk('local')->delete($replacedFiles);
    }

    public function notes(Request $request, int $rfi): RedirectResponse
    {
        $record = $this->record($request, $rfi);
        $data = $request->validate(['notes' => ['nullable', 'string', 'max:10000'], 'return_to' => ['nullable', Rule::in(['list', 'application'])]]);
        $record->update(['notes' => $data['notes'] ?? '']);

        return $this->back($request, $record, 'RFI notes saved.');
    }

    public function destroy(Request $request, int $rfi): RedirectResponse
    {
        abort_unless(in_array($request->user()->account_type, ['Admin', 'Super Admin'], true), 403);
        $record = $this->record($request, $rfi);
        $paths = array_filter([$record->request_letter_path, $record->proof_of_lodgement_path]);
        $record->delete();
        Storage::disk('local')->delete($paths);

        return $this->back($request, $record, 'RFI deleted.');
    }

    public function document(Request $request, int $rfi, string $document): StreamedResponse
    {
        $record = $this->record($request, $rfi);
        abort_unless(in_array($document, ['request_letter', 'proof_of_lodgement'], true), 404);
        $path = $record->{$document.'_path'};
        abort_unless($path && Storage::disk('local')->exists($path), 404);

        return Storage::disk('local')->response($path, $record->{$document.'_name'}, [
            'X-Content-Type-Options' => 'nosniff', 'Cache-Control' => 'private, no-store',
        ], 'inline');
    }

    private function record(Request $request, int $rfi): ApplicationRfi
    {
        return $this->list->visible($request->user())->with(['application', 'officer'])->findOrFail($rfi);
    }

    private function back(Request $request, ApplicationRfi $rfi, string $message): RedirectResponse
    {
        $canOpenApplication = $rfi->application && Application::assignedTo($request->user())->whereKey($rfi->application_id)->exists();
        $url = $request->input('return_to') === 'application' && $canOpenApplication
            ? route('applications.show', ['application' => $rfi->application_id, 'tab' => 'rfi']).'#application-tabs'
            : route('rfis');

        return redirect()->to($url)->with('success', $message);
    }

    public function export(Request $request): StreamedResponse
    {
        $query = $this->list->filtered($request->user(), $this->filters($request));

        $showNotes = app(\App\Support\PSC\CrmAccess::class)->notes($request->user(), 'rfis');

        return response()->streamDownload(function () use ($query, $showNotes) {
            $handle = fopen('php://output', 'w');
            fwrite($handle, "\xEF\xBB\xBF");
            fputcsv($handle, array_values(RfiList::COLUMNS), ',', '"', '');
            foreach ($query->lazy(250) as $rfi) {
                $values = [str_pad((string) $rfi->application_id, 5, '0', STR_PAD_LEFT), $rfi->request_label,
                    $rfi->received_date?->format('d/m/Y'), $rfi->due_date?->format('d/m/Y'), $rfi->status,
                    $rfi->officer?->name, $rfi->request_letter_name, $rfi->lodged_date?->format('d/m/Y'),
                    $rfi->proof_of_lodgement_name, $showNotes ? $rfi->notes : null];
                $values = array_map(function ($value) {
                    $value = (string) ($value ?? '');

                    return preg_match('/^[\s\x{FEFF}]*[=+@-]|^[\t\r\n]/u', $value) ? "'".$value : $value;
                }, $values);
                fputcsv($handle, $values, ',', '"', '');
            }
            fclose($handle);
        }, 'RFI list - '.now('Asia/Manila')->format('Ymd - H:i:s').'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    private function filters(Request $request): array
    {
        $rules = ['application_id' => ['nullable', 'string', 'max:80'], 'display_closed' => ['nullable', 'boolean'],
            'request_type' => ['nullable', 'array', 'max:5'], 'request_type.*' => ['required', Rule::in(ApplicationRfi::TYPES), 'distinct'],
            'status' => ['nullable', 'array', 'max:5'], 'status.*' => ['required', Rule::in(ApplicationRfi::STATUSES), 'distinct'],
            'assigned_to_user_id' => ['nullable', 'array', 'max:500'], 'assigned_to_user_id.*' => ['required', 'integer', 'distinct'],
            'sort' => ['sometimes', Rule::in(RfiList::SORTABLE)], 'direction' => ['sometimes', Rule::in(['asc', 'desc'])],
            'per_page' => ['sometimes', 'integer', Rule::in([10, 20, 50])], 'page' => ['sometimes', 'integer', 'min:1']];
        foreach (['received_date', 'due_date', 'lodged_date'] as $column) {
            $rules[$column.'_from'] = ['nullable', 'date_format:Y-m-d'];
            $rules[$column.'_to'] = ['nullable', 'date_format:Y-m-d', ...($request->filled($column.'_from') ? ['after_or_equal:'.$column.'_from'] : [])];
        }

        return $request->validate($rules);
    }
}

<?php

namespace App\Http\Controllers;

use App\Models\Application;
use App\Models\ApplicationActivity;
use App\Models\ApplicationTypeSetting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ApplicationActivitiesController extends Controller
{
    public function store(Request $request, int $application): RedirectResponse
    {
        $record = Application::query()->assignedTo($request->user())->whereNotNull('organisation_id')
            ->whereIn('category', ['SBS', 'Nomination'])->findOrFail($application);
        $kind = $request->validate(['kind' => ['required', Rule::in(array_keys(ApplicationActivity::TABS)),
            Rule::in(array_keys(ApplicationTypeSetting::tabsFor($record->category, $record->sponsor_type)))]]);
        $financial = in_array($kind['kind'], ['payments', 'quotes'], true);
        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'], 'status' => ['nullable', 'string', 'max:80'],
            'reference' => ['nullable', 'string', 'max:255'], 'due_date' => ['nullable', 'date_format:Y-m-d'],
            'amount' => $financial ? ['required', 'numeric', 'min:0', 'max:9999999999.99', 'decimal:0,2'] : ['prohibited'],
            'currency' => $financial ? ['required', 'regex:/^[A-Z]{3}$/'] : ['prohibited'],
        ]);
        $record->activities()->create($kind + $data);

        return redirect()->route('applications.show', ['application' => $record, 'tab' => $kind['kind']])
            ->with('success', 'Activity record added.');
    }
}

<?php

namespace App\Http\Controllers;

use App\Models\Application;
use App\Support\PSC\ApplicationLinks;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ApplicationLinksController extends Controller
{
    public function edit(Request $request, int $application, ApplicationLinks $links): View|JsonResponse
    {
        $record = Application::query()->assignedTo($request->user())->findOrFail($application);
        $validated = $request->validate([
            'search' => ['nullable', 'string', 'max:255'],
            'page' => ['sometimes', 'integer', 'min:1'],
            'application_id' => ['nullable', 'integer', 'min:1'],
        ]);
        $search = trim($validated['search'] ?? '');

        if ($request->expectsJson()) {
            return response()->json([
                'linked' => $links->rows($record, $request->user())->get(),
                'candidates' => $links->choices($record, $request->user(), isset($validated['application_id']) ? (int) $validated['application_id'] : null)->paginate(15),
            ]);
        }

        return view('crm.applications.links', [
            'title' => 'PSC CRM - Link/Unlink items',
            'application' => $record,
            'linkedItems' => $links->rows($record, $request->user())->get(),
            'candidates' => $links->candidates($record, $request->user(), $search)->paginate(15)->withQueryString(),
            'search' => $search,
        ]);
    }

    public function update(Request $request, int $application, ApplicationLinks $links): RedirectResponse|JsonResponse
    {
        Application::query()->assignedTo($request->user())->findOrFail($application);
        if ($request->input('action') === 'save') {
            $validated = $request->validate([
                'add' => ['present', 'array', 'max:100'],
                'remove' => ['present', 'array', 'max:100'],
                'add.*' => ['required', 'integer', 'min:1', 'distinct', Rule::notIn([$application])],
                'remove.*' => ['required', 'integer', 'min:1', 'distinct', Rule::notIn([$application])],
            ]);
            abort_if(array_intersect($validated['add'], $validated['remove']) !== [], 422, 'An application cannot be added and removed together.');
            $links->save($request->user(), $application, $validated['add'], $validated['remove']);

            if ($request->expectsJson()) {
                return response()->json(['message' => 'Linked items saved.']);
            }

            return redirect()->route('applications.show', $application)->with('success', 'Linked items saved.');
        }
        $validated = $request->validate([
            'linked_application_id' => ['required', 'integer', 'min:1', Rule::notIn([$application])],
            'action' => ['required', Rule::in(['link', 'unlink'])],
        ], ['linked_application_id.not_in' => 'An application cannot link to itself.']);

        $links->change($request->user(), $application, (int) $validated['linked_application_id'], $validated['action'] === 'link');

        return redirect()->route('applications.links.edit', $application)
            ->with('success', $validated['action'] === 'link' ? 'Application linked.' : 'Application unlinked.');
    }
}

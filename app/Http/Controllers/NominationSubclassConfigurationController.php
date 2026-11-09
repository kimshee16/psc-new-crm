<?php

namespace App\Http\Controllers;

use App\Models\NominationSubclass;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class NominationSubclassConfigurationController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        return $this->save($request, new NominationSubclass);
    }

    public function update(Request $request, NominationSubclass $nominationSubclass): RedirectResponse
    {
        return $this->save($request, $nominationSubclass);
    }

    private function save(Request $request, NominationSubclass $option): RedirectResponse
    {
        $this->authorizeConfiguration($request);
        $data = $request->validate([
            'subclass' => ['required', 'string', 'regex:/^\d{3}$/', Rule::in(array_keys(NominationSubclass::visaOptions())),
                Rule::unique('nomination_subclasses', 'subclass')->ignore($option)],
            'sbs_required' => ['required', 'boolean'],
        ]);
        $option->fill($data)->save();

        return redirect()->route('configuration', ['tab' => 'nomination-subclasses'])
            ->with('success', 'Nomination subclass was saved.');
    }

    public function destroy(Request $request, NominationSubclass $nominationSubclass): RedirectResponse
    {
        $this->authorizeConfiguration($request);
        // Existing nominations retain their saved pathway when an option is removed.
        $nominationSubclass->delete();

        return redirect()->route('configuration', ['tab' => 'nomination-subclasses'])
            ->with('success', 'Nomination subclass was removed.');
    }

    private function authorizeConfiguration(Request $request): void
    {
        abort_unless(in_array($request->user()?->account_type, [User::ACCOUNT_TYPE_ADMIN, 'Super Admin'], true), 403);
    }
}

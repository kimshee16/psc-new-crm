<?php

namespace App\Http\Controllers;

use App\Models\EventReferral;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class EventReferralConfigurationController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        return $this->save($request, new EventReferral);
    }

    public function update(Request $request, EventReferral $eventReferral): RedirectResponse
    {
        return $this->save($request, $eventReferral);
    }

    private function save(Request $request, EventReferral $eventReferral): RedirectResponse
    {
        abort_unless(in_array($request->user()?->account_type, [User::ACCOUNT_TYPE_ADMIN, 'Super Admin'], true), 403);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:160', Rule::unique('event_referrals', 'name')->ignore($eventReferral)],
            'sort_order' => ['required', 'integer', 'min:1', 'max:999'],
            'active' => ['required', 'boolean'],
        ], [], ['name' => 'event referral option', 'sort_order' => 'sort order']);

        $eventReferral->fill($validated)->save();

        return redirect()->route('configuration', ['tab' => 'event-marketing'])
            ->with('success', 'Event referral option was saved.');
    }
}

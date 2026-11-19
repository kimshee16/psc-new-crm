<?php

namespace App\Http\Controllers;

use App\Models\EventReferral;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

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
        ], [], ['name' => 'event referral option', 'sort_order' => 'position']);

        DB::transaction(function () use ($eventReferral, $validated) {
            $options = EventReferral::query()->orderBy('id')->lockForUpdate()->get();
            $option = $eventReferral->exists ? $options->find($eventReferral->id) : new EventReferral;
            abort_unless($option, 404);

            if ($options->contains(fn (EventReferral $other) => $other->isNot($option) && $other->sort_order === (int) $validated['sort_order'])) {
                throw ValidationException::withMessages(['sort_order' => 'That position is already in use. Choose an unused position.']);
            }

            $option->fill($validated)->save();
        }, 5);

        return redirect()->route('configuration', ['tab' => 'event-marketing'])
            ->with('success', 'Event referral option was saved.');
    }
}

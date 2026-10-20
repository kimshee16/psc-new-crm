<?php

namespace App\Http\Controllers;

use App\Models\PscEvent;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class EventConfigurationController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        return $this->save($request, new PscEvent);
    }

    public function update(Request $request, PscEvent $event): RedirectResponse
    {
        return $this->save($request, $event);
    }

    private function save(Request $request, PscEvent $event): RedirectResponse
    {
        abort_unless(in_array($request->user()?->account_type, [User::ACCOUNT_TYPE_ADMIN, 'Super Admin'], true), 403);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:160'],
            'starts_at' => ['required', 'date_format:Y-m-d\TH:i'],
            'location' => ['required', 'string', 'max:180'],
            'active' => ['required', 'boolean'],
        ], [], ['name' => 'event name', 'starts_at' => 'event date and time']);

        $event->fill($validated)->save();

        return redirect()->route('configuration', ['tab' => 'events'])
            ->with('success', 'PSC event was saved.');
    }
}

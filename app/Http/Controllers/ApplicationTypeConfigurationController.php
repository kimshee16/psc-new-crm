<?php

namespace App\Http\Controllers;

use App\Models\ApplicationTypeSetting;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ApplicationTypeConfigurationController extends Controller
{
    public function update(Request $request): RedirectResponse
    {
        abort_unless(in_array($request->user()?->account_type, [User::ACCOUNT_TYPE_ADMIN, 'Super Admin'], true), 403);

        $validated = $request->validate([
            'category' => ['required', 'string', Rule::in(array_keys(ApplicationTypeSetting::types()))],
            'tabs' => ['sometimes', 'array', 'max:'.count(ApplicationTypeSetting::TABS)],
            'tabs.*' => ['required', 'string', 'distinct', Rule::in(array_keys(ApplicationTypeSetting::TABS))],
        ]);

        ApplicationTypeSetting::query()->updateOrCreate(['category' => $validated['category']], [
            'tabs' => array_keys(ApplicationTypeSetting::availableTabs($validated['tabs'] ?? [])),
        ]);

        return redirect()->route('configuration', ['tab' => 'application-types'])
            ->with('success', ApplicationTypeSetting::types()[$validated['category']].' tabs were saved.');
    }
}

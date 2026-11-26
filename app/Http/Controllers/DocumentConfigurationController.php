<?php

namespace App\Http\Controllers;

use App\Models\ApplicationCatalogEntry;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class DocumentConfigurationController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        abort_unless(in_array($request->user()?->account_type, [User::ACCOUNT_TYPE_ADMIN, 'Super Admin'], true), 403);

        $document = $request->validate([
            'type' => ['required', 'string', Rule::in(ApplicationCatalogEntry::DOCUMENT_TYPES)],
            'name' => ['required', 'string', 'max:255'],
        ]);

        $entry = ApplicationCatalogEntry::query()->firstOrCreate(['catalog' => 'required_documents'] + $document);

        return redirect()->route('configuration', ['tab' => 'documents'])
            ->with('success', $entry->wasRecentlyCreated ? 'Document added.' : 'This document already exists.');
    }
}

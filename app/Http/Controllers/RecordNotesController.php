<?php

namespace App\Http\Controllers;

use App\Models\Application;
use App\Support\PSC\RecordNotes;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RecordNotesController extends Controller
{
    public function save(Request $request, string $module, string $recordId, RecordNotes $notes, ?string $noteId = null): JsonResponse
    {
        $record = match ($module) {
            'clients' => app(ClientsController::class)->noteRecord($recordId, $request->user()),
            'leads' => app(LeadsController::class)->noteRecord($recordId, $request->user()),
            'applications' => Application::query()->assignedTo($request->user())->findOrFail($recordId),
            default => abort(404),
        };
        $action = match ($request->method()) {
            'POST' => 'store', 'PATCH' => 'update', 'DELETE' => 'destroy',
        };
        $data = $request->validate([
            'version' => ['required', 'string', 'size:64'],
            'body' => [$action === 'destroy' ? 'nullable' : 'required', 'string', 'max:10000'],
        ]);
        $record = $notes->mutate($record, $request->user(), $data['version'], $action, $noteId, $data['body'] ?? null);

        return response()->json(['html' => view('components.record-notes', [
            'stored' => $record->notes, 'module' => $module, 'recordId' => $recordId, 'editable' => true,
            'notice' => $action === 'destroy' ? 'Note removed.' : 'Note saved.',
        ])->render()]);
    }
}

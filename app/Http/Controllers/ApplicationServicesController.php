<?php

namespace App\Http\Controllers;

use App\Models\Application;
use App\Models\ApplicationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

class ApplicationServicesController extends Controller
{
    public function save(Request $request, int $application, ?int $service = null): JsonResponse
    {
        $disk = Storage::disk('local');
        $newPath = null;
        $oldPath = null;

        try {
            DB::transaction(function () use ($request, $application, $service, &$newPath, &$oldPath): void {
                $record = $this->studyApplication($request, $application, lock: true);
                $row = $service ? $record->services()->findOrFail($service) : $record->services()->make();
                $type = $request->validate(['type' => ['required', Rule::in(array_keys(ApplicationService::TYPES))]])['type'];
                $money = ['required', 'numeric', 'decimal:0,2', 'min:0', 'max:999999999.99'];
                $details = $request->validate(match ($type) {
                    'oshc' => [
                        'provider' => ['required', Rule::in(ApplicationService::PROVIDERS)],
                        'cover' => ['required', Rule::in(ApplicationService::COVERS)],
                        'starts' => ['required', 'date_format:Y-m-d'],
                        'ends' => ['required', 'date_format:Y-m-d', 'after_or_equal:starts'],
                        'amount' => $money,
                    ],
                    'agent' => ['amount' => $money],
                    'other' => ['amount' => $money, 'description' => ['required', 'string', 'max:2000']],
                    'translation', 'afp' => ['quantity' => ['required', 'integer', 'min:1', 'max:999']],
                    'gsr' => [],
                    'visa' => [
                        'option' => ['required', 'string', 'size:64'],
                        'quantities' => ['nullable', 'array:adult,child,stac,instalment'],
                        'quantities.*' => ['required', 'integer', 'min:0', 'max:999'],
                    ],
                });
                $request->validate(['screenshot' => $type === 'oshc'
                    ? ['nullable', 'image', 'mimes:png,jpg,jpeg,webp', 'max:5120'] : ['prohibited']]);

                if ($type === 'gsr' && $record->services()->where('type', 'gsr')->where('id', '!=', $row->id ?? 0)->exists()) {
                    throw ValidationException::withMessages(['type' => 'Only one GSR service can be added per application.']);
                }

                if (isset(ApplicationService::RATES[$type])) {
                    $details['quantity'] = (int) ($details['quantity'] ?? 1);
                    $details['rate'] = $row->type === $type ? $row->details['rate'] : ApplicationService::RATES[$type];
                    $total = $details['rate'] * $details['quantity'];
                } elseif ($type === 'visa') {
                    [$details, $total] = $this->visaDetails($details, $row);
                } else {
                    $total = (int) round((float) $details['amount'] * 100);
                }

                $screenshot = $type === 'oshc' ? $row->screenshot : null;
                if ($request->hasFile('screenshot')) {
                    $newPath = $request->file('screenshot')->store('application-services', 'local');
                    abort_unless($newPath, 503, 'The screenshot could not be saved.');
                    $screenshot = $newPath;
                }
                $oldPath = $screenshot !== $row->screenshot ? $row->screenshot : null;
                $row->fill(['type' => $type, 'details' => $details, 'total_cents' => $total, 'screenshot' => $screenshot])->save();
            });
        } catch (Throwable $exception) {
            if ($newPath) {
                $disk->delete($newPath);
            }
            throw $exception;
        }

        if ($oldPath) {
            $disk->delete($oldPath);
        }

        return response()->json(['message' => 'Service saved.']);
    }

    public function destroy(Request $request, int $application, int $service): JsonResponse
    {
        $path = DB::transaction(function () use ($request, $application, $service): ?string {
            $record = $this->studyApplication($request, $application, lock: true);
            $row = $record->services()->findOrFail($service);
            $row->delete();

            return $row->screenshot;
        });
        if ($path) {
            Storage::disk('local')->delete($path);
        }

        return response()->json(['message' => 'Service removed.']);
    }

    public function screenshot(Request $request, int $application, int $service): StreamedResponse
    {
        $row = $this->studyApplication($request, $application)->services()->findOrFail($service);
        $disk = Storage::disk('local');
        abort_unless($row->screenshot && $disk->exists($row->screenshot), 404);

        return $disk->download($row->screenshot, null, ['Cache-Control' => 'private, no-store']);
    }

    private function studyApplication(Request $request, int $application, bool $lock = false): Application
    {
        return Application::query()->assignedTo($request->user())->where('category', 'Study')
            ->when($lock, fn ($query) => $query->lockForUpdate())->findOrFail($application);
    }

    private function visaDetails(array $details, ApplicationService $row): array
    {
        $option = $row->type === 'visa' && $row->details['option'] === $details['option']
            ? $row->details : (ApplicationService::visaOptions()[$details['option']] ?? null);
        if (! $option) {
            throw ValidationException::withMessages(['option' => 'Select a configured visa fee.']);
        }

        $total = 0;
        $quantities = [];
        foreach ($option['rates'] as $key => $rate) {
            $quantities[$key] = $key === 'primary' ? 1 : (int) ($details['quantities'][$key] ?? 0);
            $total += $rate * $quantities[$key];
        }

        return [[
            'option' => $details['option'], 'name' => $option['name'],
            'rates' => $option['rates'], 'quantities' => $quantities,
        ], $total];
    }
}

<?php

namespace App\Http\Controllers;

use App\Models\ApplicationWorkflow;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class VisaChargeConfigurationController extends Controller
{
    public const AMOUNTS = [
        'primary' => 'Primary applicant (AUD)',
        'adult' => 'Additional 18 or over (AUD)',
        'child' => 'Additional under 18 (AUD)',
        'stac' => 'STAC (AUD)',
        'instalment' => '2nd instalment (AUD)',
    ];

    public function update(Request $request): JsonResponse
    {
        abort_unless(in_array($request->user()?->account_type, [User::ACCOUNT_TYPE_ADMIN, 'Super Admin'], true), 403);

        $rules = [
            'groups' => ['required', 'array'],
            'groups.*.id' => ['required', 'integer', 'distinct'],
            'groups.*.revision' => ['required', 'string', 'size:64'],
            'groups.*.rows' => ['required', 'array', 'min:1'],
            'groups.*.rows.*.name' => ['nullable', 'string', 'max:255'],
            'groups.*.rows.*.description' => ['nullable', 'string', 'max:2000'],
            'groups.*.rows.*.effective' => ['nullable', 'date_format:Y-m-d'],
        ];
        foreach (array_keys(self::AMOUNTS) as $field) {
            $rules["groups.*.rows.*.$field"] = ['nullable', 'numeric', 'decimal:0,2', 'min:0', 'max:999999999.99'];
        }

        $groups = collect($request->validate($rules)['groups'])->sortBy('id');
        DB::transaction(function () use ($groups): void {
            foreach ($groups as $index => $group) {
                $workflow = ApplicationWorkflow::query()->where('category', 'Visa')
                    ->lockForUpdate()->findOrFail($group['id']);
                if (! hash_equals(self::revision($workflow), $group['revision'])) {
                    throw ValidationException::withMessages(['groups' => 'Charges changed in another session. Reload the page before editing again.']);
                }

                $rows = array_values($group['rows']);
                $hasVariations = collect($rows)->contains(fn (array $row) => filled($row['name'] ?? null));
                $dates = [];
                foreach ($rows as $rowIndex => &$row) {
                    $path = "groups.$index.rows.$rowIndex";
                    $name = $row['name'] ?? null;
                    $effective = $row['effective'] ?? null;
                    if ($hasVariations && blank($name)) {
                        throw ValidationException::withMessages(["$path.name" => 'Enter a name for each variation.']);
                    }
                    $hasAmount = false;
                    foreach (array_keys(self::AMOUNTS) as $field) {
                        $amount = $row[$field] ?? null;
                        $hasAmount = $hasAmount || (float) $amount > 0;
                        $row[$field] = $amount === null ? null : number_format((float) $amount, 2, '.', '');
                    }
                    if (($hasAmount || (! $hasVariations && count($rows) > 1)) && ! $effective) {
                        throw ValidationException::withMessages(["$path.effective" => 'Enter an effective date.']);
                    }
                    $key = mb_strtolower($name ?? '').'|'.$effective;
                    if (isset($dates[$key])) {
                        throw ValidationException::withMessages(["$path.effective" => 'Another row for this charge has this date']);
                    }
                    $dates[$key] = true;
                }
                unset($row);
                $workflow->visa_charges = $rows;
                $workflow->save();
            }
        });

        return response()->json(['saved' => true]);
    }

    public static function revision(ApplicationWorkflow $workflow): string
    {
        return hash('sha256', json_encode($workflow->visa_charges));
    }
}

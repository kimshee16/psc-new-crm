<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ApplicationService extends Model
{
    public const TYPES = [
        'visa' => 'Visa Application fee - 500',
        'oshc' => 'OSHC',
        'translation' => 'Translation',
        'afp' => 'AFP clearance',
        'gsr' => 'GSR',
        'agent' => 'Agent fee',
        'other' => 'Other',
    ];

    public const PROVIDERS = ['ahm', 'Allianz Care', 'Bupa', 'Medibank', 'nib'];

    public const COVERS = ['Single', 'Couple', 'Single Parent', 'Family'];

    public const RATES = ['translation' => 700, 'afp' => 5000, 'gsr' => 15000];

    public const VISA_FEES = [
        'primary' => 'Base visa fee',
        'adult' => 'Additional applicant, 18 or over',
        'child' => 'Additional applicant, under 18',
        'stac' => 'Subsequent temporary application charge (STAC)',
        'instalment' => 'Second instalment',
    ];

    protected $fillable = ['type', 'details', 'total_cents', 'screenshot'];

    protected function casts(): array
    {
        return ['details' => 'array', 'total_cents' => 'integer'];
    }

    public static function visaOptions(): array
    {
        $workflow = ApplicationWorkflow::query()->where('category', 'Visa')
            ->where('name', 'like', '500 - %')->first();

        return collect($workflow?->visaChargesAsOf(today()->toDateString()) ?? [])
            ->mapWithKeys(function (array $row): array {
                $name = $row['name'] ?? '';
                $rates = [];
                foreach (self::VISA_FEES as $key => $label) {
                    $rate = (int) round((float) ($row[$key] ?? 0) * 100);
                    if ($rate > 0) {
                        $rates[$key] = $rate;
                    }
                }

                return [hash('sha256', mb_strtolower($name)) => ['name' => $name, 'rates' => $rates]];
            })->all();
    }
}

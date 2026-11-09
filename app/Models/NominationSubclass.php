<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class NominationSubclass extends Model
{
    protected $fillable = ['subclass', 'sbs_required'];

    protected function casts(): array
    {
        return ['sbs_required' => 'boolean'];
    }

    /** Full visa labels indexed by subclass, using workflow choices before the shared defaults. */
    public static function visaOptions(): array
    {
        $labels = ApplicationWorkflow::query()->where('category', 'Visa')->orderBy('id')->get()
            ->flatMap(fn (ApplicationWorkflow $workflow) => $workflow->summary_options['visa_subclass'] ?? [])
            ->concat(config('applications.creation_options.visa_subclass', []));
        $options = [];
        foreach ($labels as $label) {
            if (is_string($label) && mb_strlen($label) <= 255 && preg_match('/^(\d{3})\s*[-–]\s*\S/u', trim($label), $match)) {
                $options[$match[1]] ??= trim($label);
            }
        }

        return $options;
    }
}

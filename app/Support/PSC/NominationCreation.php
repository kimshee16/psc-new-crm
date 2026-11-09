<?php

namespace App\Support\PSC;

use App\Models\NominationSubclass;

class NominationCreation
{
    public function subclasses(): array
    {
        $visaOptions = NominationSubclass::visaOptions();

        // An empty catalogue stays empty; general visa choices are not nomination options.
        return NominationSubclass::query()->orderBy('id')->pluck('subclass')
            ->map(fn (string $subclass) => $visaOptions[$subclass] ?? null)
            ->filter()->values()->all();
    }
}

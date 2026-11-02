<?php

namespace App\Support\PSC;

use App\Models\EducationLevel;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class EducationLevelOrdering
{
    public function save(EducationLevel $level, array $attributes, ?int $position): void
    {
        DB::transaction(function () use ($level, $attributes, $position) {
            $levels = EducationLevel::query()->orderBy('sort_order')->orderBy('id')->lockForUpdate()->get();
            // Use fresh instances on every transaction retry and detect deleted rows.
            $level = $level->exists ? $levels->firstWhere('id', $level->id) : new EducationLevel;
            abort_unless($level, 404);
            if (! $level->exists && $levels->count() >= 999) {
                throw ValidationException::withMessages(['sort_order' => 'A maximum of 999 education levels is supported.']);
            }
            $position ??= $level->exists ? $level->sort_order : $levels->count() + 1;
            $ordered = $levels->reject(fn ($row) => $row->id === $level->id)->values();
            $position = max(1, min($position, $ordered->count() + 1));
            $level->fill($attributes);
            // Reserve temporary positions so the unique index also protects reordering.
            EducationLevel::query()->increment('sort_order', 2000);
            if (! $level->exists) {
                $level->sort_order = 0;
                $level->save();
            }
            $ordered->splice($position - 1, 0, [$level]);
            foreach ($ordered as $index => $row) {
                EducationLevel::query()->whereKey($row->id)->update(array_merge(
                    $row->id === $level->id ? $attributes : [], ['sort_order' => $index + 1]
                ));
            }
        }, 5);
    }

    public function remove(EducationLevel $level): void
    {
        DB::transaction(function () use ($level) {
            $levels = EducationLevel::query()->orderBy('sort_order')->orderBy('id')->lockForUpdate()->get();
            $level->delete();
            EducationLevel::query()->increment('sort_order', 2000);
            foreach ($levels->reject(fn ($row) => $row->id === $level->id)->values() as $index => $row) {
                EducationLevel::query()->whereKey($row->id)->update(['sort_order' => $index + 1]);
            }
        }, 5);
    }
}

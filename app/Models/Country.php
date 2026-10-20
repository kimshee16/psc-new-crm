<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Country extends Model
{
    protected $fillable = ['name', 'active'];

    protected function casts(): array
    {
        return ['active' => 'boolean'];
    }

    /** @return list<string> */
    public static function names(): array
    {
        return static::query()->where('active', true)->orderBy('name')->pluck('name')->all();
    }

    /** @return list<string> */
    public static function clientNationalities(?string $current = null): array
    {
        // Keep the CRM's existing demonyms available alongside country names.
        $options = static::query()->where('active', true)->get(['name', 'legacy_nationality'])
            ->flatMap(fn (Country $country) => [$country->name, $country->legacy_nationality]);

        return $options->push($current)->filter()->unique()->sort()->values()->all();
    }

    /** @return list<string> */
    public function nationalityValues(): array
    {
        return array_values(array_filter([$this->name, $this->legacy_nationality]));
    }
}

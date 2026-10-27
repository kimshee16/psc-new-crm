<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

class EventReferral extends Model
{
    protected $fillable = ['name', 'active', 'sort_order'];

    protected function casts(): array
    {
        return ['active' => 'boolean', 'sort_order' => 'integer'];
    }

    public static function activeOptions(): Collection
    {
        return static::query()->where('active', true)->orderBy('sort_order')->orderBy('name')->get();
    }
}

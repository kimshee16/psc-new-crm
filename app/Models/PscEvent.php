<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PscEvent extends Model
{
    public function registrationUrl(): string
    {
        return route('event-registration.show', $this);
    }

    public function leads(): HasMany
    {
        return $this->hasMany(Lead::class);
    }

    /**
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'starts_at',
        'location',
        'active',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'starts_at' => 'datetime',
            'active' => 'boolean',
        ];
    }
}

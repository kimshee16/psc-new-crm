<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PscEvent extends Model
{
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

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Location extends Model
{
    public static function isOtherLocation(string $name): bool
    {
        return preg_match('/\bother\b/i', $name) === 1;
    }

    /**
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'office_code',
        'active',
        'sort_order',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'active' => 'boolean',
        ];
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LeadRedirection extends Model
{
    protected $fillable = [
        'nationality',
        'current_location',
        'office_code',
        'active',
    ];

    protected function casts(): array
    {
        return ['active' => 'boolean'];
    }
}

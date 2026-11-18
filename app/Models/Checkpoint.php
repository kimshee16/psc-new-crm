<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Checkpoint extends Model
{
    use SoftDeletes;

    protected $fillable = ['name', 'statuses'];

    protected function casts(): array
    {
        return ['statuses' => 'array'];
    }
}

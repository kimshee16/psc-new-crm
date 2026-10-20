<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Region extends Model
{
    protected $fillable = ['name', 'regional_manager_id'];

    public function regionalManager(): BelongsTo
    {
        return $this->belongsTo(User::class, 'regional_manager_id');
    }

    public function offices(): BelongsToMany
    {
        return $this->belongsToMany(Office::class)->orderBy('code');
    }
}

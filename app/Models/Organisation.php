<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Organisation extends Model
{
    protected $fillable = ['name', 'trading_name', 'abn', 'address', 'created_by_user_id'];

    public function getReferenceAttribute(): string
    {
        return 'ORG-'.str_pad((string) $this->id, 6, '0', STR_PAD_LEFT);
    }

    public function scopeVisibleTo(Builder $query, User $user): void
    {
        $query->where(fn (Builder $visible) => $visible
            ->where('created_by_user_id', $user->id)
            ->orWhereHas('applications', fn (Builder $applications) => $applications->assignedTo($user)));
    }

    public function contacts(): HasMany
    {
        return $this->hasMany(OrganisationContact::class);
    }

    public function applications(): HasMany
    {
        return $this->hasMany(Application::class);
    }
}

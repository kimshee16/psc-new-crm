<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StaffLeave extends Model
{
    protected $fillable = ['starts_on', 'ends_on', 'reason'];

    protected $attributes = ['active' => true];

    protected function casts(): array
    {
        return ['starts_on' => 'date', 'ends_on' => 'date', 'active' => 'boolean'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function scopeOnDate(Builder $query, string $date): Builder
    {
        return $query->where('active', true)
            ->whereDate('starts_on', '<=', $date)
            ->whereDate('ends_on', '>=', $date);
    }

    public function status(): string
    {
        if (! $this->active) {
            return 'Cancelled';
        }

        $today = today();

        if ($this->starts_on->gt($today)) {
            return 'Upcoming';
        }

        return $this->ends_on->lt($today) ? 'Completed' : 'On leave';
    }
}

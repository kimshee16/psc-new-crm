<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class ApplicationWorkflow extends Model
{
    protected $fillable = ['name', 'category', 'summary_options', 'migration_agent_id'];

    protected function casts(): array
    {
        return ['summary_options' => 'array', 'checkpoint_ids' => 'array', 'defaults' => 'array', 'visa_charges' => 'array'];
    }

    public function migrationAgent(): BelongsTo
    {
        return $this->belongsTo(User::class, 'migration_agent_id');
    }

    public function visaChargesAsOf(string $date): array
    {
        if ($this->category !== 'Visa') {
            return [];
        }

        return collect($this->visa_charges ?? [])
            ->filter(fn (array $row) => filled($row['effective'] ?? null) && $row['effective'] <= $date)
            ->sortByDesc('effective')
            ->unique(fn (array $row) => mb_strtolower($row['name'] ?? ''))
            ->filter(fn (array $row) => (float) ($row['primary'] ?? 0) > 0)
            ->sortBy(fn (array $row) => mb_strtolower($row['name'] ?? ''))
            ->values()
            ->all();
    }

    public function getMigrationAgentDetailsAttribute(): ?MigrationAgent
    {
        return $this->migrationAgent?->migrationAgentDetails;
    }

    public function caseHandlers(): BelongsToMany
    {
        return $this->belongsToMany(User::class);
    }
}

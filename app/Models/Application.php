<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Application extends Model
{
    public const TYPES = [
        'Study' => 'Study Application', 'Visa' => 'Visa Application',
        'ART Appeal' => 'ART Appeal', 'Assessment' => 'Assessment',
        'Citizenship' => 'Citizenship Application', 'Nomination' => 'Nomination',
    ];

    public const STATUSES = ['Not Started', 'In Progress', 'Withdrawn', 'Pending Decision', 'Finalised'];

    public const PRIORITIES = ['L' => 'Low', 'M' => 'Medium', 'H' => 'High'];

    protected $attributes = ['status' => 'Not Started', 'priority' => 'M'];

    protected $fillable = [
        'client_id', 'category', 'course', 'visa_subclass', 'nomination_pathway',
        'assessment_body', 'critical_date', 'priority', 'status',
        'primary_counsellor_id', 'secondary_counsellor_id', 'migration_agent_id',
        'organisation_id', 'application_workflow_id', 'psc_client', 'notes',
    ];

    protected function casts(): array
    {
        return ['critical_date' => 'date:Y-m-d', 'psc_client' => 'boolean'];
    }

    protected static function booted(): void
    {
        static::creating(function (Application $application): void {
            $application->primary_counsellor_id ??= auth()->id();
            $application->migration_agent_id ??= $application->workflow?->migration_agent_id;
        });
    }

    public function organisation(): BelongsTo
    {
        return $this->belongsTo(Organisation::class);
    }

    public function workflow(): BelongsTo
    {
        return $this->belongsTo(ApplicationWorkflow::class, 'application_workflow_id');
    }

    public function linkedApplication(): BelongsTo
    {
        return $this->belongsTo(self::class, 'linked_application_id');
    }

    public function sourceLead(): BelongsTo
    {
        return $this->belongsTo(Lead::class, 'source_lead_id');
    }

    public function getOwnerNameAttribute(): string
    {
        return $this->organisation?->name ?? trim(implode(' ', array_filter([
            $this->client?->first_name, $this->client?->middle_name, $this->client?->surname,
        ])));
    }

    public function getTypeLabelAttribute(): string
    {
        return self::TYPES[$this->category] ?? $this->category;
    }

    public function scopeAssignedTo(Builder $query, User $user): void
    {
        $query->where(fn (Builder $assigned) => $assigned
            ->where('applications.primary_counsellor_id', $user->id)
            ->orWhere('applications.secondary_counsellor_id', $user->id)
            ->orWhere('applications.migration_agent_id', $user->id));
    }

    public function scopeActive(Builder $query): void
    {
        $query->whereRaw("LOWER(TRIM(applications.status)) NOT IN ('finalised', 'withdrawn')");
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function primaryCounsellor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'primary_counsellor_id');
    }

    public function secondaryCounsellor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'secondary_counsellor_id');
    }

    public function migrationAgent(): BelongsTo
    {
        return $this->belongsTo(User::class, 'migration_agent_id');
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Application extends Model
{
    public const TYPES = [
        'Study' => 'Study Application', 'Visa' => 'Visa Application',
        'ART Appeal' => 'ART Appeal', 'Assessment' => 'Assessment',
        'Citizenship' => 'Citizenship Application', 'Nomination' => 'Nomination',
    ];

    public const STATUSES = ['Not Started', 'In Progress', 'Withdrawn', 'Pending Decision', 'Finalised'];

    public const PRIORITIES = ['L' => 'Low', 'M' => 'Medium', 'H' => 'High'];

    public function getIsLabourAgreementAttribute(): bool
    {
        return $this->category === 'SBS' && strcasecmp(trim((string) $this->sponsor_type), 'Labour Agreement') === 0;
    }

    public function getRecordReferenceAttribute(): string
    {
        $prefix = match ($this->category) {
            'SBS' => strcasecmp(trim((string) $this->sponsor_type), 'Labour Agreement') === 0 ? 'LAB' : 'SBS',
            'Nomination' => 'NOM',
            default => 'PSC',
        };

        return $prefix.'-'.str_pad((string) $this->id, 4, '0', STR_PAD_LEFT);
    }

    public function statusOptions(): array
    {
        return match ($this->category) {
            'Visa' => [...self::STATUSES, 'Granted'],
            'SBS' => ['Not Started', 'In Progress', 'Withdrawn', 'Pending Decision', 'Appealing', 'Active', 'Expired', 'Finalised - Refused'],
            'Nomination' => ['Not Started', 'In Progress', 'Withdrawn', 'Pending Decision', 'Appealing', 'Finalised'],
            default => self::STATUSES,
        };
    }

    protected $attributes = ['status' => 'Not Started', 'priority' => 'M'];

    protected $fillable = [
        'client_id', 'category', 'course', 'visa_subclass', 'nomination_pathway',
        'assessment_body', 'critical_date', 'priority', 'status',
        'primary_counsellor_id', 'secondary_counsellor_id', 'migration_agent_id',
        'organisation_id', 'application_workflow_id', 'psc_client', 'notes',
        'sponsor_type', 'approval_date', 'expiry_date', 'home_affairs_reference', 'trn',
        'nominated_position_title', 'outcome', 'nominee_name', 'nominee_client_id',
        'sponsorship_id', 'anzsco_code', 'annual_salary', 'lodgement_date', 'reference_number',
        'outcome_date', 'appeal_deadline', 'appeal_status', 'art_case_number',
        'final_outcome', 'final_outcome_date', 'google_drive_url',
        'initial_consultation', 'service_agreement', 'supporting_documents', 'application_stage',
    ];

    protected function casts(): array
    {
        return ['critical_date' => 'date:Y-m-d', 'psc_client' => 'boolean',
            'approval_date' => 'date:Y-m-d', 'expiry_date' => 'date:Y-m-d',
            'lodgement_date' => 'date:Y-m-d', 'outcome_date' => 'date:Y-m-d',
            'appeal_deadline' => 'date:Y-m-d', 'final_outcome_date' => 'date:Y-m-d', 'annual_salary' => 'decimal:2'];
    }

    public function nomineeClient(): BelongsTo
    {
        return $this->belongsTo(Client::class, 'nominee_client_id');
    }

    public function activities(): HasMany
    {
        return $this->hasMany(ApplicationActivity::class);
    }

    protected static function booted(): void
    {
        static::creating(function (Application $application): void {
            $application->primary_counsellor_id ??= auth()->id();
            $application->migration_agent_id ??= $application->workflow?->migration_agent_id;
        });
        static::saved(function (Application $application): void {
            if ($application->category === 'Visa' && $application->wasChanged(['client_id', 'visa_subclass'])) {
                // Keep nomination tables and exports in sync when a linked visa changes.
                self::query()->linkedTo($application)->where('category', 'Nomination')->update([
                    'nominee_client_id' => $application->client_id, 'nominee_name' => null,
                    'nomination_pathway' => $application->visa_subclass, 'updated_at' => now(),
                ]);
            }
        });
    }

    public function organisation(): BelongsTo
    {
        return $this->belongsTo(Organisation::class);
    }

    public function sponsorship(): BelongsTo
    {
        return $this->belongsTo(self::class, 'sponsorship_id');
    }

    public function workflow(): BelongsTo
    {
        return $this->belongsTo(ApplicationWorkflow::class, 'application_workflow_id');
    }

    public function linkedApplication(): BelongsTo
    {
        return $this->belongsTo(self::class, 'linked_application_id');
    }

    public function scopeLinkedTo(Builder $query, Application $application): void
    {
        // Include both directions of PSC-33 links without rewriting existing records.
        $query->where('applications.id', '!=', $application->id)
            ->where(function (Builder $linked) use ($application) {
                $linked->where('applications.linked_application_id', $application->id)
                    ->orWhere('applications.id', $application->linked_application_id)
                    ->orWhereExists(function ($links) use ($application) {
                        $links->selectRaw('1')->from('application_links')
                            ->where(function ($pair) use ($application) {
                                $pair->where('application_links.application_id', $application->id)
                                    ->whereColumn('application_links.linked_application_id', 'applications.id');
                            })->orWhere(function ($pair) use ($application) {
                                $pair->where('application_links.linked_application_id', $application->id)
                                    ->whereColumn('application_links.application_id', 'applications.id');
                            });
                    });
            });
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
        if ($this->category === 'SBS') {
            return strcasecmp(trim((string) $this->sponsor_type), 'Labour Agreement') === 0
                ? 'Labour Agreement Application' : 'SBS Application';
        }

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

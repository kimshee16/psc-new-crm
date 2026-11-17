<?php

namespace App\Models;

use App\Support\PSC\SbsRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ApplicationActivity extends Model
{
    public const TABS = ['tasks' => 'Tasks', 'payments' => 'Payments', 'rfi' => 'RFI', 'quotes' => 'Quotes', 'agreements' => 'Agreements', 'lmt' => 'LMT'];

    public const ORGANISATION_TABS = ['notes' => 'Notes', 'tasks' => 'Tasks', 'rfi' => 'RFI', 'payments' => 'Payments', 'agreements' => 'Agreements', 'quotes' => 'Quotes'];

    public const TASK_STATUSES = ['To Do', 'In Progress', 'Pending External', 'Done'];

    protected $fillable = ['kind', 'title', 'status', 'reference', 'due_date', 'amount', 'currency', 'assigned_to_user_id', 'application_id', 'lead_id'];

    protected static function booted(): void
    {
        static::creating(function (ApplicationActivity $activity): void {
            $activity->created_by_user_id = auth()->id();
            if ($activity->kind === 'tasks') {
                $activity->assigned_to_user_id ??= auth()->id();
                $activity->status ??= 'To Do';
            }
        });

        static::saved(function (ApplicationActivity $activity): void {
            if ($activity->kind !== 'agreements' || ! $activity->wasChanged(['status', 'kind']) && ! $activity->wasRecentlyCreated) {
                return;
            }
            $application = $activity->application;
            if ($application && SbsRecord::supports($application)) {
                $stage = match (strtolower(trim((string) $activity->status))) {
                    'sent', 'sent - pending client' => 'Sent - Pending Client',
                    'signed', 'digitally signed' => 'Signed',
                    default => null,
                };
                if ($stage) {
                    $application->update(['service_agreement' => $stage]);
                }
            }
        });
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to_user_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function lead(): BelongsTo
    {
        return $this->belongsTo(Lead::class);
    }

    public function getTaskStatusAttribute(): string
    {
        return match (strtolower(trim((string) $this->status))) {
            'pending', 'to do', '' => 'To Do',
            'in progress' => 'In Progress',
            'pending external' => 'Pending External',
            'done' => 'Done',
            default => $this->status,
        };
    }

    public function getRelatedReferenceAttribute(): string
    {
        return $this->application?->record_reference ?? $this->lead?->lead_id ?? '';
    }

    public function getRelatedContextAttribute(): string
    {
        if ($this->application) {
            return $this->application->owner_name ?: ($this->application->nominee_name ?: $this->application->type_label);
        }

        return $this->lead ? trim(implode(' ', array_filter([
            $this->lead->first_name, $this->lead->middle_name, $this->lead->surname,
        ]))) : '';
    }

    protected function casts(): array
    {
        return ['due_date' => 'date:Y-m-d', 'amount' => 'decimal:2'];
    }

    public function application(): BelongsTo
    {
        return $this->belongsTo(Application::class);
    }
}

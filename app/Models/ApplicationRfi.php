<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ApplicationRfi extends Model
{
    public const TYPES = ['RFI (s56 notice)', 'Health or character', 'Biometrics', 'Interview', 'Other'];

    public const STATUSES = ['Received', 'Awaiting client', 'In preparation', 'Ready for review', 'Closed'];

    protected $table = 'application_activities';

    protected $attributes = ['kind' => 'rfi', 'status' => 'Received'];

    protected $fillable = ['request_type', 'request_type_other', 'received_date', 'due_date', 'status',
        'assigned_to_user_id', 'request_letter_path', 'request_letter_name', 'lodged_date',
        'proof_of_lodgement_path', 'proof_of_lodgement_name', 'notes'];

    protected function casts(): array
    {
        return ['received_date' => 'date:Y-m-d', 'due_date' => 'date:Y-m-d', 'lodged_date' => 'date:Y-m-d'];
    }

    protected static function booted(): void
    {
        static::addGlobalScope('rfi', fn (Builder $query) => $query->where('application_activities.kind', 'rfi'));
        static::saving(function (ApplicationRfi $rfi): void {
            $rfi->kind = 'rfi';
            $rfi->title = $rfi->request_label;
        });
    }

    public function getRequestLabelAttribute(): string
    {
        return $this->request_type === 'Other'
            ? ($this->request_type_other ?: $this->title ?: 'Other')
            : ($this->request_type ?: $this->title ?: 'Other');
    }

    public function scopeOutstanding(Builder $query): void
    {
        $query->where(fn (Builder $outstanding) => $outstanding->where('status', '!=', 'Closed')->orWhereNull('status'));
    }

    public function application(): BelongsTo
    {
        return $this->belongsTo(Application::class);
    }

    public function officer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to_user_id');
    }
}

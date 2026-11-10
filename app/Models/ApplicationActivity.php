<?php

namespace App\Models;

use App\Support\PSC\SbsRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ApplicationActivity extends Model
{
    public const TABS = ['tasks' => 'Tasks', 'payments' => 'Payments', 'rfi' => 'RFI', 'quotes' => 'Quotes', 'agreements' => 'Agreements', 'lmt' => 'LMT'];

    public const ORGANISATION_TABS = ['notes' => 'Notes', 'tasks' => 'Tasks', 'rfi' => 'RFI', 'payments' => 'Payments', 'agreements' => 'Agreements', 'quotes' => 'Quotes'];

    public const TASK_STATUSES = ['Pending', 'In progress', 'Done'];

    protected $fillable = ['kind', 'title', 'status', 'reference', 'due_date', 'amount', 'currency', 'assigned_to_user_id'];

    protected static function booted(): void
    {
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

    protected function casts(): array
    {
        return ['due_date' => 'date:Y-m-d', 'amount' => 'decimal:2'];
    }

    public function application(): BelongsTo
    {
        return $this->belongsTo(Application::class);
    }
}

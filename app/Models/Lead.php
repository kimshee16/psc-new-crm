<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Lead extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = [
        'lead_id',
        'client_id',
        'assigned_user_id',
        'assigned_name',
        'assigned_office_code',
        'assigned_office',
        'assignment_reason',
        'form_type',
        'source',
        'psc_event_id',
        'event_name',
        'status',
        'priority',
        'prospective_service',
        'critical_date',
        'first_name',
        'middle_name',
        'surname',
        'dob',
        'email',
        'phone_country_code',
        'phone_number',
        'mobile',
        'nationality',
        'current_location',
        'highest_education_level',
        'secondary_counsellor',
        'migration_agent',
        'enquiry',
        'notes',
        'submitted_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'dob' => 'date:Y-m-d',
            'critical_date' => 'date:Y-m-d',
            'submitted_at' => 'datetime',
        ];
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function assignedUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_user_id');
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(PscEvent::class, 'psc_event_id');
    }
}

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
        'status',
        'first_name',
        'middle_name',
        'surname',
        'email',
        'phone_country_code',
        'phone_number',
        'mobile',
        'nationality',
        'current_location',
        'enquiry',
        'submitted_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
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
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Client extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = [
        'client_id',
        'created_by_user_id',
        'first_name',
        'middle_name',
        'surname',
        'dob',
        'phone_country_code',
        'phone_number',
        'mobile',
        'email',
        'nationality',
        'current_location',
        'street',
        'suburb',
        'state',
        'postcode',
        'overseas_address',
        'admin_office',
        'client_status',
        'current_visa',
        'visa_expiry',
        'passport_photo_path',
        'notes',
        'primary_counsellor',
        'secondary_counsellor',
        'migration_agent',
        'tag',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'dob' => 'date:Y-m-d',
            'visa_expiry' => 'date:Y-m-d',
        ];
    }

    public function leads(): HasMany
    {
        return $this->hasMany(Lead::class);
    }
}

<?php

namespace App\Models;

use App\Support\PSC\CrmAccess;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Client extends Model
{
    public function scopeVisibleTo(Builder $query, User $user): void
    {
        $access = app(CrmAccess::class);
        if (! $access->allows($user, 'clients')) {
            $query->whereRaw('1 = 0');
        } elseif ($access->scope($user) !== 'existing') {
            $assignee = trim($user->name) !== '' ? $user->name : $user->email;
            $query->where(function (Builder $scope) use ($user, $access, $assignee): void {
                $scope->where('created_by_user_id', $user->id)
                    ->orWhere('primary_counsellor', $assignee)
                    ->orWhere('secondary_counsellor', $assignee)
                    ->orWhere('migration_agent', $assignee);
                if ($access->scope($user) !== 'assigned') {
                    $scope->orWhereIn('admin_office', $access->officeNamesAndCodes($user));
                }
            });
        }
    }

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

    public function applications(): HasMany
    {
        return $this->hasMany(Application::class);
    }
}

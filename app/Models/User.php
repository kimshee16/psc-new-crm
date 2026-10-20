<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    protected $attributes = ['is_active' => true, 'portal_invite_status' => 'Invite Sent'];

    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    public const ACCOUNT_TYPE_ADMIN = 'Admin';

    public const ACCOUNT_TYPE_MANAGER = 'Manager';

    public const ACCOUNT_TYPE_REGIONAL_MANAGER = 'Regional manager';

    public const CONFIGURABLE_ROLES = ['Counsellor', self::ACCOUNT_TYPE_MANAGER, self::ACCOUNT_TYPE_REGIONAL_MANAGER, self::ACCOUNT_TYPE_ADMIN, 'Super Admin'];

    public const INVITE_STATUSES = ['Send Invite', 'Invite Sent', 'Restrict Access'];

    public const MY_OFFICES_ACCOUNT_TYPES = [
        self::ACCOUNT_TYPE_ADMIN,
        self::ACCOUNT_TYPE_MANAGER,
        self::ACCOUNT_TYPE_REGIONAL_MANAGER,
    ];

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'account_type',
        'offices',
        'default_office_code',
        'include_in_round_robin',
        'first_name', 'last_name', 'phone_country_code', 'phone_number', 'picture_path',
        'is_active', 'portal_invite_status',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'offices' => 'array',
            'include_in_round_robin' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    public function canAccessMyOffices(): bool
    {
        return in_array($this->account_type, self::MY_OFFICES_ACCOUNT_TYPES, true);
    }

    public function hasPortalAccess(): bool
    {
        return $this->is_active && $this->portal_invite_status !== 'Restrict Access';
    }

    /**
     * @return list<string>
     */
    public function assignedOfficeCodes(): array
    {
        if (! is_array($this->offices)) {
            return [];
        }

        return array_values(array_unique(array_filter(
            array_map(fn ($office) => is_string($office) ? strtoupper($office) : null, $this->offices)
        )));
    }

    public function defaultOfficeCode(): ?string
    {
        $assignedOfficeCodes = $this->assignedOfficeCodes();
        $configured = is_string($this->default_office_code) ? strtoupper(trim($this->default_office_code)) : '';

        if ($configured !== '') {
            return $configured;
        }

        return $assignedOfficeCodes[0] ?? null;
    }

    public function managedRegions(): HasMany
    {
        return $this->hasMany(Region::class, 'regional_manager_id');
    }

    public function staffLeaves(): HasMany
    {
        return $this->hasMany(StaffLeave::class);
    }

    /** @return list<string> */
    public function accessibleOfficeCodes(): array
    {
        $codes = $this->assignedOfficeCodes();

        if ($this->account_type === self::ACCOUNT_TYPE_REGIONAL_MANAGER) {
            $regionalCodes = Office::query()->whereHas('regions',
                fn ($query) => $query->where('regional_manager_id', $this->getKey())
            )->pluck('code')->all();
            $codes = array_merge($codes, $regionalCodes);
        }

        return array_values(array_unique($codes));
    }

    /** @return array{name: string, address: string} */
    public function officeSenderIdentity(): array
    {
        $office = Office::query()->where('code', $this->defaultOfficeCode())->first()
            ?? Office::defaultOffice();

        return $office->senderIdentity();
    }
}

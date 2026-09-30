<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable;

    public const ACCOUNT_TYPE_ADMIN = 'Admin';
    public const ACCOUNT_TYPE_MANAGER = 'Manager';
    public const ACCOUNT_TYPE_REGIONAL_MANAGER = 'Regional manager';

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
        ];
    }

    public function canAccessMyOffices(): bool
    {
        return in_array($this->account_type, self::MY_OFFICES_ACCOUNT_TYPES, true);
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
}

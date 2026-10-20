<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Office extends Model
{
    protected $fillable = ['code', 'name', 'office_id', 'is_default', 'sender_name', 'sender_email'];

    public function regions(): BelongsToMany
    {
        return $this->belongsToMany(Region::class);
    }

    protected function casts(): array
    {
        return ['is_default' => 'boolean', 'office_id' => 'integer'];
    }

    public static function defaultOffice(): self
    {
        return static::query()->where('is_default', true)->firstOrFail();
    }

    public static function defaultCode(): string
    {
        return static::defaultOffice()->code;
    }

    /** @return array<string, string> */
    public static function options(): array
    {
        return static::query()->orderByDesc('office_id')->orderBy('code')->pluck('name', 'code')->all();
    }

    /** @return array{name: string, address: string} */
    public function senderIdentity(): array
    {
        return [
            'name' => $this->sender_name ?: (string) config('mail.from.name'),
            'address' => $this->sender_email ?: (string) config('mail.from.address'),
        ];
    }
}

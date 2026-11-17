<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MigrationAgent extends Model
{
    protected $fillable = ['first_name', 'last_name', 'registration_number', 'email', 'signature_path', 'user_id'];

    public function getNameAttribute(): string
    {
        return trim($this->first_name.' '.$this->last_name);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isInUse(): bool
    {
        return $this->user_id !== null && (Application::where('migration_agent_id', $this->user_id)->exists()
            || ApplicationWorkflow::where('migration_agent_id', $this->user_id)->exists());
    }
}

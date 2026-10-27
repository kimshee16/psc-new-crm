<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class ApplicationWorkflow extends Model
{
    protected $fillable = ['name', 'category', 'summary_options', 'migration_agent_id'];

    protected function casts(): array
    {
        return ['summary_options' => 'array'];
    }

    public function migrationAgent(): BelongsTo
    {
        return $this->belongsTo(User::class, 'migration_agent_id');
    }

    public function caseHandlers(): BelongsToMany
    {
        return $this->belongsToMany(User::class);
    }
}

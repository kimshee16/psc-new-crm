<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RolePrivilege extends Model
{
    protected $fillable = ['account_type', 'permissions', 'record_scope', 'notes_access'];

    protected function casts(): array
    {
        return ['permissions' => 'array'];
    }
}

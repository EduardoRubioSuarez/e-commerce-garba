<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Guarded;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Model;

#[Guarded(['*'])]
#[Hidden(['token_hash'])]
class TrustedAdminDevice extends Model
{
    protected function casts(): array
    {
        return ['user_id' => 'integer', 'auth_version' => 'integer', 'expires_at' => 'immutable_datetime', 'last_used_at' => 'immutable_datetime'];
    }
}

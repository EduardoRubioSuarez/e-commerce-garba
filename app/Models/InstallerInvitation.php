<?php

namespace App\Models;

use Database\Factories\InstallerInvitationFactory;
use Illuminate\Database\Eloquent\Attributes\Guarded;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use LogicException;

#[Guarded(['*'])]
#[Hidden(['token_hash'])]
class InstallerInvitation extends Model
{
    /** @use HasFactory<InstallerInvitationFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return ['user_id' => 'integer', 'expires_at' => 'immutable_datetime', 'used_at' => 'immutable_datetime', 'revoked_at' => 'immutable_datetime'];
    }

    protected static function booted(): void
    {
        static::deleting(function (): never {
            throw new LogicException('Invitation history must be preserved.');
        });
    }
}

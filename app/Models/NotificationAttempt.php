<?php

namespace App\Models;

use Database\Factories\NotificationAttemptFactory;
use Illuminate\Database\Eloquent\Attributes\Guarded;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

#[Guarded(['*'])]
#[Hidden(['claim_token'])]
class NotificationAttempt extends Model
{
    /** @use HasFactory<NotificationAttemptFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return ['started_at' => 'immutable_datetime', 'sending_at' => 'immutable_datetime', 'finished_at' => 'immutable_datetime'];
    }
}

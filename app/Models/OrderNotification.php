<?php

namespace App\Models;

use App\Enums\NotificationStatus;
use Database\Factories\OrderNotificationFactory;
use Illuminate\Database\Eloquent\Attributes\Guarded;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Guarded(['*'])]
#[Hidden(['destination', 'claim_token', 'payload'])]
class OrderNotification extends Model
{
    /** @use HasFactory<OrderNotificationFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return ['payload' => 'encrypted:array', 'status' => NotificationStatus::class, 'requested_at' => 'immutable_datetime', 'accepted_at' => 'immutable_datetime', 'claimed_at' => 'immutable_datetime', 'lease_expires_at' => 'immutable_datetime', 'next_attempt_at' => 'immutable_datetime', 'cancelled_at' => 'immutable_datetime'];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }
}

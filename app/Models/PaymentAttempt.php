<?php

namespace App\Models;

use App\Enums\PaymentAttemptStatus;
use Database\Factories\PaymentAttemptFactory;
use Illuminate\Database\Eloquent\Attributes\Guarded;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

#[Guarded(['*'])]
#[Hidden(['id', 'order_id', 'submission_key', 'confirmation_hash', 'idempotency_key', 'checkout_parameters', 'stripe_session_id', 'stripe_payment_intent_id'])]
class PaymentAttempt extends Model
{
    /** @use HasFactory<PaymentAttemptFactory> */
    use HasFactory;

    protected static function booted(): void
    {
        static::updating(function (self $attempt): void {
            if ($attempt->isDirty(['id', 'order_id', 'reference', 'submission_key', 'confirmation_hash', 'idempotency_key', 'amount_cents', 'currency', 'created_at'])) {
                throw new LogicException('Payment attempt confirmation and money cannot be changed.');
            }
        });
        static::deleting(function (): never {
            throw new LogicException('A payment attempt must be preserved.');
        });
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'order_id' => 'integer', 'status' => PaymentAttemptStatus::class,
            'amount_cents' => 'integer', 'checkout_parameters' => 'array',
            'expires_at' => 'immutable_datetime',
            'first_sent_at' => 'immutable_datetime',
            'last_checked_at' => 'immutable_datetime',
        ];
    }

    /** @return BelongsTo<Order, $this> */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }
}

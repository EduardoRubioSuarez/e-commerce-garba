<?php

namespace App\Models;

use Database\Factories\StripeEventFactory;
use Illuminate\Database\Eloquent\Attributes\Guarded;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

#[Guarded(['*'])]
#[Hidden(['payload', 'raw_body'])]
class StripeEvent extends Model
{
    /** @use HasFactory<StripeEventFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return ['payload' => 'array', 'received_at' => 'immutable_datetime', 'processed_at' => 'immutable_datetime', 'last_checked_at' => 'immutable_datetime'];
    }

    public static function supports(string $type): bool
    {
        return in_array($type, ['checkout.session.completed', 'checkout.session.expired', 'payment_intent.succeeded', 'payment_intent.payment_failed',
            'charge.succeeded', 'charge.refunded', 'refund.created', 'refund.updated', 'refund.failed', 'charge.dispute.created', 'charge.dispute.updated', 'charge.dispute.closed'], true);
    }
}

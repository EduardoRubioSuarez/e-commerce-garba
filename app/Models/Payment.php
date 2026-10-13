<?php

namespace App\Models;

use Database\Factories\PaymentFactory;
use Illuminate\Database\Eloquent\Attributes\Guarded;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Guarded(['*'])]
#[Hidden(['destination', 'claim_token'])]
class Payment extends Model
{
    /** @use HasFactory<PaymentFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return ['amount_cents' => 'integer', 'livemode' => 'boolean', 'paid_at' => 'immutable_datetime'];
    }

    public function refunds(): HasMany
    {
        return $this->hasMany(Refund::class);
    }
}

<?php

namespace App\Models;

use App\Enums\OrderStatus;
use App\Enums\PriceTier;
use Database\Factories\OrderFactory;
use Illuminate\Database\Eloquent\Attributes\Guarded;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;
use LogicException;

#[Guarded(['*'])]
#[Hidden(['id', 'user_id', 'contact_name', 'contact_email', 'phone_original', 'phone_canonical', 'fingerprint', 'token_hash', 'token_expires_at'])]
class Order extends Model
{
    private bool $attachingCoupon = false;

    /** @use HasFactory<OrderFactory> */
    use HasFactory;

    protected static function booted(): void
    {
        static::updating(function (self $order): void {
            $allowed = ['status', 'token_hash', 'token_expires_at', 'updated_at'];
            if ($order->attachingCoupon && $order->getRawOriginal('coupon_id') === null
                && $order->getRawOriginal('coupon_use_id') === null) {
                $allowed = [...$allowed, 'coupon_id', 'coupon_use_id'];
                if ($order->getRawOriginal('coupon_snapshot') === null) {
                    $allowed[] = 'coupon_snapshot';
                }
            }
            if (array_diff(array_keys($order->getDirty()), $allowed) !== []) {
                throw new LogicException('An accepted order snapshot cannot be changed.');
            }
        });
        static::deleting(function (): never {
            throw new LogicException('An accepted order must be preserved.');
        });
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'user_id' => 'integer',
            'coupon_id' => 'integer',
            'coupon_use_id' => 'integer',
            'coupon_snapshot' => 'array',
            'status' => OrderStatus::class,
            'tier' => PriceTier::class,
            'subtotal_cents' => 'integer',
            'discount_cents' => 'integer',
            'base_cents' => 'integer',
            'tax_cents' => 'integer',
            'total_cents' => 'integer',
            'settings_version' => 'integer',
            'token_expires_at' => 'immutable_datetime',
        ];
    }

    /** @param array<string, mixed> $snapshot */
    public function attachCoupon(CouponUse $use, array $snapshot): void
    {
        if (DB::transactionLevel() < 1 || $use->order_id !== $this->id || $this->coupon_id !== null) {
            throw new LogicException('A coupon snapshot can only be attached once in its financial transaction.');
        }
        $this->attachingCoupon = true;
        try {
            $this->forceFill(['coupon_id' => $use->coupon_id, 'coupon_use_id' => $use->id, 'coupon_snapshot' => $snapshot]);
            if (! $this->save()) {
                throw new \RuntimeException('Coupon snapshot persistence failed.');
            }
        } finally {
            $this->attachingCoupon = false;
        }
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return HasMany<OrderLine, $this> */
    public function lines(): HasMany
    {
        return $this->hasMany(OrderLine::class)->orderBy('id');
    }

    /** @return HasMany<PaymentAttempt, $this> */
    public function paymentAttempts(): HasMany
    {
        return $this->hasMany(PaymentAttempt::class);
    }
}

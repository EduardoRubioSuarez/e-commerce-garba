<?php

namespace App\Models;

use App\Enums\CouponUseStatus;
use Database\Factories\CouponUseFactory;
use Illuminate\Database\Eloquent\Attributes\Guarded;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

#[Guarded(['*'])]
#[Hidden(['phone_canonical'])]
class CouponUse extends Model
{
    /** @use HasFactory<CouponUseFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return ['status' => CouponUseStatus::class, 'coupon_id' => 'integer', 'order_id' => 'integer',
            'payment_attempt_id' => 'integer', 'payment_id' => 'integer', 'reserved_at' => 'immutable_datetime',
            'consumed_at' => 'immutable_datetime', 'released_at' => 'immutable_datetime'];
    }

    protected static function booted(): void
    {
        static::updating(function (self $use): void {
            if ($use->isDirty(['coupon_id', 'phone_canonical'])) {
                throw new LogicException('The permanent coupon/contact pair cannot change.');
            }
        });
        static::deleting(function (): never {
            throw new LogicException('Coupon rights must be preserved.');
        });
    }

    /** @return BelongsTo<Coupon, $this> */
    public function coupon(): BelongsTo
    {
        return $this->belongsTo(Coupon::class);
    }
}

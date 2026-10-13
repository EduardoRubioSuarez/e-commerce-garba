<?php

namespace App\Models;

use Database\Factories\CouponUseHistoryFactory;
use Illuminate\Database\Eloquent\Attributes\Guarded;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use LogicException;

#[Guarded(['*'])]
class CouponUseHistory extends Model
{
    /** @use HasFactory<CouponUseHistoryFactory> */
    use HasFactory;

    protected static function booted(): void
    {
        static::updating(function (): never {
            throw new LogicException('Coupon history is immutable.');
        });
        static::deleting(function (): never {
            throw new LogicException('Coupon history must be preserved.');
        });
    }
}

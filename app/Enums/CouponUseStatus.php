<?php

namespace App\Enums;

enum CouponUseStatus: string
{
    case Free = 'free';
    case Reserved = 'reserved';
    case Consumed = 'consumed';
}

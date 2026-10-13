<?php

namespace App\Enums;

enum CouponKind: string
{
    case Percentage = 'percentage';
    case Fixed = 'fixed';
}

<?php

namespace App\Enums;

enum PaymentAttemptStatus: string
{
    case Creating = 'creating';
    case Open = 'open';
    case Unknown = 'unknown';
    case Paid = 'paid';
    case Failed = 'failed';
    case Expired = 'expired';
}

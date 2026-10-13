<?php

namespace App\Enums;

enum NotificationStatus: string
{
    case Pending = 'pending';
    case Claimed = 'claimed';
    case Accepted = 'accepted';
    case Failed = 'failed';
    case Uncertain = 'uncertain';
    case Cancelled = 'cancelled';
}

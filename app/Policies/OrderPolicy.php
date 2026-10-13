<?php

namespace App\Policies;

use App\Models\Order;
use App\Models\User;
use App\Services\OrderAccessService;

class OrderPolicy
{
    public function view(?User $user, Order $order): bool
    {
        return app(OrderAccessService::class)->allows($order, $user, null);
    }
}

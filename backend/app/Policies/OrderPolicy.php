<?php

namespace App\Policies;

use App\Models\Order;
use App\Models\User;

class OrderPolicy
{
    public function view(User $user, Order $order): bool
    {
        return $order->customer?->user_id === $user->id || $user->hasAnyPermission(['orders.view', 'orders.manage']);
    }

    public function cancel(User $user, Order $order): bool
    {
        return $order->customer?->user_id === $user->id || $user->hasAnyPermission(['orders.manage']);
    }
}

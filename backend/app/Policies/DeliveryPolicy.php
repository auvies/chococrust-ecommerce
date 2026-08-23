<?php

namespace App\Policies;

use App\Models\Delivery;
use App\Models\User;

class DeliveryPolicy
{
    public function view(User $user, Delivery $delivery): bool
    {
        return $delivery->rider_id === $user->id
            || $delivery->order?->customer?->user_id === $user->id
            || $user->hasAnyPermission(['deliveries.manage_all']);
    }

    public function update(User $user, Delivery $delivery): bool
    {
        return ($delivery->rider_id === $user->id && $user->hasAnyPermission(['deliveries.manage_own']))
            || $user->hasAnyPermission(['deliveries.manage_all']);
    }
}

<?php

namespace App\Policies;

use App\Models\Address;
use App\Models\User;

class AddressPolicy
{
    public function view(User $user, Address $address): bool
    {
        return $address->customer->user_id === $user->id || $user->hasAnyPermission(['customers.view', 'customers.manage']);
    }

    public function update(User $user, Address $address): bool
    {
        return $address->customer->user_id === $user->id || $user->hasAnyPermission(['customers.manage']);
    }

    public function delete(User $user, Address $address): bool
    {
        return $this->update($user, $address);
    }
}

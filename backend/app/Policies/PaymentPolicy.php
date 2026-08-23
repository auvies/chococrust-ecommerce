<?php

namespace App\Policies;

use App\Models\Payment;
use App\Models\User;

class PaymentPolicy
{
    public function view(User $user, Payment $payment): bool
    {
        return $payment->order?->customer?->user_id === $user->id
            || $user->hasAnyPermission(['payments.view', 'payments.manage']);
    }
}

<?php

namespace App\Events;

class OrderConfirmed extends OrderLifecycleEvent
{
    public function eventType(): string
    {
        return 'ORDER_CONFIRMED';
    }
}

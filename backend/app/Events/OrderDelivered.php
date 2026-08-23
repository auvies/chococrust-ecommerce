<?php

namespace App\Events;

class OrderDelivered extends OrderLifecycleEvent
{
    public function eventType(): string
    {
        return 'ORDER_DELIVERED';
    }
}

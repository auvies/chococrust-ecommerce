<?php

namespace App\Events;

class OrderCancelled extends OrderLifecycleEvent
{
    public function eventType(): string
    {
        return 'ORDER_CANCELLED';
    }
}

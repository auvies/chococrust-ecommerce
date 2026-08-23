<?php

namespace App\Events;

class OrderDispatched extends OrderLifecycleEvent
{
    public function eventType(): string
    {
        return 'ORDER_DISPATCHED';
    }
}

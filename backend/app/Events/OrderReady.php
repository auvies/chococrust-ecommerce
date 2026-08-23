<?php

namespace App\Events;

class OrderReady extends OrderLifecycleEvent
{
    public function eventType(): string
    {
        return 'ORDER_READY';
    }
}

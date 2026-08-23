<?php

namespace App\Events;

/** Shared shape for every order-status event (ORDER_CONFIRMED/READY/DISPATCHED/DELIVERED/CANCELLED) - just the order identity plus whatever that specific transition adds. */
abstract class OrderLifecycleEvent extends DomainEvent
{
    public function __construct(
        public readonly int $orderId,
        public readonly string $orderNumber,
    ) {}

    public function subject(): array
    {
        return ['type' => 'order', 'id' => $this->orderId];
    }

    public function payload(): array
    {
        return ['order_id' => $this->orderId, 'order_number' => $this->orderNumber];
    }
}

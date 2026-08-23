<?php

namespace App\Events;

class OrderCreated extends DomainEvent
{
    public function __construct(
        public readonly int $orderId,
        public readonly string $orderNumber,
        public readonly int $customerId,
        public readonly float $total,
    ) {}

    public function eventType(): string
    {
        return 'ORDER_CREATED';
    }

    public function subject(): array
    {
        return ['type' => 'order', 'id' => $this->orderId];
    }

    public function payload(): array
    {
        return [
            'order_id' => $this->orderId,
            'order_number' => $this->orderNumber,
            'customer_id' => $this->customerId,
            'total' => $this->total,
        ];
    }
}

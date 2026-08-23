<?php

namespace App\Events;

class PaymentSubmitted extends DomainEvent
{
    public function __construct(
        public readonly int $paymentId,
        public readonly int $orderId,
        public readonly string $orderNumber,
        public readonly string $method,
    ) {}

    public function eventType(): string
    {
        return 'PAYMENT_SUBMITTED';
    }

    public function subject(): array
    {
        return ['type' => 'payment', 'id' => $this->paymentId];
    }

    public function payload(): array
    {
        return [
            'payment_id' => $this->paymentId,
            'order_id' => $this->orderId,
            'order_number' => $this->orderNumber,
            'method' => $this->method,
        ];
    }
}

<?php

namespace App\Events;

/** Fired only from PaymentService::verifyCod() (staff verification, never a rider's collection claim alone - see PaymentService's own docblock). */
class PaymentVerified extends DomainEvent
{
    public function __construct(
        public readonly int $paymentId,
        public readonly int $orderId,
        public readonly string $orderNumber,
    ) {}

    public function eventType(): string
    {
        return 'PAYMENT_VERIFIED';
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
        ];
    }
}

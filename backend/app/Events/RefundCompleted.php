<?php

namespace App\Events;

/** Fired once per successful refund transaction (PaymentService::refund()) - including a partial refund, since the business event is "a refund transaction completed," not "the payment is now fully refunded." */
class RefundCompleted extends DomainEvent
{
    public function __construct(
        public readonly int $paymentId,
        public readonly int $orderId,
        public readonly string $orderNumber,
        public readonly float $amount,
    ) {}

    public function eventType(): string
    {
        return 'REFUND_COMPLETED';
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
            'amount' => $this->amount,
        ];
    }
}

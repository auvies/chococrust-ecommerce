<?php

namespace App\Notifications;

use App\Models\Order;

class OrderPlaced extends CustomerFacingNotification
{
    public function __construct(private readonly Order $order) {}

    protected function templateKey(): string
    {
        return 'order.placed';
    }

    protected function data(): array
    {
        return [
            'order_id' => $this->order->id,
            'order_number' => $this->order->order_number,
            'total' => (string) $this->order->total,
            'currency' => $this->order->currency,
        ];
    }

    protected function fallbackBody(): string
    {
        return "Thanks for your order! {$this->order->order_number} has been placed and is now being processed.";
    }
}

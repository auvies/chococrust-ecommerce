<?php

namespace App\Notifications;

use App\Models\Order;

class OrderStatusChanged extends CustomerFacingNotification
{
    public function __construct(
        private readonly Order $order,
        private readonly string $fromStatus,
        private readonly string $toStatus,
    ) {}

    protected function templateKey(): string
    {
        return 'order.status_changed';
    }

    protected function data(): array
    {
        return [
            'order_id' => $this->order->id,
            'order_number' => $this->order->order_number,
            'from_status' => $this->fromStatus,
            'to_status' => $this->toStatus,
        ];
    }

    protected function fallbackBody(): string
    {
        return "Your order {$this->order->order_number} is now {$this->toStatus}.";
    }
}

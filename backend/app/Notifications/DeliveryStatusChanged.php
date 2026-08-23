<?php

namespace App\Notifications;

use App\Models\Delivery;

class DeliveryStatusChanged extends CustomerFacingNotification
{
    public function __construct(private readonly Delivery $delivery, private readonly string $status) {}

    protected function templateKey(): string
    {
        return 'delivery.status_changed';
    }

    protected function data(): array
    {
        return [
            'order_id' => $this->delivery->order_id,
            'order_number' => $this->delivery->order?->order_number,
            'status' => $this->status,
            'tracking_number' => $this->delivery->tracking_number,
        ];
    }

    protected function fallbackBody(): string
    {
        $orderNumber = $this->delivery->order?->order_number ?? "#{$this->delivery->order_id}";

        return "The delivery for order {$orderNumber} is now {$this->status}.";
    }
}

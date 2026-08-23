<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class OrderResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'order_number' => $this->order_number,
            'status' => $this->status,
            'currency' => $this->currency,
            'subtotal' => (float) $this->subtotal,
            'discount_total' => (float) $this->discount_total,
            'delivery_fee' => (float) $this->delivery_fee,
            'tax_total' => (float) $this->tax_total,
            'total' => (float) $this->total,
            'delivery_type' => $this->delivery_type,
            'estimated_delivery_minutes' => $this->estimated_delivery_minutes,
            'shipping_address_snapshot' => $this->shipping_address_snapshot,
            'contact_name' => $this->contact_name,
            'contact_phone' => $this->contact_phone,
            'contact_email' => $this->contact_email,
            'notes' => $this->notes,
            'placed_at' => $this->placed_at?->toIso8601String(),
            'items' => OrderItemResource::collection($this->whenLoaded('items')),
            'status_history' => OrderStatusHistoryResource::collection($this->whenLoaded('statusHistory')),
            'delivery' => $this->whenLoaded('delivery', fn () => $this->delivery ? DeliveryResource::make($this->delivery) : null),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}

<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DeliveryResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'order_id' => $this->order_id,
            'type' => $this->type,
            'courier_name' => $this->courier_name,
            'rider_id' => $this->rider_id,
            'tracking_number' => $this->tracking_number,
            'status' => $this->status,
            'delivery_attempts' => $this->delivery_attempts,
            'failure_reason' => $this->failure_reason,
            'delivery_fee' => (float) $this->delivery_fee,
            'estimated_delivery_at' => $this->estimated_delivery_at?->toIso8601String(),
            'delivered_at' => $this->delivered_at?->toIso8601String(),
            'tracking_events' => DeliveryTrackingEventResource::collection($this->whenLoaded('trackingEvents')),
        ];
    }
}

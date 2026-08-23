<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DeliveryTrackingEventResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'status' => $this->status,
            'location' => $this->location,
            'note' => $this->note,
            'occurred_at' => $this->occurred_at?->toIso8601String(),
        ];
    }
}

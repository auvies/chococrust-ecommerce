<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DeliveryRuleResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'scope' => $this->scope,
            'category_id' => $this->category_id,
            'product_id' => $this->product_id,
            'delivery_type' => $this->delivery_type,
            'is_deliverable' => $this->is_deliverable,
            'min_order_amount' => $this->min_order_amount !== null ? (float) $this->min_order_amount : null,
            'flat_fee' => $this->flat_fee !== null ? (float) $this->flat_fee : null,
            'local_areas' => $this->local_areas,
            'estimated_minutes' => $this->estimated_minutes,
            'priority' => $this->priority,
            'is_active' => $this->is_active,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}

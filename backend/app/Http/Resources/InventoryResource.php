<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class InventoryResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $reserved = $this->whenLoaded('variant', fn () => $this->variant->reservations()
            ->whereIn('status', ['pending', 'committed'])
            ->sum('quantity'));

        $sold = $this->whenLoaded('variant', fn () => $this->variant->reservations()
            ->where('status', 'fulfilled')
            ->sum('quantity'));

        return [
            'id' => $this->id,
            'product_variant_id' => $this->product_variant_id,
            'location' => $this->location,
            'quantity_on_hand' => $this->quantity_on_hand,
            'quantity_reserved' => $reserved,
            'quantity_available' => is_int($reserved) ? $this->quantity_on_hand - $reserved : null,
            'quantity_sold' => $sold,
            'reorder_level' => $this->reorder_level,
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}

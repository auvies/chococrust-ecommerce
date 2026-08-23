<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProductVariantResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'sku' => $this->sku,
            'name' => $this->name,
            'price' => (float) $this->price,
            'compare_at_price' => $this->compare_at_price !== null ? (float) $this->compare_at_price : null,
            'currency' => $this->currency,
            'weight_grams' => $this->weight_grams,
            'attributes' => $this->attributes,
            'is_default' => $this->is_default,
            'is_active' => $this->is_active,
        ];
    }
}

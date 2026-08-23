<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CodRecordResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'order_id' => $this->order_id,
            'status' => $this->status,
            'amount_due' => (float) $this->amount_due,
            'amount_collected' => $this->amount_collected !== null ? (float) $this->amount_collected : null,
            'collected_by' => $this->collected_by,
            'collected_at' => $this->collected_at?->toIso8601String(),
            'verified_by' => $this->verified_by,
            'verified_at' => $this->verified_at?->toIso8601String(),
        ];
    }
}

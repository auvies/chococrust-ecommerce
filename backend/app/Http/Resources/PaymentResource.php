<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PaymentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'order_id' => $this->order_id,
            'method' => $this->method,
            'status' => $this->status,
            'amount' => (float) $this->amount,
            'currency' => $this->currency,
            'gateway' => $this->gateway,
            // gateway_reference is the provider's own token, never raw
            // card data (CLAUDE.md §5) - still only shown to staff who can
            // manage payments, not echoed back to the customer at large.
            'gateway_reference' => $this->when(
                $request->user()?->hasAnyPermission(['payments.view', 'payments.manage']),
                $this->gateway_reference,
            ),
            'paid_at' => $this->paid_at?->toIso8601String(),
            'refunded_total' => $this->whenLoaded(
                'transactions',
                fn () => (float) $this->transactions->where('type', 'refund')->where('status', 'succeeded')->sum('amount'),
            ),
        ];
    }
}

<?php

namespace App\Http\Requests\Delivery;

use Illuminate\Foundation\Http\FormRequest;

class UpdateDeliveryStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('delivery'));
    }

    public function rules(): array
    {
        return [
            'status' => ['required', 'in:assigned,picked_up,in_transit,out_for_delivery,delivered,failed,returned'],
            'location' => ['nullable', 'string', 'max:255'],
            'note' => ['nullable', 'string', 'max:1000'],
            // Free text, not an enum (customer_refused, address_not_found,
            // customer_unavailable, ...) - only meaningful (and required)
            // when the attempt didn't succeed.
            'failure_reason' => ['required_if:status,failed,returned', 'nullable', 'string', 'max:255'],
        ];
    }
}

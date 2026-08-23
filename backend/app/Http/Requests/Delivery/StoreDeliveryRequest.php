<?php

namespace App\Http\Requests\Delivery;

use Illuminate\Foundation\Http\FormRequest;

class StoreDeliveryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->hasAnyPermission(['deliveries.manage_all']);
    }

    public function rules(): array
    {
        return [
            'type' => ['required', 'in:local,nationwide'],
            'courier_name' => ['nullable', 'string', 'max:255'],
            'rider_id' => ['nullable', 'integer', 'exists:users,id'],
            'address_id' => ['nullable', 'integer', 'exists:addresses,id'],
            'delivery_fee' => ['sometimes', 'numeric', 'min:0'],
            'estimated_delivery_at' => ['nullable', 'date'],
        ];
    }
}

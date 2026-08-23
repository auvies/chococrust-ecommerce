<?php

namespace App\Http\Requests\Orders;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // any authenticated customer may place an order
    }

    public function rules(): array
    {
        return [
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_variant_id' => ['required', 'integer', 'exists:product_variants,id'],
            'items.*.quantity' => ['required', 'integer', 'min:1', 'max:100'],
            'items.*.customization_note' => ['nullable', 'string', 'max:500'],
            'delivery_type' => ['required', 'in:local,nationwide'],
            'shipping_address_id' => ['required', 'integer', 'exists:addresses,id'],
            'coupon_code' => ['nullable', 'string', 'max:50'],
            'contact_name' => ['required', 'string', 'max:255'],
            'contact_phone' => ['required', 'string', 'max:30'],
            'contact_email' => ['nullable', 'email'],
            'notes' => ['nullable', 'string', 'max:1000'],
            // Allow-listed from config, not a fixed `in:cod` - see
            // config/payments.php for why this stays a config list, not an
            // enum: adding a future gateway is a one-line config change.
            'payment_method' => ['required', 'string', Rule::in(config('payments.methods'))],
        ];
    }
}

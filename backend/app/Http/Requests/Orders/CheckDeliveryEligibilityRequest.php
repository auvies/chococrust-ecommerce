<?php

namespace App\Http\Requests\Orders;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Same item/delivery-type/address shape as StoreOrderRequest, minus the
 * checkout-only fields (contact info, payment method) - this is a
 * read-only preview, not an order placement.
 */
class CheckDeliveryEligibilityRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // any authenticated customer may preview their own cart's eligibility
    }

    public function rules(): array
    {
        return [
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_variant_id' => ['required', 'integer', 'exists:product_variants,id'],
            'items.*.quantity' => ['required', 'integer', 'min:1', 'max:100'],
            'delivery_type' => ['required', 'in:local,nationwide'],
            'shipping_address_id' => ['nullable', 'integer', 'exists:addresses,id'],
        ];
    }
}

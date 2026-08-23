<?php

namespace App\Http\Requests\DeliveryRules;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Managed from the Category Manager admin screen, so it's gated behind the
 * same `categories.manage` permission rather than a new permission slug.
 */
class StoreDeliveryRuleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->hasAnyPermission(['categories.manage']);
    }

    public function rules(): array
    {
        return [
            'scope' => ['required', Rule::in(['global', 'category', 'product'])],
            'category_id' => ['nullable', 'integer', 'exists:categories,id'],
            'product_id' => ['nullable', 'integer', 'exists:products,id'],
            'delivery_type' => ['required', Rule::in(['local', 'nationwide', 'both'])],
            'is_deliverable' => ['sometimes', 'boolean'],
            'min_order_amount' => ['nullable', 'numeric', 'min:0'],
            'flat_fee' => ['nullable', 'numeric', 'min:0'],
            'local_areas' => ['nullable', 'array'],
            'local_areas.*' => ['string', 'max:255'],
            'estimated_minutes' => ['nullable', 'integer', 'min:1'],
            'priority' => ['sometimes', 'integer'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }

    /** Mirrors the DeliveryRule model's own save-time invariant, surfaced as a normal 422 instead of a 500. */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $scope = $this->input('scope');
            $hasCategory = $this->filled('category_id');
            $hasProduct = $this->filled('product_id');

            $valid = match ($scope) {
                'global' => ! $hasCategory && ! $hasProduct,
                'category' => $hasCategory && ! $hasProduct,
                'product' => $hasProduct && ! $hasCategory,
                default => true,
            };

            if (! $valid) {
                $validator->errors()->add('scope', 'The scope must match exactly one of category_id/product_id.');
            }
        });
    }
}

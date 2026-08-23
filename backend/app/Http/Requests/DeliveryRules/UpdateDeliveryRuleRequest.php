<?php

namespace App\Http\Requests\DeliveryRules;

use App\Models\DeliveryRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdateDeliveryRuleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->hasAnyPermission(['categories.manage']);
    }

    public function rules(): array
    {
        return [
            'scope' => ['sometimes', Rule::in(['global', 'category', 'product'])],
            'category_id' => ['nullable', 'integer', 'exists:categories,id'],
            'product_id' => ['nullable', 'integer', 'exists:products,id'],
            'delivery_type' => ['sometimes', Rule::in(['local', 'nationwide', 'both'])],
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

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            /** @var DeliveryRule $rule */
            $rule = $this->route('deliveryRule');
            $scope = $this->input('scope', $rule->scope);
            $hasCategory = $this->has('category_id') ? $this->filled('category_id') : $rule->category_id !== null;
            $hasProduct = $this->has('product_id') ? $this->filled('product_id') : $rule->product_id !== null;

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

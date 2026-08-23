<?php

namespace App\Http\Requests\Payments;

use Illuminate\Foundation\Http\FormRequest;

class CollectCodRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->hasAnyPermission(['cod.collect', 'cod.manage']);
    }

    public function rules(): array
    {
        return [
            'amount_collected' => ['required', 'numeric', 'min:0'],
        ];
    }
}

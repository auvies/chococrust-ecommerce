<?php

namespace App\Http\Requests\Orders;

use Illuminate\Foundation\Http\FormRequest;

class UpdateOrderStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->hasAnyPermission(['orders.manage']);
    }

    public function rules(): array
    {
        return [
            // Mirrors OrderService::TRANSITIONS' full vocabulary - the
            // service itself is what actually enforces which *transitions*
            // are legal from the order's current status; this only bounds
            // the request to a real status value.
            'status' => ['required', 'in:confirmed,processing,ready,dispatched,delivered,completed,cancelled,refunded'],
            'note' => ['nullable', 'string', 'max:1000'],
        ];
    }
}

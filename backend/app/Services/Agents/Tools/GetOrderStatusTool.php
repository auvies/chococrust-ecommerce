<?php

namespace App\Services\Agents\Tools;

use App\Models\Order;
use App\Models\User;
use App\Services\Agents\Contracts\AgentToolInterface;
use Illuminate\Validation\Rule;

/**
 * Read-only, low-risk, unrestricted (ported from the pre-Phase-13
 * AiToolService). Deliberately excludes contact name/phone/address even
 * though `Order` has them - status only, permanently, per the original
 * AiToolService docblock's "no PII" rule, still true here.
 */
class GetOrderStatusTool implements AgentToolInterface
{
    public function name(): string
    {
        return 'get_order_status';
    }

    public function allowedAgentTypes(): array
    {
        return [];
    }

    public function isHighRisk(): bool
    {
        return false;
    }

    public function requiredApprovalPermission(): string
    {
        return ''; // never queued for approval - isHighRisk() is false
    }

    public function rules(): array
    {
        return [
            'order_number' => ['required', 'string', Rule::exists('orders', 'order_number')],
        ];
    }

    public function handle(array $input, User $actor): array
    {
        $order = Order::where('order_number', $input['order_number'])->firstOrFail();

        return ['order_number' => $order->order_number, 'status' => $order->status];
    }
}

<?php

namespace App\Services\Agents\Tools;

use App\Models\Order;
use App\Models\User;
use App\Services\Agents\AgentType;
use App\Services\Agents\Contracts\AgentToolInterface;
use App\Services\Orders\OrderService;
use Illuminate\Validation\Rule;

/**
 * High-risk (the business's own list: "Order cancellation"). Same
 * approval-gated shape as RequestRefundTool - see that class's docblock
 * for why `$actor` in `handle()` is the reviewer, not the requesting agent.
 */
class RequestOrderCancellationTool implements AgentToolInterface
{
    public function __construct(private readonly OrderService $orders) {}

    public function name(): string
    {
        return 'request_order_cancellation';
    }

    public function allowedAgentTypes(): array
    {
        return [AgentType::ORDER, AgentType::CUSTOMER_SUPPORT];
    }

    public function isHighRisk(): bool
    {
        return true;
    }

    public function requiredApprovalPermission(): string
    {
        return 'orders.manage';
    }

    public function rules(): array
    {
        return [
            'order_number' => ['required', 'string', Rule::exists('orders', 'order_number')],
            'reason' => ['required', 'string', 'max:500'],
        ];
    }

    public function handle(array $input, User $actor): array
    {
        $order = Order::where('order_number', $input['order_number'])->firstOrFail();
        $updated = $this->orders->cancel($order, $actor->id, $input['reason']);

        return ['order_number' => $updated->order_number, 'status' => $updated->status];
    }
}

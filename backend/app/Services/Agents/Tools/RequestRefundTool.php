<?php

namespace App\Services\Agents\Tools;

use App\Models\Payment;
use App\Models\User;
use App\Services\Agents\AgentType;
use App\Services\Agents\Contracts\AgentToolInterface;
use App\Services\Payments\PaymentService;
use Illuminate\Validation\Rule;

/**
 * High-risk (the business's own list: "Refund"). Never executes from a
 * tool call directly - AgentToolGateway always routes this into
 * AgentApprovalService's pending queue. `handle()` only ever runs once,
 * from AgentApprovalService::approve(), with `$actor` being the human
 * reviewer, not the requesting agent - PaymentService::refund()'s own
 * audit log then correctly names the accountable staff member.
 */
class RequestRefundTool implements AgentToolInterface
{
    public function __construct(private readonly PaymentService $payments) {}

    public function name(): string
    {
        return 'request_refund';
    }

    public function allowedAgentTypes(): array
    {
        return [AgentType::PAYMENT, AgentType::ORDER, AgentType::CUSTOMER_SUPPORT];
    }

    public function isHighRisk(): bool
    {
        return true;
    }

    public function requiredApprovalPermission(): string
    {
        return 'orders.refund';
    }

    public function rules(): array
    {
        return [
            'payment_id' => ['required', 'integer', Rule::exists('payments', 'id')],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'reason' => ['required', 'string', 'max:500'],
        ];
    }

    public function handle(array $input, User $actor): array
    {
        $payment = Payment::findOrFail($input['payment_id']);
        $updated = $this->payments->refund($payment, (float) $input['amount'], $input['reason'], $actor->id);

        return ['payment_id' => $updated->id, 'status' => $updated->status];
    }
}

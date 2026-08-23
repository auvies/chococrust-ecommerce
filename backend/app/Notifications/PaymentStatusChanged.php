<?php

namespace App\Notifications;

use App\Models\Payment;

/**
 * Fired from PaymentService only (refund, collectCod, verifyCod,
 * markCodFailed, markCodReturned). An agent can *request* a refund
 * (RequestRefundTool, Phase 13) but never trigger this notification
 * directly - the request only ever reaches PaymentService::refund() after
 * a human reviewer approves it (AgentApprovalService::approve()), so this
 * still only ever fires from a human-authorized action, same as every
 * other PaymentService call site.
 */
class PaymentStatusChanged extends CustomerFacingNotification
{
    public function __construct(
        private readonly Payment $payment,
        private readonly string $fromStatus,
        private readonly string $toStatus,
    ) {}

    protected function templateKey(): string
    {
        return 'payment.status_changed';
    }

    protected function data(): array
    {
        return [
            'order_id' => $this->payment->order_id,
            'order_number' => $this->payment->order?->order_number,
            'from_status' => $this->fromStatus,
            'to_status' => $this->toStatus,
        ];
    }

    protected function fallbackBody(): string
    {
        $orderNumber = $this->payment->order?->order_number ?? "#{$this->payment->order_id}";

        return "The payment for order {$orderNumber} is now {$this->toStatus}.";
    }
}

<?php

namespace App\Services\Payments;

use App\Events\PaymentVerified;
use App\Events\RefundCompleted;
use App\Models\CodRecord;
use App\Models\Payment;
use App\Models\PaymentTransaction;
use App\Notifications\PaymentStatusChanged;
use App\Services\Audit\AuditLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

/**
 * CLAUDE.md §5: refund and payment-status-changing actions are always
 * audit-logged. COD collection/verification is its own controlled state
 * machine (see cod_records) and only "verify" - staff confirming cash was
 * actually reconciled - flips the underlying payment to paid; "collect"
 * alone (the rider's own claim) does not.
 */
class PaymentService
{
    public function refund(Payment $payment, float $amount, string $reason, int $actorId): Payment
    {
        $alreadyRefunded = (float) $payment->transactions()
            ->where('type', 'refund')->where('status', 'succeeded')->sum('amount');

        if ($amount <= 0 || $amount > (float) $payment->amount - $alreadyRefunded) {
            throw ValidationException::withMessages(['amount' => 'Refund amount exceeds what remains refundable.']);
        }

        $fromStatus = $payment->status;

        $payment = DB::transaction(function () use ($payment, $amount, $reason, $alreadyRefunded) {
            PaymentTransaction::create([
                'payment_id' => $payment->id,
                'type' => 'refund',
                'status' => 'succeeded',
                'amount' => $amount,
                'raw_response' => ['reason' => $reason],
                'processed_at' => now(),
            ]);

            $totalRefunded = $alreadyRefunded + $amount;
            $before = $payment->toArray();
            $payment->update([
                'status' => $totalRefunded >= (float) $payment->amount ? 'refunded' : 'partially_refunded',
            ]);

            AuditLogger::log('payment.refunded', $payment, before: $before, after: $payment->fresh()->toArray());

            return $payment->fresh();
        });

        $this->notifyCustomer($payment, $fromStatus, $payment->status);
        RefundCompleted::dispatch($payment->id, $payment->order_id, $payment->order?->order_number ?? '', $amount);

        return $payment;
    }

    /**
     * "Delivered -> COD Collected" (the business's flow brief): a rider's
     * own claim that cash changed hands. This is deliberately NOT the same
     * as the payment being settled - only staff *verification* does that
     * (see verifyCod()) - but the payment status still moves to
     * COD_COLLECTED so the payments/COD dashboards can distinguish "cash in
     * the rider's hand" from "cash reconciled with the business."
     */
    public function collectCod(CodRecord $cod, float $amountCollected, int $riderId): CodRecord
    {
        if ($cod->status !== 'awaiting_delivery') {
            throw ValidationException::withMessages(['status' => 'This COD order is not awaiting collection.']);
        }

        $fromStatus = $cod->payment->status;

        $cod = DB::transaction(function () use ($cod, $amountCollected, $riderId) {
            $before = $cod->toArray();
            $cod->update([
                'status' => 'collected',
                'amount_collected' => $amountCollected,
                'collected_by' => $riderId,
                'collected_at' => now(),
            ]);

            $payment = $cod->payment;
            $paymentBefore = $payment->toArray();
            $payment->update(['status' => 'cod_collected']);

            AuditLogger::log('cod.collected', $cod, before: $before, after: $cod->fresh()->toArray());
            AuditLogger::log('payment.marked_cod_collected', $payment, before: $paymentBefore, after: $payment->fresh()->toArray());

            return $cod->fresh();
        });

        $this->notifyCustomer($cod->payment, $fromStatus, $cod->payment->status);

        return $cod;
    }

    /** Staff confirms the collected cash was actually reconciled/deposited - this, not the rider's own claim, is what finally flips the payment to PAID. */
    public function verifyCod(CodRecord $cod, int $verifierId): CodRecord
    {
        if ($cod->status !== 'collected') {
            throw ValidationException::withMessages(['status' => 'This COD order has not been marked collected yet.']);
        }

        $fromStatus = $cod->payment->status;

        $cod = DB::transaction(function () use ($cod, $verifierId) {
            $before = $cod->toArray();
            $cod->update(['status' => 'deposited', 'verified_by' => $verifierId, 'verified_at' => now()]);

            $payment = $cod->payment;
            $paymentBefore = $payment->toArray();
            $payment->update(['status' => 'paid', 'paid_at' => now()]);

            AuditLogger::log('cod.verified', $cod, before: $before, after: $cod->fresh()->toArray());
            AuditLogger::log('payment.marked_paid', $payment, before: $paymentBefore, after: $payment->fresh()->toArray());

            return $cod->fresh();
        });

        $this->notifyCustomer($cod->payment, $fromStatus, $cod->payment->status);
        PaymentVerified::dispatch($cod->payment->id, $cod->order_id, $cod->payment->order?->order_number ?? '');

        return $cod;
    }

    /**
     * "Failed delivery" / "Customer refused" / "Return to seller": no cash
     * ever changed hands, so the payment moves to FAILED (not REFUNDED -
     * there was never a successful payment to refund) and the COD record
     * itself records why.
     */
    public function markCodFailed(CodRecord $cod, string $reason, int $actorId): CodRecord
    {
        if (! in_array($cod->status, ['awaiting_delivery'], true)) {
            throw ValidationException::withMessages(['status' => 'This COD order is not awaiting delivery.']);
        }

        $fromStatus = $cod->payment->status;

        $cod = DB::transaction(function () use ($cod, $reason) {
            $before = $cod->toArray();
            $cod->update(['status' => 'failed_collection', 'notes' => $reason]);

            $payment = $cod->payment;
            $paymentBefore = $payment->toArray();
            $payment->update(['status' => 'failed']);

            AuditLogger::log('cod.failed', $cod, before: $before, after: $cod->fresh()->toArray());
            AuditLogger::log('payment.marked_failed', $payment, before: $paymentBefore, after: $payment->fresh()->toArray());

            return $cod->fresh();
        });

        $this->notifyCustomer($cod->payment, $fromStatus, $cod->payment->status);

        return $cod;
    }

    /** A returned-to-seller delivery: same "no cash collected" outcome as markCodFailed(), recorded distinctly for the Returns dashboard. */
    public function markCodReturned(CodRecord $cod, ?string $reason, int $actorId): CodRecord
    {
        $fromStatus = $cod->payment->status;

        $cod = DB::transaction(function () use ($cod, $reason) {
            $before = $cod->toArray();
            $cod->update(['status' => 'returned', 'notes' => $reason]);

            $payment = $cod->payment;
            if (! in_array($payment->status, ['paid', 'refunded', 'partially_refunded'], true)) {
                $paymentBefore = $payment->toArray();
                $payment->update(['status' => 'failed']);
                AuditLogger::log('payment.marked_failed', $payment, before: $paymentBefore, after: $payment->fresh()->toArray());
            }

            AuditLogger::log('cod.returned', $cod, before: $before, after: $cod->fresh()->toArray());

            return $cod->fresh();
        });

        if ($cod->payment->status !== $fromStatus) {
            $this->notifyCustomer($cod->payment, $fromStatus, $cod->payment->status);
        }

        return $cod;
    }

    /** Same non-critical-path posture as OrderService::notifyCustomer() - never let a notification failure affect a payment-sensitive mutation. */
    private function notifyCustomer(Payment $payment, string $fromStatus, string $toStatus): void
    {
        try {
            $payment->loadMissing('order.customer.user');
            $payment->order?->customer?->user?->notify(new PaymentStatusChanged($payment, $fromStatus, $toStatus));
        } catch (\Throwable $e) {
            Log::warning('notification.dispatch_failed', [
                'notification' => PaymentStatusChanged::class,
                'error' => $e->getMessage(),
            ]);
        }
    }
}

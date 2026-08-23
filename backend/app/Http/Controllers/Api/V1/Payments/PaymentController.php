<?php

namespace App\Http\Controllers\Api\V1\Payments;

use App\Http\Controllers\Controller;
use App\Http\Requests\Payments\CollectCodRequest;
use App\Http\Requests\Payments\MarkCodFailedRequest;
use App\Http\Requests\Payments\RefundPaymentRequest;
use App\Http\Requests\Payments\ReturnCodRequest;
use App\Http\Resources\CodRecordResource;
use App\Http\Resources\PaymentResource;
use App\Models\CodRecord;
use App\Models\Payment;
use App\Services\Payments\PaymentService;
use App\Support\Api\ApiQuery;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PaymentController extends Controller
{
    public function __construct(private readonly PaymentService $payments) {}

    public function index(Request $request): JsonResponse
    {
        abort_unless($request->user()->hasAnyPermission(['payments.view', 'payments.manage']), 403);

        $payments = ApiQuery::for($request, Payment::query())
            ->filters(['order_id', 'status', 'method'])
            ->sorts(['created_at', 'amount'])
            ->paginate();

        return PaymentResource::collection($payments)->response();
    }

    public function show(Request $request, Payment $payment): JsonResponse
    {
        $this->authorize('view', $payment);

        return PaymentResource::make($payment->load('transactions'))->response();
    }

    /** CLAUDE.md §13: idempotent - a retried refund never double-refunds. */
    public function refund(RefundPaymentRequest $request, Payment $payment): JsonResponse
    {
        $payment = $this->payments->refund(
            $payment,
            (float) $request->validated('amount'),
            $request->validated('reason'),
            $request->user()->id,
        );

        return PaymentResource::make($payment->load('transactions'))->response();
    }

    public function collectCod(CollectCodRequest $request, CodRecord $codRecord): JsonResponse
    {
        $cod = $this->payments->collectCod($codRecord, (float) $request->validated('amount_collected'), $request->user()->id);

        return CodRecordResource::make($cod)->response();
    }

    public function verifyCod(Request $request, CodRecord $codRecord): JsonResponse
    {
        abort_unless($request->user()->hasAnyPermission(['cod.manage']), 403);

        $cod = $this->payments->verifyCod($codRecord, $request->user()->id);

        return CodRecordResource::make($cod)->response();
    }

    /** "Failed delivery" - a COD order never delivered (customer unreachable, address wrong, ...), so no cash was ever collected. */
    public function failCod(MarkCodFailedRequest $request, CodRecord $codRecord): JsonResponse
    {
        $cod = $this->payments->markCodFailed($codRecord, $request->validated('reason'), $request->user()->id);

        return CodRecordResource::make($cod)->response();
    }

    /** "Customer refused" / "Return to seller" - the parcel came back; distinct from a failed *attempt* for reporting. */
    public function returnCod(ReturnCodRequest $request, CodRecord $codRecord): JsonResponse
    {
        $cod = $this->payments->markCodReturned($codRecord, $request->validated('reason'), $request->user()->id);

        return CodRecordResource::make($cod)->response();
    }

    /**
     * COD reconciliation (CLAUDE.md §5's "reconciled through the admin
     * panel by staff") needs a way to find records to collect/verify in
     * the first place - `cod.manage` only, mirroring PaymentController's
     * own index() gating, since collectCod()/verifyCod() already require it
     * for the verify step (collect itself is `cod.collect`, but that's a
     * narrower "act on the one delivery I'm holding" permission, not a
     * browse-everything one).
     */
    public function indexCod(Request $request): JsonResponse
    {
        abort_unless($request->user()->hasAnyPermission(['cod.manage']), 403);

        $records = ApiQuery::for($request, CodRecord::query())
            ->filters(['status', 'order_id'])
            ->sorts(['created_at', 'amount_due'])
            ->paginate();

        return CodRecordResource::collection($records)->response();
    }

    public function showCod(Request $request, CodRecord $codRecord): JsonResponse
    {
        abort_unless($request->user()->hasAnyPermission(['cod.manage']), 403);

        return CodRecordResource::make($codRecord)->response();
    }
}

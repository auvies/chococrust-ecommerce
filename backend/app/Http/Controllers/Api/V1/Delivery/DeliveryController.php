<?php

namespace App\Http\Controllers\Api\V1\Delivery;

use App\Http\Controllers\Controller;
use App\Http\Requests\Delivery\StoreDeliveryRequest;
use App\Http\Requests\Delivery\UpdateDeliveryStatusRequest;
use App\Http\Resources\DeliveryResource;
use App\Models\Delivery;
use App\Models\Order;
use App\Notifications\DeliveryStatusChanged;
use App\Services\Audit\AuditLogger;
use App\Services\Payments\PaymentService;
use App\Support\Api\ApiQuery;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class DeliveryController extends Controller
{
    public function __construct(private readonly PaymentService $payments) {}

    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $query = Delivery::query();

        if ($user->hasAnyPermission(['deliveries.manage_all'])) {
            // full visibility
        } elseif ($user->hasAnyPermission(['deliveries.view_own', 'deliveries.manage_own'])) {
            $query->where('rider_id', $user->id);
        } else {
            abort(403);
        }

        $deliveries = ApiQuery::for($request, $query)
            ->filters(['status', 'type', 'rider_id'])
            ->sorts(['created_at', 'estimated_delivery_at'])
            ->paginate();

        return DeliveryResource::collection($deliveries)->response();
    }

    public function show(Request $request, Delivery $delivery): JsonResponse
    {
        $this->authorize('view', $delivery);

        return DeliveryResource::make($delivery->load('trackingEvents'))->response();
    }

    public function store(StoreDeliveryRequest $request, Order $order): JsonResponse
    {
        $delivery = $order->delivery()->create($request->validated() + ['status' => 'pending']);

        AuditLogger::log('delivery.created', $delivery, after: $delivery->toArray());

        return DeliveryResource::make($delivery)->response()->setStatusCode(201);
    }

    /**
     * "Delivery attempts, Failed delivery, Customer refused, Return to
     * seller" (the business's tracking brief): every move to
     * 'out_for_delivery' counts as a new attempt, and a 'failed'/'returned'
     * status requires a `failure_reason` (see UpdateDeliveryStatusRequest)
     * so the reason is queryable, not buried in a free-text note. A
     * 'returned' delivery on a COD order also fails its payment - no cash
     * ever changed hands, so PAID would be wrong and the payment stays
     * whatever it already was otherwise (never downgrading an
     * already-settled payment - see PaymentService::markCodReturned()).
     */
    public function updateStatus(UpdateDeliveryStatusRequest $request, Delivery $delivery): JsonResponse
    {
        $status = $request->validated('status');
        $before = ['status' => $delivery->status, 'delivery_attempts' => $delivery->delivery_attempts];

        DB::transaction(function () use ($request, $delivery, $status) {
            $delivery->update([
                'status' => $status,
                'delivery_attempts' => $status === 'out_for_delivery' ? $delivery->delivery_attempts + 1 : $delivery->delivery_attempts,
                'failure_reason' => in_array($status, ['failed', 'returned'], true)
                    ? $request->validated('failure_reason')
                    : $delivery->failure_reason,
                'delivered_at' => $status === 'delivered' ? now() : $delivery->delivered_at,
            ]);

            $delivery->trackingEvents()->create([
                'status' => $status,
                'location' => $request->validated('location'),
                'note' => $request->validated('note') ?? $request->validated('failure_reason'),
                'occurred_at' => now(),
            ]);

            if ($status === 'returned') {
                $codRecord = $delivery->order?->payments()->latest()->first()?->codRecord;
                if ($codRecord) {
                    $this->payments->markCodReturned($codRecord, $request->validated('failure_reason'), $request->user()->id);
                }
            }
        });

        AuditLogger::log('delivery.status_changed', $delivery, before: $before, after: [
            'status' => $delivery->fresh()->status,
            'delivery_attempts' => $delivery->fresh()->delivery_attempts,
        ]);

        try {
            $delivery->loadMissing('order.customer.user');
            $delivery->order?->customer?->user?->notify(new DeliveryStatusChanged($delivery, $status));
        } catch (\Throwable $e) {
            Log::warning('notification.dispatch_failed', [
                'notification' => DeliveryStatusChanged::class,
                'error' => $e->getMessage(),
            ]);
        }

        return DeliveryResource::make($delivery->load('trackingEvents'))->response();
    }
}

<?php

namespace App\Http\Controllers\Api\V1\Orders;

use App\Http\Controllers\Controller;
use App\Events\PaymentSubmitted;
use App\Http\Requests\Orders\CheckDeliveryEligibilityRequest;
use App\Http\Requests\Orders\StoreOrderRequest;
use App\Http\Requests\Orders\UpdateOrderStatusRequest;
use App\Http\Resources\OrderResource;
use App\Models\Address;
use App\Models\CodRecord;
use App\Models\Order;
use App\Models\Payment;
use App\Services\Audit\AuditLogger;
use App\Services\Orders\OrderService;
use App\Support\Api\ApiQuery;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OrderController extends Controller
{
    public function __construct(private readonly OrderService $orders) {}

    public function index(Request $request): JsonResponse
    {
        $isStaff = $request->user()->hasAnyPermission(['orders.view', 'orders.manage']);

        $query = Order::query()->with(['items']);

        if (! $isStaff) {
            $customer = $request->user()->customer()->firstOrFail();
            $query->where('customer_id', $customer->id);
        }

        $orders = ApiQuery::for($request, $query)
            ->filters(['status', 'delivery_type', 'customer_id'])
            ->sorts(['created_at', 'total'])
            ->searchable(['order_number', 'contact_name', 'contact_phone'])
            ->paginate();

        return OrderResource::collection($orders)->response();
    }

    public function show(Request $request, Order $order): JsonResponse
    {
        $this->authorize('view', $order);

        return OrderResource::make($order->load(['items', 'statusHistory', 'delivery.trackingEvents']))->response();
    }

    /**
     * CLAUDE.md §13: order creation is idempotent - see routes/api/orders.php.
     *
     * "Order Created -> Inventory Reserved -> COD Pending" (the business's
     * flow brief): OrderService::create() already reserved inventory before
     * this returns; a COD payment starts life at 'cod_pending' rather than
     * the generic 'pending' so the COD/payments admin dashboards can
     * distinguish "awaiting staff confirmation" from "awaiting a gateway"
     * from the first row onward.
     */
    public function store(StoreOrderRequest $request): JsonResponse
    {
        $customer = $request->user()->customer()->firstOrFail();
        $data = $request->validated();

        $order = $this->orders->create($customer, $data);

        $isCod = in_array($data['payment_method'], config('payments.cod_methods'), true);

        $payment = Payment::create([
            'order_id' => $order->id,
            'method' => $data['payment_method'],
            'status' => $isCod ? 'cod_pending' : 'pending',
            'amount' => $order->total,
            'currency' => $order->currency,
            'gateway' => $isCod ? null : $data['payment_method'],
        ]);

        if ($isCod) {
            CodRecord::create([
                'order_id' => $order->id,
                'payment_id' => $payment->id,
                'status' => 'awaiting_delivery',
                'amount_due' => $order->total,
            ]);
        }

        PaymentSubmitted::dispatch($payment->id, $order->id, $order->order_number, $payment->method);

        return OrderResource::make($order)->response()->setStatusCode(201);
    }

    /**
     * Preview-only: lets the checkout UI show delivery eligibility/fee/ETA
     * for the current cart before the customer submits an order, using the
     * exact same backend-configured rules `store()` enforces (CLAUDE.md §10:
     * delivery rules come from backend configuration, never the client).
     */
    public function checkDeliveryEligibility(CheckDeliveryEligibilityRequest $request): JsonResponse
    {
        $customer = $request->user()->customer()->firstOrFail();
        $data = $request->validated();

        $address = null;
        if (! empty($data['shipping_address_id'])) {
            $address = Address::query()
                ->where('id', $data['shipping_address_id'])
                ->where('customer_id', $customer->id)
                ->first();
        }

        $result = $this->orders->previewDelivery($data['items'], $data['delivery_type'], $address);

        return response()->json([
            'data' => [
                'eligible' => true,
                'fee' => $result['fee'],
                'estimated_minutes' => $result['estimated_minutes'],
            ],
        ]);
    }

    public function updateStatus(UpdateOrderStatusRequest $request, Order $order): JsonResponse
    {
        $order = $this->orders->transitionStatus(
            $order,
            $request->validated('status'),
            $request->validated('note'),
            $request->user()->id,
        );

        return OrderResource::make($order->load('statusHistory'))->response();
    }

    public function cancel(Request $request, Order $order): JsonResponse
    {
        $this->authorize('cancel', $order);

        $order = $this->orders->cancel($order, $request->user()->id, $request->input('note'));

        AuditLogger::log('order.cancelled', $order);

        return OrderResource::make($order)->response();
    }
}

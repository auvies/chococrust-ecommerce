<?php

namespace App\Services\Analytics;

use App\Models\AiUsageLog;
use App\Models\CodRecord;
use App\Models\Customer;
use App\Models\Delivery;
use App\Models\Inventory;
use App\Models\InventoryReservation;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Payment;
use App\Models\Product;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

/**
 * Read-only reporting over data that already exists elsewhere in the API -
 * deliberately no separate analytics store/pipeline (CLAUDE.md: no
 * speculative infrastructure), the same design AnalyticsController's own
 * original docblock committed to. That docblock also named its own exit
 * condition - "a dedicated reporting service becomes worth it once a
 * concrete need... shows up" - and this phase (product/best-sellers/
 * customer/COD/delivery/inventory/payment analytics, on top of the three
 * that already existed) is that concrete need: eleven aggregation methods
 * inline in a controller would have buried the actual HTTP concerns
 * (permission gating, request parsing) under query logic, so it moved here
 * instead. Every method takes plain scalars/Carbon instances, never the
 * Request itself - parsing stays the controller's job.
 */
class AnalyticsService
{
    public function overview(): array
    {
        $ordersByStatus = Order::query()
            ->selectRaw('status, count(*) as count')
            ->groupBy('status')
            ->pluck('count', 'status');

        return [
            'orders_by_status' => $ordersByStatus,
            'orders_total' => Order::count(),
            'revenue_paid' => (float) (Payment::where('status', 'paid')->sum('amount') ?? 0),
        ];
    }

    public function sales(CarbonInterface $from, CarbonInterface $to): Collection
    {
        return Order::query()
            ->whereBetween('created_at', [$from, $to])
            ->selectRaw('date(created_at) as date, count(*) as orders, sum(total) as total')
            ->groupBy('date')
            ->orderBy('date')
            ->get();
    }

    /** Order-lifecycle analytics: status mix, average order value, cancellation rate. */
    public function orders(CarbonInterface $from, CarbonInterface $to): array
    {
        $query = Order::query()->whereBetween('created_at', [$from, $to]);

        $total = (clone $query)->count();
        $byStatus = (clone $query)->selectRaw('status, count(*) as count')->groupBy('status')->pluck('count', 'status');
        $cancelled = (int) ($byStatus['cancelled'] ?? 0);

        return [
            'orders_total' => $total,
            'by_status' => $byStatus,
            'average_order_value' => $total > 0 ? round((float) ((clone $query)->avg('total') ?? 0), 2) : 0.0,
            'cancelled_orders' => $cancelled,
            'cancellation_rate' => $total > 0 ? round($cancelled / $total, 4) : 0.0,
        ];
    }

    /** Full per-product performance table, ranked by revenue. */
    public function productPerformance(CarbonInterface $from, CarbonInterface $to, int $limit): Collection
    {
        return $this->hydrateProductRows(
            $this->productSalesQuery($from, $to)->orderByDesc('total_revenue')->limit($limit)->get()
        );
    }

    /**
     * Same underlying sales data as productPerformance(), ranked by units
     * sold instead of revenue - the same definition of "best seller" the
     * public storefront's ProductController::bestSellers() already uses
     * (real sales data, non-cancelled/non-refunded order items), just
     * admin-scoped with a date range and revenue attached.
     */
    public function bestSellers(CarbonInterface $from, CarbonInterface $to, int $limit): Collection
    {
        return $this->hydrateProductRows(
            $this->productSalesQuery($from, $to)->orderByDesc('total_quantity')->limit($limit)->get()
        );
    }

    private function productSalesQuery(CarbonInterface $from, CarbonInterface $to)
    {
        return OrderItem::query()
            ->whereNotNull('product_id')
            ->whereHas('order', fn ($orders) => $orders
                ->whereNotIn('status', ['cancelled', 'refunded'])
                ->whereBetween('created_at', [$from, $to]))
            ->selectRaw('product_id, SUM(quantity) as total_quantity, SUM(line_total) as total_revenue, COUNT(DISTINCT order_id) as order_count')
            ->groupBy('product_id');
    }

    private function hydrateProductRows(Collection $rows): Collection
    {
        $products = Product::query()->whereIn('id', $rows->pluck('product_id'))->get(['id', 'name', 'slug'])->keyBy('id');

        return $rows->map(fn ($row) => [
            'product_id' => (int) $row->product_id,
            'name' => $products->get($row->product_id)?->name ?? '(deleted product)',
            'slug' => $products->get($row->product_id)?->slug,
            'quantity_sold' => (int) $row->total_quantity,
            'revenue' => (float) $row->total_revenue,
            'order_count' => (int) $row->order_count,
        ])->values();
    }

    /** New vs. repeat customers, and the top spenders in range. */
    public function customers(CarbonInterface $from, CarbonInterface $to): array
    {
        $newCustomers = Customer::query()->whereBetween('created_at', [$from, $to])->count();

        $spendByCustomer = Order::query()
            ->whereBetween('created_at', [$from, $to])
            ->whereNotNull('customer_id')
            ->selectRaw('customer_id, COUNT(*) as orders_count, SUM(total) as total_spent')
            ->groupBy('customer_id')
            ->get();

        $customersWithOrders = $spendByCustomer->count();
        $repeatCustomers = $spendByCustomer->where('orders_count', '>', 1)->count();

        $topRows = $spendByCustomer->sortByDesc('total_spent')->take(10);
        $customers = Customer::query()->whereIn('id', $topRows->pluck('customer_id'))->with('user:id,name,email')->get()->keyBy('id');

        return [
            'new_customers' => $newCustomers,
            'customers_with_orders' => $customersWithOrders,
            'repeat_customers' => $repeatCustomers,
            'repeat_customer_rate' => $customersWithOrders > 0 ? round($repeatCustomers / $customersWithOrders, 4) : 0.0,
            'top_customers' => $topRows->map(fn ($row) => [
                'customer_id' => (int) $row->customer_id,
                'name' => $customers->get($row->customer_id)?->user?->name,
                'email' => $customers->get($row->customer_id)?->user?->email,
                'orders_count' => (int) $row->orders_count,
                'total_spent' => (float) $row->total_spent,
            ])->values(),
        ];
    }

    /**
     * COD success rate: of *resolved* COD attempts (collected/deposited vs.
     * failed_collection/returned), what share actually collected cash -
     * `awaiting_delivery` records are still in flight and deliberately
     * excluded from the rate itself so an early cohort with many pending
     * deliveries doesn't read as a false 0%.
     */
    public function codPerformance(CarbonInterface $from, CarbonInterface $to): array
    {
        $query = CodRecord::query()->whereBetween('created_at', [$from, $to]);
        $byStatus = (clone $query)->selectRaw('status, count(*) as count')->groupBy('status')->pluck('count', 'status');

        $collected = (int) (($byStatus['collected'] ?? 0) + ($byStatus['deposited'] ?? 0));
        $failed = (int) (($byStatus['failed_collection'] ?? 0) + ($byStatus['returned'] ?? 0));
        $resolved = $collected + $failed;

        return [
            'total_cod_records' => (clone $query)->count(),
            'by_status' => $byStatus,
            'collected' => $collected,
            'failed' => $failed,
            'awaiting_delivery' => (int) ($byStatus['awaiting_delivery'] ?? 0),
            'success_rate' => $resolved > 0 ? round($collected / $resolved, 4) : null,
        ];
    }

    /**
     * Failed delivery rate: of resolved deliveries (delivered vs.
     * failed/returned), what share did not succeed - pending/in-transit
     * deliveries are excluded from the rate for the same reason as above.
     */
    public function deliveryPerformance(CarbonInterface $from, CarbonInterface $to): array
    {
        $query = Delivery::query()->whereBetween('created_at', [$from, $to]);
        $byStatus = (clone $query)->selectRaw('status, count(*) as count')->groupBy('status')->pluck('count', 'status');

        $delivered = (int) ($byStatus['delivered'] ?? 0);
        $failed = (int) ($byStatus['failed'] ?? 0);
        $returned = (int) ($byStatus['returned'] ?? 0);
        $resolved = $delivered + $failed + $returned;

        return [
            'total_deliveries' => (clone $query)->count(),
            'by_status' => $byStatus,
            'delivered' => $delivered,
            'failed' => $failed,
            'returned' => $returned,
            'failed_delivery_rate' => $resolved > 0 ? round(($failed + $returned) / $resolved, 4) : null,
            'average_delivery_attempts' => round((float) ((clone $query)->avg('delivery_attempts') ?? 0), 2),
        ];
    }

    /**
     * Point-in-time stock health, not date-ranged - "available" is
     * on-hand minus active (pending/committed) reservations, the same
     * formula InventoryService::availableQuantity() uses per-variant,
     * computed here as two grouped aggregates instead of N+1 calls.
     */
    public function inventory(): array
    {
        $reservedByVariant = InventoryReservation::query()
            ->whereIn('status', ['pending', 'committed'])
            ->selectRaw('product_variant_id, SUM(quantity) as reserved')
            ->groupBy('product_variant_id')
            ->pluck('reserved', 'product_variant_id');

        $rows = Inventory::query()->with('variant:id,product_id,name,sku')->get();

        $totalOnHand = 0;
        $totalAvailable = 0;
        $outOfStock = 0;
        $lowStock = [];

        foreach ($rows as $row) {
            $reserved = (int) ($reservedByVariant[$row->product_variant_id] ?? 0);
            $available = $row->quantity_on_hand - $reserved;
            $totalOnHand += $row->quantity_on_hand;
            $totalAvailable += $available;

            if ($available <= 0) {
                $outOfStock++;
            } elseif ($row->reorder_level !== null && $available <= $row->reorder_level) {
                $lowStock[] = [
                    'product_variant_id' => $row->product_variant_id,
                    'sku' => $row->variant?->sku,
                    'name' => $row->variant?->name,
                    'location' => $row->location,
                    'available' => $available,
                    'reorder_level' => $row->reorder_level,
                ];
            }
        }

        return [
            'inventory_records_tracked' => $rows->count(),
            'total_units_on_hand' => $totalOnHand,
            'total_units_available' => $totalAvailable,
            'out_of_stock_count' => $outOfStock,
            'low_stock_count' => count($lowStock),
            // Capped so a large low-stock event can't return an unbounded
            // payload - the count above already reflects the true total.
            'low_stock' => array_slice($lowStock, 0, 25),
        ];
    }

    /** Revenue and failure/refund rates by payment method. */
    public function payments(CarbonInterface $from, CarbonInterface $to): array
    {
        $query = Payment::query()->whereBetween('created_at', [$from, $to]);

        $byMethod = (clone $query)
            ->selectRaw('method, count(*) as count, coalesce(sum(amount), 0) as total_amount')
            ->groupBy('method')
            ->get();

        $byStatus = (clone $query)->selectRaw('status, count(*) as count')->groupBy('status')->pluck('count', 'status');

        $total = (clone $query)->count();
        $failed = (int) ($byStatus['failed'] ?? 0);
        $refunded = (int) (($byStatus['refunded'] ?? 0) + ($byStatus['partially_refunded'] ?? 0));

        return [
            'total_payments' => $total,
            'total_paid_amount' => (float) ((clone $query)->where('status', 'paid')->sum('amount') ?? 0),
            'by_method' => $byMethod,
            'by_status' => $byStatus,
            'failure_rate' => $total > 0 ? round($failed / $total, 4) : 0.0,
            'refund_rate' => $total > 0 ? round($refunded / $total, 4) : 0.0,
        ];
    }

    /**
     * AI cost monitoring (CLAUDE.md §19): requests, tokens, estimated
     * cost, model usage, and agent usage - reading `ai_usage_logs`
     * directly, same as this controller always has. `by_agent_type` joins
     * through `users` since ai_usage_logs only stores the calling
     * identity's `user_id`, not a denormalized agent_type snapshot.
     */
    public function aiUsage(CarbonInterface $from, CarbonInterface $to): array
    {
        // Fully-qualified column name, not just `created_at` - by_agent_type
        // below joins `users` (which also has a created_at column), and an
        // unqualified column in the base query becomes ambiguous once a
        // clone of it gains that join.
        $logs = AiUsageLog::whereBetween('ai_usage_logs.created_at', [$from, $to]);

        $byProvider = (clone $logs)
            ->selectRaw('provider, count(*) as count, coalesce(sum(cost_usd), 0) as cost_usd, coalesce(sum(input_tokens), 0) as input_tokens, coalesce(sum(output_tokens), 0) as output_tokens')
            ->groupBy('provider')
            ->get();

        $byStatus = (clone $logs)->selectRaw('status, count(*) as count')->groupBy('status')->pluck('count', 'status');

        $byModel = (clone $logs)
            ->selectRaw('model, count(*) as count, coalesce(sum(cost_usd), 0) as cost_usd, coalesce(sum(input_tokens), 0) as input_tokens, coalesce(sum(output_tokens), 0) as output_tokens')
            ->groupBy('model')
            ->get();

        $byPurpose = (clone $logs)
            ->selectRaw('purpose, count(*) as count, coalesce(sum(cost_usd), 0) as cost_usd')
            ->groupBy('purpose')
            ->get();

        $byAgentType = (clone $logs)
            ->join('users', 'users.id', '=', 'ai_usage_logs.user_id')
            ->where('ai_usage_logs.purpose', 'agent_tool_call')
            ->selectRaw('users.agent_type as agent_type, count(*) as count, coalesce(sum(ai_usage_logs.cost_usd), 0) as cost_usd')
            ->groupBy('users.agent_type')
            ->get();

        $daily = (clone $logs)
            ->selectRaw('date(created_at) as date, count(*) as calls, coalesce(sum(cost_usd), 0) as cost_usd')
            ->groupBy('date')
            ->orderBy('date')
            ->get();

        return [
            'total_replies' => (clone $logs)->count(),
            'total_cost_usd' => (float) ((clone $logs)->sum('cost_usd') ?? 0),
            'total_input_tokens' => (int) ((clone $logs)->sum('input_tokens') ?? 0),
            'total_output_tokens' => (int) ((clone $logs)->sum('output_tokens') ?? 0),
            'by_provider' => $byProvider,
            'by_status' => $byStatus,
            'by_model' => $byModel,
            'by_purpose' => $byPurpose,
            'by_agent_type' => $byAgentType,
            'daily' => $daily,
            'limits' => $this->aiUsageLimitsStatus(),
        ];
    }

    /** Configured usage limits (App\Services\Ai\AiUsageLimiter) alongside today's actual usage, so a limit is never just a number nobody can see against reality. */
    private function aiUsageLimitsStatus(): array
    {
        $spentToday = (float) (AiUsageLog::where('created_at', '>=', now()->startOfDay())->sum('cost_usd') ?? 0);
        $requestsToday = AiUsageLog::where('created_at', '>=', now()->startOfDay())->count();

        return [
            'max_ai_calls_per_conversation_per_hour' => (int) config('services.anthropic.max_ai_calls_per_conversation_per_hour', 15),
            'max_agent_tool_calls_per_hour' => (int) config('services.anthropic.max_agent_tool_calls_per_hour', 30),
            'daily_cost_limit_usd' => (float) config('services.anthropic.daily_cost_limit_usd', 0),
            'daily_cost_spent_usd' => round($spentToday, 6),
            'daily_request_limit' => (int) config('services.anthropic.daily_request_limit', 0),
            'daily_requests_used' => $requestsToday,
        ];
    }
}

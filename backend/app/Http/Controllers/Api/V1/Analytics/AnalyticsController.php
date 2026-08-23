<?php

namespace App\Http\Controllers\Api\V1\Analytics;

use App\Http\Controllers\Controller;
use App\Services\Analytics\AnalyticsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Thin HTTP layer over AnalyticsService - request parsing (date range,
 * limit) and response shaping only; every actual aggregation lives in the
 * service (see its own docblock for why it was extracted this phase).
 * Permission gating (`analytics.view`) is centralized at the route-group
 * level (routes/api/analytics.php), not repeated per method here - CLAUDE.md
 * §8: "Role checks are centralized... not scattered ad hoc."
 */
class AnalyticsController extends Controller
{
    public function __construct(private readonly AnalyticsService $analytics) {}

    public function overview(): JsonResponse
    {
        return response()->json(['data' => $this->analytics->overview()]);
    }

    public function sales(Request $request): JsonResponse
    {
        [$from, $to] = $this->range($request);

        return response()->json(['data' => $this->analytics->sales($from, $to)]);
    }

    public function orders(Request $request): JsonResponse
    {
        [$from, $to] = $this->range($request);

        return response()->json(['data' => $this->analytics->orders($from, $to)]);
    }

    public function productPerformance(Request $request): JsonResponse
    {
        [$from, $to] = $this->range($request);

        return response()->json(['data' => $this->analytics->productPerformance($from, $to, $this->limit($request))]);
    }

    public function bestSellers(Request $request): JsonResponse
    {
        [$from, $to] = $this->range($request);

        return response()->json(['data' => $this->analytics->bestSellers($from, $to, $this->limit($request))]);
    }

    public function customers(Request $request): JsonResponse
    {
        [$from, $to] = $this->range($request);

        return response()->json(['data' => $this->analytics->customers($from, $to)]);
    }

    public function cod(Request $request): JsonResponse
    {
        [$from, $to] = $this->range($request);

        return response()->json(['data' => $this->analytics->codPerformance($from, $to)]);
    }

    public function deliveries(Request $request): JsonResponse
    {
        [$from, $to] = $this->range($request);

        return response()->json(['data' => $this->analytics->deliveryPerformance($from, $to)]);
    }

    public function inventory(): JsonResponse
    {
        return response()->json(['data' => $this->analytics->inventory()]);
    }

    public function payments(Request $request): JsonResponse
    {
        [$from, $to] = $this->range($request);

        return response()->json(['data' => $this->analytics->payments($from, $to)]);
    }

    /**
     * AI cost monitoring (CLAUDE.md §19): how many chatbot replies/agent
     * tool calls were free (deterministic/internal) vs. how many actually
     * called a paid provider, what that cost, broken down by model/
     * purpose/agent type, alongside the configured usage limits and
     * today's actual usage against them.
     */
    public function aiUsage(Request $request): JsonResponse
    {
        [$from, $to] = $this->range($request);

        return response()->json(['data' => $this->analytics->aiUsage($from, $to)]);
    }

    /** @return array{0: \Illuminate\Support\Carbon, 1: \Illuminate\Support\Carbon} */
    private function range(Request $request): array
    {
        $from = $request->date('from') ?? now()->subDays(30);
        $to = $request->date('to') ?? now();

        return [$from, $to];
    }

    private function limit(Request $request): int
    {
        return min($request->integer('limit', 10) ?: 10, 50);
    }
}

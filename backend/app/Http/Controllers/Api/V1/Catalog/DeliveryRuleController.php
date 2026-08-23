<?php

namespace App\Http\Controllers\Api\V1\Catalog;

use App\Http\Controllers\Controller;
use App\Http\Requests\DeliveryRules\StoreDeliveryRuleRequest;
use App\Http\Requests\DeliveryRules\UpdateDeliveryRuleRequest;
use App\Http\Resources\DeliveryRuleResource;
use App\Models\DeliveryRule;
use App\Services\Audit\AuditLogger;
use App\Support\Api\ApiQuery;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Delivery eligibility/fee configuration, scoped global/category/product
 * (Phase 02 schema). Managed exclusively from the Category Manager admin
 * screen (Phase 06), so it's gated behind `categories.manage` rather than a
 * new permission slug - there is no separate public read for this, unlike
 * categories/products themselves, since it's pure backend configuration
 * with no direct customer-facing view.
 */
class DeliveryRuleController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        abort_unless($request->user()->hasAnyPermission(['categories.manage']), 403);

        $rules = ApiQuery::for($request, DeliveryRule::query())
            ->filters(['scope', 'category_id', 'product_id', 'is_active'])
            ->sorts(['priority', 'created_at'])
            ->defaultPerPage(50)
            ->paginate();

        return DeliveryRuleResource::collection($rules)->response();
    }

    public function store(StoreDeliveryRuleRequest $request): JsonResponse
    {
        $rule = DeliveryRule::create($request->validated());

        AuditLogger::log('delivery_rule.created', $rule, after: $rule->toArray());

        return DeliveryRuleResource::make($rule)->response()->setStatusCode(201);
    }

    public function update(UpdateDeliveryRuleRequest $request, DeliveryRule $deliveryRule): JsonResponse
    {
        $before = $deliveryRule->toArray();
        $deliveryRule->update($request->validated());

        AuditLogger::log('delivery_rule.updated', $deliveryRule, before: $before, after: $deliveryRule->fresh()->toArray());

        return DeliveryRuleResource::make($deliveryRule)->response();
    }

    public function destroy(Request $request, DeliveryRule $deliveryRule): JsonResponse
    {
        abort_unless($request->user()->hasAnyPermission(['categories.manage']), 403);

        $before = $deliveryRule->toArray();
        $deliveryRule->delete();

        AuditLogger::log('delivery_rule.deleted', $deliveryRule, before: $before);

        return response()->json(null, 204);
    }
}
